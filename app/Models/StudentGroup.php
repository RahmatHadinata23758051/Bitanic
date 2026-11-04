<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'device_id',
        'name',
        'leader_id',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (StudentGroup $group) {
            if (empty($group->school_id)) {
                $group->school_id = auth()->user()?->school_id
                    ?? $group->classroom?->school_id
                    ?? School::where('slug', 'smk-ppn-1-sukaraja')->value('id')
                    ?? School::first()?->id;
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(UserProfile::class);
    }
}
