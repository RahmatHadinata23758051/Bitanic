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

        // Time range filtering
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

        $readings = $selectedDevice
            ? TelemetryReading::where('device_id', $selectedDevice->id)
                ->when($targetSchoolId, fn ($q) => $q->whereHas('device', fn ($dq) => $dq->where('school_id', $targetSchoolId)))
                ->where('recorded_at', '>=', $cutoff)
                ->latest('recorded_at')
                ->take(300)
                ->get()
                ->sortBy('recorded_at')
                ->values()
            : collect();

        // Provenance composition
        $provenanceGroups = $readings->pluck('provenance')->filter()->countBy();
        $provenanceSummary = match (true) {
            $readings->isEmpty() => 'Belum ada transmisi data',
            $provenanceGroups->has('hardware') && $provenanceGroups->count() === 1 => 'Hardware (Modbus RS485)',
            $provenanceGroups->has('simulation') && $provenanceGroups->count() === 1 => 'Data Simulasi',
            $provenanceGroups->has('fixture') && $provenanceGroups->count() === 1 => 'Data Uji / Fixture',
            default => 'Campuran ('.$provenanceGroups->map(fn ($cnt, $type) => ucfirst((string) $type).": {$cnt}")->implode(', ').')',
        };

        // School-scoped dynamic agronomic thresholds
        $deviceSchoolId = $selectedDevice->school_id ?? $targetSchoolId;
        $thresholds = $thresholdResolver->resolveForSchool($deviceSchoolId);
        $isCustomizedThresholds = $thresholdResolver->isCustomizedForSchool($deviceSchoolId);

        $parameters = [
            'ph' => ['label' => 'pH Tanah', 'unit' => 'pH'],
            'ec' => ['label' => 'Konduktivitas Listrik (EC)', 'unit' => 'µS/cm'],
            'humidity' => ['label' => 'Kadar Lengas Tanah', 'unit' => '%'],
            'temperature' => ['label' => 'Suhu Tanah', 'unit' => '°C'],
            'nitrogen' => ['label' => 'Nitrogen (N)', 'unit' => 'mg/kg'],
            'phosphorus' => ['label' => 'Fosfor (P)', 'unit' => 'mg/kg'],
            'potassium' => ['label' => 'Kalium (K)', 'unit' => 'mg/kg'],
        ];

        $chartData = [];
        foreach ($parameters as $key => $meta) {
            $chartData[$key] = [
                'label' => $meta['label'],
                'unit' => $meta['unit'],
                'values' => $readings->map(fn ($reading) => [
                    'x' => $reading->recorded_at?->toIso8601String() ?? $reading->recorded_at?->format('Y-m-d H:i:s'),
                    'y' => $reading->{$key} === null ? null : (float) $reading->{$key},
                ])->values(),
            ];
        }

        $buildCorrelation = static function (
            string $label,
            string $xKey,
            string $yKey,
            string $xName,
            string $yName,
            string $xUnit,
            string $yUnit,
            string $neutralDesc
        ) use ($readings): array {
            $pairs = $readings->filter(fn ($r) => $r->{$xKey} !== null && $r->{$yKey} !== null);
            $count = $pairs->count();

            $points = $pairs->map(fn ($r) => [
                'x' => round((float) $r->{$xKey}, 2),
                'y' => round((float) $r->{$yKey}, 2),
                'provenance' => $r->provenance ?? 'unknown',
            ])->values()->all();

            if ($count < 2) {
                return [
                    'label' => $label,
                    'value' => null,
                    'n' => $count,
                    'min_samples' => 10,
                    'is_sufficient' => false,
                    'status' => 'insufficient_samples',
                    'status_label' => 'Data Belum Cukup',
                    'strength_label' => 'Belum ada observasi yang cukup',
                    'interpretation' => $neutralDesc,
                    'x_name' => $xName,
                    'y_name' => $yName,
                    'x_unit' => $xUnit,
                    'y_unit' => $yUnit,
                    'points' => $points,
                    'has_variance' => false,
                ];
            }

            $xMean = (float) $pairs->avg($xKey);
            $yMean = (float) $pairs->avg($yKey);
            $numerator = 0.0;
            $xVariance = 0.0;
            $yVariance = 0.0;

            foreach ($pairs as $row) {
                $xDiff = (float) $row->{$xKey} - $xMean;
                $yDiff = (float) $row->{$yKey} - $yMean;
                $numerator += $xDiff * $yDiff;
                $xVariance += $xDiff ** 2;
                $yVariance += $yDiff ** 2;
            }

            $hasVariance = ($xVariance > 0.000001 && $yVariance > 0.000001);
            $denominator = sqrt($xVariance * $yVariance);
            $rValue = $hasVariance && $denominator > 0 ? round($numerator / $denominator, 3) : null;
            $isSufficient = ($count >= 10 && $hasVariance && $rValue !== null);

            $strengthLabel = match (true) {
                ! $hasVariance => 'Variansi Nol (Nilai Konstan)',
                $rValue === null => 'Tidak Terdefinisi',
                abs($rValue) >= 0.7 => ($rValue > 0 ? 'Korelasi Linear Positif Kuat' : 'Korelasi Linear Negatif Kuat'),
                abs($rValue) >= 0.4 => ($rValue > 0 ? 'Korelasi Linear Positif Sedang' : 'Korelasi Linear Negatif Sedang'),
                abs($rValue) >= 0.2 => ($rValue > 0 ? 'Korelasi Linear Positif Lemah' : 'Korelasi Linear Negatif Lemah'),
                default => 'Korelasi Linear Sangat Lemah / Mendekati Nol',
            };

            return [
                'label' => $label,
                'value' => $rValue, // Preserved float when n>=2 for backward test compatibility
                'n' => $count,
                'min_samples' => 10,
                'is_sufficient' => $isSufficient,
                'status' => ! $hasVariance ? 'zero_variance' : ($count < 10 ? 'insufficient_samples' : 'sufficient'),
                'status_label' => ! $hasVariance ? 'Variansi Nol' : ($count < 10 ? 'Data Belum Cukup' : 'Observasi Memadai'),
                'strength_label' => $strengthLabel,
                'interpretation' => $neutralDesc,
                'x_name' => $xName,
                'y_name' => $yName,
                'x_unit' => $xUnit,
                'y_unit' => $yUnit,
                'points' => $points,
                'has_variance' => $hasVariance,
            ];
        };

        return view('pages.analytics.agronomy-analytics', [
            'devices' => $devices,
            'schools' => $schools,
            'selectedSchoolId' => $targetSchoolId,
            'isSuperuser' => $isSuperuser,
            'selectedDevice' => $selectedDevice,
            'chartData' => $chartData,
            'readingCount' => $readings->count(),
            'readings' => $readings,
            'correlations' => [
                $buildCorrelation(
                    'pH Tanah ↔ EC Nutrisi',
                    'ph',
                    'ec',
                    'pH Tanah',
                    'Konduktivitas Listrik',
                    'pH',
                    'µS/cm',
                    'Keterikatan kovarians linear antara derajat kemasaman tanah dengan konsentrasi ion garam pupuk terlarut (EC). Nilai koefisien mencerminkan hubungan linear teramati pada dataset, bukan keterikatan kausal langsung.'
                ),
                $buildCorrelation(
                    'Suhu Tanah ↔ Kadar Lengas',
                    'temperature',
                    'humidity',
                    'Suhu Tanah',
                    'Kadar Lengas',
                    '°C',
                    '%',
                    'Kovariansi antara temperatur perakaran dengan retensi air pori tanah (VWC). Analisis mencerminkan dinamika kovarian data lapangan tanpa mengabaikan intervensi mikroklimat atau siklus irigasi.'
                ),
            ],
            'thresholds' => $thresholds,
            'isCustomizedThresholds' => $isCustomizedThresholds,
            'selectedRange' => $range,
            'periodLabel' => $rangeLabels[$range],
            'provenanceSummary' => $provenanceSummary,
        ]);
    }
}
