<?php

namespace App\Services\Agronomy;

use App\Models\TelemetryReading;
use Illuminate\Support\Collection;

class AnomalyAlertEvaluator
{
    /**
     * Ideal parameter thresholds for vocational precision agriculture standard (SMK ATPH 4.0).
     */
    public const THRESHOLDS = [
        'ph' => [
            'parameter' => 'pH Tanah',
            'min' => 5.5,
            'max' => 7.5,
            'ideal_min' => 6.0,
            'ideal_max' => 7.2,
            'unit' => 'pH',
        ],
        'ec' => [
            'parameter' => 'EC Nutrisi',
            'min' => 800.0,
            'max' => 2400.0,
            'ideal_min' => 1000.0,
            'ideal_max' => 2200.0,
            'unit' => 'µS/cm',
        ],
        'humidity' => [
            'parameter' => 'Kelembaban Tanah',
            'min' => 40.0,
            'max' => 85.0,
            'ideal_min' => 45.0,
            'ideal_max' => 65.0,
            'unit' => '%',
        ],
        'temperature' => [
            'parameter' => 'Suhu Lingkungan',
            'min' => 18.0,
            'max' => 33.0,
            'ideal_min' => 22.0,
            'ideal_max' => 29.0,
            'unit' => '°C',
        ],
        'nitrogen' => [
            'parameter' => 'Nitrogen (N)',
            'min' => 20.0,
            'max' => 150.0,
            'ideal_min' => 40.0,
            'ideal_max' => 120.0,
            'unit' => 'mg/kg',
        ],
        'phosphorus' => [
            'parameter' => 'Fosfor (P)',
            'min' => 15.0,
            'max' => 80.0,
            'ideal_min' => 20.0,
            'ideal_max' => 50.0,
            'unit' => 'mg/kg',
        ],
        'potassium' => [
            'parameter' => 'Kalium (K)',
            'min' => 50.0,
            'max' => 250.0,
            'ideal_min' => 100.0,
            'ideal_max' => 180.0,
            'unit' => 'mg/kg',
        ],
    ];

    /**
     * Evaluate a reading and generate agronomic alerts with safe non-prescriptive guidance.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluate(?TelemetryReading $reading): Collection
    {
        if (! $reading) {
            return collect();
        }

        $alerts = collect();

        // 1. Evaluasi pH Tanah (Derajat Keasaman)
        if ($reading->ph !== null) {
            $ph = (float) $reading->ph;
            if ($ph > 0) {
                if ($ph < 5.5) {
                    $alerts->push([
                        'parameter' => 'pH Tanah',
                        'parameter_key' => 'ph',
                        'value' => number_format($ph, 1),
                        'unit' => 'pH',
                        'severity' => 'danger',
                        'status' => 'active',
                        'title' => 'Tingkat Keasaman Masam Kritis (pH '.number_format($ph, 1).')',
                        'impact' => 'Penyerapan unsur N-P-K terikat kuat oleh mineral tanah; memicu toksisitas ion Aluminium (Al).',
                        'action' => 'Aplikasikan 50-80 gram kapur dolomit per m² media tanam dan siram dengan air netral untuk aktivasi buffer.',
                    ]);
                } elseif ($ph > 7.5) {
                    $alerts->push([
                        'parameter' => 'pH Tanah',
                        'parameter_key' => 'ph',
                        'value' => number_format($ph, 1),
                        'unit' => 'pH',
                        'severity' => 'warning',
                        'status' => 'active',
                        'title' => 'Tingkat Keasaman Bersifat Basa (pH '.number_format($ph, 1).')',
                        'impact' => 'Risiko klorosis klorofil akibat defisiensi zat besi (Fe) dan mangan (Mn).',
                        'action' => 'Tambahkan belerang pertanian (sulfur) atau lakukan penyiraman dengan larutan pupuk ZA berkadar asam ringan.',
                    ]);
                }
            }
        }

        // 2. Evaluasi Daya Hantar Listrik / EC (Salinitas & Konsentrasi Pupuk)
        if ($reading->ec !== null) {
            $ec = (float) $reading->ec;
            if ($ec > 0) {
                if ($ec < 800) {
                    $alerts->push([
                        'parameter' => 'EC Nutrisi',
                        'parameter_key' => 'ec',
                        'value' => number_format($ec, 0),
                        'unit' => 'µS/cm',
                        'severity' => 'warning',
                        'status' => 'active',
                        'title' => 'Konsentrasi Larutan Rendah (EC '.number_format($ec, 0).' µS/cm)',
                        'impact' => 'Tanaman kekurangan pasokan ion hara esensial untuk pembelahan sel vegetatif.',
                        'action' => 'Tambahkan larutan pupuk stok formulasi hara makro (AB Mix) dosis 5–10 ml/liter air secara terukur ke bak fertigasi hingga EC mencapai zona ideal.',
                    ]);
                } elseif ($ec > 2400) {
                    $alerts->push([
                        'parameter' => 'EC Nutrisi',
                        'parameter_key' => 'ec',
                        'value' => number_format($ec, 0),
                        'unit' => 'µS/cm',
                        'severity' => 'danger',
                        'status' => 'active',
                        'title' => 'Kepekatan Garam/Pupuk Berlebih (EC '.number_format($ec, 0).' µS/cm)',
                        'impact' => 'Potensi plasmolisis akar (fertilizer burn), daun terbakar, dan tanaman layu.',
                        'action' => 'Segera lakukan flushing media tanam dengan air murni hingga EC air limpasan turun di bawah 1600 µS/cm.',
                    ]);
                }
            }
        }

        // 3. Evaluasi Lengas / Kelembaban Tanah (Volumetric Water Content)
        if ($reading->humidity !== null) {
            $humidity = (float) $reading->humidity;
            if ($humidity > 0) {
                if ($humidity < 40.0) {
                    $alerts->push([
                        'parameter' => 'Kelembaban Tanah',
                        'parameter_key' => 'humidity',
                        'value' => number_format($humidity, 1),
                        'unit' => '%',
                        'severity' => 'danger',
                        'status' => 'active',
                        'title' => 'Media Tanam Mengering di Bawah Titik Kritis ('.number_format($humidity, 1).'%)',
                        'impact' => 'Tanaman mendekati titik layu permanen; stomata menutup dan fotosintesis terhenti.',
                        'action' => 'Lakukan penyiraman atau irigasi tetes manual hingga media mencapai kapasitas lapang (kadar lengas 50–65%) dan periksa kelancaran emisi penetes.',
                    ]);
                } elseif ($humidity > 85.0) {
                    $alerts->push([
                        'parameter' => 'Kelembaban Tanah',
                        'parameter_key' => 'humidity',
                        'value' => number_format($humidity, 1),
                        'unit' => '%',
                        'severity' => 'warning',
                        'status' => 'active',
                        'title' => 'Media Tanam Jenuh Air ('.number_format($humidity, 1).'%)',
                        'impact' => 'Aerasi pori mikro tanah terhambat, risiko anoksia akar dan infeksi jamur busuk akar Pythium.',
                        'action' => 'Hentikan siklus penyiraman dan pastikan lubang drainase polybag atau bedengan tidak tersumbat.',
                    ]);
                }
            }
        }

        // 4. Evaluasi Suhu (Temperatur Kanopi & Zona Perakaran)
        if ($reading->temperature !== null) {
            $temp = (float) $reading->temperature;
            if ($temp > 33.0) {
                $alerts->push([
                    'parameter' => 'Suhu Lingkungan',
                    'parameter_key' => 'temperature',
                    'value' => number_format($temp, 1),
                    'unit' => '°C',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Suhu Kanopi Panas Ekstrem ('.number_format($temp, 1).'°C)',
                    'impact' => 'Tingkat transpirasi melampaui serapan akar; memicu heat stress pada pucuk daun muda.',
                    'action' => 'Tingkatkan sirkulasi udara greenhouse/laboratorium dan tutup tirai paranet peneduh untuk mereduksi radiasi panas kanopi tanaman.',
                ]);
            } elseif ($temp > 0 && $temp < 18.0) {
                $alerts->push([
                    'parameter' => 'Suhu Lingkungan',
                    'parameter_key' => 'temperature',
                    'value' => number_format($temp, 1),
                    'unit' => '°C',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Suhu Lingkungan Dingin Ekstrem ('.number_format($temp, 1).'°C)',
                    'impact' => 'Aktivitas metabolisme akar dan translokasi cairan hara melambat, meningkatkan risiko chilling injury.',
                    'action' => 'Tutup ventilasi perimeter greenhouse pada malam hari dan kurangi frekuensi irigasi dingin untuk menjaga temperatur substrat akar.',
                ]);
            }
        }

        // 5. Evaluasi Unsur Hara Makro: Nitrogen (N)
        if ($reading->nitrogen !== null) {
            $nitrogen = (float) $reading->nitrogen;
            if ($nitrogen > 0 && $nitrogen < 20.0) {
                $alerts->push([
                    'parameter' => 'Nitrogen (N)',
                    'parameter_key' => 'nitrogen',
                    'value' => number_format($nitrogen, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Defisiensi Nitrogen Tersedia ('.number_format($nitrogen, 1).' mg/kg)',
                    'impact' => 'Menghambat pembentukan klorofil dan sintesis protein; memicu klorosis daun tua dan pertumbuhan kerdil.',
                    'action' => 'Aplikasikan larutan pupuk nitrogen terlarut (seperti KNO3 atau pupuk majemuk) berkonsentrasi tepat ke zona perakaran.',
                ]);
            } elseif ($nitrogen > 150.0) {
                $alerts->push([
                    'parameter' => 'Nitrogen (N)',
                    'parameter_key' => 'nitrogen',
                    'value' => number_format($nitrogen, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Kelebihan Nitrogen Tersedia ('.number_format($nitrogen, 1).' mg/kg)',
                    'impact' => 'Pertumbuhan vegetatif sukulen berlebih, jaringan daun lunak, tanaman rentan rebah dan rentan serangan hama.',
                    'action' => 'Tunda penambahan pupuk N tinggi dan seimbangkan dengan pasokan hara fosfat serta kalium.',
                ]);
            }
        }

        // 6. Evaluasi Unsur Hara Makro: Fosfor (P)
        if ($reading->phosphorus !== null) {
            $phosphorus = (float) $reading->phosphorus;
            if ($phosphorus > 0 && $phosphorus < 15.0) {
                $alerts->push([
                    'parameter' => 'Fosfor (P)',
                    'parameter_key' => 'phosphorus',
                    'value' => number_format($phosphorus, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Defisiensi Fosfor Tersedia ('.number_format($phosphorus, 1).' mg/kg)',
                    'impact' => 'Pertumbuhan sistem perakaran dan inisiasi perbungaan terhambat; daun bawah cenderung berwarna gelap keunguan.',
                    'action' => 'Berikan pupuk fosfat larut air (seperti Mono Kalium Fosfat / MKP) untuk memacu perkembangan akar lateral.',
                ]);
            }
        }

        // 7. Evaluasi Unsur Hara Makro: Kalium (K)
        if ($reading->potassium !== null) {
            $potassium = (float) $reading->potassium;
            if ($potassium > 0 && $potassium < 50.0) {
                $alerts->push([
                    'parameter' => 'Kalium (K)',
                    'parameter_key' => 'potassium',
                    'value' => number_format($potassium, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Defisiensi Kalium Tersedia ('.number_format($potassium, 1).' mg/kg)',
                    'impact' => 'Turgor stomata melemah, translokasi karbohidrat terganggu, dan tepi daun tua mengalami nekrosis mengering.',
                    'action' => 'Tambahkan pupuk kalium mudah diserap (Kalium Sulfat / K2SO4) untuk memperkuat dinding sel dan ketahanan tanaman terhadap cekaman lingkungan.',
                ]);
            }
        }

        return $alerts;
    }

    /**
     * Check if a single reading has any anomaly flags.
     */
    public function isAnomaly(TelemetryReading $reading): bool
    {
        return $this->evaluate($reading)->isNotEmpty();
    }

    /**
     * Expose standardized agronomic threshold definitions for SMK ATPH 4.0.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getThresholdCatalog(): array
    {
        return self::THRESHOLDS;
    }
}
