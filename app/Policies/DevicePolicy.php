<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    /**
     * Determine whether the user can view devices.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the device.
     */
    public function view(User $user, Device $device): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        return $user->school_id === null || (int) $user->school_id === (int) $device->school_id;
    }

    /**
     * Determine whether the user can reboot the device.
     */
    public function reboot(User $user, Device $device): bool
    {
        if ($user->isSuperuser()) {
            return $user->school_id === null || (int) $user->school_id === (int) $device->school_id;
        }

        if (! $user->isTeacher() && ! $user->isAdmin()) {
            return false;
        }

        return $user->school_id !== null && (int) $user->school_id === (int) $device->school_id;
    }

    /**
     * Determine whether the user can calibrate the device sensors.
     */
    public function calibrate(User $user, Device $device): bool
    {
        if ($user->isSuperuser()) {
            return $user->school_id === null || (int) $user->school_id === (int) $device->school_id;
        }

        if (! $user->isTeacher() && ! $user->isAdmin()) {
            return false;
        }

        return $user->school_id !== null && (int) $user->school_id === (int) $device->school_id;
    }

    /**
     * Determine whether the user can rotate the device API key.
     * Platform-only operation: strictly superuser.
     */
    public function rotateApiKey(User $user, Device $device): bool
    {
        if (! $user->isSuperuser()) {
            return false;
        }

        return $user->school_id === null || (int) $user->school_id === (int) $device->school_id;
    }
}
