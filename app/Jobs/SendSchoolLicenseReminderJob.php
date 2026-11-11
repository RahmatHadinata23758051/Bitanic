<?php

namespace App\Jobs;

use App\Mail\SchoolLicenseExpirationReminderMail;
use App\Models\SchoolLicenseLog;
use App\Models\SchoolLicenseNotification;
use App\Services\License\SchoolLicenseResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        // 1. Idempotency check: Do not re-send if already processed
        if ($this->notification->isSent() || $this->notification->isSkipped()) {
            return;
        }

        $license = $this->notification->license()->with(['school', 'terms'])->first();
        if (! $license) {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => 'Lisensi tidak ditemukan pada basis data.',
            ]);

            return;
        }

        // 2. Revalidate entitlement at execution time
        // If license was already renewed, or is expired/not active, skip.
        $resolution = $resolver->resolve($license);
        $term = $this->notification->term ?? $resolution['active_term'];

        // If the license is expired, or target term is superseded/inactive, or days remaining moved > 14
        if ($resolution['status'] === 'expired') {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => 'Dilewati: Lisensi telah kedaluwarsa sebelum email dikirim.',
            ]);

            return;
        }

        // Check if expiration date on the term or license is still the same as scheduled
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

        if ($daysRemaining < 0) {
            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_SKIPPED,
                'error_message' => 'Dilewati: Lisensi telah melampaui tanggal kedaluwarsa.',
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
                'details' => "Pengingat kedaluwarsa H-14 berhasil dikirim ke {$this->notification->recipient_email}",
                'reason' => 'Sistem otomatisasi pengingat lisensi Bitanic H-14',
            ]);
        } catch (Throwable $e) {
            Log::error('SendSchoolLicenseReminderJob failed', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
            ]);

            $this->notification->update([
                'status' => SchoolLicenseNotification::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }
}
