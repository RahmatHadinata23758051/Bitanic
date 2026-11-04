<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentReportAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_report_id',
        'attempt_number',
        'lks_answers',
        'observation_notes',
        'telemetry_snapshot',
        'evidence_reading_id',
        'device_id',
        'activity_schedule_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'lks_answers' => 'array',
            'telemetry_snapshot' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function studentReport(): BelongsTo
    {
        return $this->belongsTo(StudentReport::class, 'student_report_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ActivitySchedule::class, 'activity_schedule_id');
    }

    public function evidenceReading(): BelongsTo
    {
        return $this->belongsTo(TelemetryReading::class, 'evidence_reading_id');
    }
}
