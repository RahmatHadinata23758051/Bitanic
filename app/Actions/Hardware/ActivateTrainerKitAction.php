<?php

namespace App\Actions\Hardware;

use App\Models\Device;
use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateTrainerKitAction
{
    /**
     * Activate a trainer kit for school deployment and pedagogical IoT operations.
     * Idempotent: activating an already activated kit succeeds cleanly without duplicating state.
     *
     * @param array{
     *     user_id?: int|null,
     *     notes?: string|null,
     * } $data
     */
    public function execute(TrainerKitInventory $inventory, array $data = []): TrainerKitInventory
    {
        return DB::transaction(function () use ($inventory, $data) {
            /** @var TrainerKitInventory $lockedInventory */
            $lockedInventory = TrainerKitInventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            if (empty($lockedInventory->school_id)) {
                throw ValidationException::withMessages([
                    'school_id' => 'Unit trainer kit belum memiliki kepemilikan sekolah. Tidak dapat diaktivasi.',
                ]);
            }

            if ($lockedInventory->status === TrainerKitInventory::STATUS_DECOMMISSIONED) {
                throw ValidationException::withMessages([
                    'status' => 'Unit yang telah dipensiunkan tidak dapat diaktivasi.',
                ]);
            }

            // IDEMPOTENCY: If already activated, return immediately without duplicate lifecycle logs
            if ($lockedInventory->status === TrainerKitInventory::STATUS_ACTIVATED) {
                return $lockedInventory;
            }

            $fromStatus = $lockedInventory->status;
            $userId = $data['user_id'] ?? auth()->id();

            $lockedInventory->status = TrainerKitInventory::STATUS_ACTIVATED;
            if (! empty($data['notes'])) {
                $lockedInventory->notes = ($lockedInventory->notes ? $lockedInventory->notes."\n" : '').$data['notes'];
            }
            $lockedInventory->save();

            // If a workstation device is bound, mark device active in platform registry
            if ($lockedInventory->device_id) {
                Device::where('id', $lockedInventory->device_id)->update([
                    'is_active' => true,
                ]);
            }

            TrainerKitLifecycleLog::create([
                'inventory_id' => $lockedInventory->id,
                'user_id' => $userId,
                'action' => 'ACTIVATED',
                'from_status' => $fromStatus,
                'to_status' => TrainerKitInventory::STATUS_ACTIVATED,
                'from_school_id' => $lockedInventory->school_id,
                'to_school_id' => $lockedInventory->school_id,
                'from_device_id' => $lockedInventory->device_id,
                'to_device_id' => $lockedInventory->device_id,
                'details' => 'Aktivasi unit di registri platform Bitanic berhasil (stok/workstation siap operasional pembelajaran).',
                'created_at' => now(),
            ]);

            return $lockedInventory;
        });
    }
}
