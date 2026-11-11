<?php

namespace App\Policies;

use App\Models\TrainerKitInventory;
use App\Models\User;

class TrainerKitInventoryPolicy
{
    /**
     * Determine whether the user can view the global trainer kit inventory list.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can view the specific inventory item.
     */
    public function view(User $user, TrainerKitInventory $inventory): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        if ($user->isAdmin() && $inventory->school_id !== null) {
            return (int) $user->school_id === (int) $inventory->school_id;
        }

        return false;
    }

    /**
     * Determine whether the user can register new inventory units.
     */
    public function create(User $user): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can update kit metadata.
     */
    public function update(User $user, TrainerKitInventory $inventory): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can record a sale or school ownership transfer.
     */
    public function recordSale(User $user, TrainerKitInventory $inventory): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can bind/unbind a workstation device.
     */
    public function assignDevice(User $user, TrainerKitInventory $inventory): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        if ($user->isAdmin() && $inventory->school_id !== null) {
            return (int) $user->school_id === (int) $inventory->school_id;
        }

        return false;
    }

    /**
     * Determine whether the user can activate the unit in the platform registry.
     */
    public function activate(User $user, TrainerKitInventory $inventory): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        if ($user->isAdmin() && $inventory->school_id !== null) {
            return (int) $user->school_id === (int) $inventory->school_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the inventory item.
     */
    public function delete(User $user, TrainerKitInventory $inventory): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can record physical handover (BAST).
     */
    public function recordHandover(User $user, TrainerKitInventory $inventory): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can correct warranty dates.
     */
    public function correctWarranty(User $user, TrainerKitInventory $inventory): bool
    {
        return $user->isSuperuser();
    }

    /**
     * Determine whether the user can view warranty information for this kit.
     */
    public function viewWarranty(User $user, TrainerKitInventory $inventory): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        if (! empty($user->school_id) && ! empty($inventory->school_id)) {
            return (int) $user->school_id === (int) $inventory->school_id;
        }

        return false;
    }
}
