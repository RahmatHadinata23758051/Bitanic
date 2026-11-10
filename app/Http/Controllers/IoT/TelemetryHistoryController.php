<?php

namespace App\Http\Controllers\IoT;

use App\Actions\Telemetry\ExportTelemetryCsvAction;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Services\Agronomy\AgronomicThresholdResolver;
use App\Services\Agronomy\AnomalyAlertEvaluator;
use App\Services\Hardware\DeviceAvailabilityResolver;
use App\Services\Tenancy\SchoolContextResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TelemetryHistoryController extends Controller
{
    /**
     * Display paginated telemetry logs with filters, verified alerts, and honest summaries.
     */
    public function index(
        Request $request,
        AnomalyAlertEvaluator $evaluator,
        AgronomicThresholdResolver $thresholdResolver,
        DeviceAvailabilityResolver $availabilityResolver,
        SchoolContextResolver $contextResolver
    ): View {
        $user = $request->user();
        $context = $contextResolver->resolve($request, $user);

        $targetSchoolId = $context->targetSchoolId;
        $selectedSchoolId = $context->selectedSchoolId;
        $schools = $context->schools;
        $isSuperuser = $context->isSuperuser;

        $devices = Device::query()
            ->when($targetSchoolId, fn ($query) => $query->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->get();

        $validationError = $this->validateFilterInputs($request);

        $validSelectedDevice = null;
        if ($request->filled('device_id')) {
            $deviceId = $request->query('device_id');
            $validSelectedDevice = $devices->first(fn ($d) => (string) $d->id === (string) $deviceId || $d->code === (string) $deviceId);
        }

        $query = $this->buildFilteredQuery($request, $devices, $thresholdResolver, $validationError)
            ->when($targetSchoolId, fn ($query) => $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('school_id', $targetSchoolId)));

        // Active Actionable Alert Semantics:
        // Distinguish active emergencies on fresh hardware readings from historical deviations.
        $activeAlerts = collect();
        $alertDevice = null;
        $isFreshAlert = false;

        $hasDateFilter = $request->filled('start_date') || $request->filled('end_date');

        if (! $hasDateFilter && $validationError === null) {
            $candidateReading = null;

            if ($validSelectedDevice) {
                // Selected station: strictly hardware provenance, latest recorded reading
                $candidateReading = TelemetryReading::where('device_id', $validSelectedDevice->id)
                    ->where('provenance', 'hardware')
                    ->whereNotNull('recorded_at')
                    ->latest('recorded_at')
                    ->first();
            } else {
                // All stations: strictly hardware provenance among authorized devices
                $deviceIds = $devices->pluck('id')->all();
                if (! empty($deviceIds)) {
                    $candidateReading = TelemetryReading::whereIn('device_id', $deviceIds)
                        ->where('provenance', 'hardware')
                        ->whereNotNull('recorded_at')
                        ->latest('recorded_at')
                        ->first();
                }
            }

            if ($candidateReading && $candidateReading->recorded_at && $candidateReading->device) {
                $targetDevice = $candidateReading->device;
                $timeout = $availabilityResolver->getTimeoutSeconds($targetDevice);

                // Freshness check: must be within device availability heartbeat window
                if ($candidateReading->recorded_at->greaterThanOrEqualTo(now()->subSeconds($timeout))) {
                    $deviceSchoolId = $targetDevice->school_id;
                    $alerts = $evaluator->evaluate($candidateReading, $deviceSchoolId);

                    if ($alerts->isNotEmpty()) {
                        $isFreshAlert = true;
                        $activeAlerts = $alerts;
                        $alertDevice = $targetDevice;
                    }
                }
            }
        }

        // Contextual metrics
        $totalLogsCount = (clone $query)->count();
        $totalDevicesCount = $devices->count();
        $onlineDevicesCount = $devices->filter(fn ($d) => $availabilityResolver->isOnline($d))->count();
        $schoolTotalReadings = TelemetryReading::whereIn('device_id', $devices->pluck('id'))->count();

        // Selected period label with safe parsing guard
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $periodLabel = 'Semua Riwayat Waktu';

        if ($validationError === null) {
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $periodLabel = Carbon::createFromFormat('Y-m-d', $startDate, 'Asia/Jakarta')->isoFormat('D MMM Y')
                    .' – '
                    .Carbon::createFromFormat('Y-m-d', $endDate, 'Asia/Jakarta')->isoFormat('D MMM Y').' (WIB)';
            } elseif ($request->filled('start_date')) {
                $periodLabel = 'Sejak '.Carbon::createFromFormat('Y-m-d', $startDate, 'Asia/Jakarta')->isoFormat('D MMM Y').' (WIB)';
            } elseif ($request->filled('end_date')) {
                $periodLabel = 'Sampai '.Carbon::createFromFormat('Y-m-d', $endDate, 'Asia/Jakarta')->isoFormat('D MMM Y').' (WIB)';
            }
        } else {
            $periodLabel = 'Filter Tanggal Tidak Valid';
        }

        $readings = $query->paginate(25)->withQueryString();

        return view('pages.telemetry.history', [
            'readings' => $readings,
            'devices' => $devices,
            'schools' => $schools,
            'selectedSchoolId' => $targetSchoolId,
            'isSuperuser' => $isSuperuser,
            'alertEvaluator' => $evaluator,
            'activeAlerts' => $activeAlerts,
            'alertDevice' => $alertDevice,
            'isFreshAlert' => $isFreshAlert,
            'totalLogsCount' => $totalLogsCount,
            'totalDevicesCount' => $totalDevicesCount,
            'onlineDevicesCount' => $onlineDevicesCount,
            'schoolTotalReadings' => $schoolTotalReadings,
            'periodLabel' => $periodLabel,
            'selectedDevice' => $request->query('device_id'),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedStatus' => $request->query('status', 'all'),
            'selectedProvenance' => $request->query('provenance', 'all'),
            'validationError' => $validationError,
        ]);
    }

    /**
     * Export filtered telemetry history into clean CSV file.
     */
    public function exportCsv(
        Request $request,
        ExportTelemetryCsvAction $action,
        AgronomicThresholdResolver $thresholdResolver,
        SchoolContextResolver $contextResolver
    ): StreamedResponse {
        $user = $request->user();
        $context = $contextResolver->resolve($request, $user);

        $targetSchoolId = $context->targetSchoolId;
        $selectedSchoolId = $context->selectedSchoolId;
        $isSuperuser = $context->isSuperuser;

        $validationError = $this->validateFilterInputs($request);
        if ($validationError) {
            abort(422, $validationError);
        }

        $devices = Device::query()
            ->when($targetSchoolId, fn ($query) => $query->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->get();

        $query = $this->buildFilteredQuery($request, $devices, $thresholdResolver, $validationError)
            ->when($targetSchoolId, fn ($query) => $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('school_id', $targetSchoolId)));

        $filename = 'bitanic-telemetry-'.now()->setTimezone('Asia/Jakarta')->format('Ymd_His').'.csv';

        return $action->execute($query, $filename, $targetSchoolId);
    }

    /**
     * Validate incoming HTTP filter inputs strictly before any parsing.
     */
    protected function validateFilterInputs(Request $request): ?string
    {
        $validator = Validator::make($request->query(), [
            'device_id' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'string', 'max:15'],
            'end_date' => ['nullable', 'string', 'max:15'],
            'status' => ['nullable', 'in:all,normal,anomaly'],
            'provenance' => ['nullable', 'in:all,hardware,simulation,fixture'],
            'school_id' => ['nullable', 'integer'],
        ], [
            'status.in' => 'Pilihan status kondisi tidak valid.',
            'provenance.in' => 'Pilihan asal data tidak valid.',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if ($startDate !== null && $startDate !== '') {
            if (! $this->isValidCalendarDate($startDate)) {
                return 'Format tanggal mulai tidak valid (harus YYYY-MM-DD dengan tanggal kalender yang benar).';
            }
        }

        if ($endDate !== null && $endDate !== '') {
            if (! $this->isValidCalendarDate($endDate)) {
                return 'Format tanggal akhir tidak valid (harus YYYY-MM-DD dengan tanggal kalender yang benar).';
            }
        }

        if ($startDate !== null && $startDate !== '' && $endDate !== null && $endDate !== '') {
            if ($startDate > $endDate) {
                return 'Tanggal mulai tidak boleh melebihi tanggal akhir.';
            }
        }

        return null;
    }

    /**
     * Validate that a string matches YYYY-MM-DD and represents a real calendar date.
     */
    protected function isValidCalendarDate(mixed $date): bool
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)) {
            return false;
        }

        [$year, $month, $day] = explode('-', $date);

        return checkdate((int) $month, (int) $day, (int) $year);
    }

    /**
     * Build base query with incoming HTTP filters, multi-school canonical thresholds, and fail-closed security.
     *
     * @param  Collection<int, Device>  $devices
     * @return Builder<TelemetryReading>
     */
    protected function buildFilteredQuery(
        Request $request,
        Collection $devices,
        AgronomicThresholdResolver $thresholdResolver,
        ?string $validationError = null
    ): Builder {
        $query = TelemetryReading::with('device')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');

        if ($validationError !== null) {
            // Fail closed on invalid inputs (do not return unexpected or broadened data)
            return $query->whereRaw('1 = 0');
        }

        // 1. Device filter with fail-closed IDOR check
        if ($request->filled('device_id')) {
            $deviceId = $request->query('device_id');
            $validDevice = $devices->first(fn ($d) => (string) $d->id === (string) $deviceId || $d->code === (string) $deviceId);
            if ($validDevice) {
                $query->where('device_id', $validDevice->id);
            } else {
                // Cross-school IDOR attempt: requested device does not belong to authorized school
                return $query->whereRaw('1 = 0');
            }
        }

        // 2. Date filtering with Asia/Jakarta (WIB) conversion to UTC
        if ($request->filled('start_date')) {
            try {
                $startUtc = Carbon::createFromFormat('Y-m-d H:i:s', $request->query('start_date').' 00:00:00', 'Asia/Jakarta')->setTimezone('UTC');
                $query->where('recorded_at', '>=', $startUtc);
            } catch (\Throwable) {
                return $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('end_date')) {
            try {
                $endUtc = Carbon::createFromFormat('Y-m-d H:i:s', $request->query('end_date').' 23:59:59', 'Asia/Jakarta')->setTimezone('UTC');
                $query->where('recorded_at', '<=', $endUtc);
            } catch (\Throwable) {
                return $query->whereRaw('1 = 0');
            }
        }

        // 3. Provenance filter
        $provenance = $request->query('provenance', 'all');
        if (in_array($provenance, ['hardware', 'simulation', 'fixture'], true)) {
            $query->where('provenance', $provenance);
        }

        // 4. Condition status filter matching canonical threshold resolver per device owning school
        $status = $request->query('status', 'all');
        if (in_array($status, ['anomaly', 'normal'], true)) {
            $schoolGroups = $devices->groupBy(fn ($d) => $d->school_id ?? 0);

            if ($schoolGroups->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            $query->where(function (Builder $statusQuery) use ($schoolGroups, $thresholdResolver, $status) {
                foreach ($schoolGroups as $schoolId => $schoolDevices) {
                    $deviceIds = $schoolDevices->pluck('id')->all();
                    if (empty($deviceIds)) {
                        continue;
                    }

                    $thresholds = $thresholdResolver->resolveForSchool($schoolId ?: null);

                    $statusQuery->orWhere(function (Builder $sub) use ($deviceIds, $thresholds, $status) {
                        $sub->whereIn('device_id', $deviceIds);
                        if ($status === 'anomaly') {
                            $this->applyAnomalySqlCondition($sub, $thresholds);
                        } else {
                            $this->applyNormalSqlCondition($sub, $thresholds);
                        }
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Apply anomaly condition matching canonical AnomalyAlertEvaluator thresholds.
     *
     * @param  array<string, array<string, mixed>>  $thresholds
     */
    protected function applyAnomalySqlCondition(Builder $query, array $thresholds): void
    {
        $query->where(function (Builder $q) use ($thresholds) {
            $q->where(function ($sub) {
                $sub->whereNotNull('threshold_flags')
                    ->where('threshold_flags', '!=', '[]')
                    ->where('threshold_flags', '!=', '{}');
            })
                ->orWhere(fn ($sub) => $sub->whereNotNull('ph')->where(fn ($s) => $s->where('ph', '<', (float) ($thresholds['ph']['ideal_min'] ?? 6.0))->orWhere('ph', '>', (float) ($thresholds['ph']['ideal_max'] ?? 7.2))))
                ->orWhere(fn ($sub) => $sub->whereNotNull('ec')->where(fn ($s) => $s->where('ec', '<', (float) ($thresholds['ec']['ideal_min'] ?? 1000.0))->orWhere('ec', '>', (float) ($thresholds['ec']['ideal_max'] ?? 2200.0))))
                ->orWhere(fn ($sub) => $sub->whereNotNull('humidity')->where(fn ($s) => $s->where('humidity', '<', (float) ($thresholds['moisture']['ideal_min'] ?? 45.0))->orWhere('humidity', '>', (float) ($thresholds['moisture']['ideal_max'] ?? 65.0))))
                ->orWhere(fn ($sub) => $sub->whereNotNull('temperature')->where(fn ($s) => $s->where('temperature', '<', (float) ($thresholds['temperature']['ideal_min'] ?? 22.0))->orWhere('temperature', '>', (float) ($thresholds['temperature']['ideal_max'] ?? 29.0))))
                ->orWhere(fn ($sub) => $sub->whereNotNull('nitrogen')->where(fn ($s) => $s->where('nitrogen', '<', (float) ($thresholds['nitrogen']['ideal_min'] ?? 40.0))->orWhere('nitrogen', '>', (float) ($thresholds['nitrogen']['ideal_max'] ?? 120.0))))
                ->orWhere(fn ($sub) => $sub->whereNotNull('phosphorus')->where(fn ($s) => $s->where('phosphorus', '<', (float) ($thresholds['phosphorus']['ideal_min'] ?? 25.0))->orWhere('phosphorus', '>', (float) ($thresholds['phosphorus']['ideal_max'] ?? 60.0))))
                ->orWhere(fn ($sub) => $sub->whereNotNull('potassium')->where(fn ($s) => $s->where('potassium', '<', (float) ($thresholds['potassium']['ideal_min'] ?? 100.0))->orWhere('potassium', '>', (float) ($thresholds['potassium']['ideal_max'] ?? 200.0))));
        });
    }

    /**
     * Apply normal condition matching canonical AnomalyAlertEvaluator thresholds.
     *
     * @param  array<string, array<string, mixed>>  $thresholds
     */
    protected function applyNormalSqlCondition(Builder $query, array $thresholds): void
    {
        $query->where(function (Builder $q) use ($thresholds) {
            // 1. Must not have stored anomaly threshold flags
            $q->where(function ($sub) {
                $sub->whereNull('threshold_flags')
                    ->orWhere('threshold_flags', '[]')
                    ->orWhere('threshold_flags', '{}');
            });

            // 2. Must have at least one measured parameter (not empty/all-null)
            $q->where(function ($sub) {
                $sub->whereNotNull('ph')
                    ->orWhereNotNull('ec')
                    ->orWhereNotNull('humidity')
                    ->orWhereNotNull('temperature')
                    ->orWhereNotNull('nitrogen')
                    ->orWhereNotNull('phosphorus')
                    ->orWhereNotNull('potassium');
            });

            // 3. All non-null parameters must fall within ideal range
            $q->where(fn ($sub) => $sub->whereNull('ph')->orWhereBetween('ph', [(float) ($thresholds['ph']['ideal_min'] ?? 6.0), (float) ($thresholds['ph']['ideal_max'] ?? 7.2)]))
                ->where(fn ($sub) => $sub->whereNull('ec')->orWhereBetween('ec', [(float) ($thresholds['ec']['ideal_min'] ?? 1000.0), (float) ($thresholds['ec']['ideal_max'] ?? 2200.0)]))
                ->where(fn ($sub) => $sub->whereNull('humidity')->orWhereBetween('humidity', [(float) ($thresholds['moisture']['ideal_min'] ?? 45.0), (float) ($thresholds['moisture']['ideal_max'] ?? 65.0)]))
                ->where(fn ($sub) => $sub->whereNull('temperature')->orWhereBetween('temperature', [(float) ($thresholds['temperature']['ideal_min'] ?? 22.0), (float) ($thresholds['temperature']['ideal_max'] ?? 29.0)]))
                ->where(fn ($sub) => $sub->whereNull('nitrogen')->orWhereBetween('nitrogen', [(float) ($thresholds['nitrogen']['ideal_min'] ?? 40.0), (float) ($thresholds['nitrogen']['ideal_max'] ?? 120.0)]))
                ->where(fn ($sub) => $sub->whereNull('phosphorus')->orWhereBetween('phosphorus', [(float) ($thresholds['phosphorus']['ideal_min'] ?? 25.0), (float) ($thresholds['phosphorus']['ideal_max'] ?? 60.0)]))
                ->where(fn ($sub) => $sub->whereNull('potassium')->orWhereBetween('potassium', [(float) ($thresholds['potassium']['ideal_min'] ?? 100.0), (float) ($thresholds['potassium']['ideal_max'] ?? 200.0)]));
        });
    }
}
