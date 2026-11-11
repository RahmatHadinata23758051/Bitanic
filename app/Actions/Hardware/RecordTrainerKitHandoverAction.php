<?php

namespace App\Actions\Hardware;

use App\Models\AuditLog;
use App\Models\TrainerKitHandover;
use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordTrainerKitHandoverAction
{
    /**
     * Record physical trainer kit handover (BAST) and configure the 1-year hardware warranty.
     *
     * @param array{
     *     bast_number: string,
     *     handover_date: string|\DateTimeInterface,
     *     recipient_name: string,
     *     recipient_title?: string|null,
     *     recipient_phone?: string|null,
     *     start_warranty_now?: bool,
     *     warranty_start_date?: string|\DateTimeInterface|null,
     *     notes?: string|null,
     *     recorded_by?: int|null,
     * } $data
     */
    public function execute(TrainerKitInventory $inventory, array $data): TrainerKitHandover
    {
        $bastNumber = trim($data['bast_number'] ?? '');
        if (empty($bastNumber)) {
            throw ValidationException::withMessages([
                'bast_number' => 'Nomor Berita Acara Serah Terima (BAST) wajib diisi.',
            ]);
        }

        if (empty($data['handover_date'])) {
            throw ValidationException::withMessages([
                'handover_date' => 'Tanggal serah terima fisik unit wajib diisi.',
            ]);
        }

        $handoverDate = Carbon::parse($data['handover_date'])->startOfDay();
        if ($handoverDate->isFuture()) {
            throw ValidationException::withMessages([
                'handover_date' => 'Tanggal serah terima tidak boleh di masa depan.',
            ]);
        }

        $recipientName = trim($data['recipient_name'] ?? '');
        if (empty($recipientName)) {
            throw ValidationException::withMessages([
                'recipient_name' => 'Nama pihak penerima unit di sekolah wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($inventory, $bastNumber, $handoverDate, $recipientName, $data) {
            /** @var TrainerKitInventory $lockedInventory */
            $lockedInventory = TrainerKitInventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            // 1. Legitimate school ownership validation
            if (empty($lockedInventory->school_id)) {
                throw ValidationException::withMessages([
                    'school_id' => 'Perangkat belum memiliki penetapan kepemilikan sekolah (stok pabrik belum terjual).',
                ]);
            }

            if (in_array($lockedInventory->status, [TrainerKitInventory::STATUS_REGISTERED, TrainerKitInventory::STATUS_DECOMMISSIONED])) {
                throw ValidationException::withMessages([
                    'status' => 'Serah terima hanya dapat dicatat untuk unit yang telah terjual ke sekolah.',
                ]);
            }

            // 2. Duplicate handover prevention
            if (TrainerKitHandover::where('inventory_id', $lockedInventory->id)->exists()) {
                throw ValidationException::withMessages([
                    'bast_number' => 'Berita Acara Serah Terima (BAST) untuk unit ini sudah pernah tercatat.',
                ]);
            }

            $recordedBy = $data['recorded_by'] ?? auth()->id();
            $startWarranty = $data['start_warranty_now'] ?? true;

            $warrantyStartDate = null;
            $warrantyExpirationDate = null;

            if ($startWarranty) {
                $rawStart = ! empty($data['warranty_start_date']) ? $data['warranty_start_date'] : $handoverDate;
                $warrantyStartDate = Carbon::parse($rawStart)->startOfDay();
                // 1 calendar year calculation with leap-year handling (Feb 29 -> Feb 28)
                $warrantyExpirationDate = $warrantyStartDate->copy()->addYearNoOverflow();
            }

            // 3. Create Handover record
            $handover = TrainerKitHandover::create([
                'inventory_id' => $lockedInventory->id,
                'school_id' => $lockedInventory->school_id,
                'bast_number' => $bastNumber,
                'handover_date' => $handoverDate->toDateString(),
                'recipient_name' => $recipientName,
                'recipient_title' => $data['recipient_title'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'warranty_start_date' => $warrantyStartDate?->toDateString(),
                'warranty_expiration_date' => $warrantyExpirationDate?->toDateString(),
                'recorded_by' => $recordedBy,
                'notes' => $data['notes'] ?? null,
            ]);

            // 4. Update Inventory cache dates
            $lockedInventory->handover_date = $handoverDate->toDateString();
            $lockedInventory->warranty_start_date = $warrantyStartDate?->toDateString();
            $lockedInventory->warranty_expiration_date = $warrantyExpirationDate?->toDateString();
            $lockedInventory->save();

            // 5. Append-only lifecycle logs
            TrainerKitLifecycleLog::create([
                'inventory_id' => $lockedInventory->id,
                'user_id' => $recordedBy,
                'action' => 'HANDOVER_RECORDED',
                'from_status' => $lockedInventory->status,
                'to_status' => $lockedInventory->status,
                'from_school_id' => $lockedInventory->school_id,
                'to_school_id' => $lockedInventory->school_id,
                'from_device_id' => $lockedInventory->device_id,
                'to_device_id' => $lockedInventory->device_id,
                'details' => "BAST fisik dicatat: {$bastNumber} (Penerima: {$recipientName}).",
                'created_at' => now(),
            ]);

            if ($warrantyStartDate && $warrantyExpirationDate) {
                TrainerKitLifecycleLog::create([
                    'inventory_id' => $lockedInventory->id,
                    'user_id' => $recordedBy,
                    'action' => 'WARRANTY_COMMENCED',
                    'from_status' => $lockedInventory->status,
                    'to_status' => $lockedInventory->status,
                    'from_school_id' => $lockedInventory->school_id,
                    'to_school_id' => $lockedInventory->school_id,
                    'from_device_id' => $lockedInventory->device_id,
                    'to_device_id' => $lockedInventory->device_id,
                    'details' => "Garansi hardware 1 tahun resmi aktif mulai {$warrantyStartDate->toDateString()} s.d. {$warrantyExpirationDate->toDateString()} (Ref BAST: {$bastNumber}).",
                    'created_at' => now(),
                ]);
            }

            // 6. Audit Trail
            $user = auth()->user();
            AuditLog::create([
                'user_id' => $recordedBy,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'TRAINER_KIT_HANDOVER_RECORDED',
                'target_type' => TrainerKitInventory::class,
                'target_id' => $lockedInventory->id,
                'school_id' => $lockedInventory->school_id,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'diff' => [
                    'bast_number' => $bastNumber,
                    'handover_date' => $handoverDate->toDateString(),
                    'recipient' => $recipientName,
                    'warranty_start' => $warrantyStartDate?->toDateString(),
                    'warranty_expiration' => $warrantyExpirationDate?->toDateString(),
                ],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $handover;
        });
    }
}
