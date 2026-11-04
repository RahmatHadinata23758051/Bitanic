<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawDataLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'kit_code',
        'packet_hex',
        'protocol',
        'level',
        'parsed_payload',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'parsed_payload' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
