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
     * Record a sale or transfer of a trainer kit to a specific school.
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

        $saleRef = trim($data['sale_reference'] ?? '');
        if (empty($saleRef)) {
            throw ValidationException::withMessages([
                'sale_reference' => 'Nomor referensi penjualan / pesanan / BAST wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($inventory, $school, $saleRef, $data) {
            /** @var TrainerKitInventory $lockedInventory */
            $lockedInventory = TrainerKitInventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedInventory->status, [TrainerKitInventory::STATUS_DECOMMISSIONED])) {
                throw ValidationException::withMessages([
                    'status' => 'Unit dengan status telah dipensiunkan (decommissioned) tidak dapat dijual kembali.',
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

            // If transferring to a different school and device was assigned, unbind the old school device
            $fromDeviceId = $lockedInventory->device_id;
            $toDeviceId = $lockedInventory->device_id;
            if ($fromSchoolId !== null && $fromSchoolId !== $school->id) {
                $toDeviceId = null;
                $lockedInventory->device_id = null;
            }

            // Determine new status
            $newStatus = $lockedInventory->status;
            if ($newStatus === TrainerKitInventory::STATUS_REGISTERED) {
                $newStatus = TrainerKitInventory::STATUS_SOLD;
            }

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
                'from_device_id' => $fromDeviceId,
                'to_device_id' => $toDeviceId,
                'details' => "Penjualan / penetapan kepemilikan dicatat untuk sekolah: {$school->name} (Ref: {$saleRef})",
                'created_at' => now(),
            ]);

            return $lockedInventory;
        });
    }
}
