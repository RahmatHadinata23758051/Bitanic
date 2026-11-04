<?php

namespace App\Actions\Practicum;

use App\Models\StudentReport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthorizeReportRevisionAction
{
    /**
     * Authorize a revision request for a student report.
     * Reopens the report for student resubmission while keeping grade history intact.
     * BR-204: A finalized report can only re-enter workflow through an authorized revision request.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(StudentReport $report, User $teacher, string $instructions): StudentReport
    {
        // 1. Role verification: Only teacher or superuser can authorize revision
        if (! in_array($teacher->role, ['guru', 'superuser'], true)) {
            throw new AuthorizationException('Hanya guru pembimbing yang berhak mengizinkan perbaikan laporan.');
        }

        // 2. School tenancy isolation
        $student = $report->user;
        if ($teacher->role !== 'superuser') {
            if (! $teacher->school_id || ! $student?->school_id || (int) $student->school_id !== (int) $teacher->school_id) {
                throw new AuthorizationException('Guru tidak diizinkan membuka revisi laporan dari sekolah lain.');
            }
        }

        // 3. Instructions/reason must be provided
        $trimmedInstructions = trim($instructions);
        if ($trimmedInstructions === '') {
            throw ValidationException::withMessages([
                'instructions' => 'Instruksi atau alasan revisi wajib diisi oleh guru.',
            ]);
        }

        return DB::transaction(function () use ($report, $trimmedInstructions) {
            $locked = StudentReport::whereKey($report->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => 'revision',
                'teacher_feedback' => $trimmedInstructions,
            ]);

            return $locked;
        });
    }
}
