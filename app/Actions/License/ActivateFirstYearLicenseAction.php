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

class ActivateFirstYearLicenseAction
{
    /**
     * Activate the initial 1-year LMS license entitlement for a school.
     * Enforces calendar year leap-year safety and date-only [start_date, expiration_date) convention.
     *
     * @param array{
     *     start_date: string|\DateTimeInterface,
     *     notes?: string|null,
     *     recorded_by?: int|null,
     * } $data
     */
    public function execute(SchoolLicense $license, array $data): SchoolLicense
    {
        if (empty($data['start_date'])) {
            throw ValidationException::withMessages([
                'start_date' => 'Tanggal efektif mulai lisensi tahun pertama wajib ditentukan.',
            ]);
        }

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        // Exact 1 calendar year with leap-year protection (Feb 29 -> Feb 28)
        $expirationDate = $startDate->copy()->addYearNoOverflow();

        return DB::transaction(function () use ($license, $startDate, $expirationDate, $data) {
            /** @var SchoolLicense $lockedLicense */
            $lockedLicense = SchoolLicense::where('id', $license->id)->lockForUpdate()->firstOrFail();

            // 1. Idempotency and double-activation validation
            $existingTerm = SchoolLicenseTerm::where('license_id', $lockedLicense->id)
                ->where('term_number', 1)
                ->first();

            if ($existingTerm) {
                if ($existingTerm->start_date->toDateString() === $startDate->toDateString()) {
                    return $lockedLicense;
                }

                throw ValidationException::withMessages([
                    'start_date' => 'Lisensi sekolah telah memiliki penetapan hak akses tahun pertama. Gunakan fitur koreksi tanggal atau perpanjangan tahunan.',
                ]);
            }

            $recordedBy = $data['recorded_by'] ?? auth()->id();
            $today = Carbon::today();
            $computedStatus = SchoolLicense::STATUS_ACTIVE;
            if ($today->lt($startDate)) {
                $computedStatus = SchoolLicense::STATUS_PENDING;
            } elseif ($today->gte($expirationDate)) {
                $computedStatus = SchoolLicense::STATUS_EXPIRED;
            }

            // 2. Create Term 1 (First-Year Bundling Entitlement)
            SchoolLicenseTerm::create([
                'license_id' => $lockedLicense->id,
                'term_number' => 1,
                'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
                'start_date' => $startDate->toDateString(),
                'expiration_date' => $expirationDate->toDateString(),
                'status' => $computedStatus,
                'authorized_by' => $recordedBy,
                'notes' => $data['notes'] ?? 'Aktivasi hak akses lisensi tahun pertama (Bundling Trainer Kit).',
                'created_at' => now(),
            ]);

            // 3. Update canonical license record
            $lockedLicense->start_date = $startDate->toDateString();
            $lockedLicense->expiration_date = $expirationDate->toDateString();
            $lockedLicense->status = $computedStatus;
            $lockedLicense->entitlement_type = SchoolLicense::ENTITLEMENT_FIRST_YEAR;
            $lockedLicense->last_authorized_by = $recordedBy;
            $lockedLicense->last_authorized_at = now();
            if (! empty($data['notes'])) {
                $lockedLicense->notes = $data['notes'];
            }
            $lockedLicense->save();

            // 4. Log in append-only history
            SchoolLicenseLog::create([
                'license_id' => $lockedLicense->id,
                'action' => SchoolLicenseLog::ACTION_ACTIVATED,
                'user_id' => $recordedBy,
                'old_start_date' => null,
                'new_start_date' => $startDate->toDateString(),
                'old_expiration_date' => null,
                'new_expiration_date' => $expirationDate->toDateString(),
                'details' => "Aktivasi hak akses lisensi tahun pertama berlaku {$startDate->toDateString()} s.d. {$expirationDate->toDateString()} (Status: {$computedStatus}).",
                'reason' => 'Persetujuan resmi hak akses lisensi LMS tahun pertama oleh Superuser.',
                'created_at' => now(),
            ]);

            // 5. Audit Log
            $user = auth()->user();
            AuditLog::create([
                'user_id' => $recordedBy,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'SCHOOL_LICENSE_ACTIVATED',
                'target_type' => SchoolLicense::class,
                'target_id' => $lockedLicense->id,
                'school_id' => $lockedLicense->school_id,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'diff' => [
                    'license_number' => $lockedLicense->license_number,
                    'start_date' => $startDate->toDateString(),
                    'expiration_date' => $expirationDate->toDateString(),
                    'status' => $computedStatus,
                ],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $lockedLicense;
        });
    }
}
