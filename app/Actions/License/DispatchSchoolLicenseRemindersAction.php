<?php

namespace App\Actions\License;

use App\Jobs\SendSchoolLicenseReminderJob;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseNotification;
use App\Models\User;
use App\Services\License\SchoolLicenseResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchSchoolLicenseRemindersAction
{
    protected SchoolLicenseResolver $resolver;

    public function __construct(SchoolLicenseResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Dispatch H-14 license expiration reminders to school administrators.
     *
     * @param  int  $targetDays  Days remaining threshold (default: 14)
     * @param  bool  $dryRun  If true, simulates without creating DB records or dispatching jobs
     * @return array Summary of dispatch operations
     */
    public function execute(int $targetDays = 14, bool $dryRun = false): array
    {
        $today = Carbon::today('Asia/Jakarta');
        $targetExpirationDate = $today->copy()->addDays($targetDays)->toDateString();

        $remindersEnabled = (bool) config('license.reminders_enabled', false);

        if (! $remindersEnabled && ! $dryRun) {
            Log::info('Pengingat email otomatis lisensi dinonaktifkan (LICENSE_REMINDERS_ENABLED=false). Pengiriman email nyata ditangguhkan.');
        }

        if (empty(config('license.support_contact.email')) && app()->environment('production')) {
            Log::warning('Konfigurasi deployment belum lengkap: LICENSE_SUPPORT_EMAIL tidak didefinisikan pada lingkungan produksi.');
        }

        $licenses = SchoolLicense::with(['school', 'terms'])
            ->where(function ($query) use ($targetExpirationDate) {
                $query->whereDate('expiration_date', $targetExpirationDate)
                    ->orWhereHas('terms', function ($q) use ($targetExpirationDate) {
                        $q->whereDate('expiration_date', $targetExpirationDate);
                    });
            })
            ->get();

        $summary = [
            'target_days' => $targetDays,
            'target_date' => $targetExpirationDate,
            'dry_run' => $dryRun,
            'reminders_enabled' => $remindersEnabled,
            'found_licenses' => $licenses->count(),
            'queued' => 0,
            'skipped_existing' => 0,
            'skipped_no_admins' => 0,
            'skipped_not_active' => 0,
            'skipped_disabled' => 0,
            'details' => [],
        ];

        foreach ($licenses as $license) {
            $resolution = $this->resolver->resolve($license, $today);

            // Re-verify that the license is indeed active on $today and expires exactly in $targetDays
            if ($resolution['status'] !== SchoolLicense::STATUS_ACTIVE) {
                $summary['skipped_not_active']++;
                $summary['details'][] = [
                    'license_number' => $license->license_number,
                    'school' => $license->school?->name,
                    'status' => 'skipped',
                    'reason' => "Lisensi berstatus {$resolution['status']}, bukan active.",
                ];

                continue;
            }

            if ($resolution['days_remaining'] !== $targetDays) {
                $summary['skipped_not_active']++;
                $summary['details'][] = [
                    'license_number' => $license->license_number,
                    'school' => $license->school?->name,
                    'status' => 'skipped',
                    'reason' => "Sisa hari ({$resolution['days_remaining']}) tidak tepat {$targetDays} hari.",
                ];

                continue;
            }

            $school = $license->school;
            if (! $school) {
                continue;
            }

            $activeTerm = $resolution['active_term'];

            // Find genuine active school administrators (admin_sekolah, legacy admin)
            $admins = User::query()
                ->where('school_id', $school->id)
                ->whereIn('role', [User::ROLE_ADMIN_SEKOLAH, User::ROLE_ADMIN])
                ->where('is_active', true)
                ->whereNotNull('email')
                ->get();

            if ($admins->isEmpty()) {
                $summary['skipped_no_admins']++;
                $summary['details'][] = [
                    'license_number' => $license->license_number,
                    'school' => $school->name,
                    'status' => 'skipped',
                    'reason' => 'Tidak ditemukan akun admin_sekolah aktif untuk sekolah ini.',
                ];
                Log::warning('H-14 reminder skipped: no active admin found for school', [
                    'school_id' => $school->id,
                    'school_name' => $school->name,
                    'license_number' => $license->license_number,
                ]);

                continue;
            }

            foreach ($admins as $admin) {
                if (! $remindersEnabled && ! $dryRun) {
                    $summary['skipped_disabled']++;
                    $summary['details'][] = [
                        'license_number' => $license->license_number,
                        'school' => $school->name,
                        'recipient' => $admin->email,
                        'status' => 'skipped_disabled',
                        'reason' => 'Pengingat dinonaktifkan oleh flag konfigurasi LICENSE_REMINDERS_ENABLED=false.',
                    ];

                    continue;
                }

                DB::transaction(function () use (
                    $license,
                    $activeTerm,
                    $school,
                    $admin,
                    $today,
                    $resolution,
                    $dryRun,
                    &$summary
                ) {
                    // Idempotency check with row-lock: notification record for this license, term, type, and recipient
                    $existing = SchoolLicenseNotification::where('license_id', $license->id)
                        ->where('term_id', $activeTerm?->id)
                        ->where('notification_type', SchoolLicenseNotification::TYPE_REMINDER_H14)
                        ->where('recipient_email', $admin->email)
                        ->lockForUpdate()
                        ->first();

                    if ($existing && ($existing->isSent() || $existing->isQueued() || $existing->isProcessing() || $existing->isSkipped())) {
                        $summary['skipped_existing']++;
                        $summary['details'][] = [
                            'license_number' => $license->license_number,
                            'school' => $school->name,
                            'recipient' => $admin->email,
                            'status' => 'skipped',
                            'reason' => "Pengingat sudah pernah dibuat atau terkirim (ID: {$existing->id}, Status: {$existing->status}).",
                        ];

                        return;
                    }

                    if ($existing && $existing->isFailed() && $existing->attempts >= 3) {
                        $summary['skipped_existing']++;
                        $summary['details'][] = [
                            'license_number' => $license->license_number,
                            'school' => $school->name,
                            'recipient' => $admin->email,
                            'status' => 'skipped',
                            'reason' => "Pengingat gagal mencapai batas maksimal percobaan (ID: {$existing->id}).",
                        ];

                        return;
                    }

                    if ($dryRun) {
                        $summary['queued']++;
                        $summary['details'][] = [
                            'license_number' => $license->license_number,
                            'school' => $school->name,
                            'recipient' => $admin->email,
                            'status' => 'simulated_queue',
                        ];

                        return;
                    }

                    // Create or re-queue notification atomically
                    $notification = SchoolLicenseNotification::updateOrCreate(
                        [
                            'license_id' => $license->id,
                            'term_id' => $activeTerm?->id,
                            'notification_type' => SchoolLicenseNotification::TYPE_REMINDER_H14,
                            'recipient_email' => $admin->email,
                        ],
                        [
                            'school_id' => $school->id,
                            'recipient_name' => $admin->name,
                            'channel' => SchoolLicenseNotification::CHANNEL_MAIL,
                            'status' => SchoolLicenseNotification::STATUS_QUEUED,
                            'scheduled_for' => $today,
                            'claimed_at' => null,
                            'sent_at' => null,
                            'error_message' => null,
                            'payload' => [
                                'days_remaining' => $resolution['days_remaining'],
                                'expiration_date' => $activeTerm?->expiration_date?->toDateString() ?? $license->expiration_date?->toDateString(),
                                'school_name' => $school->name,
                                'license_number' => $license->license_number,
                            ],
                        ]
                    );

                    SendSchoolLicenseReminderJob::dispatch($notification);
                    $summary['queued']++;
                    $summary['details'][] = [
                        'license_number' => $license->license_number,
                        'school' => $school->name,
                        'recipient' => $admin->email,
                        'status' => 'queued',
                        'notification_id' => $notification->id,
                    ];
                });
            }
        }

        return $summary;
    }
}
