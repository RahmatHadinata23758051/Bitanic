<?php

namespace Tests\Feature;

use App\Actions\Telemetry\ClearTelemetryAction;
use App\Models\Device;
use App\Models\TelemetryReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_telemetry_only_for_specified_device_without_affecting_other_devices(): void
    {
        $deviceA = Device::create(['code' => 'DEV-A', 'name' => 'Device A', 'is_active' => true]);
        $deviceB = Device::create(['code' => 'DEV-B', 'name' => 'Device B', 'is_active' => true]);

        // 3 readings for Device A
        for ($i = 0; $i < 3; $i++) {
            TelemetryReading::create([
                'device_id' => $deviceA->id,
                'nitrogen' => 10,
                'phosphorus' => 10,
                'potassium' => 10,
                'ec' => 10,
                'ph' => 7,
                'temperature' => 25,
                'humidity' => 80,
                'raw_payload' => [],
                'recorded_at' => now(),
            ]);
        }

        // 2 readings for Device B
        for ($i = 0; $i < 2; $i++) {
            TelemetryReading::create([
                'device_id' => $deviceB->id,
                'nitrogen' => 20,
                'phosphorus' => 20,
                'potassium' => 20,
                'ec' => 20,
                'ph' => 7,
                'temperature' => 25,
                'humidity' => 80,
                'raw_payload' => [],
                'recorded_at' => now(),
            ]);
        }

        $this->assertDatabaseCount('telemetry_readings', 5);

        // Execute clear action only for Device A
        $action = new ClearTelemetryAction;
        $deletedCount = $action->execute($deviceA);

        $this->assertEquals(3, $deletedCount);

        // Verifikasi bahwa Device A bersih, tetapi Device B tetap memiliki 2 baris data utuh!
        $this->assertEquals(0, TelemetryReading::where('device_id', $deviceA->id)->count());
        $this->assertEquals(2, TelemetryReading::where('device_id', $deviceB->id)->count());
    }

    public function test_it_deletes_telemetry_within_specific_date_range_only(): void
    {
        $device = Device::create(['code' => 'DEV-A', 'name' => 'Device A', 'is_active' => true]);

        // Reading yesterday
        TelemetryReading::create([
            'device_id' => $device->id,
            'nitrogen' => 10,
            'phosphorus' => 10,
            'potassium' => 10,
            'ec' => 10,
            'ph' => 7,
            'temperature' => 25,
            'humidity' => 80,
            'raw_payload' => [],
            'recorded_at' => now()->subDays(2),
        ]);

        // Reading today
        TelemetryReading::create([
            'device_id' => $device->id,
            'nitrogen' => 10,
            'phosphorus' => 10,
            'potassium' => 10,
            'ec' => 10,
            'ph' => 7,
            'temperature' => 25,
            'humidity' => 80,
            'raw_payload' => [],
            'recorded_at' => now(),
        ]);

        $this->assertDatabaseCount('telemetry_readings', 2);

        // Delete only data older than yesterday
        $action = new ClearTelemetryAction;
        $deletedCount = $action->execute($device, [
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $this->assertEquals(1, $deletedCount);
        $this->assertEquals(1, TelemetryReading::where('device_id', $device->id)->count());
    }
}
