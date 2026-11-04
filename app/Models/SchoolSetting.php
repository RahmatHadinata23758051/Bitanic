<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_name',
        'academic_year',
        'semester',
        'course_name',
        'lead_teacher',
        'sensor_thresholds',
        'audio_alarm_enabled',
        'auto_sync_interval_sec',
    ];

    protected function casts(): array
    {
        return [
            'sensor_thresholds' => 'array',
            'audio_alarm_enabled' => 'boolean',
            'auto_sync_interval_sec' => 'integer',
        ];
    }
}
