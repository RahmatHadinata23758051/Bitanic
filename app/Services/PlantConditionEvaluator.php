<?php

namespace App\Services;

use App\Models\TelemetryReading;

class PlantConditionEvaluator
{
    /**
     * Evaluate suitability condition for a list of plants based on soil telemetry.
     * Guaranteed null-safe: handles empty database and missing sensors without crashing.
     *
     * @param  array<string>  $plants
     * @return array<int, array{tanaman: string, kondisi: string}>
     */
    public function evaluate(array $plants, ?TelemetryReading $reading = null): array
    {
        if (empty($plants)) {
            return [];
        }

        // Safe navigation with realistic agritech baseline fallback
        $hasTelemetry = $reading !== null;
        $ph = (float) ($reading?->ph ?? 6.5);
        $humid = (float) ($reading?->humidity ?? 70.0);
        $n = (float) ($reading?->nitrogen ?? 0.0);
        $p = (float) ($reading?->phosphorus ?? 0.0);
        $k = (float) ($reading?->potassium ?? 0.0);
        $ec = (float) ($reading?->ec ?? 0.0);
        $temp = (float) ($reading?->temperature ?? 27.0);

        $results = [];

        foreach ($plants as $plant) {
            $plantName = trim((string) $plant);
            if (empty($plantName)) {
                continue;
            }

            if (! $hasTelemetry) {
                $kondisi = "Data telemetri sensor stasiun belum tercatat. Menggunakan estimasi agronomi tanah standar (pH: {$ph}, Kelembapan: {$humid}%, Suhu: {$temp}°C). Disarankan menghubungkan stasiun sensor untuk pantauan presisi {$plantName}.";
            } else {
                $phStatus = $ph < 5.5 ? 'asam' : ($ph > 7.5 ? 'basa' : 'ideal');
                $humidStatus = $humid < 50 ? 'kering' : ($humid > 85 ? 'sangat lembap' : 'optimal');
                $tempStatus = $temp < 20 ? 'dingin' : ($temp > 33 ? 'panas' : 'normal');

                $kondisi = "Kondisi tanah untuk {$plantName}: pH {$ph} ({$phStatus}), kelembapan {$humid}% ({$humidStatus}), suhu {$temp}°C ({$tempStatus}). Kadar hara N: {$n} mg/kg, P: {$p} mg/kg, K: {$k} mg/kg, EC: {$ec} µS/cm.";
            }

            $results[] = [
                'tanaman' => $plantName,
                'kondisi' => $kondisi,
            ];
        }

        return $results;
    }
}
