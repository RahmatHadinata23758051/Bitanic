<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningAndRebootTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_api_key_hides_key_hash_and_supports_secure_generation(): void
    {
        $device = Device::create([
            'code' => 'TK-KEY-01',
            'name' => 'Meja Test Key',
            'is_active' => true,
        ]);

        $result = DeviceApiKey::generateForDevice($device);

        $this->assertArrayHasKey('plain_key', $result);
        $this->assertArrayHasKey('api_key', $result);
        $this->assertStringStartsWith('btk_', $result['plain_key']);

        /** @var DeviceApiKey $apiKey */
        $apiKey = $result['api_key'];
        $this->assertEquals(hash('sha256', $result['plain_key']), $apiKey->key_hash);
        $this->assertTrue($apiKey->isValid());

        // Ensure key_hash is hidden from array / json
        $this->assertArrayNotHasKey('key_hash', $apiKey->toArray());

        // Test revoke
        $apiKey->revoke();
        $this->assertFalse($apiKey->fresh()->isValid());
        $this->assertNotNull($apiKey->fresh()->revoked_at);
    }

    public function test_reboot_does_not_forge_online_status_or_last_seen_at_without_ack(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $oldTimestamp = now()->subHours(5);
        $device = Device::create([
            'code' => 'TK-REBOOT-01',
            'name' => 'Meja Offline',
            'status' => 'offline',
            'last_seen_at' => $oldTimestamp,
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->post(route('kits.reboot', $device->id));

        $response->assertRedirect(route('kits.index'));
        $response->assertSessionHas('warning');

        $device->refresh();
        // Crucial: status must NOT be forced to 'online' and last_seen_at must NOT be updated without hardware ACK!
        $this->assertNotEquals('online', $device->status);
        $this->assertEquals($oldTimestamp->toDateTimeString(), $device->last_seen_at->toDateTimeString());

        // Audit log must record the command
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Kirim Perintah Soft-Reboot (Simulasi)',
        ]);
    }

    public function test_import_legacy_command_blocks_fresh_in_production(): void
    {
        // Mock production environment
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('bitanic:import-legacy --fresh')
            ->assertFailed();
    }

    public function test_import_legacy_command_blocks_without_force_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('bitanic:import-legacy')
            ->assertFailed();
    }

    public function test_database_seeder_safeguard_skips_when_users_exist_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');
        User::factory()->create(['email' => 'admin.prod@bitanic.id']);

        $this->artisan('db:seed', ['--force' => true])
            ->expectsOutputToContain('Production environment dengan data pengguna aktif terdeteksi')
            ->assertSuccessful();
    }

    public function test_device_api_key_middleware_authorizes_telemetry_ingest(): void
    {
        $device = Device::create([
            'code' => 'TK-INGEST-01',
            'name' => 'Meja IoT Ingest',
            'is_active' => true,
        ]);
        $keyResult = DeviceApiKey::generateForDevice($device);
        $plainKey = $keyResult['plain_key'];

        // 1. Valid key -> 201 Created
        $response = $this->withHeaders([
            'X-Device-Key' => $plainKey,
        ])->postJson('/api/v1/telemetry/ingest', [
            'soilrs485' => [
                'Ph' => 6.5,
                'Humidity' => 70.0,
                'Temperature' => 25.5,
            ],
        ]);
        $response->assertStatus(201);

        // 2. Revoked key -> 401 Unauthorized
        $keyResult['api_key']->revoke();
        $revokedResponse = $this->withHeaders([
            'X-Device-Key' => $plainKey,
        ])->postJson('/api/v1/telemetry/ingest', [
            'soilrs485' => ['Ph' => 6.5],
        ]);
        $revokedResponse->assertStatus(401);

        // 3. Inactive device -> 401 Unauthorized
        $device->update(['is_active' => false]);
        $key2Result = DeviceApiKey::generateForDevice($device);
        $inactiveResponse = $this->withHeaders([
            'X-Device-Key' => $key2Result['plain_key'],
        ])->postJson('/api/v1/telemetry/ingest', [
            'soilrs485' => ['Ph' => 6.5],
        ]);
        $inactiveResponse->assertStatus(401);

        // 4. Missing header -> 401
        $missingResponse = $this->postJson('/api/v1/telemetry/ingest', [
            'soilrs485' => ['Ph' => 6.5],
        ]);
        $missingResponse->assertStatus(401);
    }
}
