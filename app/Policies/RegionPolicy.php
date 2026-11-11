<?php

namespace App\Policies;

use App\Models\User;

class RegionPolicy
{
    /**
     * Determine whether the user can view the regions list.
     */
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can manage (sync, create, edit) region master data.
     */
    public function manage(User $user): bool
    {
        return $user->isSuperuser();
    }
}
