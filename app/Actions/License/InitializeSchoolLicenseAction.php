<?php

namespace App\Actions\License;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InitializeSchoolLicenseAction
{
    /**
     * Initialize canonical LMS license record for a school in pending status.
     * Enforces One School = One LMS License contract and idempotent execution.
     *
     * @param array{
     *     notes?: string|null,
     *     recorded_by?: int|null,
     * } $data
     */
    public function execute(School $school, array $data = []): SchoolLicense
    {
        if ($school->status !== 'active') {
            throw ValidationException::withMessages([
                'school_id' => 'Inisialisasi lisensi LMS hanya dapat dilakukan untuk sekolah berstatus aktif.',
            ]);
        }

        return DB::transaction(function () use ($school, $data) {
            /** @var School $lockedSchool */
            $lockedSchool = School::where('id', $school->id)->lockForUpdate()->firstOrFail();

            // 1. Idempotency Check: return existing license if already created
            $existingLicense = SchoolLicense::where('school_id', $lockedSchool->id)->lockForUpdate()->first();
            if ($existingLicense) {
                return $existingLicense;
            }

            $recordedBy = $data['recorded_by'] ?? auth()->id();
            $baseNumber = 'LIC-'.($lockedSchool->npsn ?: str_pad((string) $lockedSchool->id, 5, '0', STR_PAD_LEFT)).'-'.date('Y');
            $licenseNumber = $baseNumber;
            $counter = 1;
            while (SchoolLicense::where('license_number', $licenseNumber)->exists()) {
                $licenseNumber = $baseNumber.'-'.Str::upper(Str::random(3));
                $counter++;
                if ($counter > 10) {
                    $licenseNumber = 'LIC-'.date('Y').'-'.Str::upper(Str::random(8));
                    break;
                }
            }

            // 2. Create canonical license record in pending status
            $license = SchoolLicense::create([
                'school_id' => $lockedSchool->id,
                'license_number' => $licenseNumber,
                'status' => SchoolLicense::STATUS_PENDING,
                'entitlement_type' => SchoolLicense::ENTITLEMENT_FIRST_YEAR,
                'start_date' => null,
                'expiration_date' => null,
                'notes' => $data['notes'] ?? 'Inisialisasi registri lisensi LMS sekolah.',
                'last_authorized_by' => $recordedBy,
                'last_authorized_at' => now(),
            ]);

            // 3. Log initial creation in append-only license log
            SchoolLicenseLog::create([
                'license_id' => $license->id,
                'action' => SchoolLicenseLog::ACTION_INITIALIZED,
                'user_id' => $recordedBy,
                'old_start_date' => null,
                'new_start_date' => null,
                'old_expiration_date' => null,
                'new_expiration_date' => null,
                'details' => "Inisialisasi lisensi LMS sekolah {$lockedSchool->name} dengan nomor {$licenseNumber}.",
                'reason' => 'Pendaftaran awal entitas sekolah ke registri lisensi Bitanic.',
                'created_at' => now(),
            ]);

            // 4. Audit Log
            $user = auth()->user();
            AuditLog::create([
                'user_id' => $recordedBy,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'SCHOOL_LICENSE_INITIALIZED',
                'target_type' => SchoolLicense::class,
                'target_id' => $license->id,
                'school_id' => $lockedSchool->id,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'diff' => [
                    'license_number' => $licenseNumber,
                    'status' => SchoolLicense::STATUS_PENDING,
                    'school_id' => $lockedSchool->id,
                ],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $license;
        });
    }
}
