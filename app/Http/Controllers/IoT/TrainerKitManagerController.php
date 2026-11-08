<?php

namespace App\Http\Controllers\IoT;

use App\Actions\Hardware\CreateSensorCalibrationProfileAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\School;
use App\Services\Hardware\DeviceAvailabilityResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrainerKitManagerController extends Controller
{
    /**
     * Display the Trainer Kit fleet and hardware status.
     */
    public function index(Request $request, DeviceAvailabilityResolver $resolver): View
    {
        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Akses Trainer Kit memerlukan asosiasi sekolah yang valid.');
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

        $kits = Device::with(['latestReading', 'activeApiKey'])
            ->when($targetSchoolId, fn ($query) => $query->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        // Compute deterministic availability per device based on heartbeat
        $kits->getCollection()->transform(fn ($kit) => $resolver->applyToDevice($kit));

        $canViewSensitiveMetadata = $user && ($user->isGuru() || $user->isSuperuser());
        $canReboot = $user && ($user->isGuru() || $user->isSuperuser());
        $canRotateApiKey = (bool) $user?->isSuperuser();

        // Server-side metadata stripping for guests and students (QA-01)
        if (! $canViewSensitiveMetadata) {
            $kits->getCollection()->each(function ($kit) {
                $kit->ip_address = null;
                $kit->mac_address = null;
                $kit->firmware_version = null;
                $kit->connection_config = null;
                $kit->unsetRelation('activeApiKey');
                $kit->unsetRelation('apiKeys');
                $kit->makeHidden(['ip_address', 'mac_address', 'firmware_version', 'connection_config', 'activeApiKey', 'apiKeys']);
            });
        }

        $totalCount = $resolver->countTotal($targetSchoolId);
        $onlineCount = $resolver->countOnline($targetSchoolId);

        return view('pages.kits.trainer-kits', [
            'kits' => $kits,
            'schools' => $schools,
            'selectedSchoolId' => $targetSchoolId,
            'isSuperuser' => $isSuperuser,
            'canViewSensitiveMetadata' => $canViewSensitiveMetadata,
            'canReboot' => $canReboot,
            'canRotateApiKey' => $canRotateApiKey,
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

        // Fail-closed tenant validation: device's school must exist and be active (QA-05)
        $school = $device->school ?? School::find($device->school_id);
        if (! $school || $school->status !== 'active') {
            abort(403, 'Aksi ditolak: sekolah perangkat tidak aktif atau belum terdaftar.');
        }

        if (! $user || ! $user->school_id || (int) $device->school_id !== (int) $user->school_id) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk memutasi perangkat sekolah lain.');
        }
        abort_unless($user->school && $user->school->status === 'active', 403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');

        // No command transport or physical ACK is currently implemented.
        // Record the simulated operation, but never claim the device rebooted or changed its heartbeat/status.
        AuditLog::create([
            'school_id' => $device->school_id,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Unknown',
            'actor_role' => $user?->role ?? 'unknown',
            'action' => 'Simulasi Soft-Reboot (Transport Hardware Belum Tersedia)',
            'target_type' => Device::class,
            'target_id' => (string) $device->id,
            'diff' => [
                'operation' => 'simulated_soft_reboot',
                'hardware_transport' => 'unavailable',
                'device_status_mutated' => false,
                'is_cross_school' => $user && $user->school_id && (int) $user->school_id !== (int) $device->school_id,
            ],
            'details' => 'Simulasi permintaan reboot untuk '.$device->code.' dicatat: perintah fisik tidak dikirim karena transport hardware/ACK belum tersedia.',
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('kits.index')
            ->with('warning', 'Permintaan simulasi reboot dicatat. Perintah fisik tidak dikirim karena Trainer Kit belum memiliki transport hardware dan ACK terverifikasi.');
    }

    /**
     * Update calibration offsets for sensors atomically.
     */
    public function updateCalibration(
        Request $request,
        Device $device,
        CreateSensorCalibrationProfileAction $createCalibrationProfile
    ): RedirectResponse {
        $user = $request->user();

        // Fail-closed tenant validation: device's school must exist and be active (QA-05)
        $school = $device->school ?? School::find($device->school_id);
        if (! $school || $school->status !== 'active') {
            abort(403, 'Aksi ditolak: sekolah perangkat tidak aktif atau belum terdaftar.');
        }

        if (! $user || ! $user->school_id || (int) $device->school_id !== (int) $user->school_id) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk memutasi perangkat sekolah lain.');
        }
        abort_unless($user->school && $user->school->status === 'active', 403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');

        $validated = $request->validate([
            'phOffset' => 'required|numeric|between:-5,5',
            'moistureOffset' => 'required|numeric|between:-20,20',
            'ecOffset' => 'required|numeric|between:-500,500',
            'reason' => 'nullable|string|max:255',
        ]);

        $reason = ! empty($validated['reason'])
            ? $validated['reason']
            : 'Penyetelan offset kalibrasi sensor meja praktikum oleh guru pengampu.';

        // Enforce all-or-nothing multi-parameter calibration in a single atomic transaction (QA-03)
        try {
            DB::transaction(function () use ($device, $validated, $reason, $user, $createCalibrationProfile) {
                // Pessimistic lock on device row to serialize concurrent calibration updates
                Device::query()->whereKey($device->getKey())->lockForUpdate()->firstOrFail();

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
            });
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('kits.index')
                ->with('error', 'Gagal memperbarui kalibrasi multi-parameter. Tidak ada perubahan yang disimpan; silakan periksa nilai dan coba lagi.');
        }

        return redirect()->route('kits.index')
            ->with('success', 'Offset kalibrasi sensor untuk '.$device->code.' berhasil diperbarui secara atomik (Profil software aktif; downlink hardware: BLOCKED).');
    }

    /**
     * Safely rotate the API key for a device.
     */
    public function rotateApiKey(Request $request, Device $device): RedirectResponse
    {
        $user = $request->user();

        // Fail-closed tenant validation: device's school must exist and be active (QA-05)
        $school = $device->school ?? School::find($device->school_id);
        if (! $school || $school->status !== 'active') {
            abort(403, 'Aksi ditolak: sekolah perangkat tidak aktif atau belum terdaftar.');
        }

        if (! $user || ! $user->school_id || (int) $device->school_id !== (int) $user->school_id) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk memutasi perangkat sekolah lain.');
        }
        abort_unless($user->school && $user->school->status === 'active', 403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');

        $rotation = DeviceApiKey::rotateForDevice($device, $request->user());

        return redirect()->route('kits.index')
            ->with('success', "Kunci API untuk {$device->code} berhasil dirotasi. Salin kunci baru yang hanya ditampilkan satu kali.")
            ->with('new_api_key', $rotation['plain_key']);
    }
}
