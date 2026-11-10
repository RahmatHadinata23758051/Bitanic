<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    /**
     * Determine whether the user can view any classrooms.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperuser() || $user->school_id !== null;
    }

    /**
     * Determine whether the user can view the classroom.
     */
    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        return $user->school_id !== null && (int) $user->school_id === (int) $classroom->school_id;
    }

    /**
     * Determine whether the user can create classrooms.
     */
    public function create(User $user): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        return ($user->isTeacher() || $user->isAdmin()) && $user->school_id !== null;
    }

    /**
     * Determine whether the user can update the classroom.
     */
    public function update(User $user, Classroom $classroom): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        return ($user->isTeacher() || $user->isAdmin())
            && $user->school_id !== null
            && (int) $user->school_id === (int) $classroom->school_id;
    }

    /**
     * Determine whether the user can delete the classroom.
     */
    public function delete(User $user, Classroom $classroom): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        return ($user->isTeacher() || $user->isAdmin())
            && $user->school_id !== null
            && (int) $user->school_id === (int) $classroom->school_id;
    }
}
