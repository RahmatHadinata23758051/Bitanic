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

        $nitrogen = (float) ($samples['Nitrogen'] ?? 0);
        $phosphorus = (float) ($samples['Phosporus'] ?? ($samples['Phosphorus'] ?? 0));
        $potassium = (float) ($samples['Kalium'] ?? ($samples['Potassium'] ?? 0));
        $ec = (float) ($samples['Ec'] ?? ($samples['EC'] ?? 0));
        $ph = (float) ($samples['Ph'] ?? ($samples['pH'] ?? 0));
        $temperature = (float) ($samples['Temperature'] ?? 0);
        $humidity = (float) ($samples['Humidity'] ?? 0);

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
            'nitrogen' => $nitrogen,
            'phosphorus' => $phosphorus,
            'potassium' => $potassium,
            'ec' => $ec,
            'ph' => $ph,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'raw_payload' => $rawPayload,
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
