<?php

namespace App\Http\Controllers\IoT;

use App\Actions\Hardware\CreateSensorCalibrationProfileAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Services\Hardware\DeviceAvailabilityResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class TrainerKitManagerController extends Controller
{
    /**
     * Display the Trainer Kit fleet and hardware status.
     */
    public function index(Request $request, DeviceAvailabilityResolver $resolver): View
    {
        $schoolId = $request->user()?->school_id;
        $isSuperuser = (bool) $request->user()?->isSuperuser();

        if ($request->user() && ! $isSuperuser && ! $schoolId) {
            abort(403, 'Akses Trainer Kit memerlukan asosiasi sekolah yang valid.');
        }

        $kits = Device::with(['latestReading', 'activeApiKey'])
            ->when(! $isSuperuser, fn ($query) => $query->where('school_id', $schoolId))
            ->orderBy('code')
            ->paginate(15);

        // Compute deterministic availability per device based on heartbeat
        $kits->getCollection()->transform(fn ($kit) => $resolver->applyToDevice($kit));

        $targetSchoolId = ! $isSuperuser ? $schoolId : null;
        $totalCount = $resolver->countTotal($targetSchoolId);
        $onlineCount = $resolver->countOnline($targetSchoolId);

        return view('pages.kits.index', [
            'kits' => $kits,
            'stats' => [
                'total' => $totalCount,
                'online' => $onlineCount,
                'attention' => max(0, $totalCount - $onlineCount),
            ],
        ]);
    }

    /**
     * Handle simulated soft-reboot of a Trainer Kit.
     */
    public function reboot(Request $request, Device $device): RedirectResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperuser()) {
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
    public function updateCalibration(
        Request $request,
        Device $device,
        CreateSensorCalibrationProfileAction $createCalibrationProfile
    ): RedirectResponse {
        $user = $request->user();
        if ($user && ! $user->isSuperuser()) {
            abort_unless($user->school_id && (int) $device->school_id === (int) $user->school_id, 403);
        }

        $validated = $request->validate([
            'phOffset' => 'required|numeric|between:-5,5',
            'moistureOffset' => 'required|numeric|between:-20,20',
            'ecOffset' => 'required|numeric|between:-500,500',
            'reason' => 'nullable|string|max:255',
        ]);

        $reason = ! empty($validated['reason'])
            ? $validated['reason']
            : 'Penyetelan offset kalibrasi sensor meja praktikum oleh guru pengampu.';

        try {
            $createCalibrationProfile->execute(
                device: $device,
                sensorModel: 'RS485-SOIL-7IN1',
                parameter: 'ph',
                method: 'offset',
                parameters: ['offset' => (float) $validated['phOffset']],
                reason: $reason,
                profileName: 'Profil Kalibrasi pH Meja '.$device->code,
                user: $user
            );

            $createCalibrationProfile->execute(
                device: $device,
                sensorModel: 'RS485-SOIL-7IN1',
                parameter: 'humidity',
                method: 'offset',
                parameters: ['offset' => (float) $validated['moistureOffset']],
                reason: $reason,
                profileName: 'Profil Kalibrasi Kelembapan Meja '.$device->code,
                user: $user
            );

            $createCalibrationProfile->execute(
                device: $device,
                sensorModel: 'RS485-SOIL-7IN1',
                parameter: 'ec',
                method: 'offset',
                parameters: ['offset' => (float) $validated['ecOffset']],
                reason: $reason,
                profileName: 'Profil Kalibrasi EC Meja '.$device->code,
                user: $user
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('kits.index')
                ->with('error', 'Gagal memperbarui kalibrasi: '.$e->getMessage());
        }

        return redirect()->route('kits.index')
            ->with('success', 'Offset kalibrasi sensor untuk '.$device->code.' berhasil diperbarui (Profil software aktif; downlink hardware: BLOCKED).');
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
