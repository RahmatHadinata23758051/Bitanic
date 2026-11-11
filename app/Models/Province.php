<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Province extends Model
{
    use HasFactory;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
    ];

    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class, 'province_code', 'code');
    }

    public function districts(): HasManyThrough
    {
        return $this->hasManyThrough(District::class, Regency::class, 'province_code', 'regency_code', 'code', 'code');
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class, 'province_code', 'code');
    }
}
