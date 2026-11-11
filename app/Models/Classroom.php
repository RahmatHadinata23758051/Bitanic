<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'department',
        'academic_year',
        'homeroom_teacher_id',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom) {
            if (empty($classroom->school_id)) {
                $userSchoolId = auth()->user()?->school_id;
                if (! empty($userSchoolId)) {
                    $classroom->school_id = $userSchoolId;
                }
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(StudentGroup::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(UserProfile::class);
    }
}
