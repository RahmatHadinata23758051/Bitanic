<?php

namespace App\Policies;

use App\Models\StudentReport;
use App\Models\User;

class StudentReportPolicy
{
    /**
     * Determine whether the user can view the student report.
     */
    public function view(User $user, StudentReport $report): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        if ($user->id === $report->user_id) {
            return true;
        }

        $reportStudent = $report->user;
        if (! $reportStudent || ! $reportStudent->school_id) {
            return false;
        }

        return ($user->isTeacher() || $user->isAdmin())
            && $user->school_id !== null
            && (int) $user->school_id === (int) $reportStudent->school_id;
    }

    /**
     * Determine whether the user can grade the student report.
     */
    public function grade(User $user, StudentReport $report): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        if (! $user->isTeacher() && ! $user->isAdmin()) {
            return false;
        }

        $reportStudent = $report->user;
        if (! $reportStudent || ! $reportStudent->school_id) {
            return false;
        }

        return $user->school_id !== null && (int) $user->school_id === (int) $reportStudent->school_id;
    }

    /**
     * Determine whether the user can request revision for the student report.
     */
    public function requestRevision(User $user, StudentReport $report): bool
    {
        return $this->grade($user, $report);
    }
}
