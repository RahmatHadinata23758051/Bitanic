<?php

namespace App\Models;

use App\Services\License\SchoolLicenseResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SchoolLicense extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const ENTITLEMENT_FIRST_YEAR = 'first_year_included';

    public const ENTITLEMENT_STANDARD = 'standard';

    public const ENTITLEMENT_ENTERPRISE = 'enterprise';

    protected $fillable = [
        'school_id',
        'license_number',
        'status',
        'entitlement_type',
        'start_date',
        'expiration_date',
        'notes',
        'last_authorized_by',
        'last_authorized_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expiration_date' => 'date',
        'last_authorized_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(SchoolLicenseTerm::class, 'license_id')->orderBy('term_number', 'asc');
    }

    public function latestTerm(): HasOne
    {
        return $this->hasOne(SchoolLicenseTerm::class, 'license_id')->latestOfMany('term_number');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SchoolLicenseLog::class, 'license_id')->latest('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(SchoolLicenseNotification::class, 'license_id')->latest('created_at');
    }

    public function lastAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_authorized_by');
    }

    /**
     * Compute current license status based on date-only [start_date, expiration_date) convention
     * and authorized term intervals (resolving inter-term gaps correctly).
     */
    public function getComputedStatusAttribute(): string
    {
        return app(SchoolLicenseResolver::class)->resolveStatus($this);
    }

    public function getActiveTerm(?Carbon $date = null): ?SchoolLicenseTerm
    {
        return app(SchoolLicenseResolver::class)->resolve($this, $date)['active_term'];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->computed_status) {
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_PENDING => 'Menunggu Efektif',
            self::STATUS_EXPIRED => 'Kedaluwarsa',
            default => 'Pending',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->computed_status) {
            self::STATUS_ACTIVE => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::STATUS_PENDING => 'bg-amber-50 text-amber-700 border-amber-200',
            self::STATUS_EXPIRED => 'bg-rose-50 text-rose-700 border-rose-200',
            default => 'bg-slate-100 text-slate-600 border-slate-200',
        };
    }

    public function getDaysRemainingAttribute(): int
    {
        return app(SchoolLicenseResolver::class)->resolve($this)['days_remaining'];
    }

    public function isActive(?Carbon $date = null): bool
    {
        return app(SchoolLicenseResolver::class)->isActive($this, $date);
    }
}
