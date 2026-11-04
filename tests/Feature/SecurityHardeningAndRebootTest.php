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
        $this->assertSame(substr($result['plain_key'], 0, 12), $apiKey->key_prefix);

        // Ensure key_hash is hidden from array / json
        $this->assertArrayNotHasKey('key_hash', $apiKey->toArray());

        // Rotation revokes all active keys, returns a distinct secret, and audits the actor.
        $teacher = User::factory()->create(['role' => 'superuser']);
        $secondKey = DeviceApiKey::generateForDevice($device);
        $rotation = DeviceApiKey::rotateForDevice($device, $teacher);

        $this->assertNotSame($secondKey['plain_key'], $rotation['plain_key']);
        $this->assertSame(hash('sha256', $rotation['plain_key']), $rotation['api_key']->key_hash);
        $this->assertSame(substr($rotation['plain_key'], 0, 12), $rotation['api_key']->key_prefix);
        $this->assertNotNull($apiKey->fresh()->revoked_at);
        $this->assertNotNull($secondKey['api_key']->fresh()->revoked_at);
        $this->assertNull($rotation['api_key']->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $teacher->id,
            'action' => 'Rotasi Kunci API Perangkat',
        ]);

        // Test revoke
        $apiKey->refresh();
        $apiKey->revoke();
        $this->assertFalse($apiKey->fresh()->isValid());
        $this->assertNotNull($apiKey->fresh()->revoked_at);
    }

    public function test_superuser_can_rotate_device_api_key_and_other_roles_cannot(): void
    {
        $device = Device::create([
            'code' => 'TK-KEY-ROTATE-01',
            'name' => 'Meja Test Rotasi',
            'is_active' => true,
        ]);
        $activeKey = DeviceApiKey::generateForDevice($device);
        $teacher = User::factory()->create(['role' => 'guru']);
        $superuser = User::factory()->create(['role' => 'superuser']);

        $this->actingAs($teacher)
            ->post(route('kits.api-key.rotate', $device))
            ->assertForbidden();
        $this->assertNull($activeKey['api_key']->fresh()->revoked_at);

        $rotateResponse = $this->actingAs($superuser)
            ->post(route('kits.api-key.rotate', $device));
        $rotateResponse->assertRedirect(route('kits.index'))
            ->assertSessionHas('new_api_key');
        $newPlainKey = $rotateResponse->getSession()->get('new_api_key');
        $successMessage = $rotateResponse->getSession()->get('success');
        $this->assertStringNotContainsString($newPlainKey, $successMessage);

        $this->get(route('kits.index'))
            ->assertSee($newPlainKey)
            ->assertSee(substr($newPlainKey, 0, 12))
            ->assertSee(route('kits.api-key.rotate', $device), false)
            ->assertDontSee($activeKey['plain_key']);
        $this->get(route('kits.index'))->assertDontSee($newPlainKey);

        $this->assertNotNull($activeKey['api_key']->fresh()->revoked_at);
        $this->assertSame(1, DeviceApiKey::query()
            ->where('device_id', $device->id)
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_device_api_key_middleware_tracks_successful_usage_only(): void
    {
        $device = Device::create([
            'code' => 'TK-KEY-USE-01',
            'name' => 'Meja Test Penggunaan',
            'is_active' => true,
        ]);
        $keyResult = DeviceApiKey::generateForDevice($device);

        $this->travel(10)->minutes();
        $this->withHeader('X-Device-Key', $keyResult['plain_key'])
            ->postJson('/api/v1/telemetry/ingest', ['soilrs485' => ['Ph' => 6.5]])
            ->assertStatus(201);
        $this->assertSame(now()->toDateTimeString(), $keyResult['api_key']->fresh()->last_used_at->toDateTimeString());

        $lastUsedAt = $keyResult['api_key']->fresh()->last_used_at;
        $this->withHeader('X-Device-Key', 'invalid-key')
            ->postJson('/api/v1/telemetry/ingest', ['soilrs485' => ['Ph' => 6.5]])
            ->assertUnauthorized();
        $this->assertEquals($lastUsedAt->toDateTimeString(), $keyResult['api_key']->fresh()->last_used_at->toDateTimeString());
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
