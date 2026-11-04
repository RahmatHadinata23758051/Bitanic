<?php

namespace App\Events;

use App\Models\TelemetryReading;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorData implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TelemetryReading $reading;

    /**
     * Create a new event instance.
     */
    public function __construct(TelemetryReading $reading)
    {
        $this->reading = $reading->loadMissing('device');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sensor-data'),
            new Channel('device.' . $this->reading->device->code),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'SensorData';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->reading->id,
            'device_id' => $this->reading->device_id,
            'device_code' => $this->reading->device->code,
            'nitrogen' => (float) $this->reading->nitrogen,
            'phosphorus' => (float) $this->reading->phosphorus,
            'potassium' => (float) $this->reading->potassium,
            'ec' => (float) $this->reading->ec,
            'ph' => (float) $this->reading->ph,
            'temperature' => (float) $this->reading->temperature,
            'humidity' => (float) $this->reading->humidity,
            'threshold_flags' => $this->reading->threshold_flags,
            'recorded_at' => $this->reading->recorded_at->toIso8601String(),
        ];
    }
}
