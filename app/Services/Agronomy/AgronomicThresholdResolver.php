<?php

namespace App\Services\Agronomy;

use App\Models\SchoolSetting;

class AgronomicThresholdResolver
{
    /**
     * Vocational precision agriculture standard thresholds (SMK ATPH 4.0).
     */
    public const DEFAULTS = [
        'ph' => [
            'parameter' => 'Derajat Keasaman (pH)',
            'key' => 'ph',
            'ideal_min' => 6.0,
            'ideal_max' => 7.2,
            'unit' => 'pH',
            'step' => 0.1,
            'scale_min' => 0,
            'scale_max' => 14,
            'critical_low' => 5.5,
            'critical_high' => 8.0,
            'sublabel' => 'Aktivitas ion H⁺',
            'definition' => 'Derajat kemasaman tanah yang menentukan kelarutan ion hara bagi penyerapan perakaran tanaman.',
            'importance' => 'Mengatur ketersediaan hara makro dan menjaga aktivitas mikroorganisme tanah yang menguntungkan.',
            'riskNote' => 'Nilai terlalu rendah mengikat fosfor dan memicu keracunan aluminium, sedangkan nilai tinggi menghambat hara mikro.',
        ],
        'moisture' => [
            'parameter' => 'Kadar Lengas Tanah',
            'key' => 'moisture',
            'ideal_min' => 45.0,
            'ideal_max' => 65.0,
            'unit' => '%',
            'step' => 1,
            'scale_min' => 0,
            'scale_max' => 100,
            'critical_low' => 30.0,
            'critical_high' => 80.0,
            'sublabel' => 'Volumetric water (VWC)',
            'definition' => 'Persentase volume pori tanah yang terisi air untuk kelangsungan metabolisme dan transpirasi.',
            'importance' => 'Menjaga turgor sel tanaman dan menjadi medium transportasi unsur hara dari perakaran.',
            'riskNote' => 'Kekurangan memicu plasmolisis sel layu permanen, sedangkan kelebihan memicu busuk akar karena anoksia.',
        ],
        'ec' => [
            'parameter' => 'Konduktivitas Listrik (EC)',
            'key' => 'ec',
            'ideal_min' => 1000.0,
            'ideal_max' => 2200.0,
            'unit' => 'µS/cm',
            'step' => 50,
            'scale_min' => 0,
            'scale_max' => 4000,
            'critical_low' => 800.0,
            'critical_high' => 2600.0,
            'sublabel' => 'Indeks kepekatan pupuk',
            'definition' => 'Daya hantar listrik ion terlarut yang mengukur konsentrasi total garam pupuk pada media tanam.',
            'importance' => 'Memandu presisi dosis pemupukan fertigasi agar sesuai ambang batas toleransi fisiologis.',
            'riskNote' => 'EC terlalu tinggi menyebabkan osmosis balik (akar terbakar), sedangkan EC rendah menandakan nutrisi defisit.',
        ],
        'temperature' => [
            'parameter' => 'Suhu Tanah',
            'key' => 'temperature',
            'ideal_min' => 22.0,
            'ideal_max' => 29.0,
            'unit' => '°C',
            'step' => 0.5,
            'scale_min' => 10,
            'scale_max' => 45,
            'critical_low' => 18.0,
            'critical_high' => 34.0,
            'sublabel' => 'Aktivitas biologis tanah',
            'definition' => 'Suhu zona perakaran yang mempengaruhi laju respirasi, serapan air, dan aktivitas enzim mikroba.',
            'importance' => 'Menentukan kecepatan metabolisme akar tanaman dan perkecambahan mikroorganisme dekomposer.',
            'riskNote' => 'Suhu terlalu tinggi memicu respirasi berlebih dan denaturasi enzim, sedangkan suhu dingin menghambat translokasi.',
        ],
        'nitrogen' => [
            'parameter' => 'Nitrogen Tersedia (N)',
            'key' => 'nitrogen',
            'ideal_min' => 40.0,
            'ideal_max' => 120.0,
            'unit' => 'mg/kg',
            'step' => 5,
            'scale_min' => 0,
            'scale_max' => 200,
            'critical_low' => 20.0,
            'critical_high' => 160.0,
            'sublabel' => 'Pertumbuhan vegetatif',
            'definition' => 'Unsur hara makro primer penunjang klorofil, pembentukan protein, dan ekspansi daun vegetatif.',
            'importance' => 'Mendorong pembentukan biomassa hijau dan fotosintesis aktif selama fase vegetatif tanaman.',
            'riskNote' => 'Defisiensi menyebabkan klorosis daun tua, sedangkan kelebihan membuat jaringan sukulen rentan hama.',
        ],
        'phosphorus' => [
            'parameter' => 'Fosfor Tersedia (P)',
            'key' => 'phosphorus',
            'ideal_min' => 25.0,
            'ideal_max' => 60.0,
            'unit' => 'mg/kg',
            'step' => 5,
            'scale_min' => 0,
            'scale_max' => 100,
            'critical_low' => 15.0,
            'critical_high' => 85.0,
            'sublabel' => 'Energi dan perakaran',
            'definition' => 'Unsur hara makro penyusun transfer energi (ATP), inisiasi perakaran, dan diferensiasi bunga.',
            'importance' => 'Memicu perkembangan sistem perakaran dalam dan transisi menuju fase generatif pembungaan.',
            'riskNote' => 'Defisiensi menyebabkan daun keunguan dan akar kerdil, kelebihan memicu defisiensi seng dan besi.',
        ],
        'potassium' => [
            'parameter' => 'Kalium Tersedia (K)',
            'key' => 'potassium',
            'ideal_min' => 100.0,
            'ideal_max' => 200.0,
            'unit' => 'mg/kg',
            'step' => 10,
            'scale_min' => 0,
            'scale_max' => 300,
            'critical_low' => 50.0,
            'critical_high' => 260.0,
            'sublabel' => 'Kekebalan dan kualitas buah',
            'definition' => 'Unsur hara makro pengatur turgor stomata, aktivasi enzim, translokasi asimilat, dan ketahanan penyakit.',
            'importance' => 'Meningkatkan bobot buah, kadar gula (Brix), serta ketahanan dinding sel tanaman terhadap cekaman kekeringan.',
            'riskNote' => 'Defisiensi menyebabkan nekrosis tepi daun, kelebihan menghambat penyerapan kation magnesium dan kalsium.',
        ],
    ];

    /**
     * Resolve authoritative thresholds for a given school, merging stored settings with default presets.
     *
     * @return array<string, array<string, mixed>>
     */
    public function resolveForSchool(?int $schoolId): array
    {
        $resolved = self::DEFAULTS;

        if (! $schoolId) {
            return $resolved;
        }

        $setting = SchoolSetting::where('school_id', $schoolId)->first();
        if (! $setting || ! is_array($setting->sensor_thresholds)) {
            return $resolved;
        }

        $th = $setting->sensor_thresholds;

        // pH
        if (isset($th['minPH'])) {
            $resolved['ph']['ideal_min'] = (float) $th['minPH'];
        }
        if (isset($th['maxPH'])) {
            $resolved['ph']['ideal_max'] = (float) $th['maxPH'];
        }

        // Moisture / Lengas
        if (isset($th['minMoisture'])) {
            $resolved['moisture']['ideal_min'] = (float) $th['minMoisture'];
        }
        if (isset($th['maxMoisture'])) {
            $resolved['moisture']['ideal_max'] = (float) $th['maxMoisture'];
        }

        // EC
        if (isset($th['minEC'])) {
            $resolved['ec']['ideal_min'] = (float) $th['minEC'];
        }
        if (isset($th['maxEC'])) {
            $resolved['ec']['ideal_max'] = (float) $th['maxEC'];
        }

        // Temperature
        if (isset($th['minTemperature'])) {
            $resolved['temperature']['ideal_min'] = (float) $th['minTemperature'];
        }
        if (isset($th['maxTemperature'])) {
            $resolved['temperature']['ideal_max'] = (float) $th['maxTemperature'];
        }

        // Nitrogen
        if (isset($th['minNitrogen'])) {
            $resolved['nitrogen']['ideal_min'] = (float) $th['minNitrogen'];
        }
        if (isset($th['maxNitrogen'])) {
            $resolved['nitrogen']['ideal_max'] = (float) $th['maxNitrogen'];
        }

        // Phosphorus
        if (isset($th['minPhosphorus'])) {
            $resolved['phosphorus']['ideal_min'] = (float) $th['minPhosphorus'];
        }
        if (isset($th['maxPhosphorus'])) {
            $resolved['phosphorus']['ideal_max'] = (float) $th['maxPhosphorus'];
        }

        // Potassium
        if (isset($th['minPotassium'])) {
            $resolved['potassium']['ideal_min'] = (float) $th['minPotassium'];
        }
        if (isset($th['maxPotassium'])) {
            $resolved['potassium']['ideal_max'] = (float) $th['maxPotassium'];
        }

        return $resolved;
    }

    /**
     * Evaluate a single parameter against its resolved threshold.
     *
     * @param  string  $paramKey  e.g. 'ph', 'moisture', 'ec', 'temperature', 'nitrogen', 'phosphorus', 'potassium'
     * @param  float|null  $val  Sensor numeric value
     * @param  array  $threshold  Resolved parameter threshold definition
     * @return array{status: string, label: string, badge_class: string, is_optimal: bool}
     */
    public function evaluateValue(string $paramKey, ?float $val, array $threshold): array
    {
        if ($val === null) {
            return [
                'status' => 'optimal',
                'label' => 'Belum ada data',
                'badge_class' => 'text-slate-400 bg-slate-100',
                'is_optimal' => false,
            ];
        }

        $idealMin = (float) ($threshold['ideal_min'] ?? 0);
        $idealMax = (float) ($threshold['ideal_max'] ?? 100);
        $critLow = (float) ($threshold['critical_low'] ?? ($idealMin * 0.8));
        $critHigh = (float) ($threshold['critical_high'] ?? ($idealMax * 1.2));

        if ($val >= $idealMin && $val <= $idealMax) {
            return [
                'status' => 'optimal',
                'label' => 'Dalam rentang acuan',
                'badge_class' => 'text-emerald-700 bg-emerald-50',
                'is_optimal' => true,
            ];
        }

        if ($val < $idealMin) {
            $isCritical = $val <= $critLow;

            return [
                'status' => $isCritical ? 'critical' : 'warning',
                'label' => match ($paramKey) {
                    'ph' => $isCritical ? 'Masam Ekstrem' : 'Agak Masam',
                    'moisture' => $isCritical ? 'Kering Kritis' : 'Lengas Rendah',
                    'ec' => $isCritical ? 'Defisit Ekstrem' : 'Rendah/Defisit',
                    'temperature' => $isCritical ? 'Dingin Kritis' : 'Suhu Rendah',
                    'nitrogen', 'phosphorus', 'potassium' => $isCritical ? 'Defisiensi Berat' : 'Defisiensi',
                    default => 'Rendah',
                },
                'badge_class' => $isCritical ? 'text-rose-700 bg-rose-50' : 'text-amber-700 bg-amber-50',
                'is_optimal' => false,
            ];
        }

        // val > idealMax
        $isCritical = $val >= $critHigh;

        return [
            'status' => $isCritical ? 'critical' : 'warning',
            'label' => match ($paramKey) {
                'ph' => $isCritical ? 'Alkali Ekstrem' : 'Alkali/Basa',
                'moisture' => $isCritical ? 'Jenuh Air Kritis' : 'Lengas Tinggi',
                'ec' => $isCritical ? 'Salin Ekstrem' : 'Salin Pekat',
                'temperature' => $isCritical ? 'Panas Cekaman' : 'Suhu Tinggi',
                'nitrogen' => $isCritical ? 'Toksisitas N' : 'Nitrogen Berlebih',
                'phosphorus' => $isCritical ? 'Fiksasi Berlebih' : 'Fosfor Tinggi',
                'potassium' => $isCritical ? 'Antagonis Hara' : 'Kalium Tinggi',
                default => 'Tinggi',
            },
            'badge_class' => $isCritical ? 'text-rose-700 bg-rose-50' : 'text-amber-700 bg-amber-50',
            'is_optimal' => false,
        ];
    }

    /**
     * Evaluate all 7 parameters from a telemetry reading map.
     *
     * @param  array<string, float|null>  $telemetry  Map containing 'ph', 'humidity' (or 'moisture'), 'ec', 'temperature', 'nitrogen', 'phosphorus', 'potassium'
     * @param  array<string, array<string, mixed>>  $thresholds
     * @return array<string, array{status: string, label: string, badge_class: string, is_optimal: bool}>
     */
    public function evaluateAll(array $telemetry, array $thresholds): array
    {
        $evaluations = [];

        $map = [
            'ph' => $telemetry['ph'] ?? null,
            'moisture' => $telemetry['humidity'] ?? ($telemetry['moisture'] ?? null),
            'ec' => $telemetry['ec'] ?? null,
            'temperature' => $telemetry['temperature'] ?? null,
            'nitrogen' => $telemetry['nitrogen'] ?? null,
            'phosphorus' => $telemetry['phosphorus'] ?? null,
            'potassium' => $telemetry['potassium'] ?? null,
        ];

        foreach ($map as $key => $val) {
            $threshold = $thresholds[$key] ?? self::DEFAULTS[$key];
            $evaluations[$key] = $this->evaluateValue($key, $val !== null ? (float) $val : null, $threshold);
        }

        return $evaluations;
    }

    /**
     * Calculate an overall Soil Health Index score (30 - 100), or null if no fresh live hardware telemetry.
     */
    public function calculateHealthScore(array $telemetry, array $thresholds, bool $isLive): ?int
    {
        if (! $isLive) {
            return null;
        }

        $evaluations = $this->evaluateAll($telemetry, $thresholds);
        $score = 100;

        foreach ($evaluations as $key => $eval) {
            if ($eval['is_optimal']) {
                continue;
            }
            if ($eval['status'] === 'critical') {
                $score -= match ($key) {
                    'ph', 'moisture' => 15,
                    default => 10,
                };
            } elseif ($eval['status'] === 'warning') {
                $score -= match ($key) {
                    'ph', 'moisture' => 8,
                    default => 5,
                };
            }
        }

        return max(30, min(100, $score));
    }
}
