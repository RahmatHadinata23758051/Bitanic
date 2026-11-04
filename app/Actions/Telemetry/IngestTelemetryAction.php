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
    public function execute(Device $device, array $rawPayload): TelemetryReading
    {
        $samples = $rawPayload['soilrs485'] ?? $rawPayload;

        $offsets = $device->calibration_offsets ?? [];

        $applyOffset = static function (array $keys) use ($samples, $offsets): float {
            $rawValue = 0.0;
            foreach ($keys as $key) {
                if (array_key_exists($key, $samples)) {
                    $rawValue = (float) $samples[$key];
                    break;
                }
            }

            $offset = 0.0;
            foreach ($keys as $key) {
                if (isset($offsets[$key])) {
                    $offset = (float) $offsets[$key];
                    break;
                }
            }

            return $rawValue + $offset;
        };

        $nitrogen = $applyOffset(['Nitrogen', 'nitrogen']);
        $phosphorus = $applyOffset(['Phosporus', 'Phosphorus', 'phosphorus']);
        $potassium = $applyOffset(['Kalium', 'Potassium', 'potassium']);
        $ec = $applyOffset(['Ec', 'EC', 'ec']);
        $ph = $applyOffset(['Ph', 'pH', 'ph']);
        $temperature = $applyOffset(['Temperature', 'temperature']);
        $humidity = $applyOffset(['Humidity', 'humidity']);

        $appliedOffsets = [
            'nitrogen' => $offsets['Nitrogen'] ?? $offsets['nitrogen'] ?? 0.0,
            'phosphorus' => $offsets['Phosporus'] ?? $offsets['Phosphorus'] ?? $offsets['phosphorus'] ?? 0.0,
            'potassium' => $offsets['Kalium'] ?? $offsets['Potassium'] ?? $offsets['potassium'] ?? 0.0,
            'ec' => $offsets['Ec'] ?? $offsets['EC'] ?? $offsets['ec'] ?? 0.0,
            'ph' => $offsets['Ph'] ?? $offsets['pH'] ?? $offsets['ph'] ?? 0.0,
            'temperature' => $offsets['Temperature'] ?? $offsets['temperature'] ?? 0.0,
            'humidity' => $offsets['Humidity'] ?? $offsets['humidity'] ?? 0.0,
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
            'provenance' => 'hardware',
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
