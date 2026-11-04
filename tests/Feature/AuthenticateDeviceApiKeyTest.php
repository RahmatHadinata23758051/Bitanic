<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticateDeviceApiKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('device.key')->post('/test/iot-ingest', function () {
            $device = request()->attributes->get('device');
            return response()->json([
                'success' => true,
                'device_code' => $device->code,
            ]);
        });
    }

    public function test_it_rejects_request_without_device_key_header(): void
    {
        $response = $this->postJson('/test/iot-ingest', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Missing or invalid X-Device-Key header.',
            ]);
    }

    public function test_it_rejects_request_with_invalid_device_key(): void
    {
        $response = $this->withHeaders([
            'X-Device-Key' => 'invalid-fake-key-12345',
        ])->postJson('/test/iot-ingest', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Invalid device API key.',
            ]);
    }

    public function test_it_rejects_request_with_revoked_device_key(): void
    {
        $device = Device::create([
            'code' => 'TEST-DEV-01',
            'name' => 'Test Station',
            'is_active' => true,
        ]);

        $plainKey = 'valid-secret-key-123';
        DeviceApiKey::create([
            'device_id' => $device->id,
            'key_hash' => hash('sha256', $plainKey),
            'revoked_at' => now(), // Telah dicabut
        ]);

        $response = $this->withHeaders([
            'X-Device-Key' => $plainKey,
        ])->postJson('/test/iot-ingest', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Device API key has been revoked.',
            ]);
    }

    public function test_it_allows_request_with_valid_active_key(): void
    {
        $device = Device::create([
            'code' => 'BTLP-UPI-STATION',
            'name' => 'UPI Field Station',
            'is_active' => true,
        ]);

        $plainKey = 'super-secret-iot-token-999';
        DeviceApiKey::create([
            'device_id' => $device->id,
            'key_hash' => hash('sha256', $plainKey),
            'revoked_at' => null,
        ]);

        $response = $this->withHeaders([
            'X-Device-Key' => $plainKey,
        ])->postJson('/test/iot-ingest', []);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'device_code' => 'BTLP-UPI-STATION',
            ]);
    }
}
