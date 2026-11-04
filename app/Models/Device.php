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
        'school_id',
        'code',
        'name',
        'bench_location',
        'ip_address',
        'mac_address',
        'firmware_version',
        'protocol',
        'status',
        'battery_percent',
        'rssi_signal',
        'relays_state',
        'calibration_offsets',
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
            'relays_state' => 'array',
            'calibration_offsets' => 'array',
            'battery_percent' => 'integer',
            'rssi_signal' => 'integer',
            'installation_date' => 'date',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Device $device) {
            if (empty($device->school_id)) {
                $device->school_id = School::where('slug', 'smk-ppn-1-sukaraja')->value('id') ?? School::first()?->id;
            }
        });
    }

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class);
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
