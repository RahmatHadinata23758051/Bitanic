<?php

namespace App\Actions\Hardware;

use App\Models\School;
use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use App\Models\TrainerKitSaleRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordTrainerKitSaleAction
{
    /**
     * Record a sale of a trainer kit from factory inventory to a specific school.
     * Enforces DEVICE-SALE-STATE-01: prevents unauthorized resale/transfer,
     * rejects double assignment, and supports idempotent retries.
     *
     * @param array{
     *     school_id: int,
     *     sale_reference: string,
     *     sold_at?: string|\DateTimeInterface|null,
     *     sold_by?: int|null,
     *     notes?: string|null,
     * } $data
     */
    public function execute(TrainerKitInventory $inventory, array $data): TrainerKitInventory
    {
        $schoolId = (int) ($data['school_id'] ?? 0);
        $school = School::find($schoolId);
        if (! $school) {
            throw ValidationException::withMessages([
                'school_id' => 'Sekolah penerima unit tidak ditemukan.',
            ]);
        }

        if ($school->status !== 'active') {
            throw ValidationException::withMessages([
                'school_id' => 'Sekolah penerima berstatus tidak aktif. Unit hanya dapat dialokasikan ke sekolah yang aktif.',
            ]);
        }

        $saleRef = trim($data['sale_reference'] ?? '');
        if (empty($saleRef)) {
            throw ValidationException::withMessages([
                'sale_reference' => 'Nomor referensi penjualan / pesanan / BAST wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($inventory, $school, $saleRef, $data) {
            /** @var TrainerKitInventory $lockedInventory */
            $lockedInventory = TrainerKitInventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            // Check if unit is already sold, assigned, activated, or decommissioned
            if ($lockedInventory->status !== TrainerKitInventory::STATUS_REGISTERED) {
                // Check for idempotent retry: same school and same sale reference
                if ($lockedInventory->school_id === $school->id) {
                    $existingSale = TrainerKitSaleRecord::where('inventory_id', $lockedInventory->id)
                        ->where('sale_reference', $saleRef)
                        ->first();
                    if ($existingSale) {
                        return $lockedInventory;
                    }
                }

                throw ValidationException::withMessages([
                    'status' => 'Unit Trainer Kit sudah memiliki status '.$lockedInventory->status.' (resale atau transfer kepemilikan antar-sekolah belum didukung).',
                ]);
            }

            $fromStatus = $lockedInventory->status;
            $fromSchoolId = $lockedInventory->school_id;
            $soldAt = ! empty($data['sold_at']) ? Carbon::parse($data['sold_at']) : now();
            $soldBy = $data['sold_by'] ?? auth()->id();

            // Record sale record
            TrainerKitSaleRecord::create([
                'inventory_id' => $lockedInventory->id,
                'school_id' => $school->id,
                'sale_reference' => $saleRef,
                'sold_at' => $soldAt,
                'sold_by' => $soldBy,
                'notes' => $data['notes'] ?? null,
            ]);

            $newStatus = TrainerKitInventory::STATUS_SOLD;
            $lockedInventory->school_id = $school->id;
            $lockedInventory->status = $newStatus;
            $lockedInventory->save();

            TrainerKitLifecycleLog::create([
                'inventory_id' => $lockedInventory->id,
                'user_id' => $soldBy,
                'action' => 'SALE_RECORDED',
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'from_school_id' => $fromSchoolId,
                'to_school_id' => $school->id,
                'from_device_id' => null,
                'to_device_id' => null,
                'details' => "Penjualan / penetapan kepemilikan dicatat untuk sekolah: {$school->name} (Ref: {$saleRef})",
                'created_at' => now(),
            ]);

            return $lockedInventory;
        });
    }
}
