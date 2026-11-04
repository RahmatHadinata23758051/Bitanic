<?php

namespace App\Actions\Hardware;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\SensorCalibrationProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateSensorCalibrationProfileAction
{
    /**
     * Create, audit, and activate a versioned sensor calibration profile.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws InvalidArgumentException
     */
    public function execute(
        Device $device,
        string $sensorModel,
        string $parameter,
        string $method,
        array $parameters,
        string $reason,
        string $profileName,
        ?User $user = null,
        ?CarbonInterface $effectiveFrom = null
    ): SensorCalibrationProfile {
        if ($user && ! $user->isSuperuser()) {
            if (! $user->school_id || (int) $device->school_id !== (int) $user->school_id) {
                throw new InvalidArgumentException('Pengguna tidak memiliki wewenang untuk mengkalibrasi perangkat sekolah lain.');
            }
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan kalibrasi wajib diisi untuk catatan jejak audit operasional.');
        }

        // Validate parameter and method against verified hardware sensor specification catalog
        $paramSpec = SensorCalibrationProfile::validateSpecification($sensorModel, $parameter, $method, $parameters);
        $now = $effectiveFrom ? Carbon::instance($effectiveFrom) : Carbon::now();

        return DB::transaction(function () use ($device, $sensorModel, $parameter, $method, $parameters, $reason, $profileName, $user, $paramSpec, $now) {
            // Serialize concurrent profile creation for one device to protect version sequence
            Device::whereKey($device->id)->lockForUpdate()->firstOrFail();

            $lastVersion = (int) SensorCalibrationProfile::where('device_id', $device->id)
                ->where('parameter', $parameter)
                ->lockForUpdate()
                ->max('version');
            $newVersion = $lastVersion + 1;

            // Archive any previously effective profile without overwriting its original data lineage
            SensorCalibrationProfile::where('device_id', $device->id)
                ->where('parameter', $parameter)
                ->whereNull('effective_until')
                ->where('effective_from', '<=', $now)
                ->update(['effective_until' => $now]);

            $profile = SensorCalibrationProfile::create([
                'device_id' => $device->id,
                'sensor_model' => $sensorModel,
                'firmware_version' => $device->firmware_version ?: 'ESP32-BTLP-v1.0',
                'profile_name' => $profileName,
                'version' => $newVersion,
                'parameter' => $parameter,
                'method' => $method,
                'parameters' => $parameters,
                'unit' => $paramSpec['unit'],
                'register_address' => $paramSpec['register_address'],
                'min_range' => $paramSpec['min_range'],
                'max_range' => $paramSpec['max_range'],
                'effective_from' => $now,
                'effective_until' => null,
                'created_by' => $user?->id,
                'reason' => $reason,
            ]);

            // Keep legacy offsets array synchronized for backwards compatibility with legacy UI cards
            if ($method === 'offset') {
                $currentOffsets = $device->calibration_offsets ?? [];
                $currentOffsets[$parameter] = (float) $parameters['offset'];
                $device->update(['calibration_offsets' => $currentOffsets]);
            }

            AuditLog::create([
                'user_id' => $user?->id,
                'actor_name' => $user?->name ?? 'System',
                'actor_role' => $user?->role ?? 'system',
                'action' => 'Kalibrasi Sensor Profil',
                'details' => "Aktivasi profil kalibrasi v{$newVersion} ({$profileName}) untuk parameter {$parameter} pada {$device->code}. Alasan: {$reason}. Status downlink hardware: BLOCKED (menunggu spesifikasi firmware & transport).",
                'ip_address' => request()?->ip() ?? '127.0.0.1',
            ]);

            return $profile;
        });
    }
}
