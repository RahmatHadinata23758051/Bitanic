<?php

namespace App\Actions\License;

use App\Models\AuditLog;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorrectSchoolLicenseDatesAction
{
    /**
     * Correct school LMS license commencement dates with mandatory audit reason.
     * Recalculates exact 1 calendar year expiration date and persists immutable history.
     *
     * @param array{
     *     start_date: string|\DateTimeInterface,
     *     correction_reason: string,
     *     recorded_by?: int|null,
     * } $data
     */
    public function execute(SchoolLicense $license, array $data): SchoolLicense
    {
        $reason = trim($data['correction_reason'] ?? '');
        if (empty($reason) || strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'correction_reason' => 'Alasan koreksi tanggal lisensi wajib diisi minimal 5 karakter untuk jejak audit.',
            ]);
        }

        if (empty($data['start_date'])) {
            throw ValidationException::withMessages([
                'start_date' => 'Tanggal mulai lisensi baru wajib diisi.',
            ]);
        }

        $newStartDate = Carbon::parse($data['start_date'])->startOfDay();
        // 1 calendar year computation with leap-year protection (Feb 29 -> Feb 28)
        $newExpirationDate = $newStartDate->copy()->addYearNoOverflow();

        return DB::transaction(function () use ($license, $newStartDate, $newExpirationDate, $reason, $data) {
            /** @var SchoolLicense $lockedLicense */
            $lockedLicense = SchoolLicense::where('id', $license->id)->lockForUpdate()->firstOrFail();

            $oldStartDate = $lockedLicense->start_date ? Carbon::parse($lockedLicense->start_date)->toDateString() : null;
            $oldExpirationDate = $lockedLicense->expiration_date ? Carbon::parse($lockedLicense->expiration_date)->toDateString() : null;
            $recordedBy = $data['recorded_by'] ?? auth()->id();

            $today = Carbon::today();
            $computedStatus = SchoolLicense::STATUS_ACTIVE;
            if ($today->lt($newStartDate)) {
                $computedStatus = SchoolLicense::STATUS_PENDING;
            } elseif ($today->gte($newExpirationDate)) {
                $computedStatus = SchoolLicense::STATUS_EXPIRED;
            }

            // 1. Update latest Term dates if exists
            $latestTerm = $lockedLicense->latestTerm;
            if ($latestTerm) {
                $latestTerm->start_date = $newStartDate->toDateString();
                $latestTerm->expiration_date = $newExpirationDate->toDateString();
                $latestTerm->status = $computedStatus;
                $latestTerm->save();
            }

            // 2. Update master license dates
            $lockedLicense->start_date = $newStartDate->toDateString();
            $lockedLicense->expiration_date = $newExpirationDate->toDateString();
            $lockedLicense->status = $computedStatus;
            $lockedLicense->last_authorized_by = $recordedBy;
            $lockedLicense->last_authorized_at = now();
            $lockedLicense->save();

            // 3. Persist immutable license log
            SchoolLicenseLog::create([
                'license_id' => $lockedLicense->id,
                'action' => SchoolLicenseLog::ACTION_DATES_CORRECTED,
                'user_id' => $recordedBy,
                'old_start_date' => $oldStartDate,
                'new_start_date' => $newStartDate->toDateString(),
                'old_expiration_date' => $oldExpirationDate,
                'new_expiration_date' => $newExpirationDate->toDateString(),
                'details' => "Koreksi tanggal lisensi dari [{$oldStartDate} - {$oldExpirationDate}] menjadi [{$newStartDate->toDateString()} - {$newExpirationDate->toDateString()}]. Alasan: {$reason}",
                'reason' => $reason,
                'created_at' => now(),
            ]);

            // 4. Audit Log
            $user = auth()->user();
            AuditLog::create([
                'user_id' => $recordedBy,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'SCHOOL_LICENSE_DATES_CORRECTED',
                'target_type' => SchoolLicense::class,
                'target_id' => $lockedLicense->id,
                'school_id' => $lockedLicense->school_id,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'diff' => [
                    'old_start_date' => $oldStartDate,
                    'new_start_date' => $newStartDate->toDateString(),
                    'old_expiration_date' => $oldExpirationDate,
                    'new_expiration_date' => $newExpirationDate->toDateString(),
                    'reason' => $reason,
                ],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $lockedLicense;
        });
    }
}
