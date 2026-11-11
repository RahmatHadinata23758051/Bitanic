<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerKitLifecycleLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'inventory_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'from_school_id',
        'to_school_id',
        'from_device_id',
        'to_device_id',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(TrainerKitInventory::class, 'inventory_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fromSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'from_school_id');
    }

    public function toSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'to_school_id');
    }

    public function fromDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'from_device_id');
    }

    public function toDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'to_device_id');
    }
}
