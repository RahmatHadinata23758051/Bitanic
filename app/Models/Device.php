<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'connection_type',
        'connection_config',
        'installation_date',
        'note',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'connection_config' => 'array',
            'installation_date' => 'date',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(DeviceApiKey::class);
    }

    public function activeApiKey(): HasOne
    {
        return $this->hasOne(DeviceApiKey::class)->whereNull('revoked_at')->latestOfMany();
    }

    public function telemetryReadings(): HasMany
    {
        return $this->hasMany(TelemetryReading::class);
    }

    public function thresholds(): HasMany
    {
        return $this->hasMany(SensorThreshold::class);
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(TelemetryReading::class)->latestOfMany('recorded_at');
    }
}
