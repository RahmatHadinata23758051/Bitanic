<?php

namespace App\Http\Controllers\Academic;

use App\Actions\Schedule\CreateScheduleAction;
use App\Http\Controllers\Controller;
use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentGroup;
use App\Models\User;
use App\Services\Hardware\DeviceAvailabilityResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ActivityScheduleController extends Controller
{
    /**
     * Display practicum schedules timeline and trainer kit bench allocations.
     */
    public function index(Request $request, DeviceAvailabilityResolver $availabilityResolver): View
    {
        $user = $request->user();
        $schoolId = $user?->school_id;
        $isSuperuser = (bool) $user?->isSuperuser();

        if ($user && ! $isSuperuser && ! $schoolId) {
            abort(403, 'Akses jadwal praktikum memerlukan asosiasi sekolah yang valid.');
        }

        $selectedSchoolId = $request->query('school_id');
        $schools = $isSuperuser ? School::orderBy('name')->get() : collect();

        $targetSchoolId = $isSuperuser
            ? ($selectedSchoolId ? (int) $selectedSchoolId : null)
            : ($schoolId ?? School::query()->where('status', 'active')->orderBy('id')->value('id') ?? -1);

        $schedules = ActivitySchedule::with(['device', 'participants.user', 'classroom', 'studentGroup', 'modulePracticum'])
            ->when($targetSchoolId !== null, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('activity_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $devices = Device::where('is_active', true)
            ->when($targetSchoolId !== null, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->get();
        $classrooms = Classroom::when($targetSchoolId !== null, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('name')
            ->get();
        $studentGroups = StudentGroup::when($targetSchoolId !== null, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('name')
            ->get();
        $modules = ModulePracticum::orderBy('meeting_number')->get();

        // Status kit ketersediaan hari ini
        $today = now()->toDateString();
        $bookedDevicesToday = ActivitySchedule::where('activity_date', $today)
            ->when($targetSchoolId !== null, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->pluck('device_id')
            ->filter()
            ->unique()
            ->toArray();

        $onlineDevicesCount = 0;
        $deviceStatuses = [];
        foreach ($devices as $kit) {
            $isOnline = $availabilityResolver->isOnline($kit);
            if ($isOnline) {
                $onlineDevicesCount++;
            }
            $deviceStatuses[$kit->id] = [
                'is_online' => $isOnline,
                'is_booked_today' => in_array($kit->id, $bookedDevicesToday, true),
            ];
        }

        $canManageSchedules = (bool) ($user && in_array($user->role, ['guru', 'superuser', 'admin'], true));

        return view('pages.schedules.activity-schedules', [
            'schedules' => $schedules,
            'devices' => $devices,
            'classrooms' => $classrooms,
            'studentGroups' => $studentGroups,
            'modules' => $modules,
            'bookedDevicesToday' => $bookedDevicesToday,
            'deviceStatuses' => $deviceStatuses,
            'onlineDevicesCount' => $onlineDevicesCount,
            'todayDate' => $today,
            'isSuperuser' => $isSuperuser,
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'canManageSchedules' => $canManageSchedules,
        ]);
    }

    /**
     * Store a newly created practicum schedule.
     */
    public function store(Request $request, CreateScheduleAction $action): RedirectResponse
    {
        $schoolId = $request->user()?->school_id;
        $isSuperuser = (bool) $request->user()?->isSuperuser();

        if ($request->user() && ! $isSuperuser && ! $schoolId) {
            abort(403, 'Pembuatan jadwal memerlukan asosiasi sekolah yang valid.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'device_id' => [
                'nullable',
                'integer',
                'exists:devices,id',
                function ($attribute, $value, $fail) use ($schoolId, $isSuperuser) {
                    if ($value && ! $isSuperuser) {
                        if (! $schoolId) {
                            $fail('Meja Trainer Kit tersebut bukan milik sekolah Anda.');

                            return;
                        }
                        $device = Device::find($value);
                        if ($device && (int) $device->school_id !== (int) $schoolId) {
                            $fail('Meja Trainer Kit tersebut bukan milik sekolah Anda.');
                        }
                    }
                },
            ],
            'classroom_id' => [
                'nullable',
                'integer',
                'exists:classrooms,id',
                function ($attribute, $value, $fail) use ($schoolId, $isSuperuser) {
                    if ($value && ! $isSuperuser) {
                        $classroom = Classroom::find($value);
                        if (! $schoolId || ! $classroom || (int) $classroom->school_id !== (int) $schoolId) {
                            $fail('Kelas tersebut bukan milik sekolah Anda.');
                        }
                    }
                },
            ],
            'student_group_id' => [
                'nullable',
                'integer',
                'exists:student_groups,id',
                function ($attribute, $value, $fail) use ($schoolId, $isSuperuser) {
                    if ($value && ! $isSuperuser) {
                        $group = StudentGroup::find($value);
                        if (! $schoolId || ! $group || (int) $group->school_id !== (int) $schoolId) {
                            $fail('Kelompok siswa tersebut bukan milik sekolah Anda.');
                        }
                    }
                },
            ],
            'module_practicum_id' => 'nullable|integer|exists:module_practicums,id',
            'activity_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'agenda' => 'nullable|string',
        ], [
            'title.required' => 'Judul atau nama kelas praktikum wajib diisi.',
            'activity_date.required' => 'Tanggal pelaksanaan praktikum wajib dipilih.',
            'start_time.required' => 'Jam mulai wajib diisi (format JJ:MM).',
            'end_time.required' => 'Jam selesai wajib diisi (format JJ:MM).',
        ]);

        $creator = Auth::user() ?? User::where('role', 'guru')->first() ?? User::first();

        $action->execute($validated, $creator);

        return redirect()->route('schedules.index')
            ->with('success', 'Jadwal praktikum & alokasi meja trainer kit berhasil disimpan.');
    }

    /**
     * Remove the specified practicum schedule.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $schoolId = $user?->school_id;

        $schedule = ActivitySchedule::query()
            ->when(! $isSuperuser, fn ($query) => $query->where('school_id', $schoolId ?: -1))
            ->findOrFail($id);

        $schedule->delete();

        return redirect()->route('schedules.index')
            ->with('success', 'Jadwal praktikum berhasil dihapus.');
    }
}
