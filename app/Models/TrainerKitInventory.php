<?php

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
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
        return in_array($this->status, [self::STATUS_REGISTERED, self::STATUS_SOLD]);
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
