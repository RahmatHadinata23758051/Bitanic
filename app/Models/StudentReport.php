<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'module_id',
        'activity_schedule_id',
        'lks_answers',
        'observation_notes',
        'telemetry_snapshot',
        'status',
        'rubric_scores',
        'final_grade',
        'teacher_feedback',
        'graded_by',
    ];

    protected function casts(): array
    {
        return [
            'lks_answers' => 'array',
            'telemetry_snapshot' => 'array',
            'rubric_scores' => 'array',
            'final_grade' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModulePracticum::class, 'module_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ActivitySchedule::class, 'activity_schedule_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
