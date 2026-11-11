<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolLicenseNotification extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const TYPE_REMINDER_H14 = 'reminder_h14';

    public const CHANNEL_MAIL = 'mail';

    protected $fillable = [
        'license_id',
        'term_id',
        'school_id',
        'recipient_email',
        'recipient_name',
        'notification_type',
        'channel',
        'status',
        'scheduled_for',
        'sent_at',
        'error_message',
        'payload',
    ];

    protected $casts = [
        'scheduled_for' => 'date',
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(SchoolLicense::class, 'license_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(SchoolLicenseTerm::class, 'term_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
