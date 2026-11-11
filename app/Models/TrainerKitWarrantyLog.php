<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerKitWarrantyLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'inventory_id',
        'handover_id',
        'user_id',
        'old_start_date',
        'new_start_date',
        'old_expiration_date',
        'new_expiration_date',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'old_start_date' => 'date',
        'new_start_date' => 'date',
        'old_expiration_date' => 'date',
        'new_expiration_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(TrainerKitInventory::class, 'inventory_id');
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(TrainerKitHandover::class, 'handover_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
