<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TelemetryReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_successfully(): void
    {
        $device = Device::create([
            'code' => 'BITANIC-TK-01',
            'name' => 'BITANIC Precision Agritech Kit #01',
            'connection_type' => 'modbus_rs485',
            'is_active' => true,
        ]);

        TelemetryReading::create([
            'device_id' => $device->id,
            'nitrogen' => 65.5,
            'phosphorus' => 32.0,
            'potassium' => 140.0,
            'ec' => 1420.0,
            'ph' => 6.5,
            'temperature' => 26.5,
            'humidity' => 45.0,
            'raw_payload' => ['sample' => 'test'],
            'recorded_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('BITANIC Precision Agritech Kit #01');
        $response->assertSee('Derajat Keasaman (pH)');
        $response->assertSee('Konduktivitas Listrik (EC)');
        $response->assertSee('Kadar Lengas Tanah');
        $response->assertSee('Glosarium');
    }

    public function test_relay_toggle_endpoint_updates_state(): void
    {
        $response = $this->postJson('/api/v1/devices/relay/toggle', [
            'relay_key' => 'irrigationPump',
            'state' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'relay_key' => 'irrigationPump',
            'state' => true,
        ]);
    }
}
