<?php

namespace App\Policies;

use App\Models\ActivitySchedule;
use App\Models\User;

class ActivitySchedulePolicy
{
    /**
     * Determine whether the user can view schedules.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can manage (create, update, delete) schedules.
     */
    public function manage(User $user, ?ActivitySchedule $schedule = null): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        if (! $user->isTeacher() && ! $user->isAdmin()) {
            return false;
        }

        if (! $user->school_id) {
            return false;
        }

        if ($schedule !== null) {
            return (int) $user->school_id === (int) $schedule->school_id;
        }

        return true;
    }
}
