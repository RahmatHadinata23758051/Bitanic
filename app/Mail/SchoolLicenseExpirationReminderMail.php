<?php

namespace App\Mail;

use App\Models\SchoolLicense;
use App\Models\SchoolLicenseTerm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SchoolLicenseExpirationReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public SchoolLicense $license;

    public ?SchoolLicenseTerm $term;

    public string $schoolName;

    public int $daysRemaining;

    /**
     * Create a new message instance.
     */
    public function __construct(SchoolLicense $license, ?SchoolLicenseTerm $term = null, int $daysRemaining = 14)
    {
        $this->license = $license;
        $this->term = $term;
        $this->schoolName = $license->school?->name ?? 'Sekolah Mitra';
        $this->daysRemaining = $daysRemaining;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Pemberitahuan] Masa Berlaku Lisensi LMS Bitanic — {$this->schoolName} Berakhir dalam {$this->daysRemaining} Hari",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.license-expiration-reminder',
            with: [
                'license' => $this->license,
                'term' => $this->term,
                'schoolName' => $this->schoolName,
                'daysRemaining' => $this->daysRemaining,
                'expirationDate' => $this->term?->expiration_date?->translatedFormat('d F Y') ?? $this->license->expiration_date?->translatedFormat('d F Y'),
            ],
        );
    }
}
