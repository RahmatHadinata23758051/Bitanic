<?php

namespace App\Jobs;

use App\Mail\SchoolLicenseExpirationReminderMail;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseLog;
use App\Models\SchoolLicenseNotification;
use App\Services\License\SchoolLicenseResolver;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSchoolLicenseReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public SchoolLicenseNotification $notification;

    /**
     * Create a new job instance.
     */
    public function __construct(SchoolLicenseNotification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Execute the job.
     */
    public function handle(SchoolLicenseResolver $resolver): void
    {
        // 1. Transaction-safe atomic claim to prevent concurrent worker execution
        $claimed = DB::transaction(function () {
            $notification = SchoolLicenseNotification::where('id', $this->notification->id)
                ->lockForUpdate()
                ->first();

            if (! $notification) {
                return null;
            }

            // Already sent, skipped, or unknown delivery result (do not blindly re-attempt)
            if ($notification->isSent() || $notification->isSkipped() || $notification->isUnknown()) {
                return null;
            }

            // If currently claimed by an active worker (< 5 min timeout lease), do not touch
            if ($notification->isProcessing() && $notification->claimed_at && $notification->claimed_at->gt(now()->subMinutes(5))) {
                return null;
            }

            // Stop retrying if max attempts (3x) reached
            if ($notification->attempts >= 3) {
                $notification->update([
                    'status' => SchoolLicenseNotification::STATUS_FAILED,
                    'error_message' => 'Batas maksimal percobaan pengiriman (3x) telah tercapai.',
                ]);

                return null;
            }

            $isOrphanRecovery = $notification->isProcessing() && $notification->claimed_at && $notification->claimed_at->lte(now()->subMinutes(5));

            $notification->update([
                'status' => SchoolLicenseNotification::STATUS_PROCESSING,
                'claimed_at' => now(),
                'attempts' => $notification->attempts + 1,
                'error_message' => $isOrphanRecovery
                    ? 'Memulihkan pemrosesan dari worker yang terputus (lease expired).'
                    : $notification->error_message,
            ]);

            return $notification;
        });

        if (! $claimed) {
            return;
        }

        $this->notification = $claimed;

        $license = $this->notification->license()->with(['school', 'terms'])->first();
        if (! $license) {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => 'Lisensi tidak ditemukan pada basis data.',
            ]);

            return;
        }

        // 2. Revalidate entitlement at execution time using Asia/Jakarta day boundary
        $todayWib = Carbon::today('Asia/Jakarta');
        $resolution = $resolver->resolve($license, $todayWib);
        $term = $this->notification->term ?? $resolution['active_term'];

        // If the license is expired, skip
        if ($resolution['status'] === SchoolLicense::STATUS_EXPIRED) {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => 'Dilewati: Lisensi telah kedaluwarsa sebelum email dikirim.',
            ]);

            return;
        }

        // If license is not active, skip
        if ($resolution['status'] !== SchoolLicense::STATUS_ACTIVE) {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => "Dilewati: Lisensi tidak berstatus aktif ({$resolution['status']}).",
            ]);

            return;
        }

        // Check if expiration date on the term or license is still valid
        $expirationDate = $term?->expiration_date ?? $license->expiration_date;
        if (! $expirationDate) {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => 'Dilewati: Tanggal kedaluwarsa lisensi tidak valid.',
            ]);

            return;
        }

        $daysRemaining = $resolution['days_remaining'];

        // If license has been renewed or has more than 14 days remaining, skip
        if ($daysRemaining > 14) {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => "Dilewati: Lisensi telah diperpanjang (sisa {$daysRemaining} hari).",
            ]);

            return;
        }

        // Stale H-14 job policy:
        // If queue delay causes days_remaining to drop significantly below threshold (< 13 days),
        // do not silently send misleading stale expiration info.
        if ($daysRemaining < 13) {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => "Dilewati: Pengingat H-14 kedaluwarsa akibat keterlambatan antrean (sisa {$daysRemaining} hari).",
            ]);

            return;
        }

        // 3. Dispatch the mail
        try {
            Mail::to($this->notification->recipient_email, $this->notification->recipient_name)
                ->send(new SchoolLicenseExpirationReminderMail($license, $term, max(1, $daysRemaining)));

            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            // Append audit log for traceability
            SchoolLicenseLog::create([
                'license_id' => $license->id,
                'action' => 'reminder_h14_sent',
                'user_id' => null,
                'details' => "Pengingat kedaluwarsa H-14 berhasil dikirim ke {$this->notification->recipient_email} (Percobaan ke-{$this->notification->attempts})",
                'reason' => 'Sistem otomatisasi pengingat lisensi Bitanic H-14',
            ]);
        } catch (Throwable $e) {
            $isUncertainTransport = str_contains(strtolower($e->getMessage()), 'timeout')
                || str_contains(strtolower($e->getMessage()), 'timed out')
                || str_contains(strtolower($e->getMessage()), 'connection reset')
                || str_contains(strtolower($e->getMessage()), 'broken pipe')
                || str_contains(strtolower($e->getMessage()), 'transport');

            Log::error('SendSchoolLicenseReminderJob failed', [
                'notification_id' => $this->notification->id,
                'recipient' => $this->notification->recipient_email,
                'attempt' => $this->notification->attempts,
                'uncertain_delivery' => $isUncertainTransport,
                'error' => $e->getMessage(),
            ]);

            // If max attempts reached and delivery outcome is uncertain, mark as STATUS_UNKNOWN to prevent duplicate sends
            $newStatus = ($this->notification->attempts >= 3 && $isUncertainTransport)
                ? SchoolLicenseNotification::STATUS_UNKNOWN
                : SchoolLicenseNotification::STATUS_FAILED;

            $this->notification->update([
                'status' => $newStatus,
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            // Re-throw if under 3 attempts to allow queue driver retry
            if ($this->notification->attempts < 3) {
                throw $e;
            }
        }
    }
}
