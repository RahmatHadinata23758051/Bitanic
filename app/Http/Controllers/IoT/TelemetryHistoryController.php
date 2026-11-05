<?php

namespace App\Http\Controllers\IoT;

use App\Actions\Telemetry\ExportTelemetryCsvAction;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Services\Agronomy\AnomalyAlertEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TelemetryHistoryController extends Controller
{
    /**
     * Display paginated telemetry logs with filters and real-time agronomic alerts.
     */
    public function index(Request $request, AnomalyAlertEvaluator $evaluator): View
    {
        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Akses riwayat telemetri memerlukan asosiasi sekolah yang valid.');
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

        $devices = Device::query()
            ->when($targetSchoolId, fn ($query) => $query->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->get();

        $query = $this->buildFilteredQuery($request, $devices)
            ->when($targetSchoolId, fn ($query) => $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('school_id', $targetSchoolId)));

        // Anomaly alert uses only the caller's visible school telemetry.
        $latestReading = (clone $query)->with('device')->latest('recorded_at')->first();
        $activeAlerts = $evaluator->evaluate($latestReading);

        // Metrik ringkasan
        $totalLogsCount = (clone $query)->count();
        $activeDevicesCount = $devices->where('is_active', true)->count();

        $readings = $query->paginate(25)->withQueryString();

        return view('pages.telemetry.history', [
            'readings' => $readings,
            'devices' => $devices,
            'schools' => $schools,
            'selectedSchoolId' => $targetSchoolId,
            'isSuperuser' => $isSuperuser,
            'alertEvaluator' => $evaluator,
            'activeAlerts' => $activeAlerts,
            'latestReading' => $latestReading,
            'totalLogsCount' => $totalLogsCount,
            'activeDevicesCount' => $activeDevicesCount,
            'selectedDevice' => $request->query('device_id'),
            'startDate' => $request->query('start_date'),
            'endDate' => $request->query('end_date'),
            'selectedStatus' => $request->query('status', 'all'),
        ]);
    }

    /**
     * Export filtered telemetry history into clean CSV file.
     */
    public function exportCsv(Request $request, ExportTelemetryCsvAction $action): StreamedResponse
    {
        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Ekspor telemetri memerlukan asosiasi sekolah yang valid.');
            }
            $school = $user->school;
            if (! $school || $school->status !== 'active') {
                abort(403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');
            }
        }

        $selectedSchoolId = $request->query('school_id');

        if ($isSuperuser && $selectedSchoolId) {
            $targetSchoolId = (int) $selectedSchoolId;
        } else {
            $targetSchoolId = $isSuperuser
                ? null
                : ($schoolId ?? School::query()->where('status', 'active')->orderBy('id')->value('id') ?? -1);
        }

        $devices = Device::query()
            ->when($targetSchoolId, fn ($query) => $query->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->get();

        $query = $this->buildFilteredQuery($request, $devices)
            ->when($targetSchoolId, fn ($query) => $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('school_id', $targetSchoolId)));
        $filename = 'bitanic-telemetry-'.now()->format('Ymd_His').'.csv';

        return $action->execute($query, $filename);
    }

    /**
     * Build base query with incoming HTTP filters and cross-school IDOR protection.
     *
     * @param  Collection<int, Device>  $devices
     * @return Builder<TelemetryReading>
     */
    protected function buildFilteredQuery(Request $request, Collection $devices): Builder
    {
        $query = TelemetryReading::with('device')->orderByDesc('recorded_at');

        if ($request->filled('device_id')) {
            $deviceId = $request->query('device_id');
            $validDevice = $devices->first(fn ($d) => (string) $d->id === (string) $deviceId || $d->code === (string) $deviceId);
            if ($validDevice) {
                $query->where('device_id', $validDevice->id);
            } else {
                // Cross-school IDOR attempt: requested device does not belong to authorized school
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('start_date')) {
            $query->where('recorded_at', '>=', $request->query('start_date').' 00:00:00');
        }

        if ($request->filled('end_date')) {
            $query->where('recorded_at', '<=', $request->query('end_date').' 23:59:59');
        }

        $status = $request->query('status', 'all');
        if ($status === 'anomaly') {
            $query->where(function ($q) {
                $q->whereNotNull('threshold_flags')
                    ->where('threshold_flags', '!=', '[]')
                    ->orWhere('ph', '<', 5.5)
                    ->orWhere('ph', '>', 7.5)
                    ->orWhere('ec', '<', 800)
                    ->orWhere('ec', '>', 2400)
                    ->orWhere('humidity', '<', 40.0)
                    ->orWhere('humidity', '>', 85.0);
            });
        } elseif ($status === 'normal') {
            $query->where(function ($q) {
                $q->whereNull('threshold_flags')
                    ->orWhere('threshold_flags', '[]')
                    ->whereBetween('ph', [5.5, 7.5])
                    ->whereBetween('ec', [800, 2400])
                    ->whereBetween('humidity', [40.0, 85.0]);
            });
        }

        return $query;
    }
}
