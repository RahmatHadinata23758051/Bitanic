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
            'ideal_min' => 25.0,
            'ideal_max' => 60.0,
            'unit' => 'mg/kg',
        ],
        'potassium' => [
            'parameter' => 'Kalium (K)',
            'min' => 50.0,
            'max' => 250.0,
            'ideal_min' => 100.0,
            'ideal_max' => 200.0,
            'unit' => 'mg/kg',
        ],
    ];

    protected AgronomicThresholdResolver $thresholdResolver;

    public function __construct(?AgronomicThresholdResolver $thresholdResolver = null)
    {
        $this->thresholdResolver = $thresholdResolver ?? app(AgronomicThresholdResolver::class);
    }

    /**
     * Resolve effective thresholds for evaluation (school-scoped or defaults).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEffectiveThresholds(?int $schoolId = null): array
    {
        return $this->thresholdResolver->resolveForSchool($schoolId);
    }

    /**
     * Evaluate a reading and generate agronomic alerts with safe neutral inspection guidance.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluate(?TelemetryReading $reading, ?int $schoolId = null): Collection
    {
        if (! $reading) {
            return collect();
        }

        $effectiveSchoolId = $schoolId ?? $reading->device?->school_id;
        $thresholds = $this->getEffectiveThresholds($effectiveSchoolId);
        $alerts = collect();

        // 1. Evaluasi pH Tanah (Derajat Keasaman)
        if ($reading->ph !== null) {
            $ph = (float) $reading->ph;
            $phMin = (float) ($thresholds['ph']['ideal_min'] ?? 6.0);
            $phMax = (float) ($thresholds['ph']['ideal_max'] ?? 7.2);

            if ($ph >= 0) {
                if ($ph < $phMin) {
                    $alerts->push([
                        'parameter' => 'pH Tanah',
                        'parameter_key' => 'ph',
                        'value' => number_format($ph, 1),
                        'unit' => 'pH',
                        'severity' => $ph < 5.0 ? 'danger' : 'warning',
                        'status' => 'active',
                        'title' => 'Tingkat Keasaman Masam (pH '.number_format($ph, 1).')',
                        'impact' => 'Penyerapan hara makro terhambat dan kelarutan logam tanah meningkat.',
                        'action' => 'Verifikasi kalibrasi sensor pH dan periksa buffer kemasaman media. Konsultasikan kebutuhan kapur dolomit dengan instruktur sesuai hasil uji tanah sebelum aplikasi.',
                    ]);
                } elseif ($ph > $phMax) {
                    $alerts->push([
                        'parameter' => 'pH Tanah',
                        'parameter_key' => 'ph',
                        'value' => number_format($ph, 1),
                        'unit' => 'pH',
                        'severity' => 'warning',
                        'status' => 'active',
                        'title' => 'Tingkat Keasaman Bersifat Basa (pH '.number_format($ph, 1).')',
                        'impact' => 'Ketersediaan unsur mikro esensial (Fe, Mn, Zn, Cu) menurun.',
                        'action' => 'Verifikasi elektroda sensor pH dan periksa kualitas air irigasi. Konsultasikan tindakan penurunan pH (seperti belerang pertanian) dengan instruktur sebelum aplikasi.',
                    ]);
                }
            }
        }

        // 2. Evaluasi Daya Hantar Listrik / EC (Salinitas & Konsentrasi Pupuk)
        if ($reading->ec !== null) {
            $ec = (float) $reading->ec;
            $ecMin = (float) ($thresholds['ec']['ideal_min'] ?? 1000.0);
            $ecMax = (float) ($thresholds['ec']['ideal_max'] ?? 2200.0);

            if ($ec >= 0) {
                if ($ec < $ecMin) {
                    $alerts->push([
                        'parameter' => 'EC Nutrisi',
                        'parameter_key' => 'ec',
                        'value' => number_format($ec, 0),
                        'unit' => 'µS/cm',
                        'severity' => 'warning',
                        'status' => 'active',
                        'title' => 'Konsentrasi Larutan Nutrisi Rendah (EC '.number_format($ec, 0).' µS/cm)',
                        'impact' => 'Ketersediaan ion hara terlarut di bawah rentang acuan vegetatif.',
                        'action' => 'Periksa konsentrasi larutan nutrisi (AB Mix) dan kebersihan probe EC. Verifikasi takaran dengan instruktur sebelum penambahan larutan hara.',
                    ]);
                } elseif ($ec > $ecMax) {
                    $alerts->push([
                        'parameter' => 'EC Nutrisi',
                        'parameter_key' => 'ec',
                        'value' => number_format($ec, 0),
                        'unit' => 'µS/cm',
                        'severity' => 'danger',
                        'status' => 'active',
                        'title' => 'Kepekatan Garam/Pupuk Berlebih (EC '.number_format($ec, 0).' µS/cm)',
                        'impact' => 'Tekanan osmotik tinggi berisiko memicu plasmolisis dan daun terbakar.',
                        'action' => 'Lakukan flushing (pembilasan) media tanam dengan air baku dan periksa kalibrasi probe EC untuk mencegah stres osmotik perakaran.',
                    ]);
                }
            }
        }

        // 3. Evaluasi Lengas / Kelembaban Tanah (Volumetric Water Content)
        if ($reading->humidity !== null) {
            $humidity = (float) $reading->humidity;
            $humMin = (float) ($thresholds['moisture']['ideal_min'] ?? 45.0);
            $humMax = (float) ($thresholds['moisture']['ideal_max'] ?? 65.0);

            if ($humidity >= 0) {
                if ($humidity < $humMin) {
                    $alerts->push([
                        'parameter' => 'Kelembaban Tanah',
                        'parameter_key' => 'humidity',
                        'value' => number_format($humidity, 1),
                        'unit' => '%',
                        'severity' => 'danger',
                        'status' => 'active',
                        'title' => 'Media Tanam Mengering di Bawah Titik Kritis ('.number_format($humidity, 1).'%)',
                        'impact' => 'Tanaman mendekati titik layu sementara; turgor sel dan fotosintesis terhambat.',
                        'action' => 'Periksa sistem irigasi, kebasahan substrat, dan kontak probe kelembaban tanah dengan media tanam.',
                    ]);
                } elseif ($humidity > $humMax) {
                    $alerts->push([
                        'parameter' => 'Kelembaban Tanah',
                        'parameter_key' => 'humidity',
                        'value' => number_format($humidity, 1),
                        'unit' => '%',
                        'severity' => 'warning',
                        'status' => 'active',
                        'title' => 'Media Tanam Jenuh Air ('.number_format($humidity, 1).'%)',
                        'impact' => 'Aerasi pori tanah terhambat, risiko anoksia perakaran meningkat.',
                        'action' => 'Periksa kelancaran drainase media dan tunda penyiraman untuk memulihkan aerasi perakaran.',
                    ]);
                }
            }
        }

        // 4. Evaluasi Suhu (Temperatur Kanopi & Zona Perakaran)
        if ($reading->temperature !== null) {
            $temp = (float) $reading->temperature;
            $tempMin = (float) ($thresholds['temperature']['ideal_min'] ?? 22.0);
            $tempMax = (float) ($thresholds['temperature']['ideal_max'] ?? 29.0);

            if ($temp > $tempMax) {
                $alerts->push([
                    'parameter' => 'Suhu Lingkungan',
                    'parameter_key' => 'temperature',
                    'value' => number_format($temp, 1),
                    'unit' => '°C',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Suhu Kanopi Panas Ekstrem ('.number_format($temp, 1).'°C)',
                    'impact' => 'Tingkat transpirasi melampaui serapan akar; memicu heat stress pada pucuk daun muda.',
                    'action' => 'Tingkatkan sirkulasi ventilasi dan periksa paranet peneduh untuk mengurangi radiasi panas kanopi.',
                ]);
            } elseif ($temp > 0 && $temp < $tempMin) {
                $alerts->push([
                    'parameter' => 'Suhu Lingkungan',
                    'parameter_key' => 'temperature',
                    'value' => number_format($temp, 1),
                    'unit' => '°C',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Suhu Lingkungan Dingin Ekstrem ('.number_format($temp, 1).'°C)',
                    'impact' => 'Aktivitas metabolisme akar dan translokasi cairan hara melambat.',
                    'action' => 'Periksa ventilasi dan kondisi mikroklimat ruang praktik/greenhouse untuk menjaga temperatur media.',
                ]);
            }
        }

        // 5. Evaluasi Unsur Hara Makro: Nitrogen (N)
        if ($reading->nitrogen !== null) {
            $nitrogen = (float) $reading->nitrogen;
            $nMin = (float) ($thresholds['nitrogen']['ideal_min'] ?? 40.0);
            $nMax = (float) ($thresholds['nitrogen']['ideal_max'] ?? 120.0);

            if ($nitrogen >= 0 && $nitrogen < $nMin) {
                $alerts->push([
                    'parameter' => 'Nitrogen (N)',
                    'parameter_key' => 'nitrogen',
                    'value' => number_format($nitrogen, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Estimasi Nitrogen Rendah ('.number_format($nitrogen, 1).' mg/kg)',
                    'impact' => 'Menghambat pembentukan klorofil dan sintesis protein; memicu klorosis daun tua dan pertumbuhan kerdil.',
                    'action' => 'Verifikasi tren pembacaan sensor dan konsultasikan kebutuhan pemupukan nitrogen dengan instruktur.',
                ]);
            } elseif ($nitrogen > $nMax) {
                $alerts->push([
                    'parameter' => 'Nitrogen (N)',
                    'parameter_key' => 'nitrogen',
                    'value' => number_format($nitrogen, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Estimasi Nitrogen Tinggi ('.number_format($nitrogen, 1).' mg/kg)',
                    'impact' => 'Pertumbuhan vegetatif sukulen berlebih, jaringan daun lunak, tanaman rentan rebah.',
                    'action' => 'Verifikasi pembacaan sensor dan pantau perkembangan vegetatif sebelum penambahan pupuk hara.',
                ]);
            }
        }

        // 6. Evaluasi Unsur Hara Makro: Fosfor (P)
        if ($reading->phosphorus !== null) {
            $phosphorus = (float) $reading->phosphorus;
            $pMin = (float) ($thresholds['phosphorus']['ideal_min'] ?? 25.0);
            $pMax = (float) ($thresholds['phosphorus']['ideal_max'] ?? 60.0);

            if ($phosphorus >= 0 && $phosphorus < $pMin) {
                $alerts->push([
                    'parameter' => 'Fosfor (P)',
                    'parameter_key' => 'phosphorus',
                    'value' => number_format($phosphorus, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Estimasi Fosfor Rendah ('.number_format($phosphorus, 1).' mg/kg)',
                    'impact' => 'Pertumbuhan sistem perakaran dan inisiasi pembungaan terhambat; daun bawah cenderung kusam.',
                    'action' => 'Periksa pH media tanam (pH masam/basa mengikat fosfor) dan verifikasi elektroda sensor.',
                ]);
            } elseif ($phosphorus > $pMax) {
                $alerts->push([
                    'parameter' => 'Fosfor (P)',
                    'parameter_key' => 'phosphorus',
                    'value' => number_format($phosphorus, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Estimasi Fosfor Tinggi ('.number_format($phosphorus, 1).' mg/kg)',
                    'impact' => 'Kelebihan fosfat dapat menghambat penyerapan hara mikro seperti seng (Zn) dan besi (Fe).',
                    'action' => 'Verifikasi pembacaan sensor dan pantau kondisi rizosfer perakaran tanaman.',
                ]);
            }
        }

        // 7. Evaluasi Unsur Hara Makro: Kalium (K)
        if ($reading->potassium !== null) {
            $potassium = (float) $reading->potassium;
            $kMin = (float) ($thresholds['potassium']['ideal_min'] ?? 100.0);
            $kMax = (float) ($thresholds['potassium']['ideal_max'] ?? 200.0);

            if ($potassium >= 0 && $potassium < $kMin) {
                $alerts->push([
                    'parameter' => 'Kalium (K)',
                    'parameter_key' => 'potassium',
                    'value' => number_format($potassium, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Estimasi Kalium Rendah ('.number_format($potassium, 1).' mg/kg)',
                    'impact' => 'Turgor stomata melemah, translokasi karbohidrat terganggu, dan tepi daun rentan nekrosis.',
                    'action' => 'Verifikasi pembacaan sensor dan pantau gejala fisik daun serta ketahanan stres tanaman.',
                ]);
            } elseif ($potassium > $kMax) {
                $alerts->push([
                    'parameter' => 'Kalium (K)',
                    'parameter_key' => 'potassium',
                    'value' => number_format($potassium, 1),
                    'unit' => 'mg/kg',
                    'severity' => 'warning',
                    'status' => 'active',
                    'title' => 'Estimasi Kalium Tinggi ('.number_format($potassium, 1).' mg/kg)',
                    'impact' => 'Kelebihan kalium dapat mengganggu keseimbangan kation magnesium (Mg) dan kalsium (Ca).',
                    'action' => 'Verifikasi pembacaan sensor dan pantau kepekatan larutan media tanam.',
                ]);
            }
        }

        return $alerts;
    }

    /**
     * Determine full canonical classification for a telemetry reading.
     *
     * @return array{code: string, label: string, badge_class: string, alerts: Collection, deviations: array<int, string>}
     */
    public function classify(TelemetryReading $reading, ?int $schoolId = null): array
    {
        $hasAnyMeasurement = (
            $reading->ph !== null ||
            $reading->ec !== null ||
            $reading->humidity !== null ||
            $reading->temperature !== null ||
            $reading->nitrogen !== null ||
            $reading->phosphorus !== null ||
            $reading->potassium !== null
        );

        $hasThresholdFlags = ! empty($reading->threshold_flags);
        $alerts = $this->evaluate($reading, $schoolId);

        if (! $hasAnyMeasurement && ! $hasThresholdFlags) {
            return [
                'code' => 'insufficient',
                'label' => 'Data Parsial',
                'badge_class' => 'bg-slate-100 text-slate-700 border-slate-200',
                'alerts' => collect(),
                'deviations' => [],
            ];
        }

        if ($alerts->isNotEmpty() || $hasThresholdFlags) {
            $deviations = [];
            foreach ($alerts as $a) {
                $deviations[] = $a['title'];
            }
            if ($hasThresholdFlags && empty($deviations)) {
                $deviations = is_array($reading->threshold_flags)
                    ? array_values($reading->threshold_flags)
                    : [(string) $reading->threshold_flags];
            }

            return [
                'code' => 'anomaly',
                'label' => 'Anomali',
                'badge_class' => 'bg-amber-100 text-amber-800 border-amber-200',
                'alerts' => $alerts,
                'deviations' => $deviations,
            ];
        }

        return [
            'code' => 'normal',
            'label' => 'Normal',
            'badge_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'alerts' => collect(),
            'deviations' => [],
        ];
    }

    /**
     * Check if a single reading has any anomaly flags or is outside ideal reference.
     */
    public function isAnomaly(TelemetryReading $reading, ?int $schoolId = null): bool
    {
        return $this->classify($reading, $schoolId)['code'] === 'anomaly';
    }

    /**
     * Check if a single reading is within ideal reference (and not missing).
     */
    public function isNormal(TelemetryReading $reading, ?int $schoolId = null): bool
    {
        return $this->classify($reading, $schoolId)['code'] === 'normal';
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
