<?php

namespace App\Actions\Hardware;

use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterTrainerKitInventoryAction
{
    /**
     * Register a new physical trainer kit serial in the central inventory.
     *
     * @param array{
     *     serial_number: string,
     *     model_name?: string,
     *     hardware_revision?: string|null,
     *     batch_number?: string|null,
     *     notes?: string|null,
     *     registered_by?: int|null,
     * } $data
     */
    public function execute(array $data): TrainerKitInventory
    {
        $serial = trim($data['serial_number'] ?? '');
        if (empty($serial)) {
            throw ValidationException::withMessages([
                'serial_number' => 'Nomor seri trainer kit wajib diisi.',
            ]);
        }

        if (TrainerKitInventory::where('serial_number', $serial)->exists()) {
            throw ValidationException::withMessages([
                'serial_number' => "Nomor seri '{$serial}' sudah terdaftar dalam inventaris.",
            ]);
        }

        return DB::transaction(function () use ($data, $serial) {
            $inventory = TrainerKitInventory::create([
                'serial_number' => $serial,
                'model_name' => $data['model_name'] ?? 'Bitanic Trainer Kit v1.0',
                'hardware_revision' => $data['hardware_revision'] ?? 'rev1.0',
                'batch_number' => $data['batch_number'] ?? null,
                'status' => TrainerKitInventory::STATUS_REGISTERED,
                'school_id' => null,
                'device_id' => null,
                'registered_at' => now(),
                'registered_by' => $data['registered_by'] ?? auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            TrainerKitLifecycleLog::create([
                'inventory_id' => $inventory->id,
                'user_id' => $data['registered_by'] ?? auth()->id(),
                'action' => 'REGISTRATION',
                'from_status' => null,
                'to_status' => TrainerKitInventory::STATUS_REGISTERED,
                'from_school_id' => null,
                'to_school_id' => null,
                'from_device_id' => null,
                'to_device_id' => null,
                'details' => 'Unit didaftarkan ke inventaris pabrik / stok pusat Bitanic.',
                'created_at' => now(),
            ]);

            return $inventory;
        });
    }
}
