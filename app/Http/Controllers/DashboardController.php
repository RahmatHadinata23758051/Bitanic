<?php

namespace App\Http\Controllers;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Agronomy\AgronomicThresholdResolver;
use App\Services\Hardware\DeviceAvailabilityResolver;
use App\Services\License\SchoolLicenseResolver;
use App\Services\Tenancy\SchoolContextResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the vocational LMS lab overview dashboard.
     */
    public function index(
        Request $request,
        DeviceAvailabilityResolver $availability,
        AgronomicThresholdResolver $thresholdResolver,
        SchoolContextResolver $contextResolver,
        SchoolLicenseResolver $licenseResolver
    ): View {
        $user = $request->user();
        $context = $contextResolver->resolve($request, $user);

        $targetSchoolId = $context->targetSchoolId;
        $selectedSchoolId = $context->selectedSchoolId;
        $schools = $context->schools;
        $isSuperuser = $context->isSuperuser;

        // 1. Hardware fleet overview scoped to school
        $deviceQuery = Device::with(['latestReading', 'school'])
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
        $upcomingSchedules = ActivitySchedule::with(['device', 'classroom.homeroomTeacher', 'participants.user'])
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->where('activity_date', '>=', now()->toDateString())
            ->orderBy('activity_date')
            ->orderBy('start_time')
            ->take(4)
            ->get();

        $todaySchedule = $upcomingSchedules->first(fn ($s) => $s->activity_date && $s->activity_date->toDateString() === now()->toDateString())
            ?? $upcomingSchedules->first();

        $isScheduleToday = $todaySchedule?->activity_date
            ? $todaySchedule->activity_date->toDateString() === now()->toDateString()
            : false;

        $todaySchedules = ActivitySchedule::query()
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->whereDate('activity_date', now()->toDateString())
            ->get(['id', 'device_id', 'title', 'start_time', 'end_time']);

        $todaySchedulesCount = $todaySchedules->count();

        $hourlyAllocations = [];
        foreach ($todaySchedules as $sched) {
            if (! $sched->device_id || ! $sched->start_time || ! $sched->end_time) {
                continue;
            }
            $startHour = (int) substr($sched->start_time, 0, 2);
            $endHour = (int) substr($sched->end_time, 0, 2);
            $endMinute = (int) substr($sched->end_time, 3, 2);
            $lastHour = $endMinute > 0 ? $endHour : max($startHour, $endHour - 1);

            for ($h = $startHour; $h <= $lastHour; $h++) {
                if ($h >= 6 && $h <= 17) {
                    $hourlyAllocations[$sched->device_id][$h] = [
                        'title' => $sched->title,
                        'schedule_id' => $sched->id,
                    ];
                }
            }
        }

        // 3. Student reports count scoped by role
        $isSiswa = (bool) $user?->isSiswa();
        $pendingReportsCount = 0;
        $studentOwnReportsCount = 0;
        $studentReportsNeedingRevisionCount = 0;

        if ($isSiswa) {
            $studentOwnReportsCount = StudentReport::where('user_id', $user->id)
                ->whereIn('status', ['submitted', 'graded', 'revision'])
                ->count();
            $studentReportsNeedingRevisionCount = StudentReport::where('user_id', $user->id)
                ->where('status', 'revision')
                ->count();
        } else {
            $pendingReportsCount = StudentReport::where('status', 'submitted')
                ->when($targetSchoolId, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('school_id', $targetSchoolId)))
                ->count();
        }

        // 4. Evaluate system-wide anomalies (gated to live/fresh telemetry only)
        $thresholds = $thresholdResolver->resolveForSchool($targetSchoolId);
        $phCritLow = (float) ($thresholds['ph']['critical_low'] ?? 5.5);
        $moistureCritLow = (float) ($thresholds['moisture']['critical_low'] ?? 30.0);
        $ecMax = (float) ($thresholds['ec']['ideal_max'] ?? 2200.0);

        $anomalies = [];
        $affectedStationCodes = [];
        $hasFreshHardwareTelemetry = false;

        foreach ($devices as $dev) {
            $latest = $dev->latestReading;
            if (! $latest) {
                continue;
            }

            $freshTimeout = $availability->getTimeoutSeconds($dev);
            $isFresh = $latest->recorded_at && $latest->recorded_at->greaterThanOrEqualTo(now()->subSeconds($freshTimeout));
            $isOnline = (bool) $dev->is_online;
            $isHardware = (bool) $latest->isHardware();

            // Only alert on live/fresh hardware telemetry to avoid false alarms from stale history or fixtures
            if (! $isOnline || ! $isFresh || ! $isHardware) {
                continue;
            }

            $hasFreshHardwareTelemetry = true;

            if ($latest->ph !== null && (float) $latest->ph < $phCritLow) {
                $anomalies[] = [
                    'device' => $dev->code,
                    'bench' => $dev->bench_location ?? $dev->name,
                    'parameter' => 'pH Tanah',
                    'value' => number_format((float) $latest->ph, 2),
                    'severity' => 'critical',
                    'message' => 'Kemasaman masam ekstrem (< '.$phCritLow.'). Risiko keracunan Al/Fe.',
                    'action' => 'Aplikasi dolomit/kapur pertanian 50g/m².',
                    'recorded_at' => $latest->recorded_at,
                    'provenance' => $latest->provenance,
                ];
                $affectedStationCodes[$dev->code] = true;
            }
            if ($latest->humidity !== null && (float) $latest->humidity < $moistureCritLow) {
                $anomalies[] = [
                    'device' => $dev->code,
                    'bench' => $dev->bench_location ?? $dev->name,
                    'parameter' => 'Kadar Lengas',
                    'value' => number_format((float) $latest->humidity, 1).'%',
                    'severity' => 'critical',
                    'message' => 'Tanah mendekati titik layu permanen (< '.$moistureCritLow.'%).',
                    'action' => 'Aktifkan pompa irigasi tetes.',
                    'recorded_at' => $latest->recorded_at,
                    'provenance' => $latest->provenance,
                ];
                $affectedStationCodes[$dev->code] = true;
            }
            if ($latest->ec !== null && (float) $latest->ec > $ecMax) {
                $anomalies[] = [
                    'device' => $dev->code,
                    'bench' => $dev->bench_location ?? $dev->name,
                    'parameter' => 'EC Salinitas',
                    'value' => number_format((float) $latest->ec, 0).' µS/cm',
                    'severity' => 'warning',
                    'message' => 'Salinitas garam pupuk tinggi (> '.$ecMax.' µS/cm).',
                    'action' => 'Lakukan pembilasan air murni (leaching).',
                    'recorded_at' => $latest->recorded_at,
                    'provenance' => $latest->provenance,
                ];
                $affectedStationCodes[$dev->code] = true;
            }
        }
        $affectedStationsCount = count($affectedStationCodes);

        // 5. PRD FR-002 Student Attendance Percentage (role-scoped for privacy)
        if ($isSiswa) {
            $studentAttendance = $user->profile?->attendance_percent !== null
                ? round((float) $user->profile->attendance_percent, 1)
                : null;
            $attendanceDisplayPercent = $studentAttendance;
        } else {
            $avgAttendance = UserProfile::query()
                ->whereHas('user', function ($q) use ($targetSchoolId) {
                    $q->where('role', 'siswa');
                    if ($targetSchoolId) {
                        $q->where('school_id', $targetSchoolId);
                    }
                })
                ->whereNotNull('attendance_percent')
                ->avg('attendance_percent');

            $attendanceDisplayPercent = $avgAttendance !== null ? round((float) $avgAttendance, 1) : null;
        }

        $averageAttendance = $attendanceDisplayPercent;

        // Macro Platform Metrics for Superuser Overview
        $today = Carbon::today('Asia/Jakarta');

        $activeLicenseCondition = function ($lq) use ($today) {
            $lq->where(function ($sub) use ($today) {
                $sub->whereHas('terms', function ($tq) use ($today) {
                    $tq->where('start_date', '<=', $today)
                        ->where('expiration_date', '>', $today);
                })->orWhere(function ($fb) use ($today) {
                    $fb->doesntHave('terms')
                        ->whereNotNull('start_date')
                        ->whereNotNull('expiration_date')
                        ->where('start_date', '<=', $today)
                        ->where('expiration_date', '>', $today);
                });
            });
        };

        $expiredLicenseCondition = function ($lq) use ($today) {
            $lq->where(function ($sub) use ($today) {
                $sub->where(function ($termsBranch) use ($today) {
                    $termsBranch->whereHas('terms', function ($tq) use ($today) {
                        $tq->where('expiration_date', '<=', $today);
                    })->whereDoesntHave('terms', function ($tq) use ($today) {
                        $tq->where('start_date', '<=', $today)
                            ->where('expiration_date', '>', $today);
                    });
                })->orWhere(function ($fallbackBranch) use ($today) {
                    $fallbackBranch->doesntHave('terms')
                        ->whereNotNull('expiration_date')
                        ->where('expiration_date', '<=', $today);
                });
            });
        };

        $pendingLicenseCondition = function ($lq) use ($today) {
            $lq->where(function ($sub) use ($today) {
                $sub->where(function ($futureBranch) use ($today) {
                    $futureBranch->whereHas('terms')
                        ->whereDoesntHave('terms', function ($tq) use ($today) {
                            $tq->where('expiration_date', '<=', $today);
                        })->whereDoesntHave('terms', function ($tq) use ($today) {
                            $tq->where('start_date', '<=', $today)
                                ->where('expiration_date', '>', $today);
                        });
                })->orWhere(function ($fallbackBranch) use ($today) {
                    $fallbackBranch->doesntHave('terms')
                        ->where(function ($dt) use ($today) {
                            $dt->whereNull('start_date')
                                ->orWhere('start_date', '>', $today);
                        });
                });
            });
        };

        $macroMetrics = [
            'total_schools' => School::count(),
            'total_students' => User::where('role', 'siswa')->count(),
            'total_teachers' => User::where('role', 'guru')->count(),
            'total_devices' => Device::count(),
            'active_licenses' => School::whereHas('license', $activeLicenseCondition)->count(),
            'pending_licenses' => School::whereHas('license', $pendingLicenseCondition)->count(),
            'expired_licenses' => School::whereHas('license', $expiredLicenseCondition)->count(),
            'uninitialized_licenses' => School::doesntHave('license')->count(),
            'unentitled_licenses' => School::whereDoesntHave('license', $activeLicenseCondition)->count(),
        ];

        $allSchools = School::with([
            'license.terms',
            'provinceRelation',
            'regencyRelation',
            'districtRelation',
        ])->withCount(['devices', 'classrooms'])->orderBy('name')->get();

        $schoolCards = $allSchools->map(function (School $school) use ($today, $licenseResolver) {
            $license = $school->license;
            $resolution = $license ? $licenseResolver->resolve($license, $today) : null;
            $status = $resolution['status'] ?? 'uninitialized';
            $statusLabel = match ($status) {
                'active' => 'Aktif',
                'pending' => 'Menunggu',
                'expired' => 'Kedaluwarsa',
                default => 'Belum Diinisialisasi',
            };

            $studentsCount = User::where('role', 'siswa')->where('school_id', $school->id)->count();
            $teachersCount = User::where('role', 'guru')->where('school_id', $school->id)->count();

            $isVerified = $school->coordinate_verified_at !== null;
            $hasCoords = $school->latitude !== null && $school->longitude !== null;

            return [
                'id' => $school->id,
                'name' => $school->name,
                'npsn' => $school->npsn,
                'slug' => $school->slug,
                'province' => $school->provinceRelation?->name ?? ($school->province ?: '—'),
                'city' => $school->regencyRelation?->name ?? ($school->city ?: '—'),
                'latitude' => $school->latitude,
                'longitude' => $school->longitude,
                'has_coordinates' => $hasCoords,
                'coordinates_verified' => $isVerified,
                'license_status' => $status,
                'license_status_label' => $statusLabel,
                'license_number' => $license?->license_number ?? '—',
                'days_remaining' => $resolution['days_remaining'] ?? null,
                'devices_count' => $school->devices_count,
                'classrooms_count' => $school->classrooms_count,
                'students_count' => $studentsCount,
                'teachers_count' => $teachersCount,
                'workspace_url' => route('master.schools.show', $school->id),
            ];
        });

        $mapPins = $schoolCards->filter(fn ($s) => $s['has_coordinates'])->values()->all();

        $recentStudents = User::with(['profile', 'school'])
            ->where('role', 'siswa')
            ->latest()
            ->take(8)
            ->get();

        return view('pages.dashboard.dashboard', [
            'user' => $user,
            'devices' => $devices,
            'activeDevice' => $devices->first(),
            'onlineKitsCount' => $onlineKitsCount,
            'totalKitsCount' => $totalKitsCount,
            'todaySchedule' => $todaySchedule,
            'isScheduleToday' => $isScheduleToday,
            'todaySchedulesCount' => $todaySchedulesCount,
            'hourlyAllocations' => $hourlyAllocations,
            'upcomingSchedules' => $upcomingSchedules,
            'pendingReportsCount' => $pendingReportsCount,
            'studentOwnReportsCount' => $studentOwnReportsCount,
            'studentReportsNeedingRevisionCount' => $studentReportsNeedingRevisionCount,
            'anomalies' => $anomalies,
            'affectedStationsCount' => $affectedStationsCount,
            'criticalAlertsCount' => count($anomalies),
            'hasFreshHardwareTelemetry' => $hasFreshHardwareTelemetry,
            'averageAttendance' => $averageAttendance,
            'attendanceDisplayPercent' => $attendanceDisplayPercent,
            'isSuperuser' => $isSuperuser,
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'macroMetrics' => $macroMetrics,
            'schoolCards' => $schoolCards,
            'mapPins' => $mapPins,
            'recentStudents' => $recentStudents,
        ]);
    }
}
