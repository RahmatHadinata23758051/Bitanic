<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainerKitManagerController extends Controller
{
    /**
     * Display the Trainer Kit fleet and hardware status.
     */
    public function index(Request $request): View
    {
        $schoolId = $request->user()?->school_id;
        $isSuperuser = $request->user()?->role === 'superuser';

        if ($request->user() && ! $isSuperuser && ! $schoolId) {
            abort(403, 'Akses Trainer Kit memerlukan asosiasi sekolah yang valid.');
        }

        $kits = Device::with(['latestReading', 'activeApiKey'])
            ->when(! $isSuperuser, fn ($query) => $query->where('school_id', $schoolId))
            ->orderBy('code')
            ->paginate(15);

        return view('pages.kits.index', [
            'kits' => $kits,
            'stats' => [
                'total' => Device::query()->when(! $isSuperuser, fn ($query) => $query->where('school_id', $schoolId))->count(),
                'online' => Device::where('status', 'online')->when(! $isSuperuser, fn ($query) => $query->where('school_id', $schoolId))->count(),
                'attention' => Device::whereIn('status', ['anomaly', 'offline', 'maintenance'])
                    ->when(! $isSuperuser, fn ($query) => $query->where('school_id', $schoolId))
                    ->count(),
            ],
        ]);
    }

    /**
     * Handle simulated soft-reboot of a Trainer Kit.
     */
    public function reboot(Request $request, Device $device): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->role !== 'superuser') {
            abort_unless($user->school_id && (int) $device->school_id === (int) $user->school_id, 403);
        }

        // No command transport or physical ACK is currently implemented.
        // Record the attempted operation, but never claim the device rebooted or changed its heartbeat/status.
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'actor_name' => $request->user()?->name ?? 'Unknown',
            'actor_role' => $request->user()?->role ?? 'unknown',
            'action' => 'Kirim Perintah Soft-Reboot (Simulasi)',
            'details' => 'Permintaan reboot untuk '.$device->code.' tidak dikirim: transport hardware/ACK belum tersedia.',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('kits.index')
            ->with('warning', 'Permintaan reboot tidak dikirim. Trainer Kit belum memiliki integrasi perintah dan ACK hardware.');
    }

    /**
     * Update calibration offsets for sensors.
     */
    public function updateCalibration(Request $request, Device $device): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->role !== 'superuser') {
            abort_unless($user->school_id && (int) $device->school_id === (int) $user->school_id, 403);
        }

        $validated = $request->validate([
            'phOffset' => 'required|numeric|between:-5,5',
            'moistureOffset' => 'required|numeric|between:-20,20',
            'ecOffset' => 'required|numeric|between:-500,500',
        ]);

        $device->update([
            'calibration_offsets' => [
                'ph' => (float) $validated['phOffset'],
                'humidity' => (float) $validated['moistureOffset'],
                'ec' => (float) $validated['ecOffset'],
            ],
        ]);

        return redirect()->route('kits.index')
            ->with('success', 'Offset kalibrasi sensor untuk '.$device->code.' berhasil diperbarui.');
    }

    /**
     * Safely rotate the API key for a device.
     */
    public function rotateApiKey(Request $request, Device $device): RedirectResponse
    {
        $rotation = DeviceApiKey::rotateForDevice($device, $request->user());

        return redirect()->route('kits.index')
            ->with('success', "Kunci API untuk {$device->code} berhasil dirotasi. Salin kunci baru yang hanya ditampilkan satu kali.")
            ->with('new_api_key', $rotation['plain_key']);
    }
}
