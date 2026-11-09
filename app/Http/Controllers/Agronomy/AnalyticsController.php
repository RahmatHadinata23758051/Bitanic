<?php

namespace App\Http\Controllers\Agronomy;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Services\Agronomy\AgronomicThresholdResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Display the agronomic analytics page with data-backed correlation summaries and threshold matrices.
     */
    public function index(Request $request, AgronomicThresholdResolver $thresholdResolver): View
    {
        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Akses analitik memerlukan asosiasi sekolah yang valid.');
            }
            $school = $user->school;
            if (! $school || $school->status !== 'active') {
                abort(403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');
            }
        }

        $selectedSchoolId = $request->query('school_id');
        $schools = $isSuperuser ? School::where('status', 'active')->orderBy('name')->get() : collect();

        if ($isSuperuser && $selectedSchoolId) {
            $targetSchoolId = (int) $selectedSchoolId;
        } else {
            $targetSchoolId = $isSuperuser
                ? null
                : ($schoolId ?? School::query()->where('status', 'active')->orderBy('id')->value('id') ?? -1);
        }

        $deviceId = $request->query('device_id');
        $devices = Device::query()
            ->where('is_active', true)
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->get();

        $selectedDevice = null;
        if ($deviceId) {
            $selectedDevice = $devices->first(fn ($d) => (string) $d->id === (string) $deviceId || $d->code === (string) $deviceId);
        }
        if (! $selectedDevice) {
            $selectedDevice = $devices->first();
        }

        // Global period selector
        $range = (string) $request->query('range', '24h');
        if (! in_array($range, ['1h', '6h', '24h', '7d'], true)) {
            $range = '24h';
        }

        $cutoff = match ($range) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            default => now()->subHours(24),
        };

        $rangeLabels = [
            '1h' => '1 Jam Terakhir',
            '6h' => '6 Jam Terakhir',
            '24h' => '24 Jam Terakhir',
            '7d' => '7 Hari Terakhir',
        ];

        $rangeAxisLabels = [
            '1h' => '-1 jam',
            '6h' => '-6 jam',
            '24h' => '-24 jam',
            '7d' => '-7 hari',
        ];

        // Fetch telemetry readings strictly scoped by tenant and selected period
        $rawReadings = $selectedDevice
            ? TelemetryReading::where('device_id', $selectedDevice->id)
                ->when($targetSchoolId, fn ($q) => $q->whereHas('device', fn ($dq) => $dq->where('school_id', $targetSchoolId)))
                ->where('recorded_at', '>=', $cutoff)
                ->latest('recorded_at')
                ->take(300)
                ->get()
                ->sortBy('recorded_at')
                ->values()
            : collect();

        // Physical bounds validator for sensor telemetry sanitization
        $isWithinBounds = static function (string $key, $val): bool {
            if ($val === null || ! is_numeric($val) || ! is_finite((float) $val)) {
                return false;
            }
            $v = (float) $val;

            return match ($key) {
                'ph' => $v >= 0.0 && $v <= 14.0,
                'humidity', 'moisture' => $v >= 0.0 && $v <= 100.0,
                'ec' => $v >= 0.0 && $v <= 10000.0,
                'temperature' => $v >= -10.0 && $v <= 60.0,
                'nitrogen', 'phosphorus', 'potassium' => $v >= 0.0 && $v <= 1000.0,
                default => true,
            };
        };

        // Deduplicate consecutive timestamps and sanitize physical outliers
        $readings = $rawReadings
            ->unique(fn ($r) => $r->recorded_at?->format('Y-m-d H:i:s') ?? $r->id)
            ->map(function ($reading) use ($isWithinBounds) {
                foreach (['ph', 'ec', 'humidity', 'temperature', 'nitrogen', 'phosphorus', 'potassium'] as $col) {
                    if (! $isWithinBounds($col, $reading->{$col})) {
                        $reading->{$col} = null;
                    }
                }

                return $reading;
            })
            ->values();

        // Provenance composition
        $provenanceGroups = $readings->pluck('provenance')->filter()->countBy();
        $provenanceSummary = match (true) {
            $readings->isEmpty() => 'Belum ada transmisi data',
            $provenanceGroups->has('hardware') && $provenanceGroups->count() === 1 => 'Hardware (Modbus RS485)',
            $provenanceGroups->has('simulation') && $provenanceGroups->count() === 1 => 'Data Simulasi',
            $provenanceGroups->has('fixture') && $provenanceGroups->count() === 1 => 'Data Uji / Fixture',
            default => 'Campuran ('.$provenanceGroups->map(fn ($cnt, $type) => ucfirst((string) $type).": {$cnt}")->implode(', ').')',
        };

        // Latest transmission timestamp
        $latestReading = $readings->last();
        $latestTransmissionTime = $latestReading?->recorded_at
            ? $latestReading->recorded_at->timezone('Asia/Jakarta')->format('H.i').' WIB'
            : null;

        // School-scoped dynamic agronomic thresholds
        $deviceSchoolId = $selectedDevice->school_id ?? $targetSchoolId;
        $thresholds = $thresholdResolver->resolveForSchool($deviceSchoolId);
        $isCustomizedThresholds = $thresholdResolver->isCustomizedForSchool($deviceSchoolId);

        // Parameters metadata
        $parameters = [
            'ph' => [
                'name' => 'pH',
                'full_name' => 'Derajat Keasaman (pH)',
                'unit' => 'pH',
                'decimals' => 2,
                'default_min' => 5.0,
                'default_max' => 8.0,
            ],
            'moisture' => [
                'name' => 'Lengas tanah',
                'full_name' => 'Kadar Lengas Tanah',
                'unit' => '%',
                'decimals' => 1,
                'default_min' => 30.0,
                'default_max' => 80.0,
                'field' => 'humidity',
            ],
            'ec' => [
                'name' => 'EC (konduktivitas)',
                'full_name' => 'Konduktivitas Listrik (EC)',
                'unit' => 'µS/cm',
                'decimals' => 0,
                'default_min' => 1000.0,
                'default_max' => 3000.0,
            ],
            'temperature' => [
                'name' => 'Suhu tanah',
                'full_name' => 'Suhu Tanah',
                'unit' => '°C',
                'decimals' => 1,
                'default_min' => 18.0,
                'default_max' => 35.0,
            ],
            'nitrogen' => [
                'name' => 'Nitrogen (N)',
                'full_name' => 'Nitrogen Tersedia (N)',
                'unit' => 'mg/kg',
                'decimals' => 0,
                'default_min' => 20.0,
                'default_max' => 140.0,
            ],
            'phosphorus' => [
                'name' => 'Fosfor (P)',
                'full_name' => 'Fosfor Tersedia (P)',
                'unit' => 'mg/kg',
                'decimals' => 0,
                'default_min' => 10.0,
                'default_max' => 70.0,
            ],
            'potassium' => [
                'name' => 'Kalium (K)',
                'full_name' => 'Kalium Tersedia (K)',
                'unit' => 'mg/kg',
                'decimals' => 0,
                'default_min' => 60.0,
                'default_max' => 260.0,
            ],
        ];

        // Preserved chartData structure for single-parameter chart and test assertions
        $chartData = [];
        foreach ($parameters as $key => $meta) {
            $field = $meta['field'] ?? $key;
            $chartData[$key] = [
                'label' => $meta['full_name'],
                'unit' => $meta['unit'],
                'values' => $readings->map(fn ($reading) => [
                    'x' => $reading->recorded_at?->toIso8601String() ?? $reading->recorded_at?->format('Y-m-d H:i:s'),
                    'y' => $reading->{$field} === null ? null : (float) $reading->{$field},
                ])->values(),
            ];
        }

        // Build 7 mini-chart panels for the "Semua Parameter" 4-column overview grid
        $parameterPanels = [];
        foreach ($parameters as $key => $meta) {
            $field = $meta['field'] ?? $key;
            $th = $thresholds[$key] ?? $thresholdResolver::DEFAULTS[$key];
            $idealMin = (float) ($th['ideal_min'] ?? $meta['default_min']);
            $idealMax = (float) ($th['ideal_max'] ?? $meta['default_max']);

            $validPoints = $readings
                ->filter(fn ($r) => $r->{$field} !== null)
                ->map(fn ($r) => [
                    'x' => $r->recorded_at?->toIso8601String() ?? '',
                    'y' => (float) $r->{$field},
                    'is_optimal' => ((float) $r->{$field} >= $idealMin && (float) $r->{$field} <= $idealMax),
                    'provenance' => $r->provenance ?? 'unknown',
                ])
                ->values();

            $latestVal = $validPoints->isNotEmpty() ? $validPoints->last()['y'] : null;
            $evaluation = $thresholdResolver->evaluateValue($key, $latestVal, $th);

            // Establish responsive scale bounds that include default scale markers and actual values
            $rawValues = $validPoints->pluck('y')->all();
            $scaleMin = $meta['default_min'];
            $scaleMax = $meta['default_max'];
            if (! empty($rawValues)) {
                $scaleMin = min($scaleMin, min($rawValues));
                $scaleMax = max($scaleMax, max($rawValues));
            }
            if ($scaleMax <= $scaleMin) {
                $scaleMax = $scaleMin + 1.0;
            }

            // Build sparkline SVG coordinates (viewBox 0 0 200 60)
            $sparklinePoints = [];
            $count = $validPoints->count();
            if ($count > 0) {
                $rangeSpan = $scaleMax - $scaleMin;
                foreach ($validPoints as $idx => $pt) {
                    $normX = $count === 1 ? 100 : round(($idx / ($count - 1)) * 200, 1);
                    $normY = round(60 - ((($pt['y'] - $scaleMin) / $rangeSpan) * 52 + 4), 1);
                    $normY = max(2.0, min(58.0, $normY));
                    $sparklinePoints[] = [
                        'x' => $normX,
                        'y' => $normY,
                        'val' => $pt['y'],
                        'is_optimal' => $pt['is_optimal'],
                    ];
                }
            }

            // Reference band Y coordinates in SVG
            $bandYTop = round(60 - ((($idealMax - $scaleMin) / ($scaleMax - $scaleMin)) * 52 + 4), 1);
            $bandYBottom = round(60 - ((($idealMin - $scaleMin) / ($scaleMax - $scaleMin)) * 52 + 4), 1);
            $bandYTop = max(0.0, min(60.0, $bandYTop));
            $bandYBottom = max(0.0, min(60.0, $bandYBottom));
            $bandHeight = abs($bandYBottom - $bandYTop);

            $parameterPanels[$key] = [
                'key' => $key,
                'name' => $meta['name'],
                'full_name' => $meta['full_name'],
                'unit' => $meta['unit'],
                'decimals' => $meta['decimals'],
                'latest_val' => $latestVal,
                'status' => $evaluation,
                'ideal_min' => $idealMin,
                'ideal_max' => $idealMax,
                'scale_min' => $scaleMin,
                'scale_max' => $scaleMax,
                'points' => $sparklinePoints,
                'has_data' => $validPoints->isNotEmpty(),
                'band_y' => min($bandYTop, $bandYBottom),
                'band_height' => max(4.0, $bandHeight),
            ];
        }

        // Section 2: Deterministic bivariate correlation and regression builder
        $buildCorrelation = static function (
            string $label,
            string $xKey,
            string $yKey,
            string $xName,
            string $yName,
            string $xUnit,
            string $yUnit,
            string $neutralDesc,
            array $xThreshold,
            array $yThreshold,
            float $defaultXMin,
            float $defaultXMax,
            float $defaultYMin,
            float $defaultYMax
        ) use ($readings): array {
            $xField = $xKey === 'moisture' ? 'humidity' : $xKey;
            $yField = $yKey === 'moisture' ? 'humidity' : $yKey;

            // Strictly filter complete paired observations within physical limits
            $pairs = $readings->filter(fn ($r) => $r->{$xField} !== null && $r->{$yField} !== null);
            $count = $pairs->count();

            $xIdealMin = (float) ($xThreshold['ideal_min'] ?? $defaultXMin);
            $xIdealMax = (float) ($xThreshold['ideal_max'] ?? $defaultXMax);
            $yIdealMin = (float) ($yThreshold['ideal_min'] ?? $defaultYMin);
            $yIdealMax = (float) ($yThreshold['ideal_max'] ?? $defaultYMax);

            $points = $pairs->map(function ($r) use ($xField, $yField, $xIdealMin, $xIdealMax, $yIdealMin, $yIdealMax) {
                $xVal = round((float) $r->{$xField}, 2);
                $yVal = round((float) $r->{$yField}, 2);
                $isInZone = ($xVal >= $xIdealMin && $xVal <= $xIdealMax && $yVal >= $yIdealMin && $yVal <= $yIdealMax);

                return [
                    'x' => $xVal,
                    'y' => $yVal,
                    'is_in_zone' => $isInZone,
                    'provenance' => $r->provenance ?? 'unknown',
                ];
            })->values()->all();

            if ($count === 0) {
                return [
                    'label' => $label,
                    'value' => null,
                    'n' => 0,
                    'min_samples' => 10,
                    'is_sufficient' => false,
                    'status' => 'no_data',
                    'status_label' => 'Belum ada data berpasangan',
                    'strength_label' => null,
                    'interpretation' => $neutralDesc,
                    'x_name' => $xName,
                    'y_name' => $yName,
                    'x_unit' => $xUnit,
                    'y_unit' => $yUnit,
                    'points' => [],
                    'has_variance' => false,
                    'r_percent' => null,
                    'x_min' => $defaultXMin,
                    'x_max' => $defaultXMax,
                    'y_min' => $defaultYMin,
                    'y_max' => $defaultYMax,
                    'regression' => null,
                    'x_ideal_min' => $xIdealMin,
                    'x_ideal_max' => $xIdealMax,
                    'y_ideal_min' => $yIdealMin,
                    'y_ideal_max' => $yIdealMax,
                ];
            }

            if ($count < 2) {
                return [
                    'label' => $label,
                    'value' => null,
                    'n' => $count,
                    'min_samples' => 10,
                    'is_sufficient' => false,
                    'status' => 'insufficient_samples',
                    'status_label' => 'Data belum cukup',
                    'strength_label' => null,
                    'interpretation' => $neutralDesc,
                    'x_name' => $xName,
                    'y_name' => $yName,
                    'x_unit' => $xUnit,
                    'y_unit' => $yUnit,
                    'points' => $points,
                    'has_variance' => false,
                    'r_percent' => null,
                    'x_min' => $defaultXMin,
                    'x_max' => $defaultXMax,
                    'y_min' => $defaultYMin,
                    'y_max' => $defaultYMax,
                    'regression' => null,
                    'x_ideal_min' => $xIdealMin,
                    'x_ideal_max' => $xIdealMax,
                    'y_ideal_min' => $yIdealMin,
                    'y_ideal_max' => $yIdealMax,
                ];
            }

            $xMean = (float) $pairs->avg($xField);
            $yMean = (float) $pairs->avg($yField);
            $numerator = 0.0;
            $xVariance = 0.0;
            $yVariance = 0.0;

            foreach ($pairs as $row) {
                $xDiff = (float) $row->{$xField} - $xMean;
                $yDiff = (float) $row->{$yField} - $yMean;
                $numerator += $xDiff * $yDiff;
                $xVariance += $xDiff ** 2;
                $yVariance += $yDiff ** 2;
            }

            $hasVariance = ($xVariance > 0.000001 && $yVariance > 0.000001);
            $denominator = sqrt($xVariance * $yVariance);
            $rValue = $hasVariance && $denominator > 0 ? round($numerator / $denominator, 3) : null;
            $isSufficient = ($count >= 10 && $hasVariance && $rValue !== null);

            // Compute empirical bounds for scatter plot axes
            $xValues = array_column($points, 'x');
            $yValues = array_column($points, 'y');
            $plotXMin = min($defaultXMin, min($xValues));
            $plotXMax = max($defaultXMax, max($xValues));
            $plotYMin = min($defaultYMin, min($yValues));
            $plotYMax = max($defaultYMax, max($yValues));

            // Compute linear regression endpoints only when sample is sufficient and variance exists
            $regression = null;
            if ($isSufficient && $xVariance > 0.000001 && $rValue !== null) {
                $slope = $numerator / $xVariance;
                $intercept = $yMean - ($slope * $xMean);
                $regY1 = ($slope * $plotXMin) + $intercept;
                $regY2 = ($slope * $plotXMax) + $intercept;
                $regression = [
                    'slope' => round($slope, 4),
                    'intercept' => round($intercept, 4),
                    'x1' => $plotXMin,
                    'y1' => round($regY1, 2),
                    'x2' => $plotXMax,
                    'y2' => round($regY2, 2),
                ];
            }

            if (! $hasVariance) {
                return [
                    'label' => $label,
                    'value' => null,
                    'n' => $count,
                    'min_samples' => 10,
                    'is_sufficient' => false,
                    'status' => 'zero_variance',
                    'status_label' => 'Korelasi tidak dapat dihitung — nilai parameter konstan',
                    'strength_label' => 'Nilai parameter konstan',
                    'interpretation' => $neutralDesc,
                    'x_name' => $xName,
                    'y_name' => $yName,
                    'x_unit' => $xUnit,
                    'y_unit' => $yUnit,
                    'points' => $points,
                    'has_variance' => false,
                    'r_percent' => null,
                    'x_min' => $plotXMin,
                    'x_max' => $plotXMax,
                    'y_min' => $plotYMin,
                    'y_max' => $plotYMax,
                    'regression' => null,
                    'x_ideal_min' => $xIdealMin,
                    'x_ideal_max' => $xIdealMax,
                    'y_ideal_min' => $yIdealMin,
                    'y_ideal_max' => $yIdealMax,
                ];
            }

            $strengthLabel = match (true) {
                ! $isSufficient => null,
                $rValue === null => null,
                abs($rValue) >= 0.7 => ($rValue > 0 ? 'Korelasi positif kuat' : 'Korelasi negatif kuat'),
                abs($rValue) >= 0.4 => ($rValue > 0 ? 'Korelasi positif sedang' : 'Korelasi negatif sedang'),
                abs($rValue) >= 0.2 => ($rValue > 0 ? 'Korelasi positif lemah' : 'Korelasi negatif lemah'),
                default => 'Korelasi sangat lemah / mendekati nol',
            };

            // Calculate r slider position (0% at -1, 50% at 0, 100% at +1) only when sufficient
            $rPercent = ($isSufficient && $rValue !== null)
                ? round(max(0.0, min(100.0, (($rValue + 1.0) / 2.0) * 100.0)), 1)
                : null;

            return [
                'label' => $label,
                'value' => $rValue, // Retained float when count >= 2 for backwards test compatibility
                'n' => $count,
                'min_samples' => 10,
                'is_sufficient' => $isSufficient,
                'status' => $count < 10 ? 'insufficient_samples' : 'sufficient',
                'status_label' => $count < 10 ? 'Data belum cukup' : 'Data cukup',
                'strength_label' => $strengthLabel,
                'interpretation' => $neutralDesc,
                'x_name' => $xName,
                'y_name' => $yName,
                'x_unit' => $xUnit,
                'y_unit' => $yUnit,
                'points' => $points,
                'has_variance' => $hasVariance,
                'r_percent' => $rPercent,
                'x_min' => $plotXMin,
                'x_max' => $plotXMax,
                'y_min' => $plotYMin,
                'y_max' => $plotYMax,
                'regression' => $regression,
                'x_ideal_min' => $xIdealMin,
                'x_ideal_max' => $xIdealMax,
                'y_ideal_min' => $yIdealMin,
                'y_ideal_max' => $yIdealMax,
            ];
        };

        $correlations = [
            $buildCorrelation(
                'pH tanah ↔ EC nutrisi',
                'ph',
                'ec',
                'pH',
                'EC',
                'pH',
                'µS/cm',
                'Keterikatan linear antara derajat kemasaman tanah dan konsentrasi ion garam pupuk terlarut (EC).',
                $thresholds['ph'] ?? $thresholdResolver::DEFAULTS['ph'],
                $thresholds['ec'] ?? $thresholdResolver::DEFAULTS['ec'],
                5.5,
                8.0,
                1000.0,
                3000.0
            ),
            $buildCorrelation(
                'Suhu tanah ↔ Kadar lengas',
                'temperature',
                'humidity',
                'Suhu',
                'Lengas',
                '°C',
                '%',
                'Hubungan suhu perakaran dengan retensi air pori tanah (VWC), tanpa menghapus pengaruh irigasi.',
                $thresholds['temperature'] ?? $thresholdResolver::DEFAULTS['temperature'],
                $thresholds['moisture'] ?? $thresholdResolver::DEFAULTS['moisture'],
                20.0,
                34.0,
                30.0,
                80.0
            ),
        ];

        return view('pages.analytics.agronomy-analytics', [
            'devices' => $devices,
            'schools' => $schools,
            'selectedSchoolId' => $targetSchoolId,
            'isSuperuser' => $isSuperuser,
            'selectedDevice' => $selectedDevice,
            'chartData' => $chartData,
            'readingCount' => $readings->count(),
            'readings' => $readings,
            'parameterPanels' => $parameterPanels,
            'correlations' => $correlations,
            'thresholds' => $thresholds,
            'isCustomizedThresholds' => $isCustomizedThresholds,
            'selectedRange' => $range,
            'periodLabel' => $rangeLabels[$range],
            'periodAxisLabel' => $rangeAxisLabels[$range],
            'provenanceSummary' => $provenanceSummary,
            'latestTransmissionTime' => $latestTransmissionTime,
        ]);
    }
}
