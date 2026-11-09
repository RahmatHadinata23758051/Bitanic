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
            'definition' => 'Kemasaman tanah penentu daya larut hara perakaran.',
            'importance' => 'Mengatur kelarutan nutrisi makro-mikro tanaman.',
            'riskNote' => 'pH ekstrem (< 5,5 atau > 7,5) mengikat hara dan merusak akar.',
            'low_implication' => 'Kemasaman tinggi (fiksasi fosfor kuat, kelarutan Al/Fe toksik)',
            'high_implication' => 'Alkali/basa (defisiensi mikronutrien Fe, Mn, Zn, Cu)',
            'source_title' => 'Target Praktikum Kurikulum Merdeka SMK ATPH (Hortikultura Buah)',
            'crop_applicability' => 'Tanaman Hortikultura (Solanaceae / Tomat Ceri, Cabai)',
            'growing_medium' => 'Substrat Tanah Lempung Berpasir & Kompos Arang Sekam',
            'measurement_method' => 'Elektroda potensiometrik ion H⁺ probe Modbus RS485',
            'verification_status' => 'Acuan Presisi Lapangan (Perlu Kalibrasi Berkala pH 4.01 & 6.86)',
            'measurement_caveat' => 'Akurasi sensor lapangan bergantung pada kelembaban tanah (>30%) dan kebersihan elektroda.',
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
            'definition' => 'Air pori tanah untuk metabolisme dan turgor sel.',
            'importance' => 'Menjaga pasokan air dan metabolisme daun.',
            'riskNote' => 'Terlalu kering memicu layu; jenuh air memicu busuk akar.',
            'low_implication' => 'Cekaman kekeringan (stomata menutup, turgor daun layu)',
            'high_implication' => 'Hipoksia perakaran (anaerob), risiko busuk akar',
            'source_title' => 'Target Pengairan Presisi SMK ATPH (Kapasitas Lapang VWC)',
            'crop_applicability' => 'Hortikultura Solanaceae pada Fase Vegetatif-Generatif',
            'growing_medium' => 'Media Tanam Campuran Arang Sekam & Cocopeat',
            'measurement_method' => 'Sensor permitivitas dielektrik FDR/TDR frekuensi tinggi',
            'verification_status' => 'Target Operasional Lapangan (Berdasarkan Kapasitas Lapang Substrat)',
            'measurement_caveat' => 'Nilai kadar lengas volumetrik (VWC) sangat dipengaruhi oleh porositas dan pemadatan bulk density media tanam.',
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
            'definition' => 'Konsentrasi ion garam pupuk terlarut pada media.',
            'importance' => 'Panduan dosis kepekatan larutan fertigasi tanaman.',
            'riskNote' => 'EC pekat membakar akar; terlalu rendah defisit nutrisi.',
            'low_implication' => 'Larutan miskin hara makro, defisit nutrisi terlarut',
            'high_implication' => 'Tekanan osmotik tinggi, risiko plasmolisis / akar terbakar',
            'source_title' => 'Formula Fertigasi Standar Tomat Ceri (Bitanic Presisi)',
            'crop_applicability' => 'Tanaman Sayuran Buah (Tomat Ceri, Melon, Cabai)',
            'growing_medium' => 'Substrat Organik / Fertigasi Tetes',
            'measurement_method' => 'Sensor konduktivitas listrik probe 2-pin elektroda baja tahan karat',
            'verification_status' => 'Acuan Operasional Lapangan (EC Larutan Pori)',
            'measurement_caveat' => 'Sensor mengukur bulk EC tanah, yang dipengaruhi oleh kadar air dan salinitas; bukan pembacaan langsung larutan ekstrak pasta jenuh laboratorium (ECe).',
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
            'definition' => 'Suhu zona perakaran penunjang respirasi dan enzim.',
            'importance' => 'Menjaga laju absorpsi air dan mikroba perombak.',
            'riskNote' => 'Panas ekstrem (> 30°C) memicu stres dehidrasi tanaman.',
            'low_implication' => 'Aktivitas perakaran dan serapan hara/air lambat',
            'high_implication' => 'Respirasi berlebih, stres panas, laju transpirasi tinggi',
            'source_title' => 'Agroklimat Tropis Zona Perakaran Hortikultura',
            'crop_applicability' => 'Hortikultura Tropis Dataran Menengah-Tinggi',
            'growing_medium' => 'Semua Jenis Media Tanam Substrat',
            'measurement_method' => 'Sensor termistor NTC terenkapsulasi baja probe Modbus',
            'verification_status' => 'Pengukuran Fisik Langsung (Tervalidasi Sensorik)',
            'measurement_caveat' => 'Suhu perakaran berfluktuasi seiring intensitas radiasi matahari dan kedalaman penancapan probe sensor.',
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
            'definition' => 'Hara pembentuk klorofil dan biomassa daun hijau.',
            'importance' => 'Mendorong ekspansi daun dan fotosintesis aktif.',
            'riskNote' => 'Defisiensi memicu daun menguning; berlebih rentan hama.',
            'low_implication' => 'Klorosis daun tua, pertumbuhan vegetatif kerdil',
            'high_implication' => 'Vegetatif sukulen berlebih, rentan rebah dan hama',
            'source_title' => 'Preset Operasional Indikator NPK Probe Modbus (SMK Pertanian)',
            'crop_applicability' => 'Monitoring Tren Relatif Ketersediaan Pupuk',
            'growing_medium' => 'Substrat Tanah Padat dan Campuran Organik',
            'measurement_method' => 'Sensor cepat kolorimetrik/konduktometrik Modbus RS485 probe 7-in-1',
            'verification_status' => 'Estimasi Operasional Cepat (Bukan Uji Laboratorium Kjeldahl)',
            'measurement_caveat' => 'PERHATIAN ILMIAH: Pembacaan sensor cepat NPK probe lapangan merupakan estimasi tren operasional berbasis algoritma pabrikan, bukan analisis laboratorium kimia tanah basah destruktif (metode Kjeldahl). Jangan gunakan sebagai dasar mutlak rekomendasi pupuk tunggal tanpa uji lab tanah resmi.',
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
            'definition' => 'Penyusun energi (ATP) dan inisiasi akar serta bunga.',
            'importance' => 'Mempercepat perakaran awal dan pembungaan.',
            'riskNote' => 'Kekurangan memicu akar kerdil dan daun keunguan.',
            'low_implication' => 'Perakaran kerdil, daun tua keunguan, inisiasi bunga lambat',
            'high_implication' => 'Mengganggu penyerapan mikronutrien seng (Zn) dan besi (Fe)',
            'source_title' => 'Preset Operasional Indikator NPK Probe Modbus (SMK Pertanian)',
            'crop_applicability' => 'Monitoring Tren Relatif Ketersediaan Pupuk',
            'growing_medium' => 'Substrat Tanah Padat dan Campuran Organik',
            'measurement_method' => 'Sensor cepat optik/konduktometrik Modbus RS485 probe 7-in-1',
            'verification_status' => 'Estimasi Operasional Cepat (Bukan Uji Laboratorium Bray/Olsen)',
            'measurement_caveat' => 'PERHATIAN ILMIAH: Nilai fosfor terukur adalah estimasi relatif in-situ. Ketersediaan P riil di rizosfer dikendalikan oleh pH tanah; penentuan dosis pemupukan presisi tetap memerlukan validasi uji lab ekstrak Bray-1 / Olsen.',
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
            'definition' => 'Pengatur turgor stomata, bobot buah, dan imunitas.',
            'importance' => 'Meningkatkan ketahanan stres dan mutu panen.',
            'riskNote' => 'Kekurangan memicu tepi daun hangus dan buah kecil.',
            'low_implication' => 'Nekrosis tepi daun, batang lemah, toleransi stres rendah',
            'high_implication' => 'Antagonis serapan kation magnesium (Mg) dan kalsium (Ca)',
            'source_title' => 'Preset Operasional Indikator NPK Probe Modbus (SMK Pertanian)',
            'crop_applicability' => 'Monitoring Tren Relatif Ketersediaan Pupuk',
            'growing_medium' => 'Substrat Tanah Padat dan Campuran Organik',
            'measurement_method' => 'Sensor cepat ionik/konduktometrik Modbus RS485 probe 7-in-1',
            'verification_status' => 'Estimasi Operasional Cepat (Bukan Uji Spektrofotometri Serapan Atom)',
            'measurement_caveat' => 'PERHATIAN ILMIAH: Pembacaan kalium probe mengestimasi hara kalium bebas rizosfer secara operasional untuk pembelajaran SMK, bukan pengganti uji laboratorium NH4OAc pH 7.',
        ],
    ];

    /**
     * Check if a given school has configured custom sensor thresholds.
     */
    public function isCustomizedForSchool(?int $schoolId): bool
    {
        if (! $schoolId) {
            return false;
        }

        $setting = SchoolSetting::where('school_id', $schoolId)->first();

        return $setting && is_array($setting->sensor_thresholds) && ! empty($setting->sensor_thresholds);
    }

    /**
     * Resolve authoritative thresholds for a given school, merging stored settings with default presets.
     *
     * @return array<string, array<string, mixed>>
     */
    public function resolveForSchool(?int $schoolId): array
    {
        $resolved = self::DEFAULTS;
        foreach (array_keys($resolved) as $key) {
            $resolved[$key]['is_customized'] = false;
            $resolved[$key]['source_label'] = 'Preset Standar';
        }

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
            $resolved['ph']['is_customized'] = true;
            $resolved['ph']['source_label'] = 'Disesuaikan Sekolah';
        }
        if (isset($th['maxPH'])) {
            $resolved['ph']['ideal_max'] = (float) $th['maxPH'];
            $resolved['ph']['is_customized'] = true;
            $resolved['ph']['source_label'] = 'Disesuaikan Sekolah';
        }

        // Moisture / Lengas
        if (isset($th['minMoisture'])) {
            $resolved['moisture']['ideal_min'] = (float) $th['minMoisture'];
            $resolved['moisture']['is_customized'] = true;
            $resolved['moisture']['source_label'] = 'Disesuaikan Sekolah';
        }
        if (isset($th['maxMoisture'])) {
            $resolved['moisture']['ideal_max'] = (float) $th['maxMoisture'];
            $resolved['moisture']['is_customized'] = true;
            $resolved['moisture']['source_label'] = 'Disesuaikan Sekolah';
        }

        // EC
        if (isset($th['minEC'])) {
            $resolved['ec']['ideal_min'] = (float) $th['minEC'];
            $resolved['ec']['is_customized'] = true;
            $resolved['ec']['source_label'] = 'Disesuaikan Sekolah';
        }
        if (isset($th['maxEC'])) {
            $resolved['ec']['ideal_max'] = (float) $th['maxEC'];
            $resolved['ec']['is_customized'] = true;
            $resolved['ec']['source_label'] = 'Disesuaikan Sekolah';
        }

        // Temperature
        if (isset($th['minTemperature'])) {
            $resolved['temperature']['ideal_min'] = (float) $th['minTemperature'];
            $resolved['temperature']['is_customized'] = true;
            $resolved['temperature']['source_label'] = 'Disesuaikan Sekolah';
        }
        if (isset($th['maxTemperature'])) {
            $resolved['temperature']['ideal_max'] = (float) $th['maxTemperature'];
            $resolved['temperature']['is_customized'] = true;
            $resolved['temperature']['source_label'] = 'Disesuaikan Sekolah';
        }

        // Nitrogen
        if (isset($th['minNitrogen'])) {
            $resolved['nitrogen']['ideal_min'] = (float) $th['minNitrogen'];
            $resolved['nitrogen']['is_customized'] = true;
            $resolved['nitrogen']['source_label'] = 'Disesuaikan Sekolah';
        }
        if (isset($th['maxNitrogen'])) {
            $resolved['nitrogen']['ideal_max'] = (float) $th['maxNitrogen'];
            $resolved['nitrogen']['is_customized'] = true;
            $resolved['nitrogen']['source_label'] = 'Disesuaikan Sekolah';
        }

        // Phosphorus
        if (isset($th['minPhosphorus'])) {
            $resolved['phosphorus']['ideal_min'] = (float) $th['minPhosphorus'];
            $resolved['phosphorus']['is_customized'] = true;
            $resolved['phosphorus']['source_label'] = 'Disesuaikan Sekolah';
        }
        if (isset($th['maxPhosphorus'])) {
            $resolved['phosphorus']['ideal_max'] = (float) $th['maxPhosphorus'];
            $resolved['phosphorus']['is_customized'] = true;
            $resolved['phosphorus']['source_label'] = 'Disesuaikan Sekolah';
        }

        // Potassium
        if (isset($th['minPotassium'])) {
            $resolved['potassium']['ideal_min'] = (float) $th['minPotassium'];
            $resolved['potassium']['is_customized'] = true;
            $resolved['potassium']['source_label'] = 'Disesuaikan Sekolah';
        }
        if (isset($th['maxPotassium'])) {
            $resolved['potassium']['ideal_max'] = (float) $th['maxPotassium'];
            $resolved['potassium']['is_customized'] = true;
            $resolved['potassium']['source_label'] = 'Disesuaikan Sekolah';
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
                'status_code' => 'no_data',
                'label' => 'Belum ada data',
                'short_label' => 'Belum ada data',
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
                'status_code' => 'optimal',
                'label' => 'Dalam rentang acuan',
                'short_label' => 'Dalam rentang',
                'badge_class' => 'text-emerald-700 bg-emerald-50',
                'is_optimal' => true,
            ];
        }

        if ($val < $idealMin) {
            $isCritical = $val <= $critLow;

            return [
                'status' => $isCritical ? 'critical' : 'warning',
                'status_code' => 'low',
                'label' => match ($paramKey) {
                    'ph' => $isCritical ? 'Masam Ekstrem' : 'Agak Masam',
                    'moisture' => $isCritical ? 'Kering Kritis' : 'Lengas Rendah',
                    'ec' => $isCritical ? 'Defisit Ekstrem' : 'Rendah/Defisit',
                    'temperature' => $isCritical ? 'Dingin Kritis' : 'Suhu Rendah',
                    'nitrogen', 'phosphorus', 'potassium' => $isCritical ? 'Defisiensi Berat' : 'Defisiensi',
                    default => 'Rendah',
                },
                'short_label' => 'Di bawah rentang',
                'badge_class' => $isCritical ? 'text-rose-700 bg-rose-50' : 'text-amber-700 bg-amber-50',
                'is_optimal' => false,
            ];
        }

        // val > idealMax
        $isCritical = $val >= $critHigh;

        return [
            'status' => $isCritical ? 'critical' : 'warning',
            'status_code' => 'high',
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
            'short_label' => 'Di atas rentang',
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
