<?php

namespace App\Http\Controllers;

use App\Actions\Schedule\CreateScheduleAction;
use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ActivityScheduleController extends Controller
{
    /**
     * Display practicum schedules timeline and trainer kit bench allocations.
     */
    public function index(Request $request): View
    {
        $schoolId = $request->user()?->school_id;
        $isSuperuser = (bool) $request->user()?->isSuperuser();

        if ($request->user() && ! $isSuperuser && ! $schoolId) {
            abort(403, 'Akses jadwal praktikum memerlukan asosiasi sekolah yang valid.');
        }

        $schedules = ActivitySchedule::with(['device', 'participants.user'])
            ->when(! $isSuperuser, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('activity_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $devices = Device::where('is_active', true)
            ->when(! $isSuperuser, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('code')
            ->get();
        $modules = ModulePracticum::orderBy('meeting_number')->get();

        // Status kit ketersediaan hari ini
        $today = now()->toDateString();
        $bookedDevicesToday = ActivitySchedule::where('activity_date', $today)
            ->when(! $isSuperuser, fn ($q) => $q->where('school_id', $schoolId))
            ->pluck('device_id')
            ->filter()
            ->unique()
            ->toArray();

        return view('pages.schedules.index', [
            'schedules' => $schedules,
            'devices' => $devices,
            'modules' => $modules,
            'bookedDevicesToday' => $bookedDevicesToday,
            'todayDate' => $today,
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
