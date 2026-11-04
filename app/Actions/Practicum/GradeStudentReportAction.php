<?php

namespace App\Actions\Practicum;

use App\Models\StudentReport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GradeStudentReportAction
{
    /**
     * Grade student practicum report using 4-scale rubric aspects.
     *
     * @param  array<string, int>  $rubricScores  Array of aspectId => score (1-4)
     */
    public function execute(StudentReport $report, User $teacher, array $rubricScores, ?string $feedback = null): StudentReport
    {
        $module = $report->module;
        if (! $module) {
            throw new InvalidArgumentException('Target practicum module does not exist.');
        }

        // Cross-school tenancy protection: Guru cannot grade reports from other schools
        $student = $report->user;
        if ($teacher->role !== 'superuser') {
            if (! $teacher->school_id || ! $student?->school_id || (int) $student->school_id !== (int) $teacher->school_id) {
                throw new AuthorizationException('Guru tidak diizinkan menilai laporan siswa dari sekolah lain.');
            }
        }

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

        return DB::transaction(function () use ($report, $teacher, $rubricScores, $finalGrade, $feedback) {
            $locked = StudentReport::whereKey($report->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'rubric_scores' => $rubricScores,
                'final_grade' => $finalGrade,
                'teacher_feedback' => $feedback,
                'graded_by' => $teacher->id,
                'status' => 'graded',
            ]);

            return $locked;
        });
    }
}
