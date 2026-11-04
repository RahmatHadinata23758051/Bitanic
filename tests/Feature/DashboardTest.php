<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TelemetryReading;
use App\Models\User;
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
        $response->assertSee('Pusat Kendali Laboratorium Vokasi IoT');
        $response->assertSee('Armada Trainer Kit');
    }

    public function test_iot_monitoring_page_renders_real_time_workstation(): void
    {
        $device = Device::create([
            'code' => 'BITANIC-TK-01',
            'name' => 'BITANIC Precision Agritech Kit #01',
            'bench_location' => 'Meja Praktik 01 - Lahan Uji',
            'connection_type' => 'modbus_rs485',
            'protocol' => 'Modbus RS485',
            'firmware_version' => 'v2.4.1',
            'ip_address' => '192.168.10.101',
            'status' => 'online',
            'is_active' => true,
            'relays_state' => ['irrigationPump' => false],
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

        $response = $this->get('/iot-monitoring');

        $response->assertStatus(200);
        $response->assertSee('Monitoring Telemetri Lahan & Trainer Kit Vokasi', false);
        $response->assertSee('v2.4.1');
        $response->assertSee('Derajat Keasaman (pH)');
        $response->assertSee('Konduktivitas Listrik (EC)');
        $response->assertSee('Glosarium');
    }

    public function test_iot_monitoring_shows_explicit_empty_state_when_no_readings_exist(): void
    {
        Device::create([
            'code' => 'TK-NO-DATA',
            'name' => 'Trainer Kit Belum Aktif',
            'connection_type' => 'modbus_rs485',
            'status' => 'online',
            'is_active' => true,
        ]);

        $response = $this->get('/iot-monitoring');

        $response->assertOk();
        $response->assertSee('Belum Ada Transmisi Data');
        $response->assertSee('Menunggu Telemetri');
        $response->assertDontSee('68.2');
        $response->assertDontSee('26.8');
        $response->assertDontSee('1,420');
    }

    public function test_iot_monitoring_marks_device_offline_after_fifteen_minute_heartbeat_timeout(): void
    {
        Device::create([
            'code' => 'TK-STALE',
            'name' => 'Trainer Kit Stale',
            'connection_type' => 'modbus_rs485',
            'status' => 'online', // stale persisted status must not override heartbeat
            'last_seen_at' => now()->subMinutes(16),
            'is_active' => true,
        ]);

        $response = $this->get('/iot-monitoring');

        $response->assertOk();
        $response->assertSee('Offline');
    }

    public function test_analytics_route_renders_the_analytics_page(): void
    {
        $response = $this->get('/analytics');

        $response->assertStatus(200);
        $response->assertSee('Analitik Agronomi');
    }

    public function test_dashboard_displays_lab_overview_separate_from_sensor_dashboard(): void
    {
        Device::create([
            'code' => 'TK-DASH',
            'name' => 'Dashboard Trainer Kit',
            'connection_type' => 'modbus_rs485',
            'status' => 'online',
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Pusat Kendali Laboratorium Vokasi IoT');
        $response->assertSee('Armada Trainer Kit');
        $response->assertDontSee('Parameter Sensor Tanah 7-in-1');
    }

    public function test_practicum_reports_route_renders_report_hub(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'guru']))->get('/reports');

        $response->assertStatus(200);
        $response->assertSee('Hub Laporan Lembar Kerja Siswa (LKS)');
    }

    public function test_trainer_kits_route_renders_hardware_fleet(): void
    {
        $response = $this->get('/trainer-kits');

        $response->assertStatus(200);
        $response->assertSee('Manajemen Armada Trainer Kit IoT');
    }

    public function test_analytics_selects_device_from_available_active_devices(): void
    {
        $device = Device::create([
            'code' => 'TK-ANALYTICS',
            'name' => 'Analytics Trainer Kit',
            'connection_type' => 'modbus_rs485',
            'status' => 'online',
            'is_active' => true,
        ]);

        $response = $this->get('/analytics?device_id=' . $device->id);

        $response->assertOk();
        $response->assertSee('TK-ANALYTICS');
    }

    public function test_iot_monitoring_unknown_device_id_falls_back_to_first_active_kit(): void
    {
        Device::create([
            'code' => 'TK-FIRST',
            'name' => 'First Active Trainer Kit',
            'connection_type' => 'modbus_rs485',
            'status' => 'online',
            'is_active' => true,
        ]);

        $response = $this->get('/iot-monitoring?device_id=999999');

        $response->assertOk();
        $response->assertSee('TK-FIRST');
    }

    public function test_empty_database_renders_all_separated_primary_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('Pusat Kendali Laboratorium Vokasi IoT');
        $this->get('/iot-monitoring')->assertOk()->assertSee('Monitoring Telemetri Lahan & Trainer Kit Vokasi', false);
        $this->get('/analytics')->assertOk()->assertSee('Analitik Agronomi');
    }

    public function test_relay_toggle_endpoint_rejects_unauthenticated_requests(): void
    {
        $response = $this->postJson('/api/v1/devices/relay/toggle', [
            'relay_key' => 'irrigationPump',
            'state' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_relay_toggle_endpoint_updates_state_for_authorized_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);

        $response = $this->actingAs($teacher)->postJson('/api/v1/devices/relay/toggle', [
            'relay_key' => 'irrigationPump',
            'state' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_simulation' => true,
            'hardware_ack' => false,
            'relay_key' => 'irrigationPump',
            'state' => true,
        ]);
    }
}
