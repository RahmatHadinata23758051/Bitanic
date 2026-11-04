<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeHistory extends Model
{
    use HasFactory;

    protected $table = 'grade_history';

    protected $fillable = [
        'student_report_id',
        'graded_by',
        'rubric_scores',
        'final_grade',
        'teacher_feedback',
        'adjustment_reason',
        'transition',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'rubric_scores' => 'array',
            'final_grade' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function studentReport(): BelongsTo
    {
        return $this->belongsTo(StudentReport::class, 'student_report_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
