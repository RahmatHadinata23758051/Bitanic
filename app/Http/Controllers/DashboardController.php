<?php

namespace App\Http\Controllers;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Services\Hardware\DeviceAvailabilityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the vocational LMS lab overview dashboard.
     */
    public function index(Request $request, DeviceAvailabilityResolver $availability): View
    {
        $user = $request->user();
        $isSuperuser = $user?->isSuperuser();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser && ! $schoolId) {
            abort(403, 'Akses Dasbor memerlukan asosiasi sekolah yang valid.');
        }

        $targetSchoolId = $isSuperuser
            ? null
            : ($schoolId ?? School::query()->where('status', 'active')->orderBy('id')->value('id') ?? -1);

        // 1. Hardware fleet overview scoped to school
        $deviceQuery = Device::with('latestReading')
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('code');

        $devices = $availability->applyToCollection($deviceQuery->get());
        $onlineKitsCount = $devices->filter(fn (Device $device) => $device->is_online)->count();
        $totalKitsCount = $devices->count();

        foreach ($devices as $device) {
            $device->telemetry_empty = ! $device->latestReading;
            $device->telemetry_provenance_label = $device->latestReading?->provenance
                ? match ($device->latestReading->provenance) {
                    'hardware' => 'Hardware',
                    'simulation' => 'Simulasi',
                    'fixture' => 'Fixture / Data Uji',
                    default => 'Sumber Tidak Diketahui',
                }
                : null;
        }

        // 2. Practicum schedule for today & upcoming
        $upcomingSchedules = ActivitySchedule::with('device')
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->where('activity_date', '>=', now()->toDateString())
            ->orderBy('activity_date')
            ->orderBy('start_time')
            ->take(4)
            ->get();

        $todaySchedule = $upcomingSchedules->firstWhere('activity_date', now()->toDateString())
            ?? $upcomingSchedules->first();

        // 3. Pending student LKS reports count
        $pendingReportsCount = StudentReport::where('status', 'submitted')
            ->when($targetSchoolId, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('school_id', $targetSchoolId)))
            ->count();

        // 4. Evaluate system-wide anomalies
        $anomalies = [];
        foreach ($devices as $dev) {
            $latest = $dev->latestReading;
            if (!$latest) continue;

            if ($latest->ph < 5.5) {
                $anomalies[] = [
                    'device' => $dev->code,
                    'bench' => $dev->bench_location ?? $dev->name,
                    'parameter' => 'pH Tanah',
                    'value' => number_format((float)$latest->ph, 2),
                    'severity' => 'critical',
                    'message' => 'Kemasaman masam ekstrem (< 5.5). Risiko keracunan Al/Fe.',
                    'action' => 'Aplikasi dolomit/kapur pertanian 50g/m².',
                ];
            }
            if ($latest->humidity < 25.0) {
                $anomalies[] = [
                    'device' => $dev->code,
                    'bench' => $dev->bench_location ?? $dev->name,
                    'parameter' => 'Kadar Lengas',
                    'value' => number_format((float)$latest->humidity, 1) . '%',
                    'severity' => 'critical',
                    'message' => 'Tanah mendekati titik layu permanen (< 25%).',
                    'action' => 'Aktifkan pompa irigasi tetes.',
                ];
            }
            if ($latest->ec > 2200) {
                $anomalies[] = [
                    'device' => $dev->code,
                    'bench' => $dev->bench_location ?? $dev->name,
                    'parameter' => 'EC Salinitas',
                    'value' => number_format((float)$latest->ec, 0) . ' µS/cm',
                    'severity' => 'warning',
                    'message' => 'Salinitas garam pupuk tinggi (> 2200 µS/cm).',
                    'action' => 'Lakukan pembilasan air murni (leaching).',
                ];
            }
        }

        $activeDevice = $devices->first();

        return view('pages.dashboard.index', [
            'devices' => $devices,
            'activeDevice' => $activeDevice,
            'onlineKitsCount' => $onlineKitsCount,
            'totalKitsCount' => $totalKitsCount,
            'todaySchedule' => $todaySchedule,
            'upcomingSchedules' => $upcomingSchedules,
            'pendingReportsCount' => $pendingReportsCount,
            'anomalies' => $anomalies,
            'criticalAlertsCount' => count($anomalies),
        ]);
    }
}
