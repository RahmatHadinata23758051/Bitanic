<?php

namespace App\Policies;

use App\Models\SchoolLicense;
use App\Models\User;

class SchoolLicensePolicy
{
    /**
     * Determine whether the user can view the global license registry.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can view the specific school license.
     */
    public function view(User $user, SchoolLicense $license): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        // Admin Sekolah may view only their own school license
        if (in_array($user->role, ['admin_sekolah', 'admin']) && $user->school_id !== null) {
            return (int) $user->school_id === (int) $license->school_id;
        }

        return false;
    }

    /**
     * Determine whether the user can initialize/create a school license.
     */
    public function create(User $user): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can activate the first-year license entitlement.
     */
    public function activate(User $user, SchoolLicense $license): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can renew the school license.
     */
    public function renew(User $user, SchoolLicense $license): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can correct license dates.
     */
    public function correct(User $user, SchoolLicense $license): bool
    {
        return $user->isSuperuser();
    }
}
