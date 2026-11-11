<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolLicenseTerm extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const TYPE_INITIAL_INCLUDED = 'initial_included';

    public const TYPE_ANNUAL_RENEWAL = 'annual_renewal';

    public const TYPE_TERM_CORRECTION = 'term_correction';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'license_id',
        'term_number',
        'term_type',
        'start_date',
        'expiration_date',
        'status',
        'authorized_by',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expiration_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(SchoolLicense::class, 'license_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function getComputedStatusAttribute(): string
    {
        $today = Carbon::today();

        if ($today->lt($this->start_date)) {
            return 'pending';
        }

        if ($today->gte($this->expiration_date)) {
            return 'expired';
        }

        return 'active';
    }

    public function getTermTypeLabelAttribute(): string
    {
        return match ($this->term_type) {
            self::TYPE_INITIAL_INCLUDED => 'Tahun Pertama (Bundling Hardware)',
            self::TYPE_ANNUAL_RENEWAL => 'Perpanjangan Tahunan',
            self::TYPE_TERM_CORRECTION => 'Koreksi Adendum',
            default => ucfirst(str_replace('_', ' ', $this->term_type)),
        };
    }
}
