<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TelemetryReading;
use Database\Seeders\TrainerKitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulateSensorTelemetryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TrainerKitSeeder::class);
    }

    public function test_simulation_command_generates_telemetry_readings(): void
    {
        $this->artisan('bitanic:simulate-telemetry --count=10')
            ->assertSuccessful()
            ->expectsOutputToContain('Simulasi berhasil diselesaikan');

        $this->assertEquals(10, TelemetryReading::count());
    }

    public function test_simulation_command_supports_specific_device_filter(): void
    {
        $targetDevice = Device::where('code', 'TK-01')->firstOrFail();

        $this->artisan('bitanic:simulate-telemetry --count=5 --device=TK-01')
            ->assertSuccessful();

        $readings = TelemetryReading::where('device_id', $targetDevice->id)->get();
        $this->assertCount(5, $readings);
    }

    public function test_simulation_command_injects_anomalies_when_flagged(): void
    {
        $this->artisan('bitanic:simulate-telemetry --count=15 --anomaly')
            ->assertSuccessful();

        // Must have at least some readings with non-empty threshold flags or out-of-range pH
        $anomalyCount = TelemetryReading::where(function ($q) {
            $q->where('ph', '<', 5.0)
              ->orWhere('ec', '>', 2400)
              ->orWhere('humidity', '<', 40.0);
        })->count();

        $this->assertGreaterThan(0, $anomalyCount);
    }
}
