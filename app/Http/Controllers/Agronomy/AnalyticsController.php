<?php

namespace App\Http\Controllers\Agronomy;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Display the agronomic analytics page with data-backed correlation summaries.
     */
    public function index(Request $request): View
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

        $readings = $selectedDevice
            ? TelemetryReading::where('device_id', $selectedDevice->id)
                ->when($targetSchoolId, fn ($q) => $q->whereHas('device', fn ($dq) => $dq->where('school_id', $targetSchoolId)))
                ->latest('recorded_at')
                ->take(100)
                ->get()
                ->sortBy('recorded_at')
                ->values()
            : collect();

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

        $correlation = static function (string $xKey, string $yKey) use ($readings): ?float {
            $pairs = $readings->filter(fn ($r) => $r->{$xKey} !== null && $r->{$yKey} !== null);
            $count = $pairs->count();
            if ($count < 2) {
                return null;
            }

            $xMean = $pairs->avg($xKey);
            $yMean = $pairs->avg($yKey);
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

            $denominator = sqrt($xVariance * $yVariance);

            return $denominator > 0 ? round($numerator / $denominator, 3) : null;
        };

        return view('pages.analytics.index', [
            'devices' => $devices,
            'schools' => $schools,
            'selectedSchoolId' => $targetSchoolId,
            'isSuperuser' => $isSuperuser,
            'selectedDevice' => $selectedDevice,
            'chartData' => $chartData,
            'readingCount' => $readings->count(),
            'readings' => $readings,
            'correlations' => [
                ['label' => 'pH Tanah ↔ EC Nutrisi', 'value' => $correlation('ph', 'ec')],
                ['label' => 'Suhu Tanah ↔ Kadar Lengas', 'value' => $correlation('temperature', 'humidity')],
            ],
        ]);
    }
}
