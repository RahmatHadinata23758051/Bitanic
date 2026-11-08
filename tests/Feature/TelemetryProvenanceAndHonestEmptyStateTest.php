<?php

namespace Tests\Feature;

use App\Actions\Telemetry\IngestTelemetryAction;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryProvenanceAndHonestEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // AC-1: Empty Database renders honest empty state without fake numbers
    // -------------------------------------------------------------------------

    public function test_empty_database_renders_honest_empty_state_without_fake_metrics(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);

        // 1. Dashboard on empty DB
        $dashboardResponse = $this->actingAs($teacher)->get('/');
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Dashboard');
        $dashboardResponse->assertSee('Belum ada meja terdaftar');
        $dashboardResponse->assertSee('Belum Ada Meja Praktikum IoT Terdaftar');
        $dashboardResponse->assertDontSee('6.50');
        $dashboardResponse->assertDontSee('45.0%');
        $dashboardResponse->assertDontSee('1400');

        // 2. IoT Monitoring on empty DB
        $iotResponse = $this->actingAs($teacher)->get('/iot-monitoring');
        $iotResponse->assertOk();
        $iotResponse->assertSee('Belum Ada Transmisi Data');
        $iotResponse->assertSee('Tidak ada Trainer Kit terdaftar');
        $iotResponse->assertDontSee('Streaming Modbus Aktif');
        $iotResponse->assertDontSee('BITANIC Precision Agritech Kit #01'); // fake fallback object must not exist!
    }

    // -------------------------------------------------------------------------
    // AC-2: Inactive device renders cleanly without fake telemetry
    // -------------------------------------------------------------------------

    public function test_inactive_device_renders_without_fake_telemetry_or_live_indicator(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-DISABLED',
            'name' => 'Meja Rusak Nonaktif',
            'is_active' => false,
            'status' => 'offline',
        ]);

        $response = $this->actingAs($teacher)->get('/iot-monitoring?device_id='.$device->id);

        $response->assertOk();
        $response->assertSee('TK-DISABLED');
        $response->assertSee('Belum Ada Transmisi Data');
        $response->assertDontSee('Streaming Modbus Aktif');
    }

    // -------------------------------------------------------------------------
    // AC-3: Unknown device ID falls back cleanly without fake data
    // -------------------------------------------------------------------------

    public function test_unknown_device_id_falls_back_to_first_active_device_honestly(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $activeKit = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-REAL-ACTIVE',
            'name' => 'Meja Aktif Nyata',
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->get('/iot-monitoring?device_id=999999');

        $response->assertOk();
        $response->assertSee('TK-REAL-ACTIVE');
        $response->assertSee('Belum Ada Transmisi Data');
    }

    // -------------------------------------------------------------------------
    // AC-4: Authenticated hardware ingress sets hardware provenance
    // -------------------------------------------------------------------------

    public function test_authenticated_device_ingress_sets_hardware_provenance(): void
    {
        $device = Device::create([
            'code' => 'TK-HW-01',
            'name' => 'Hardware Kit 01',
            'is_active' => true,
        ]);

        $rawKey = 'hardware-device-key-abc';
        DeviceApiKey::create([
            'device_id' => $device->id,
            'key_hash' => hash('sha256', $rawKey),
        ]);

        $response = $this->withHeaders([
            'X-Device-Key' => $rawKey,
        ])->postJson('/api/v1/telemetry/ingest', [
            'device_id' => $device->code,
            'soilrs485' => [
                'Ph' => 6.8,
                'Humidity' => 62.0,
                'Ec' => 1350,
                'Nitrogen' => 50,
                'Phosporus' => 30,
                'Kalium' => 120,
                'Temperature' => 25.5,
            ],
        ]);

        $response->assertStatus(201);

        $reading = TelemetryReading::where('device_id', $device->id)->latest('id')->firstOrFail();
        $this->assertEquals('hardware', $reading->provenance);
        $this->assertTrue($reading->isHardware());
        $this->assertFalse($reading->isSimulation());
        $this->assertFalse($reading->isFixture());

        // Hardware ingress also updates last_seen_at
        $device->refresh();
        $this->assertNotNull($device->last_seen_at);
    }

    // -------------------------------------------------------------------------
    // AC-5: Simulation provenance is distinct and does not default to hardware
    // -------------------------------------------------------------------------

    public function test_simulation_artisan_command_sets_simulation_provenance(): void
    {
        $device = Device::create([
            'code' => 'TK-SIM-01',
            'name' => 'Simulation Station',
            'is_active' => true,
        ]);

        $this->artisan('bitanic:simulate-telemetry --count=1 --device=TK-SIM-01')
            ->assertSuccessful();

        $reading = TelemetryReading::where('device_id', $device->id)->latest('id')->firstOrFail();
        $this->assertEquals('simulation', $reading->provenance);
        $this->assertTrue($reading->isSimulation());
        $this->assertFalse($reading->isHardware());
    }

    public function test_direct_action_without_provenance_defaults_to_fixture(): void
    {
        $device = Device::create([
            'code' => 'TK-FIXTURE-01',
            'name' => 'Fixture Station',
            'is_active' => true,
        ]);

        // When tests or seeders call execute without passing provenance, it must NOT default to hardware!
        $reading = app(IngestTelemetryAction::class)->execute($device, [
            'soilrs485' => [
                'Ph' => 6.0,
            ],
        ]);

        $this->assertEquals('fixture', $reading->provenance);
        $this->assertTrue($reading->isFixture());
        $this->assertFalse($reading->isHardware());
    }

    // -------------------------------------------------------------------------
    // AC-6: UI labels simulation/fixture data distinctly
    // -------------------------------------------------------------------------

    public function test_ui_labels_simulation_and_fixture_data_distinctly(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-PROV-LABELS',
            'name' => 'Meja Label Provenance',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        // Create simulation reading
        TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'simulation',
            'ph' => 6.4,
            'humidity' => 55.0,
            'ec' => 1100,
            'recorded_at' => now(),
        ]);

        // 1. Dashboard displays simulation badge
        $dashRes = $this->actingAs($teacher)->get('/');
        $dashRes->assertOk();
        $dashRes->assertSee('Simulasi');

        // 2. Monitoring displays simulation badge
        $iotRes = $this->actingAs($teacher)->get('/iot-monitoring?device_id='.$device->id);
        $iotRes->assertOk();
        $iotRes->assertSee('Simulasi');

        // 3. Telemetry history displays provenance column
        $historyRes = $this->actingAs($teacher)->get('/telemetry/history');
        $historyRes->assertOk();
        $historyRes->assertSee('Asal Data');
        $historyRes->assertSee('Simulasi');
    }

    // -------------------------------------------------------------------------
    // AC-7: Honest empty state renders em dash when no telemetry exists
    // -------------------------------------------------------------------------

    public function test_device_with_no_reading_renders_em_dash_and_explicit_not_received_message(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-NO-READINGS',
            'name' => 'Meja Tanpa Telemetri',
            'is_active' => true,
        ]);

        $dashRes = $this->actingAs($teacher)->get('/');
        $dashRes->assertOk();
        $dashRes->assertSee('TK-NO-READINGS');
        $dashRes->assertSee('Belum Ada Transmisi Data');

        $iotRes = $this->actingAs($teacher)->get('/iot-monitoring?device_id='.$device->id);
        $iotRes->assertOk();
        $iotRes->assertSee('Belum Ada Transmisi Data');
        $iotRes->assertDontSee('Streaming Modbus Aktif');
    }

    // -------------------------------------------------------------------------
    // AC-8: Live stream indicator requires hardware provenance
    // -------------------------------------------------------------------------

    public function test_fresh_simulation_telemetry_does_not_claim_hardware_modbus_streaming(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-SIM-STREAM',
            'name' => 'Meja Simulasi Stream',
            'is_active' => true,
            'last_seen_at' => now(), // online
        ]);

        TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'simulation',
            'ph' => 6.5,
            'humidity' => 50.0,
            'ec' => 1200,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get('/iot-monitoring?device_id='.$device->id);
        $response->assertOk();
        $response->assertDontSee('Streaming Modbus Aktif');
        $response->assertSee('Simulasi');
    }

    public function test_fresh_hardware_telemetry_displays_active_modbus_streaming(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-HW-STREAM',
            'name' => 'Meja Hardware Stream',
            'is_active' => true,
            'last_seen_at' => now(), // online
        ]);

        TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'hardware',
            'ph' => 6.5,
            'humidity' => 50.0,
            'ec' => 1200,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get('/iot-monitoring?device_id='.$device->id);
        $response->assertOk();
        $response->assertSee('Streaming Modbus Aktif');
        $response->assertSee('Hardware');
    }

    // -------------------------------------------------------------------------
    // AC-9: Multi-school isolation for Dashboard & Telemetry History queries
    // -------------------------------------------------------------------------

    public function test_dashboard_and_telemetry_history_are_scoped_to_user_school(): void
    {
        $schoolA = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );
        $schoolB = School::create([
            'npsn' => '20200099',
            'name' => 'SMK B Isolated',
            'slug' => 'smk-b-isolated',
            'status' => 'active',
        ]);

        $teacherA = User::factory()->create(['role' => 'guru', 'school_id' => $schoolA->id]);
        $teacherB = User::factory()->create(['role' => 'guru', 'school_id' => $schoolB->id]);

        $deviceA = Device::create([
            'school_id' => $schoolA->id,
            'code' => 'TK-SCH-A-ISOLATED',
            'name' => 'Kit Sukaraja Alpha',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        $deviceB = Device::create([
            'school_id' => $schoolB->id,
            'code' => 'TK-SCH-B-ISOLATED',
            'name' => 'Kit Isolated Beta',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        TelemetryReading::create([
            'device_id' => $deviceA->id,
            'provenance' => 'fixture',
            'ph' => 6.1,
            'recorded_at' => now(),
        ]);
        TelemetryReading::create([
            'device_id' => $deviceB->id,
            'provenance' => 'fixture',
            'ph' => 7.1,
            'recorded_at' => now(),
        ]);

        // Dashboard scoping
        $dashResA = $this->actingAs($teacherA)->get('/');
        $dashResA->assertOk();
        $dashResA->assertSee('TK-SCH-A-ISOLATED');
        $dashResA->assertDontSee('TK-SCH-B-ISOLATED');

        $dashResB = $this->actingAs($teacherB)->get('/');
        $dashResB->assertOk();
        $dashResB->assertSee('TK-SCH-B-ISOLATED');
        $dashResB->assertDontSee('TK-SCH-A-ISOLATED');

        // History scoping
        $histResA = $this->actingAs($teacherA)->get('/telemetry/history');
        $histResA->assertOk();
        $histResA->assertSee('TK-SCH-A-ISOLATED');
        $histResA->assertDontSee('TK-SCH-B-ISOLATED');
    }

    // -------------------------------------------------------------------------
    // AC-10: Removal of fake fallback metrics in kits view and dashboard
    // -------------------------------------------------------------------------

    public function test_trainer_kits_page_and_dashboard_do_not_render_fake_battery_or_network_fallbacks(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-CLEAN-METRICS',
            'name' => 'Meja Bersih Tanpa Metrik Palsu',
            'is_active' => true,
            'ip_address' => null,
            'mac_address' => null,
            'firmware_version' => null,
            'calibration_offsets' => null,
        ]);

        // 1. Dashboard must not fabricate 90% battery or 192.168.10.x IP
        $dashRes = $this->actingAs($teacher)->get('/');
        $dashRes->assertOk();
        $dashRes->assertSee('TK-CLEAN-METRICS');
        $dashRes->assertDontSee('192.168.10.x');

        // 2. Trainer Kit fleet manager must not fabricate 90% battery, -55 dBm, 192.168.10.100, etc.
        $kitRes = $this->actingAs($teacher)->get('/trainer-kits');
        $kitRes->assertOk();
        $kitRes->assertSee('TK-CLEAN-METRICS');
        $kitRes->assertDontSee('192.168.10.100');
        $kitRes->assertDontSee('24:0A:C4:00:00:00');
        $kitRes->assertDontSee('v2.4.1-PROD');
    }

    public function test_iot_monitoring_table_header_displays_asal_data(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-HEADER-TEST',
            'name' => 'Meja Uji Header',
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->get('/iot-monitoring?device_id='.$device->id);
        $response->assertOk();
        $response->assertSee('Asal Data');
    }

    public function test_iot_monitoring_without_active_device_disables_relay_controls_cleanly(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);

        $response = $this->actingAs($teacher)->get('/iot-monitoring');
        $response->assertOk();
        $response->assertSee('Mode Monitor');
        $response->assertDontSee('device_id: "1"');
    }

    public function test_guest_telemetry_pages_only_expose_default_active_school_data(): void
    {
        $defaultSchool = School::where('status', 'active')->orderBy('id')->firstOrFail();
        $otherSchool = School::create([
            'npsn' => '99999111',
            'name' => 'Sekolah Lain',
            'slug' => 'sekolah-lain-guest-test',
            'status' => 'active',
        ]);

        $defaultDevice = Device::create([
            'school_id' => $defaultSchool->id,
            'code' => 'TK-GUEST-DEFAULT',
            'name' => 'Default Guest Kit',
            'is_active' => true,
        ]);
        $otherDevice = Device::create([
            'school_id' => $otherSchool->id,
            'code' => 'TK-GUEST-OTHER',
            'name' => 'Other School Guest Kit',
            'is_active' => true,
        ]);

        foreach ([[$defaultDevice, '6.2'], [$otherDevice, '7.4']] as [$device, $ph]) {
            TelemetryReading::create([
                'device_id' => $device->id,
                'provenance' => 'fixture',
                'ph' => $ph,
                'recorded_at' => now(),
            ]);
        }

        // Guest dashboard is a product introduction, not an operational fleet view
        $this->get('/')->assertOk()
            ->assertSee('Dashboard')
            ->assertDontSee('TK-GUEST-DEFAULT')
            ->assertDontSee('TK-GUEST-OTHER');

        // IoT Monitoring and Telemetry History still scope to default school
        $this->get('/iot-monitoring')->assertOk()
            ->assertSee('TK-GUEST-DEFAULT')
            ->assertDontSee('TK-GUEST-OTHER');

        $this->get('/telemetry/history')->assertOk()
            ->assertSee('TK-GUEST-DEFAULT')
            ->assertDontSee('TK-GUEST-OTHER');

        $export = $this->get('/telemetry/history/export')->assertOk();
        $this->assertStringContainsString('TK-GUEST-DEFAULT', $export->streamedContent());
        $this->assertStringNotContainsString('TK-GUEST-OTHER', $export->streamedContent());
    }

    public function test_absent_sensor_metrics_remain_null_instead_of_becoming_zero(): void
    {
        $device = Device::create([
            'code' => 'TK-PARTIAL-PAYLOAD',
            'name' => 'Kit Partial Payload',
            'is_active' => true,
        ]);

        $reading = app(IngestTelemetryAction::class)->execute($device, [
            'soilrs485' => ['Ph' => 6.2],
        ], provenance: 'fixture');

        $this->assertSame(6.2, (float) $reading->ph);
        $this->assertNull($reading->ec);
        $this->assertNull($reading->humidity);
        $this->assertNull($reading->temperature);
        $this->assertNull($reading->nitrogen);
        $this->assertNull($reading->phosphorus);
        $this->assertNull($reading->potassium);
    }

    public function test_telemetry_history_renders_null_parameters_as_em_dashes_not_zeroes(): void
    {
        $device = Device::create([
            'code' => 'TK-NULL-HISTORY',
            'name' => 'Kit Null History',
            'is_active' => true,
        ]);
        TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'fixture',
            'raw_payload' => ['sensor' => 'values-absent'],
            'recorded_at' => now(),
        ]);

        $response = $this->get('/telemetry/history')->assertOk();
        $response->assertSee('TK-NULL-HISTORY');
        $response->assertDontSee('0.0%');
        $response->assertDontSee('0.0°C');
        $response->assertDontSee('>0.0<', false);
        $response->assertSee('—');
    }
}
