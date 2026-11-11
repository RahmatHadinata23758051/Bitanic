<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    /**
     * Determine whether the user can view the global schools directory.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can view the school details.
     */
    public function view(User $user, School $school): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        return (int) $user->school_id === (int) $school->id;
    }

    /**
     * Determine whether the user can create schools (global tenant provisioning).
     */
    public function create(User $user): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can update the school.
     */
    public function update(User $user, School $school): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        // Tenant admins can only update their own school via tenant settings
        return $user->isAdmin() && (int) $user->school_id === (int) $school->id;
    }

    /**
     * Determine whether the user can delete the school.
     */
    public function delete(User $user, School $school): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can activate/deactivate the school.
     */
    public function toggleStatus(User $user, School $school): bool
    {
        return $user->isSuperuser();
    }
}
