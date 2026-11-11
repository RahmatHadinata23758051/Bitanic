<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainerKitHandover extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'school_id',
        'bast_number',
        'handover_date',
        'recipient_name',
        'recipient_title',
        'recipient_phone',
        'warranty_start_date',
        'warranty_expiration_date',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'warranty_start_date' => 'date',
        'warranty_expiration_date' => 'date',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(TrainerKitInventory::class, 'inventory_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function warrantyLogs(): HasMany
    {
        return $this->hasMany(TrainerKitWarrantyLog::class, 'handover_id')->latest('created_at');
    }

    /**
     * Compute current warranty status for this handover.
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
}
