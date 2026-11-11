<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasFactory;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'regency_code',
        'name',
    ];

    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_code', 'code');
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class, 'district_code', 'code');
    }
}
