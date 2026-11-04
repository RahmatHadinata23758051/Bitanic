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
    public function index(): View
    {
        $schedules = ActivitySchedule::with(['device', 'participants.user'])
            ->orderBy('activity_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $devices = Device::where('is_active', true)->orderBy('code')->get();
        $modules = ModulePracticum::orderBy('meeting_number')->get();

        // Status kit ketersediaan hari ini
        $today = now()->toDateString();
        $bookedDevicesToday = ActivitySchedule::where('activity_date', $today)
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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'device_id' => 'nullable|exists:devices,id',
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
    public function destroy(int $id): RedirectResponse
    {
        $schedule = ActivitySchedule::findOrFail($id);
        $schedule->delete();

        return redirect()->route('schedules.index')
            ->with('success', 'Jadwal praktikum berhasil dihapus.');
    }
}
