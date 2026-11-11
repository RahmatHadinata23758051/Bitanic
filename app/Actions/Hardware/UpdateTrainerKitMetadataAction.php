<?php

namespace App\Actions\Hardware;

use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTrainerKitMetadataAction
{
    /**
     * Update metadata or status for a trainer kit inventory item.
     *
     * @param array{
     *     model_name?: string,
     *     hardware_revision?: string|null,
     *     batch_number?: string|null,
     *     notes?: string|null,
     *     status?: string,
     *     user_id?: int|null,
     * } $data
     */
    public function execute(TrainerKitInventory $inventory, array $data): TrainerKitInventory
    {
        return DB::transaction(function () use ($inventory, $data) {
            /** @var TrainerKitInventory $lockedInventory */
            $lockedInventory = TrainerKitInventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            $fromStatus = $lockedInventory->status;
            $newStatus = $data['status'] ?? $lockedInventory->status;

            $validStatuses = [
                TrainerKitInventory::STATUS_REGISTERED,
                TrainerKitInventory::STATUS_SOLD,
                TrainerKitInventory::STATUS_ASSIGNED,
                TrainerKitInventory::STATUS_ACTIVATED,
                TrainerKitInventory::STATUS_MAINTENANCE,
                TrainerKitInventory::STATUS_DECOMMISSIONED,
            ];

            if (! in_array($newStatus, $validStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => "Status '{$newStatus}' tidak valid.",
                ]);
            }

            if (isset($data['model_name'])) {
                $lockedInventory->model_name = $data['model_name'];
            }
            if (array_key_exists('hardware_revision', $data)) {
                $lockedInventory->hardware_revision = $data['hardware_revision'];
            }
            if (array_key_exists('batch_number', $data)) {
                $lockedInventory->batch_number = $data['batch_number'];
            }
            if (array_key_exists('notes', $data)) {
                $lockedInventory->notes = $data['notes'];
            }

            $statusChanged = ($newStatus !== $fromStatus);
            $lockedInventory->status = $newStatus;
            $lockedInventory->save();

            $userId = $data['user_id'] ?? auth()->id();

            if ($statusChanged) {
                TrainerKitLifecycleLog::create([
                    'inventory_id' => $lockedInventory->id,
                    'user_id' => $userId,
                    'action' => 'STATUS_UPDATED',
                    'from_status' => $fromStatus,
                    'to_status' => $newStatus,
                    'from_school_id' => $lockedInventory->school_id,
                    'to_school_id' => $lockedInventory->school_id,
                    'from_device_id' => $lockedInventory->device_id,
                    'to_device_id' => $lockedInventory->device_id,
                    'details' => "Status unit diperbarui dari {$fromStatus} menjadi {$newStatus}.",
                    'created_at' => now(),
                ]);
            } else {
                TrainerKitLifecycleLog::create([
                    'inventory_id' => $lockedInventory->id,
                    'user_id' => $userId,
                    'action' => 'METADATA_UPDATED',
                    'from_status' => $fromStatus,
                    'to_status' => $fromStatus,
                    'from_school_id' => $lockedInventory->school_id,
                    'to_school_id' => $lockedInventory->school_id,
                    'from_device_id' => $lockedInventory->device_id,
                    'to_device_id' => $lockedInventory->device_id,
                    'details' => 'Metadata spesifikasi perangkat diperbarui.',
                    'created_at' => now(),
                ]);
            }

            return $lockedInventory;
        });
    }
}
