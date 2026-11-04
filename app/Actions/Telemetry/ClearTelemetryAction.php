<?php

namespace App\Actions\Telemetry;

use App\Models\Device;
use App\Models\TelemetryReading;
use InvalidArgumentException;

class ClearTelemetryAction
{
    /**
     * Safely delete telemetry readings scoped to a specific device and optional date range.
     * DILARANG KERAS memanggil truncate()!
     *
     * @param  array{start_date?: string|null, end_date?: string|null}  $filters
     */
    public function execute(Device $device, array $filters = []): int
    {
        if (!$device->exists || empty($device->id)) {
            throw new InvalidArgumentException('Target device must be an existing persisted model.');
        }

        $query = TelemetryReading::where('device_id', $device->id);

        if (!empty($filters['start_date'])) {
            $query->where('recorded_at', '>=', $filters['start_date'] . ' 00:00:00');
        }

        if (!empty($filters['end_date'])) {
            $query->where('recorded_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        return $query->delete();
    }
}
