<?php

namespace App\Services\Hardware;

use App\Models\Device;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DeviceAvailabilityResolver
{
    /**
     * Resolve the active heartbeat timeout for a given device or school.
     */
    public function getTimeoutSeconds(?Device $device = null): int
    {
        // 1. Per-device specific configuration
        if ($device && ! empty($device->connection_config['heartbeat_timeout_seconds'])) {
            return (int) $device->connection_config['heartbeat_timeout_seconds'];
        }

        // 2. Per-school specific configuration
        if ($device && $device->school_id) {
            $school = $device->relationLoaded('school') ? $device->school : $device->school()->first();
            if ($school && ! empty($school->settings['heartbeat_timeout_seconds'])) {
                return (int) $school->settings['heartbeat_timeout_seconds'];
            }
        }

        // 3. System-wide default
        return (int) config('bitanic.heartbeat_timeout_seconds', 900);
    }

    /**
     * Determine whether a device is currently online based on its last authenticated heartbeat.
     */
    public function isOnline(Device $device, ?CarbonInterface $now = null, ?int $overrideTimeout = null): bool
    {
        if (! $device->is_active || ! $device->last_seen_at) {
            return false;
        }

        $now = $now ?? Carbon::now();
        $timeout = $overrideTimeout ?? $this->getTimeoutSeconds($device);
        $threshold = $now->copy()->subSeconds($timeout);

        return $device->last_seen_at->greaterThanOrEqualTo($threshold);
    }

    /**
     * Compute the deterministic availability status for a device.
     */
    public function status(Device $device, ?CarbonInterface $now = null, ?int $overrideTimeout = null): string
    {
        if (! $device->is_active) {
            return 'offline';
        }

        if ($this->isOnline($device, $now, $overrideTimeout)) {
            return 'online';
        }

        if ($device->getRawOriginal('status') === 'maintenance') {
            return 'maintenance';
        }

        return 'offline';
    }

    /**
     * Annotate and override dynamic status on a device model instance.
     */
    public function applyToDevice(Device $device, ?CarbonInterface $now = null, ?int $overrideTimeout = null): Device
    {
        $isOnline = $this->isOnline($device, $now, $overrideTimeout);
        $device->status = $this->status($device, $now, $overrideTimeout);
        $device->is_online = $isOnline;

        return $device;
    }

    /**
     * Apply computed availability to a collection of devices.
     *
     * @param  Collection<int, Device>  $devices
     * @return Collection<int, Device>
     */
    public function applyToCollection($devices, ?CarbonInterface $now = null): Collection
    {
        return collect($devices)->map(fn (Device $device) => $this->applyToDevice($device, $now));
    }

    /**
     * Count the number of online devices within the current scope and time window.
     */
    public function countOnline(?int $schoolId = null, ?CarbonInterface $now = null, ?int $overrideTimeout = null): int
    {
        $query = Device::query()
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        return $query->with('school')->get()
            ->filter(fn (Device $device) => $this->isOnline($device, $now, $overrideTimeout))
            ->count();
    }

    /**
     * Count total devices in fleet for a school.
     */
    public function countTotal(?int $schoolId = null): int
    {
        return Device::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->count();
    }
}
