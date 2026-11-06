<?php

namespace App\Actions\Telemetry;

use App\Events\SensorData;
use App\Models\Device;
use App\Models\SchoolSetting;
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
    public function execute(Device $device, array $rawPayload, string $provenance = 'fixture'): TelemetryReading
    {
        $samples = $rawPayload['soilrs485'] ?? $rawPayload;
        $now = Carbon::now();

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

        $getLegacyOffset = static function (string $sensor) use ($offsets, $offsetAliases): float {
            foreach ($offsetAliases[$sensor] as $key) {
                if (array_key_exists($key, $offsets)) {
                    return (float) $offsets[$key];
                }
            }

            return 0.0;
        };

        $extractRaw = static function (array $keys) use ($samples): ?float {
            foreach ($keys as $key) {
                if (array_key_exists($key, $samples) && $samples[$key] !== null && $samples[$key] !== '') {
                    return (float) $samples[$key];
                }
            }

            return null;
        };

        $rawNitrogen = $extractRaw(['Nitrogen', 'nitrogen']);
        $rawPhosphorus = $extractRaw(['Phosporus', 'Phosphorus', 'phosphorus']);
        $rawPotassium = $extractRaw(['Kalium', 'Potassium', 'potassium']);
        $rawEc = $extractRaw(['Ec', 'EC', 'ec']);
        $rawPh = $extractRaw(['Ph', 'pH', 'ph']);
        $rawTemperature = $extractRaw(['Temperature', 'temperature']);
        $rawHumidity = $extractRaw(['Humidity', 'humidity']);

        $rawMetrics = [
            'nitrogen' => $rawNitrogen,
            'phosphorus' => $rawPhosphorus,
            'potassium' => $rawPotassium,
            'ec' => $rawEc,
            'ph' => $rawPh,
            'temperature' => $rawTemperature,
            'humidity' => $rawHumidity,
        ];

        // Resolve active versioned calibration profile or legacy offset per parameter
        $primaryProfile = null;
        $appliedOffsets = [];
        $calibrationLineage = [];
        $calibratedValues = [];

        foreach ($rawMetrics as $param => $rawVal) {
            if ($rawVal === null) {
                $calibratedValues[$param] = null;
                $appliedOffsets[$param] = 0.0;
                $calibrationLineage[$param] = null;

                continue;
            }

            $activeProfile = $device->getActiveCalibrationProfile($param, $now);

            if ($activeProfile) {
                $calibratedValues[$param] = $activeProfile->calibrate($rawVal);
                $appliedOffsets[$param] = round($calibratedValues[$param] - $rawVal, 2);
                $calibrationLineage[$param] = [
                    'profile_id' => $activeProfile->id,
                    'version' => $activeProfile->version,
                    'method' => $activeProfile->method,
                ];

                if (! $primaryProfile || $activeProfile->version > $primaryProfile->version) {
                    $primaryProfile = $activeProfile;
                }
            } else {
                $legacyOffset = $getLegacyOffset($param);
                $calibratedValues[$param] = $rawVal + $legacyOffset;
                $appliedOffsets[$param] = $legacyOffset;
                $calibrationLineage[$param] = null;
            }
        }

        // Resolve thresholds by specificity: global defaults, school settings, then device overrides.
        $thresholds = SensorThreshold::query()
            ->whereNull('device_id')
            ->get()
            ->keyBy('parameter');
        $schoolThresholds = $device->school_id !== null
            ? (SchoolSetting::query()->where('school_id', $device->school_id)->first()?->sensor_thresholds ?? [])
            : [];

        foreach ([
            'Nitrogen' => ['minNitrogen', 'maxNitrogen'],
            'Phosporus' => ['minPhosphorus', 'maxPhosphorus'],
            'Kalium' => ['minPotassium', 'maxPotassium'],
            'Ec' => ['minEC', 'maxEC'],
            'Ph' => ['minPH', 'maxPH'],
            'Temperature' => ['minTemperature', 'maxTemperature'],
            'Humidity' => ['minMoisture', 'maxMoisture'],
        ] as $parameter => [$minimumKey, $maximumKey]) {
            if (array_key_exists($minimumKey, $schoolThresholds) && array_key_exists($maximumKey, $schoolThresholds)) {
                $thresholds->put($parameter, (object) [
                    'min_value' => $schoolThresholds[$minimumKey],
                    'max_value' => $schoolThresholds[$maximumKey],
                ]);
            }
        }

        SensorThreshold::query()
            ->where('device_id', $device->id)
            ->get()
            ->each(fn (SensorThreshold $threshold) => $thresholds->put($threshold->parameter, $threshold));

        $paramValues = [
            'Nitrogen' => $calibratedValues['nitrogen'],
            'Phosporus' => $calibratedValues['phosphorus'],
            'Phosphorus' => $calibratedValues['phosphorus'],
            'Kalium' => $calibratedValues['potassium'],
            'Potassium' => $calibratedValues['potassium'],
            'Ec' => $calibratedValues['ec'],
            'Ph' => $calibratedValues['ph'],
            'Temperature' => $calibratedValues['temperature'],
            'Humidity' => $calibratedValues['humidity'],
        ];

        $thresholdFlags = [];
        foreach ($paramValues as $param => $val) {
            if ($val !== null && $thresholds->has($param)) {
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

        $validProvenances = config('bitanic.telemetry_provenances', ['hardware', 'simulation', 'fixture']);
        $normalizedProvenance = in_array($provenance, $validProvenances, true) ? $provenance : 'fixture';

        // Simpan satu record telemetri terpadu dengan lineage kalibrasi dan raw payload imutabel
        $reading = TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => $normalizedProvenance,
            'nitrogen' => $calibratedValues['nitrogen'],
            'phosphorus' => $calibratedValues['phosphorus'],
            'potassium' => $calibratedValues['potassium'],
            'ec' => $calibratedValues['ec'],
            'ph' => $calibratedValues['ph'],
            'temperature' => $calibratedValues['temperature'],
            'humidity' => $calibratedValues['humidity'],
            'raw_payload' => $rawPayload,
            'raw_metrics' => $rawMetrics,
            'applied_offsets' => $appliedOffsets,
            'calibration_lineage' => $calibrationLineage,
            'calibration_profile_id' => $primaryProfile?->id,
            'calibration_version' => $primaryProfile?->version,
            'calibration_applied_at' => $primaryProfile ? $now : null,
            'threshold_flags' => $thresholdFlags,
            'recorded_at' => $now,
        ]);

        // Perbarui status online perangkat HANYA jika berasal dari transmisi hardware nyata
        if ($normalizedProvenance === 'hardware') {
            $device->update(['last_seen_at' => $now]);
        }

        // Siarkan event telemetri real-time
        event(new SensorData($reading));

        return $reading;
    }
}
