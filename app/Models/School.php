<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'npsn',
        'name',
        'slug',
        'address',
        'city',
        'province',
        'province_code',
        'regency_code',
        'district_code',
        'phone',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function provinceRelation(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_code', 'code');
    }

    public function regencyRelation(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_code', 'code');
    }

    public function districtRelation(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code', 'code');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ActivitySchedule::class);
    }

    public function schoolSetting(): HasOne
    {
        return $this->hasOne(SchoolSetting::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function trainerKitInventories(): HasMany
    {
        return $this->hasMany(TrainerKitInventory::class);
    }

    public function trainerKitSaleRecords(): HasMany
    {
        return $this->hasMany(TrainerKitSaleRecord::class);
    }

    public function trainerKitHandovers(): HasMany
    {
        return $this->hasMany(TrainerKitHandover::class);
    }
}
