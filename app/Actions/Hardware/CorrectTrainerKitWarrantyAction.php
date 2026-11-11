<?php

namespace App\Actions\Hardware;

use App\Models\AuditLog;
use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use App\Models\TrainerKitWarrantyLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorrectTrainerKitWarrantyAction
{
    /**
     * Correct trainer kit warranty commencement date with mandatory audit reason.
     * Recalculates exact 1 calendar year expiration date and persists immutable history.
     *
     * @param array{
     *     warranty_start_date: string|\DateTimeInterface,
     *     correction_reason: string,
     *     recorded_by?: int|null,
     * } $data
     */
    public function execute(TrainerKitInventory $inventory, array $data): TrainerKitInventory
    {
        $reason = trim($data['correction_reason'] ?? '');
        if (empty($reason) || strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'correction_reason' => 'Alasan koreksi tanggal garansi wajib diisi minimal 5 karakter untuk jejak audit.',
            ]);
        }

        if (empty($data['warranty_start_date'])) {
            throw ValidationException::withMessages([
                'warranty_start_date' => 'Tanggal mulai garansi baru wajib diisi.',
            ]);
        }

        $newStartDate = Carbon::parse($data['warranty_start_date'])->startOfDay();
        // 1 calendar year computation with leap-year handling (Feb 29 -> Feb 28)
        $newExpirationDate = $newStartDate->copy()->addYearNoOverflow();

        return DB::transaction(function () use ($inventory, $newStartDate, $newExpirationDate, $reason, $data) {
            /** @var TrainerKitInventory $lockedInventory */
            $lockedInventory = TrainerKitInventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            // 1. Mandatory existing legitimate BAST / Handover record
            $handover = $lockedInventory->handover;
            if (! $handover) {
                throw ValidationException::withMessages([
                    'warranty_start_date' => 'Koreksi tanggal garansi hanya dapat dilakukan untuk unit yang telah memiliki Berita Acara Serah Terima (BAST).',
                ]);
            }

            // 2. Consistent owning school validation
            if (empty($lockedInventory->school_id) || (int) $lockedInventory->school_id !== (int) $handover->school_id) {
                throw ValidationException::withMessages([
                    'school_id' => 'Data kepemilikan sekolah pada unit tidak konsisten dengan Berita Acara Serah Terima (BAST).',
                ]);
            }

            $oldStartDate = $lockedInventory->warranty_start_date;
            $oldExpirationDate = $lockedInventory->warranty_expiration_date;
            $recordedBy = $data['recorded_by'] ?? auth()->id();

            // 3. Persist immutable warranty correction log
            TrainerKitWarrantyLog::create([
                'inventory_id' => $lockedInventory->id,
                'handover_id' => $handover->id,
                'user_id' => $recordedBy,
                'old_start_date' => $oldStartDate,
                'new_start_date' => $newStartDate->toDateString(),
                'old_expiration_date' => $oldExpirationDate,
                'new_expiration_date' => $newExpirationDate->toDateString(),
                'reason' => $reason,
                'created_at' => now(),
            ]);

            // 4. Update Handover
            $handover->warranty_start_date = $newStartDate->toDateString();
            $handover->warranty_expiration_date = $newExpirationDate->toDateString();
            $handover->save();

            // 5. Update Inventory dates
            $lockedInventory->warranty_start_date = $newStartDate->toDateString();
            $lockedInventory->warranty_expiration_date = $newExpirationDate->toDateString();
            $lockedInventory->save();

            // 4. Record lifecycle event
            $oldStartStr = $oldStartDate ? Carbon::parse($oldStartDate)->toDateString() : '—';
            TrainerKitLifecycleLog::create([
                'inventory_id' => $lockedInventory->id,
                'user_id' => $recordedBy,
                'action' => 'WARRANTY_CORRECTED',
                'from_status' => $lockedInventory->status,
                'to_status' => $lockedInventory->status,
                'from_school_id' => $lockedInventory->school_id,
                'to_school_id' => $lockedInventory->school_id,
                'from_device_id' => $lockedInventory->device_id,
                'to_device_id' => $lockedInventory->device_id,
                'details' => "Koreksi tanggal garansi dari {$oldStartStr} menjadi {$newStartDate->toDateString()} (Kedaluwarsa: {$newExpirationDate->toDateString()}). Alasan: {$reason}",
                'created_at' => now(),
            ]);

            // 5. Audit Log
            $user = auth()->user();
            AuditLog::create([
                'user_id' => $recordedBy,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'TRAINER_KIT_WARRANTY_CORRECTED',
                'target_type' => TrainerKitInventory::class,
                'target_id' => $lockedInventory->id,
                'school_id' => $lockedInventory->school_id,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'diff' => [
                    'old_start_date' => $oldStartStr,
                    'new_start_date' => $newStartDate->toDateString(),
                    'old_expiration_date' => $oldExpirationDate ? Carbon::parse($oldExpirationDate)->toDateString() : '—',
                    'new_expiration_date' => $newExpirationDate->toDateString(),
                    'reason' => $reason,
                ],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $lockedInventory;
        });
    }
}
