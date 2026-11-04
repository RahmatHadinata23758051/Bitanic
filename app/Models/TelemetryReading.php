<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryReading extends Model
{
    use HasFactory;

    protected $attributes = [
        'raw_payload' => '{}',
    ];

    protected $fillable = [
        'device_id',
        'nitrogen',
        'phosphorus',
        'potassium',
        'ec',
        'ph',
        'temperature',
        'humidity',
        'raw_payload',
        'threshold_flags',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'nitrogen' => 'decimal:2',
            'phosphorus' => 'decimal:2',
            'potassium' => 'decimal:2',
            'ec' => 'decimal:2',
            'ph' => 'decimal:2',
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
            'raw_payload' => 'array',
            'threshold_flags' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
