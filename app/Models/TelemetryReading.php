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
        'provenance',
        'nitrogen',
        'phosphorus',
        'potassium',
        'ec',
        'ph',
        'temperature',
        'humidity',
        'raw_payload',
        'raw_metrics',
        'applied_offsets',
        'calibration_profile_id',
        'calibration_version',
        'calibration_applied_at',
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
            'raw_metrics' => 'array',
            'applied_offsets' => 'array',
            'calibration_version' => 'integer',
            'calibration_applied_at' => 'datetime',
            'threshold_flags' => 'array',
            'recorded_at' => 'datetime',
            'provenance' => 'string',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function calibrationProfile(): BelongsTo
    {
        return $this->belongsTo(SensorCalibrationProfile::class, 'calibration_profile_id');
    }

    public function isHardware(): bool
    {
        return $this->provenance === 'hardware';
    }

    public function isSimulation(): bool
    {
        return $this->provenance === 'simulation';
    }

    public function isFixture(): bool
    {
        return $this->provenance === 'fixture';
    }

    public function scopeHardware($query)
    {
        return $query->where('provenance', 'hardware');
    }

    public function scopeSimulation($query)
    {
        return $query->where('provenance', 'simulation');
    }

    public function scopeFixture($query)
    {
        return $query->where('provenance', 'fixture');
    }
}
