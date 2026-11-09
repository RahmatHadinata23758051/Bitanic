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
        DeviceAvailabilityResolver $availabilityResolver
    ): View {
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

        $validationError = $this->validateFilterInputs($request);

        $validSelectedDevice = null;
        if ($request->filled('device_id')) {
            $deviceId = $request->query('device_id');
            $validSelectedDevice = $devices->first(fn ($d) => (string) $d->id === (string) $deviceId || $d->code === (string) $deviceId);
        }

        $thresholds = $thresholdResolver->resolveForSchool($targetSchoolId);

        $query = $this->buildFilteredQuery($request, $devices, $thresholds, $validationError)
            ->when($targetSchoolId, fn ($query) => $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('school_id', $targetSchoolId)));

        // Active Actionable Alert Semantics:
        // Distinguish active emergencies on fresh readings from historical deviations.
        $activeAlerts = collect();
        $alertDevice = null;
        $isFreshAlert = false;

        $isViewingPastDate = $request->filled('end_date') &&
            Carbon::parse($request->query('end_date'))->endOfDay()->isPast() &&
            ! Carbon::parse($request->query('end_date'))->isToday();

        if (! $isViewingPastDate && ! $validationError) {
            $candidateReading = null;
            if ($validSelectedDevice) {
                $candidateReading = TelemetryReading::where('device_id', $validSelectedDevice->id)
                    ->latest('recorded_at')
                    ->first();
            } else {
                $candidateReading = TelemetryReading::whereIn('device_id', $devices->pluck('id'))
                    ->where('provenance', 'hardware')
                    ->latest('recorded_at')
                    ->first();
            }

            if ($candidateReading && $candidateReading->recorded_at) {
                $timeout = $availabilityResolver->getTimeoutSeconds($candidateReading->device);
                if ($candidateReading->recorded_at->greaterThanOrEqualTo(now()->subSeconds($timeout))) {
                    $isFreshAlert = true;
                    $activeAlerts = $evaluator->evaluate($candidateReading, $targetSchoolId);
                    $alertDevice = $candidateReading->device;
                }
            }
        }

        // Contextual metrics
        $totalLogsCount = (clone $query)->count();
        $totalDevicesCount = $devices->count();
        $onlineDevicesCount = $devices->filter(fn ($d) => $availabilityResolver->isOnline($d))->count();
        $schoolTotalReadings = TelemetryReading::whereIn('device_id', $devices->pluck('id'))->count();

        // Selected period label
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $periodLabel = match (true) {
            $request->filled('start_date') && $request->filled('end_date') => Carbon::parse($startDate)->isoFormat('D MMM Y').' – '.Carbon::parse($endDate)->isoFormat('D MMM Y').' (WIB)',
            $request->filled('start_date') => 'Sejak '.Carbon::parse($startDate)->isoFormat('D MMM Y').' (WIB)',
            $request->filled('end_date') => 'Sampai '.Carbon::parse($endDate)->isoFormat('D MMM Y').' (WIB)',
            default => 'Semua Riwayat Waktu',
        };

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
        AgronomicThresholdResolver $thresholdResolver
    ): StreamedResponse {
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

        $validationError = $this->validateFilterInputs($request);
        if ($validationError) {
            abort(422, $validationError);
        }

        $devices = Device::query()
            ->when($targetSchoolId, fn ($query) => $query->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->get();

        $thresholds = $thresholdResolver->resolveForSchool($targetSchoolId);
        $query = $this->buildFilteredQuery($request, $devices, $thresholds, $validationError)
            ->when($targetSchoolId, fn ($query) => $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('school_id', $targetSchoolId)));

        $filename = 'bitanic-telemetry-'.now()->setTimezone('Asia/Jakarta')->format('Ymd_His').'.csv';

        return $action->execute($query, $filename, $targetSchoolId);
    }

    /**
     * Validate incoming HTTP filter inputs.
     */
    protected function validateFilterInputs(Request $request): ?string
    {
        $validator = Validator::make($request->query(), [
            'device_id' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:all,normal,anomaly'],
            'provenance' => ['nullable', 'in:all,hardware,simulation,fixture'],
            'school_id' => ['nullable', 'integer'],
        ], [
            'start_date.date_format' => 'Format tanggal mulai tidak valid (harus YYYY-MM-DD).',
            'end_date.date_format' => 'Format tanggal akhir tidak valid (harus YYYY-MM-DD).',
            'status.in' => 'Pilihan status kondisi tidak valid.',
            'provenance.in' => 'Pilihan asal data tidak valid.',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            if ($request->query('start_date') > $request->query('end_date')) {
                return 'Tanggal mulai tidak boleh melebihi tanggal akhir.';
            }
        }

        return null;
    }

    /**
     * Build base query with incoming HTTP filters, canonical thresholds, and cross-school IDOR protection.
     *
     * @param  Collection<int, Device>  $devices
     * @param  array<string, array<string, mixed>>  $thresholds
     * @return Builder<TelemetryReading>
     */
    protected function buildFilteredQuery(
        Request $request,
        Collection $devices,
        array $thresholds,
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
                $query->whereRaw('1 = 0');
            }
        }

        // 2. Date filtering with Asia/Jakarta (WIB) conversion to UTC
        if ($request->filled('start_date')) {
            try {
                $startUtc = Carbon::createFromFormat('Y-m-d H:i:s', $request->query('start_date').' 00:00:00', 'Asia/Jakarta')->setTimezone('UTC');
                $query->where('recorded_at', '>=', $startUtc);
            } catch (\Throwable) {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('end_date')) {
            try {
                $endUtc = Carbon::createFromFormat('Y-m-d H:i:s', $request->query('end_date').' 23:59:59', 'Asia/Jakarta')->setTimezone('UTC');
                $query->where('recorded_at', '<=', $endUtc);
            } catch (\Throwable) {
                $query->whereRaw('1 = 0');
            }
        }

        // 3. Provenance filter
        $provenance = $request->query('provenance', 'all');
        if (in_array($provenance, ['hardware', 'simulation', 'fixture'], true)) {
            $query->where('provenance', $provenance);
        }

        // 4. Condition status filter matching canonical threshold resolver
        $status = $request->query('status', 'all');
        if ($status === 'anomaly') {
            $query->where(function (Builder $q) use ($thresholds) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('threshold_flags')
                        ->where('threshold_flags', '!=', '[]')
                        ->where('threshold_flags', '!=', '{}');
                })
                    ->orWhere('ph', '<', (float) ($thresholds['ph']['ideal_min'] ?? 6.0))
                    ->orWhere('ph', '>', (float) ($thresholds['ph']['ideal_max'] ?? 7.2))
                    ->orWhere('ec', '<', (float) ($thresholds['ec']['ideal_min'] ?? 1000.0))
                    ->orWhere('ec', '>', (float) ($thresholds['ec']['ideal_max'] ?? 2200.0))
                    ->orWhere('humidity', '<', (float) ($thresholds['moisture']['ideal_min'] ?? 45.0))
                    ->orWhere('humidity', '>', (float) ($thresholds['moisture']['ideal_max'] ?? 65.0))
                    ->orWhere('temperature', '<', (float) ($thresholds['temperature']['ideal_min'] ?? 22.0))
                    ->orWhere('temperature', '>', (float) ($thresholds['temperature']['ideal_max'] ?? 29.0))
                    ->orWhere('nitrogen', '<', (float) ($thresholds['nitrogen']['ideal_min'] ?? 40.0))
                    ->orWhere('nitrogen', '>', (float) ($thresholds['nitrogen']['ideal_max'] ?? 120.0))
                    ->orWhere('phosphorus', '<', (float) ($thresholds['phosphorus']['ideal_min'] ?? 25.0))
                    ->orWhere('phosphorus', '>', (float) ($thresholds['phosphorus']['ideal_max'] ?? 60.0))
                    ->orWhere('potassium', '<', (float) ($thresholds['potassium']['ideal_min'] ?? 100.0))
                    ->orWhere('potassium', '>', (float) ($thresholds['potassium']['ideal_max'] ?? 200.0));
            });
        } elseif ($status === 'normal') {
            $query->where(function (Builder $q) use ($thresholds) {
                // Must not have stored anomaly threshold flags
                $q->where(function ($sub) {
                    $sub->whereNull('threshold_flags')
                        ->orWhere('threshold_flags', '[]')
                        ->orWhere('threshold_flags', '{}');
                });

                // Must have at least one measured parameter (not empty/all-null)
                $q->where(function ($sub) {
                    $sub->whereNotNull('ph')
                        ->orWhereNotNull('ec')
                        ->orWhereNotNull('humidity')
                        ->orWhereNotNull('temperature')
                        ->orWhereNotNull('nitrogen')
                        ->orWhereNotNull('phosphorus')
                        ->orWhereNotNull('potassium');
                });

                // All non-null parameters must fall within ideal range
                $q->where(fn ($sub) => $sub->whereNull('ph')->orWhereBetween('ph', [(float) ($thresholds['ph']['ideal_min'] ?? 6.0), (float) ($thresholds['ph']['ideal_max'] ?? 7.2)]))
                    ->where(fn ($sub) => $sub->whereNull('ec')->orWhereBetween('ec', [(float) ($thresholds['ec']['ideal_min'] ?? 1000.0), (float) ($thresholds['ec']['ideal_max'] ?? 2200.0)]))
                    ->where(fn ($sub) => $sub->whereNull('humidity')->orWhereBetween('humidity', [(float) ($thresholds['moisture']['ideal_min'] ?? 45.0), (float) ($thresholds['moisture']['ideal_max'] ?? 65.0)]))
                    ->where(fn ($sub) => $sub->whereNull('temperature')->orWhereBetween('temperature', [(float) ($thresholds['temperature']['ideal_min'] ?? 22.0), (float) ($thresholds['temperature']['ideal_max'] ?? 29.0)]))
                    ->where(fn ($sub) => $sub->whereNull('nitrogen')->orWhereBetween('nitrogen', [(float) ($thresholds['nitrogen']['ideal_min'] ?? 40.0), (float) ($thresholds['nitrogen']['ideal_max'] ?? 120.0)]))
                    ->where(fn ($sub) => $sub->whereNull('phosphorus')->orWhereBetween('phosphorus', [(float) ($thresholds['phosphorus']['ideal_min'] ?? 25.0), (float) ($thresholds['phosphorus']['ideal_max'] ?? 60.0)]))
                    ->where(fn ($sub) => $sub->whereNull('potassium')->orWhereBetween('potassium', [(float) ($thresholds['potassium']['ideal_min'] ?? 100.0), (float) ($thresholds['potassium']['ideal_max'] ?? 200.0)]));
            });
        }

        return $query;
    }
}
