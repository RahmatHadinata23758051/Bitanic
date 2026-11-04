<?php

namespace Tests\Feature;

use App\Actions\Telemetry\IngestTelemetryAction;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\School;
use App\Models\User;
use App\Services\Hardware\DeviceAvailabilityResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeartbeatAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected DeviceAvailabilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(DeviceAvailabilityResolver::class);
    }

    // -------------------------------------------------------------------------
    // AC-1: Availability resolver deterministic boundary behavior (default 900s)
    // -------------------------------------------------------------------------

    public function test_device_with_null_heartbeat_is_offline(): void
    {
        $device = Device::create([
            'code' => 'TK-NULL',
            'name' => 'Trainer Kit No Heartbeat',
            'is_active' => true,
            'status' => 'online', // Stale or defaulted DB status
            'last_seen_at' => null,
        ]);

        $this->assertFalse($this->resolver->isOnline($device));
        $this->assertEquals('offline', $this->resolver->status($device));
    }

    public function test_inactive_device_is_offline_even_with_fresh_heartbeat(): void
    {
        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        $device = Device::create([
            'code' => 'TK-INACTIVE',
            'name' => 'Inactive Trainer Kit',
            'is_active' => false,
            'last_seen_at' => $now,
        ]);

        $this->assertFalse($this->resolver->isOnline($device, $now));
        $this->assertEquals('offline', $this->resolver->status($device, $now));
    }

    public function test_device_within_heartbeat_window_is_online(): void
    {
        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        // 899 seconds ago: strictly within 900s window
        $device = Device::create([
            'code' => 'TK-FRESH',
            'name' => 'Fresh Trainer Kit',
            'is_active' => true,
            'last_seen_at' => $now->copy()->subSeconds(899),
        ]);

        $this->assertTrue($this->resolver->isOnline($device, $now));
        $this->assertEquals('online', $this->resolver->status($device, $now));
    }

    public function test_device_exactly_at_heartbeat_boundary_is_online(): void
    {
        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        // Exactly 900 seconds ago (15 min boundary)
        $device = Device::create([
            'code' => 'TK-BOUNDARY',
            'name' => 'Boundary Trainer Kit',
            'is_active' => true,
            'last_seen_at' => $now->copy()->subSeconds(900),
        ]);

        $this->assertTrue($this->resolver->isOnline($device, $now));
        $this->assertEquals('online', $this->resolver->status($device, $now));
    }

    public function test_device_one_second_past_boundary_is_offline(): void
    {
        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        // 901 seconds ago: 1 second past timeout
        $device = Device::create([
            'code' => 'TK-EXPIRED',
            'name' => 'Expired Trainer Kit',
            'is_active' => true,
            'last_seen_at' => $now->copy()->subSeconds(901),
        ]);

        $this->assertFalse($this->resolver->isOnline($device, $now));
        $this->assertEquals('offline', $this->resolver->status($device, $now));
    }

    // -------------------------------------------------------------------------
    // AC-2: Configurable timeout policy per-device and per-school
    // -------------------------------------------------------------------------

    public function test_device_respects_custom_device_heartbeat_timeout(): void
    {
        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        // Device configured with short 300-second timeout
        $device = Device::create([
            'code' => 'TK-CUSTOM-TIMEOUT',
            'name' => 'Custom Timeout Kit',
            'is_active' => true,
            'connection_config' => ['heartbeat_timeout_seconds' => 300],
            'last_seen_at' => $now->copy()->subSeconds(350), // older than 300s, but within default 900s
        ]);

        $this->assertFalse($this->resolver->isOnline($device, $now));
        $this->assertEquals('offline', $this->resolver->status($device, $now));
    }

    public function test_device_respects_custom_school_heartbeat_timeout(): void
    {
        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        $school = School::create([
            'npsn' => '77777777',
            'name' => 'SMK Pertanian Mandiri',
            'slug' => 'smk-pertanian-mandiri',
            'settings' => ['heartbeat_timeout_seconds' => 600],
        ]);

        $device = Device::create([
            'school_id' => $school->id,
            'code' => 'TK-SCHOOL-TIMEOUT',
            'name' => 'School Timeout Kit',
            'is_active' => true,
            'last_seen_at' => $now->copy()->subSeconds(700), // > 600s school setting
        ]);

        $this->assertFalse($this->resolver->isOnline($device, $now));
        $this->assertEquals('offline', $this->resolver->status($device, $now));
    }

    // -------------------------------------------------------------------------
    // AC-3: Recovered device immediately becomes online upon new heartbeat
    // -------------------------------------------------------------------------

    public function test_stale_device_recovers_to_online_when_authenticated_heartbeat_arrives(): void
    {
        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        $device = Device::create([
            'code' => 'TK-RECOVER',
            'name' => 'Recoverable Kit',
            'is_active' => true,
            'last_seen_at' => $now->copy()->subHours(2), // stale
        ]);

        $this->assertFalse($this->resolver->isOnline($device, $now));

        // Authenticated hardware payload arrives at 10:05
        $recoveryTime = $now->copy()->addMinutes(5);
        Carbon::setTestNow($recoveryTime);

        app(IngestTelemetryAction::class)->execute($device, [
            'soilrs485' => [
                'Ph' => 6.5,
                'Humidity' => 60.0,
                'Ec' => 1200,
            ],
        ], provenance: 'hardware');

        $device->refresh();

        $this->assertTrue($this->resolver->isOnline($device, $recoveryTime));
        $this->assertEquals('online', $this->resolver->status($device, $recoveryTime));
    }

    // -------------------------------------------------------------------------
    // AC-4: Heartbeat timestamp updated ONLY by authenticated device traffic
    // -------------------------------------------------------------------------

    public function test_simulation_telemetry_does_not_update_device_heartbeat_timestamp(): void
    {
        $oldTime = Carbon::parse('2026-11-05 08:00:00');
        $device = Device::create([
            'code' => 'TK-SIM-GUARD',
            'name' => 'Simulation Guard Kit',
            'is_active' => true,
            'last_seen_at' => $oldTime,
        ]);

        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        // Simulation ingestion
        app(IngestTelemetryAction::class)->execute($device, [
            'soilrs485' => ['Ph' => 6.2],
        ], provenance: 'simulation');

        $device->refresh();

        // last_seen_at must remain the old time!
        $this->assertEquals($oldTime->toDateTimeString(), $device->last_seen_at->toDateTimeString());
        $this->assertFalse($this->resolver->isOnline($device, $now));
    }

    public function test_fixture_telemetry_does_not_update_device_heartbeat_timestamp(): void
    {
        $oldTime = Carbon::parse('2026-11-05 08:00:00');
        $device = Device::create([
            'code' => 'TK-FIXTURE-GUARD',
            'name' => 'Fixture Guard Kit',
            'is_active' => true,
            'last_seen_at' => $oldTime,
        ]);

        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        // Fixture ingestion
        app(IngestTelemetryAction::class)->execute($device, [
            'soilrs485' => ['Ph' => 6.4],
        ], provenance: 'fixture');

        $device->refresh();

        // last_seen_at must remain the old time!
        $this->assertEquals($oldTime->toDateTimeString(), $device->last_seen_at->toDateTimeString());
        $this->assertFalse($this->resolver->isOnline($device, $now));
    }

    // -------------------------------------------------------------------------
    // AC-5: Dashboard, IoT Monitoring, and Trainer Kit Manager share one source
    // -------------------------------------------------------------------------

    public function test_all_pages_render_consistent_online_count_and_status(): void
    {
        $now = Carbon::parse('2026-11-05 10:00:00');
        Carbon::setTestNow($now);

        $teacher = User::factory()->create(['role' => 'guru']);

        // Kit 1: Online (seen 2 mins ago)
        $kit1 = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-SYNC-01',
            'name' => 'Online Kit',
            'is_active' => true,
            'status' => 'offline', // Persisted DB status is wrong/stale
            'last_seen_at' => $now->copy()->subMinutes(2),
        ]);

        // Kit 2: Stale Offline (seen 20 mins ago)
        $kit2 = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-SYNC-02',
            'name' => 'Stale Kit',
            'is_active' => true,
            'status' => 'online', // Persisted DB status claims online falsely
            'last_seen_at' => $now->copy()->subMinutes(20),
        ]);

        // 1. Dashboard: should report 1 online out of 2 total
        $dashboardRes = $this->actingAs($teacher)->get('/');
        $dashboardRes->assertOk();
        $dashboardRes->assertSee('1</span>', false); // 1 Meja Online
        $dashboardRes->assertSee('/ 2 Meja Online', false);

        // 2. Trainer Kit Manager: stats online must be 1, not 2!
        $kitsRes = $this->actingAs($teacher)->get('/trainer-kits');
        $kitsRes->assertOk();
        $kitsRes->assertViewHas('stats', function ($stats) {
            return $stats['total'] === 2 && $stats['online'] === 1;
        });

        // 3. IoT Monitoring: Kit 2 must resolve to offline
        $iotRes = $this->actingAs($teacher)->get('/iot-monitoring?device_id=' . $kit2->id);
        $iotRes->assertOk();
        $iotRes->assertSee('Offline');
    }

    public function test_legacy_import_does_not_forge_heartbeat_timestamp(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('bitanic:import-legacy');
        $this->assertSame(0, $exitCode);

        $device = Device::where('code', 'BTLP-UPI')->firstOrFail();
        $this->assertNull($device->last_seen_at);
        $this->assertFalse($this->resolver->isOnline($device));
    }
}
