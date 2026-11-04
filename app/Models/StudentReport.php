<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'module_id',
        'activity_schedule_id',
        'device_id',
        'evidence_reading_id',
        'lks_answers',
        'observation_notes',
        'telemetry_snapshot',
        'status',
        'attempt_count',
        'rubric_scores',
        'final_grade',
        'teacher_feedback',
        'revision_notes',
        'graded_by',
    ];

    protected function casts(): array
    {
        return [
            'lks_answers' => 'array',
            'telemetry_snapshot' => 'array',
            'rubric_scores' => 'array',
            'final_grade' => 'decimal:2',
            'attempt_count' => 'integer',
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

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function evidenceReading(): BelongsTo
    {
        return $this->belongsTo(TelemetryReading::class, 'evidence_reading_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(StudentReportAttempt::class, 'student_report_id')->orderBy('attempt_number');
    }
}
