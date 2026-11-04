<?php

namespace App\Http\Controllers;

use App\Actions\Telemetry\ExportTelemetryCsvAction;
use App\Models\Device;
use App\Models\TelemetryReading;
use App\Services\Agronomy\AnomalyAlertEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TelemetryHistoryController extends Controller
{
    /**
     * Display paginated telemetry logs with filters and real-time agronomic alerts.
     */
    public function index(Request $request, AnomalyAlertEvaluator $evaluator): View
    {
        $devices = Device::orderBy('name')->get();
        $query = $this->buildFilteredQuery($request);

        // Ambil pembacaan terbaru untuk Banner Anomaly Alert Hub
        $latestReading = TelemetryReading::with('device')->latest('recorded_at')->first();
        $activeAlerts = $evaluator->evaluate($latestReading);

        // Metrik ringkasan
        $totalLogsCount = TelemetryReading::count();
        $activeDevicesCount = $devices->where('is_active', true)->count();

        $readings = $query->paginate(25)->withQueryString();

        return view('pages.telemetry.history', [
            'readings' => $readings,
            'devices' => $devices,
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
        $query = $this->buildFilteredQuery($request);
        $filename = 'bitanic-telemetry-' . now()->format('Ymd_His') . '.csv';

        return $action->execute($query, $filename);
    }

    /**
     * Build base query with incoming HTTP filters.
     *
     * @param Request $request
     * @return Builder<TelemetryReading>
     */
    protected function buildFilteredQuery(Request $request): Builder
    {
        $query = TelemetryReading::with('device')->orderByDesc('recorded_at');

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->query('device_id'));
        }

        if ($request->filled('start_date')) {
            $query->where('recorded_at', '>=', $request->query('start_date') . ' 00:00:00');
        }

        if ($request->filled('end_date')) {
            $query->where('recorded_at', '<=', $request->query('end_date') . ' 23:59:59');
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
