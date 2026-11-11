<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainerKitInventory extends Model
{
    use HasFactory;

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_SOLD = 'sold';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_ACTIVATED = 'activated';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_DECOMMISSIONED = 'decommissioned';

    protected $fillable = [
        'serial_number',
        'model_name',
        'hardware_revision',
        'batch_number',
        'status',
        'school_id',
        'device_id',
        'registered_at',
        'registered_by',
        'notes',
        'handover_date',
        'warranty_start_date',
        'warranty_expiration_date',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'handover_date' => 'date',
            'warranty_start_date' => 'date',
            'warranty_expiration_date' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function saleRecords(): HasMany
    {
        return $this->hasMany(TrainerKitSaleRecord::class, 'inventory_id');
    }

    public function latestSaleRecord(): HasOne
    {
        return $this->hasOne(TrainerKitSaleRecord::class, 'inventory_id')->latestOfMany('sold_at');
    }

    public function lifecycleLogs(): HasMany
    {
        return $this->hasMany(TrainerKitLifecycleLog::class, 'inventory_id')->latest('created_at');
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(TrainerKitHandover::class, 'inventory_id')->latest('handover_date');
    }

    public function handover(): HasOne
    {
        return $this->hasOne(TrainerKitHandover::class, 'inventory_id')->latestOfMany('handover_date');
    }

    public function warrantyLogs(): HasMany
    {
        return $this->hasMany(TrainerKitWarrantyLog::class, 'inventory_id')->latest('created_at');
    }

    /**
     * Compute current warranty status based on persisted dates.
     */
    public function getWarrantyStatusAttribute(): string
    {
        if (! $this->warranty_start_date || ! $this->warranty_expiration_date) {
            return 'not_recorded';
        }

        if (Carbon::today()->gt($this->warranty_expiration_date)) {
            return 'expired';
        }

        return 'active';
    }

    public function getWarrantyStatusLabelAttribute(): string
    {
        return match ($this->warranty_status) {
            'active' => 'Aktif',
            'expired' => 'Kedaluwarsa',
            default => 'Belum Dicatat',
        };
    }

    public function getWarrantyStatusBadgeClassAttribute(): string
    {
        return match ($this->warranty_status) {
            'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'expired' => 'bg-rose-50 text-rose-700 border-rose-200',
            default => 'bg-slate-100 text-slate-600 border-slate-200',
        };
    }

    public function isActivated(): bool
    {
        return $this->status === self::STATUS_ACTIVATED;
    }

    public function isAssigned(): bool
    {
        return $this->status === self::STATUS_ASSIGNED;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function isRegistered(): bool
    {
        return $this->status === self::STATUS_REGISTERED;
    }

    public function canBeSold(): bool
    {
        return $this->status === self::STATUS_REGISTERED;
    }

    public function canBeAssigned(): bool
    {
        return ! empty($this->school_id) && ! in_array($this->status, [self::STATUS_DECOMMISSIONED]);
    }

    public function canBeActivated(): bool
    {
        return ! empty($this->school_id) && ! in_array($this->status, [self::STATUS_DECOMMISSIONED]);
    }
}
