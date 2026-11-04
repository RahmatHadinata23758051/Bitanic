<?php

namespace App\Http\Controllers;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the vocational LMS lab overview dashboard.
     */
    public function index(): View
    {
        // 1. Hardware fleet overview
        $devices = Device::with('latestReading')->orderBy('code')->get();
        $onlineKitsCount = $devices->where('status', 'online')->count();
        $totalKitsCount = $devices->count();

        // 2. Practicum schedule for today & upcoming
        $upcomingSchedules = ActivitySchedule::with('device')
            ->where('activity_date', '>=', now()->toDateString())
            ->orderBy('activity_date')
            ->orderBy('start_time')
            ->take(4)
            ->get();

        $todaySchedule = $upcomingSchedules->firstWhere('activity_date', now()->toDateString())
            ?? $upcomingSchedules->first();

        // 3. Pending student LKS reports count
        $pendingReportsCount = StudentReport::where('status', 'submitted')->count();

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
