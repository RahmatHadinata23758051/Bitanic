<?php

namespace App\Actions\Practicum;

use App\Models\GradeHistory;
use App\Models\StudentReport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GradeStudentReportAction
{
    /**
     * Grade student practicum report using 4-scale rubric aspects and maintain immutable history.
     * BR-204: Assessment Revision and Grade History.
     *
     * @param  array<string, int>  $rubricScores  Array of aspectId => score (1-4)
     *
     * @throws AuthorizationException
     */
    public function execute(
        StudentReport $report,
        User $teacher,
        array $rubricScores,
        ?string $feedback = null,
        ?string $adjustmentReason = null
    ): StudentReport {
        // 1. Role verification: Only teachers and superusers can grade
        if (! ($teacher->isGuru() || $teacher->isSuperuser())) {
            throw new AuthorizationException('Hanya guru atau superuser yang memiliki kewenangan menilai laporan.');
        }

        $module = $report->module;
        if (! $module) {
            throw new InvalidArgumentException('Target practicum module does not exist.');
        }

        // 2. Cross-school tenancy protection: Guru cannot grade reports from other schools
        $student = $report->user;
        if (! $teacher->isSuperuser()) {
            if (! $teacher->school_id || ! $student?->school_id || (int) $student->school_id !== (int) $teacher->school_id) {
                throw new AuthorizationException('Guru tidak diizinkan menilai laporan siswa dari sekolah lain.');
            }
        }

        // 3. Calculate weighted final score from rubric
        $rubricMeta = $module->rubric ?? [];
        $totalWeight = 0;
        $weightedScoreSum = 0;

        foreach ($rubricMeta as $aspect) {
            $aspectId = $aspect['id'];
            $weight = (float) ($aspect['weight'] ?? 0);
            $totalWeight += $weight;

            // Score 1 (Belum Kompeten) s.d 4 (Sangat Kompeten)
            $score = isset($rubricScores[$aspectId]) ? (int) $rubricScores[$aspectId] : 2;
            $score = max(1, min(4, $score));

            // Konversi score 1-4 ke persentase: 1 = 60%, 2 = 75%, 3 = 85%, 4 = 100%
            $percentage = match ($score) {
                4 => 100.0,
                3 => 85.0,
                2 => 75.0,
                default => 60.0,
            };

            $weightedScoreSum += ($percentage * ($weight / 100));
        }

        $finalGrade = $totalWeight > 0 ? round($weightedScoreSum, 2) : 80.0;

        // 4. Atomic Transaction: Lock report, update state, and append immutable grade record
        return DB::transaction(function () use (
            $report, $teacher, $rubricScores, $finalGrade, $feedback, $adjustmentReason
        ) {
            $locked = StudentReport::whereKey($report->id)->lockForUpdate()->firstOrFail();

            // Determine transition type: initial_grade vs revision vs adjustment
            $existingGradeCount = GradeHistory::where('student_report_id', $locked->id)->count();
            $transition = $existingGradeCount === 0 ? 'initial_grade' : 'revision';

            $locked->update([
                'rubric_scores' => $rubricScores,
                'final_grade' => $finalGrade,
                'teacher_feedback' => $feedback,
                'graded_by' => $teacher->id,
                'status' => 'graded',
            ]);

            GradeHistory::create([
                'student_report_id' => $locked->id,
                'graded_by' => $teacher->id,
                'rubric_scores' => $rubricScores,
                'final_grade' => $finalGrade,
                'teacher_feedback' => $feedback,
                'adjustment_reason' => $adjustmentReason,
                'transition' => $transition,
                'graded_at' => now(),
            ]);

            // Reload relationships
            $locked->load(['gradeHistory', 'teacher']);

            return $locked;
        });
    }
}
