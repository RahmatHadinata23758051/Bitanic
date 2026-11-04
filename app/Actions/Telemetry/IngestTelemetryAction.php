<?php

namespace App\Actions\Telemetry;

use App\Events\SensorData;
use App\Models\Device;
use App\Models\SensorThreshold;
use App\Models\TelemetryReading;
use Illuminate\Support\Carbon;

class IngestTelemetryAction
{
    /**
     * Ingest, evaluate thresholds, persist, and broadcast sensor readings.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function execute(Device $device, array $rawPayload, string $provenance = 'hardware'): TelemetryReading
    {
        $samples = $rawPayload['soilrs485'] ?? $rawPayload;

        $offsets = $device->calibration_offsets ?? [];
        $offsetAliases = [
            'ph' => ['phOffset', 'Ph', 'pH', 'ph'],
            'humidity' => ['moistureOffset', 'Humidity', 'humidity'],
            'ec' => ['ecOffset', 'Ec', 'EC', 'ec'],
            'nitrogen' => ['nitrogen', 'Nitrogen'],
            'phosphorus' => ['phosphorus', 'Phosphorus', 'Phosporus'],
            'potassium' => ['potassium', 'Potassium', 'Kalium'],
            'temperature' => ['temperature', 'Temperature'],
        ];
        $getOffset = static function (string $sensor) use ($offsets, $offsetAliases): float {
            foreach ($offsetAliases[$sensor] as $key) {
                if (array_key_exists($key, $offsets)) {
                    return (float) $offsets[$key];
                }
            }

            return 0.0;
        };

        $applyOffset = static function (array $keys, float $offset) use ($samples): float {
            $rawValue = 0.0;
            foreach ($keys as $key) {
                if (array_key_exists($key, $samples)) {
                    $rawValue = (float) $samples[$key];
                    break;
                }
            }

            return $rawValue + $offset;
        };

        $nOffset = $getOffset('nitrogen');
        $pOffset = $getOffset('phosphorus');
        $kOffset = $getOffset('potassium');
        $ecOffset = $getOffset('ec');
        $phOffset = $getOffset('ph');
        $tempOffset = $getOffset('temperature');
        $humOffset = $getOffset('humidity');

        $nitrogen = $applyOffset(['Nitrogen', 'nitrogen'], $nOffset);
        $phosphorus = $applyOffset(['Phosporus', 'Phosphorus', 'phosphorus'], $pOffset);
        $potassium = $applyOffset(['Kalium', 'Potassium', 'potassium'], $kOffset);
        $ec = $applyOffset(['Ec', 'EC', 'ec'], $ecOffset);
        $ph = $applyOffset(['Ph', 'pH', 'ph'], $phOffset);
        $temperature = $applyOffset(['Temperature', 'temperature'], $tempOffset);
        $humidity = $applyOffset(['Humidity', 'humidity'], $humOffset);

        $appliedOffsets = [
            'nitrogen' => $nOffset,
            'phosphorus' => $pOffset,
            'potassium' => $kOffset,
            'ec' => $ecOffset,
            'ph' => $phOffset,
            'temperature' => $tempOffset,
            'humidity' => $humOffset,
        ];

        // Evaluasi threshold
        $thresholds = SensorThreshold::where('device_id', $device->id)
            ->orWhereNull('device_id')
            ->get()
            ->keyBy('parameter');

        $paramValues = [
            'Nitrogen' => $nitrogen,
            'Phosporus' => $phosphorus,
            'Phosphorus' => $phosphorus,
            'Kalium' => $potassium,
            'Potassium' => $potassium,
            'Ec' => $ec,
            'Ph' => $ph,
            'Temperature' => $temperature,
            'Humidity' => $humidity,
        ];

        $thresholdFlags = [];
        foreach ($paramValues as $param => $val) {
            if ($thresholds->has($param)) {
                $thresh = $thresholds->get($param);
                if ($val < (float) $thresh->min_value) {
                    $thresholdFlags[$param] = 'low';
                } elseif ($val > (float) $thresh->max_value) {
                    $thresholdFlags[$param] = 'high';
                } else {
                    $thresholdFlags[$param] = 'optimal';
                }
            }
        }

        $now = Carbon::now();

        // Simpan satu record terpadu
        $reading = TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => in_array($provenance, ['hardware', 'simulation'], true) ? $provenance : 'hardware',
            'nitrogen' => $nitrogen,
            'phosphorus' => $phosphorus,
            'potassium' => $potassium,
            'ec' => $ec,
            'ph' => $ph,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'raw_payload' => $rawPayload,
            'applied_offsets' => $appliedOffsets,
            'threshold_flags' => $thresholdFlags,
            'recorded_at' => $now,
        ]);

        // Perbarui status online perangkat
        $device->update(['last_seen_at' => $now]);

        // Siarkan event telemetri real-time
        event(new SensorData($reading));

        return $reading;
    }
}
