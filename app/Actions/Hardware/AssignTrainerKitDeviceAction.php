<?php

namespace App\Actions\Hardware;

use App\Models\Device;
use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignTrainerKitDeviceAction
{
    /**
     * Bind or unbind an inventory kit to an operational workstation device in the same school.
     *
     * @param array{
     *     device_id: int|null,
     *     user_id?: int|null,
     * } $data
     */
    public function execute(TrainerKitInventory $inventory, array $data): TrainerKitInventory
    {
        return DB::transaction(function () use ($inventory, $data) {
            /** @var TrainerKitInventory $lockedInventory */
            $lockedInventory = TrainerKitInventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            if (empty($lockedInventory->school_id)) {
                throw ValidationException::withMessages([
                    'school_id' => 'Unit trainer kit belum ditetapkan ke sekolah manapun. Harap catat penjualan/kepemilikan sekolah terlebih dahulu.',
                ]);
            }

            if ($lockedInventory->status === TrainerKitInventory::STATUS_DECOMMISSIONED) {
                throw ValidationException::withMessages([
                    'status' => 'Unit yang telah dipensiunkan tidak dapat dihubungkan ke workstation.',
                ]);
            }

            $deviceId = ! empty($data['device_id']) ? (int) $data['device_id'] : null;
            $fromDeviceId = $lockedInventory->device_id;
            $fromStatus = $lockedInventory->status;
            $userId = $data['user_id'] ?? auth()->id();

            if ($deviceId === null) {
                // Unbind device
                $lockedInventory->device_id = null;
                $newStatus = ($lockedInventory->status === TrainerKitInventory::STATUS_ASSIGNED)
                    ? TrainerKitInventory::STATUS_SOLD
                    : $lockedInventory->status;

                $lockedInventory->status = $newStatus;
                $lockedInventory->save();

                TrainerKitLifecycleLog::create([
                    'inventory_id' => $lockedInventory->id,
                    'user_id' => $userId,
                    'action' => 'DEVICE_UNASSIGNED',
                    'from_status' => $fromStatus,
                    'to_status' => $newStatus,
                    'from_school_id' => $lockedInventory->school_id,
                    'to_school_id' => $lockedInventory->school_id,
                    'from_device_id' => $fromDeviceId,
                    'to_device_id' => null,
                    'details' => 'Unit dilepas dari workstation.',
                    'created_at' => now(),
                ]);

                return $lockedInventory;
            }

            // Bind to a workstation
            /** @var Device|null $device */
            $device = Device::where('id', $deviceId)->lockForUpdate()->first();
            if (! $device) {
                throw ValidationException::withMessages([
                    'device_id' => 'Perangkat workstation tidak ditemukan.',
                ]);
            }

            // STRICT TENANCY CHECK: Device must belong to the exact same school
            if ((int) $device->school_id !== (int) $lockedInventory->school_id) {
                throw ValidationException::withMessages([
                    'device_id' => 'Perangkat workstation harus berada di sekolah yang sama dengan kepemilikan unit trainer kit.',
                ]);
            }

            // Check if device is already linked to another active kit
            $existingBoundKit = TrainerKitInventory::where('device_id', $device->id)
                ->where('id', '!=', $lockedInventory->id)
                ->whereIn('status', [
                    TrainerKitInventory::STATUS_ASSIGNED,
                    TrainerKitInventory::STATUS_ACTIVATED,
                ])
                ->first();

            if ($existingBoundKit) {
                throw ValidationException::withMessages([
                    'device_id' => "Perangkat workstation '{$device->name}' ({$device->code}) sudah terikat ke unit lain dengan nomor seri {$existingBoundKit->serial_number}.",
                ]);
            }

            // Determine status progression
            $newStatus = $lockedInventory->status;
            if ($newStatus === TrainerKitInventory::STATUS_SOLD) {
                $newStatus = TrainerKitInventory::STATUS_ASSIGNED;
            }

            $lockedInventory->device_id = $device->id;
            $lockedInventory->status = $newStatus;
            $lockedInventory->save();

            TrainerKitLifecycleLog::create([
                'inventory_id' => $lockedInventory->id,
                'user_id' => $userId,
                'action' => 'DEVICE_ASSIGNED',
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'from_school_id' => $lockedInventory->school_id,
                'to_school_id' => $lockedInventory->school_id,
                'from_device_id' => $fromDeviceId,
                'to_device_id' => $device->id,
                'details' => "Unit dihubungkan ke workstation: {$device->name} ({$device->code}).",
                'created_at' => now(),
            ]);

            return $lockedInventory;
        });
    }
}
