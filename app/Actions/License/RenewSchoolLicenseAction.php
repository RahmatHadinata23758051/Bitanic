<?php

namespace App\Actions\License;

use App\Models\AuditLog;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseLog;
use App\Models\SchoolLicenseTerm;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RenewSchoolLicenseAction
{
    /**
     * Extend LMS license through authorized annual renewal.
     * Appends an immutable term record, extends expiration date without overlapping, and preserves prior history.
     *
     * @param array{
     *     renewal_start_date?: string|\DateTimeInterface|null,
     *     notes?: string|null,
     *     recorded_by?: int|null,
     * } $data
     */
    public function execute(SchoolLicense $license, array $data = []): SchoolLicense
    {
        return DB::transaction(function () use ($license, $data) {
            /** @var SchoolLicense $lockedLicense */
            $lockedLicense = SchoolLicense::where('id', $license->id)->lockForUpdate()->firstOrFail();

            if (! $lockedLicense->expiration_date || $lockedLicense->terms()->count() === 0) {
                throw ValidationException::withMessages([
                    'license_id' => 'Perpanjangan hanya dapat dilakukan untuk lisensi yang telah aktif memiliki penetapan tahun pertama.',
                ]);
            }

            $currentExp = Carbon::parse($lockedLicense->expiration_date)->startOfDay();
            $today = Carbon::today();

            // Seamless chaining: if current expiration is in the future, start new term upon expiration.
            // If already expired, start renewal today unless explicit renewal_start_date is passed.
            if (! empty($data['renewal_start_date'])) {
                $termStartDate = Carbon::parse($data['renewal_start_date'])->startOfDay();
            } elseif ($currentExp->gt($today)) {
                $termStartDate = $currentExp->copy();
            } else {
                $termStartDate = $today->copy();
            }

            // Expiration date computation: 1 calendar year with leap-year safety
            $termExpirationDate = $termStartDate->copy()->addYearNoOverflow();

            $recordedBy = $data['recorded_by'] ?? auth()->id();
            $nextTermNumber = ((int) $lockedLicense->terms()->max('term_number')) + 1;

            $computedStatus = SchoolLicense::STATUS_ACTIVE;
            if ($today->lt($termStartDate) && $today->lt($currentExp)) {
                $computedStatus = SchoolLicense::STATUS_ACTIVE; // still active on current term
            } elseif ($today->lt($termStartDate)) {
                $computedStatus = SchoolLicense::STATUS_PENDING;
            } elseif ($today->gte($termExpirationDate)) {
                $computedStatus = SchoolLicense::STATUS_EXPIRED;
            }

            // 1. Create new append-only term
            SchoolLicenseTerm::create([
                'license_id' => $lockedLicense->id,
                'term_number' => $nextTermNumber,
                'term_type' => SchoolLicenseTerm::TYPE_ANNUAL_RENEWAL,
                'start_date' => $termStartDate->toDateString(),
                'expiration_date' => $termExpirationDate->toDateString(),
                'status' => $today->gte($termStartDate) && $today->lt($termExpirationDate) ? 'active' : ($today->lt($termStartDate) ? 'pending' : 'expired'),
                'authorized_by' => $recordedBy,
                'notes' => $data['notes'] ?? "Perpanjangan tahunan ke-{$nextTermNumber} oleh Superuser.",
                'created_at' => now(),
            ]);

            $oldExpStr = $lockedLicense->expiration_date->toDateString();

            // 2. Extend master license expiration
            $lockedLicense->expiration_date = $termExpirationDate->toDateString();
            $lockedLicense->status = $computedStatus;
            $lockedLicense->last_authorized_by = $recordedBy;
            $lockedLicense->last_authorized_at = now();
            if (! empty($data['notes'])) {
                $lockedLicense->notes = $data['notes'];
            }
            $lockedLicense->save();

            // 3. Log history
            SchoolLicenseLog::create([
                'license_id' => $lockedLicense->id,
                'action' => SchoolLicenseLog::ACTION_RENEWED,
                'user_id' => $recordedBy,
                'old_start_date' => $lockedLicense->start_date ? Carbon::parse($lockedLicense->start_date)->toDateString() : null,
                'new_start_date' => $lockedLicense->start_date ? Carbon::parse($lockedLicense->start_date)->toDateString() : null,
                'old_expiration_date' => $oldExpStr,
                'new_expiration_date' => $termExpirationDate->toDateString(),
                'details' => "Perpanjangan tahunan ke-{$nextTermNumber} efektif {$termStartDate->toDateString()} s.d. {$termExpirationDate->toDateString()}.",
                'reason' => $data['notes'] ?? 'Otorisasi perpanjangan tahunan lisensi LMS oleh Superuser.',
                'created_at' => now(),
            ]);

            // 4. Audit Log
            $user = auth()->user();
            AuditLog::create([
                'user_id' => $recordedBy,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'SCHOOL_LICENSE_RENEWED',
                'target_type' => SchoolLicense::class,
                'target_id' => $lockedLicense->id,
                'school_id' => $lockedLicense->school_id,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'diff' => [
                    'term_number' => $nextTermNumber,
                    'old_expiration_date' => $oldExpStr,
                    'new_expiration_date' => $termExpirationDate->toDateString(),
                    'term_start_date' => $termStartDate->toDateString(),
                ],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $lockedLicense;
        });
    }
}
