<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class PlantingMediaPolicy
{
    /**
     * Determine whether the user can view planting media.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can manage (create, update, delete) planting media.
     */
    public function manage(User $user, ?Media $media = null): bool
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

        if ($media !== null) {
            return (int) $user->school_id === (int) $media->school_id;
        }

        return true;
    }
}
