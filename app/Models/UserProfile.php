<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'identifier_number',
        'department',
        'semester',
        'class_grade',
        'assigned_kit_id',
        'group_name',
        'specialization',
        'phone',
        'attendance_percent',
        'average_grade',
        'competency_predicative',
        'trainer_certified',
        'address',
        'photo',
        'gender',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
