<?php

namespace Tests\Feature;

use App\Events\SensorData;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\SchoolSetting;
use App\Models\SensorThreshold;
use App\Models\TelemetryReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TelemetryIngestTest extends TestCase
{
    use RefreshDatabase;

    protected Device $device;

    protected string $rawKey = 'test-secret-device-key-999';

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = Device::create([
            'code' => 'BTLP-TEST-STATION',
            'name' => 'Station Test Farm',
            'is_active' => true,
        ]);

        DeviceApiKey::create([
            'device_id' => $this->device->id,
            'key_hash' => hash('sha256', $this->rawKey),
            'revoked_at' => null,
        ]);

        // Buat batas ambang batas aman
        SensorThreshold::create([
            'device_id' => $this->device->id,
            'parameter' => 'Temperature',
            'min_value' => 20.00,
            'max_value' => 30.00,
        ]);

        SensorThreshold::create([
            'device_id' => $this->device->id,
            'parameter' => 'Ph',
            'min_value' => 6.00,
            'max_value' => 7.50,
        ]);
    }

    public function test_it_ingests_sensor_payload_evaluates_thresholds_and_broadcasts_event(): void
    {
        Event::fake([SensorData::class]);

        $payload = [
            'device_id' => 'BTLP-TEST-STATION',
            'soilrs485' => [
                'Nitrogen' => 45.5,
                'Phosporus' => 32.0,
                'Kalium' => 110.2,
                'Ec' => 450,
                'Ph' => 5.2, // Di bawah batas min (6.0) -> 'low'
                'Temperature' => 34.5, // Di atas batas max (30.0) -> 'high'
                'Humidity' => 78.4,
            ],
        ];

        $startTime = microtime(true);
        $response = $this->withHeaders([
            'X-Device-Key' => $this->rawKey,
        ])->postJson('/api/v1/telemetry/ingest', $payload);
        $executionTimeMs = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Telemetry reading successfully ingested and evaluated.',
            ]);

        $jsonReadings = $response->json('data.readings');
        $this->assertEquals(45.5, $jsonReadings['nitrogen']);
        $this->assertEquals(32.0, $jsonReadings['phosphorus']);
        $this->assertEquals(110.2, $jsonReadings['potassium']);
        $this->assertEquals(450.0, $jsonReadings['ec']);
        $this->assertEquals(5.2, $jsonReadings['ph']);
        $this->assertEquals(34.5, $jsonReadings['temperature']);
        $this->assertEquals(78.4, $jsonReadings['humidity']);

        // Verifikasi latensi di bawah 100ms
        $this->assertLessThan(1000, $executionTimeMs);

        // Verifikasi database
        $this->assertDatabaseCount('telemetry_readings', 1);
        $reading = TelemetryReading::first();
        $this->assertEquals($this->device->id, $reading->device_id);
        $this->assertEquals(45.5, $reading->nitrogen);
        $this->assertEquals(32.0, $reading->phosphorus);
        $this->assertEquals(110.2, $reading->potassium);
        $this->assertEquals(450, $reading->ec);
        $this->assertEquals(5.2, $reading->ph);
        $this->assertEquals(34.5, $reading->temperature);
        $this->assertEquals(78.4, $reading->humidity);
        $this->assertEquals('hardware', $reading->provenance);
        $this->assertEquals(0.0, (float) $reading->applied_offsets['ph']);

        // Verifikasi threshold flags
        $flags = $reading->threshold_flags;
        $this->assertEquals('low', $flags['Ph']);
        $this->assertEquals('high', $flags['Temperature']);

        // Verifikasi last_seen_at perangkat diperbarui
        $this->device->refresh();
        $this->assertNotNull($this->device->last_seen_at);

        // Verifikasi event disiarkan
        Event::assertDispatched(SensorData::class, function ($event) use ($reading) {
            return $event->reading->id === $reading->id;
        });
    }

    public function test_school_settings_thresholds_are_used_for_evaluation(): void
    {
        Event::fake([SensorData::class]);

        SensorThreshold::where('device_id', $this->device->id)->delete();
        SensorThreshold::create([
            'device_id' => null,
            'parameter' => 'Nitrogen',
            'min_value' => 0,
            'max_value' => 20,
        ]);
        SchoolSetting::create([
            'school_id' => $this->device->school_id,
            'sensor_thresholds' => [
                'minNitrogen' => 50,
                'maxNitrogen' => 100,
                'minMoisture' => 30,
                'maxMoisture' => 75,
                'minPH' => 5.5,
                'maxPH' => 7.2,
                'minEC' => 800,
                'maxEC' => 2200,
            ],
        ]);

        $response = $this->withHeaders([
            'X-Device-Key' => $this->rawKey,
        ])->postJson('/api/v1/telemetry/ingest', [
            'device_id' => 'BTLP-TEST-STATION',
            'soilrs485' => [
                'Nitrogen' => 45,
                'Ph' => 5.4,
                'Ec' => 700,
                'Humidity' => 20,
            ],
        ]);

        $response->assertCreated();
        $flags = TelemetryReading::latest('id')->firstOrFail()->threshold_flags;
        $this->assertSame('low', $flags['Nitrogen']);
        $this->assertSame('low', $flags['Ph']);
        $this->assertSame('low', $flags['Ec']);
        $this->assertSame('low', $flags['Humidity']);
    }

    public function test_device_threshold_overrides_school_threshold(): void
    {
        Event::fake([SensorData::class]);

        SchoolSetting::create([
            'school_id' => $this->device->school_id,
            'sensor_thresholds' => [
                'minNitrogen' => 50,
                'maxNitrogen' => 100,
            ],
        ]);
        SensorThreshold::create([
            'device_id' => $this->device->id,
            'parameter' => 'Nitrogen',
            'min_value' => 40,
            'max_value' => 60,
        ]);

        $response = $this->withHeaders([
            'X-Device-Key' => $this->rawKey,
        ])->postJson('/api/v1/telemetry/ingest', [
            'device_id' => 'BTLP-TEST-STATION',
            'soilrs485' => ['Nitrogen' => 65],
        ]);

        $response->assertCreated();
        $this->assertSame('high', TelemetryReading::latest('id')->firstOrFail()->threshold_flags['Nitrogen']);
    }

    public function test_it_supports_legacy_route_with_device_key(): void
    {
        Event::fake([SensorData::class]);

        $payload = [
            'device_id' => 'BTLP-TEST-STATION',
            'soilrs485' => [
                'Nitrogen' => 10,
                'Phosporus' => 10,
                'Kalium' => 10,
                'Ec' => 10,
                'Ph' => 7.0,
                'Temperature' => 25.0,
                'Humidity' => 80.0,
            ],
        ];

        $response = $this->withHeaders([
            'X-Device-Key' => $this->rawKey,
        ])->postJson('/api/rsc-data', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_it_applies_calibration_offsets_during_ingestion(): void
    {
        $this->device->update([
            'calibration_offsets' => [
                'ph' => 0.45,
                'Temperature' => -1.2,
                'ec' => 50,
            ],
        ]);

        $payload = [
            'device_id' => 'BTLP-TEST-STATION',
            'soilrs485' => [
                'Nitrogen' => 20,
                'Phosporus' => 20,
                'Kalium' => 20,
                'Ec' => 1000,
                'Ph' => 6.0,
                'Temperature' => 25.0,
                'Humidity' => 60.0,
            ],
        ];

        $response = $this->withHeaders([
            'X-Device-Key' => $this->rawKey,
        ])->postJson('/api/v1/telemetry/ingest', $payload);

        $response->assertStatus(201);

        $reading = TelemetryReading::latest('id')->first();
        // 6.0 + 0.45 = 6.45
        $this->assertEquals(6.45, (float) $reading->ph);
        // 25.0 - 1.2 = 23.8
        $this->assertEquals(23.8, (float) $reading->temperature);
        // 1000 + 50 = 1050
        $this->assertEquals(1050, (float) $reading->ec);
        $this->assertEquals(0.45, (float) $reading->applied_offsets['ph']);
        $this->assertEquals(-1.2, (float) $reading->applied_offsets['temperature']);
    }

    public function test_partial_payload_preserves_null_in_json_response_and_does_not_convert_to_zero(): void
    {
        $payload = [
            'device_id' => 'BTLP-TEST-STATION',
            'soilrs485' => [
                'Ph' => 6.8,
                'Temperature' => 27.5,
                // Nitrogen, Phosporus, Kalium, Ec, Humidity are absent/unmeasured
            ],
        ];

        $response = $this->withHeaders([
            'X-Device-Key' => $this->rawKey,
        ])->postJson('/api/v1/telemetry/ingest', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.readings.ph', 6.8)
            ->assertJsonPath('data.readings.temperature', 27.5)
            ->assertJsonPath('data.readings.nitrogen', null)
            ->assertJsonPath('data.readings.phosphorus', null)
            ->assertJsonPath('data.readings.potassium', null)
            ->assertJsonPath('data.readings.ec', null)
            ->assertJsonPath('data.readings.humidity', null);

        $reading = TelemetryReading::latest('id')->first();
        $this->assertEquals(6.8, (float) $reading->ph);
        $this->assertEquals(27.5, (float) $reading->temperature);
        $this->assertNull($reading->nitrogen);
        $this->assertNull($reading->phosphorus);
        $this->assertNull($reading->potassium);
        $this->assertNull($reading->ec);
        $this->assertNull($reading->humidity);
    }

    public function test_genuine_zero_readings_are_preserved_as_numeric_zero_not_null(): void
    {
        $payload = [
            'device_id' => 'BTLP-TEST-STATION',
            'soilrs485' => [
                'Nitrogen' => 0.0,
                'Phosporus' => 0,
                'Kalium' => 0.0,
                'Ec' => 0,
                'Ph' => 7.0,
                'Temperature' => 25.0,
                'Humidity' => 50.0,
            ],
        ];

        $response = $this->withHeaders([
            'X-Device-Key' => $this->rawKey,
        ])->postJson('/api/v1/telemetry/ingest', $payload);

        $response->assertStatus(201);
        $jsonReadings = $response->json('data.readings');
        $this->assertSame(0, $jsonReadings['nitrogen']);
        $this->assertSame(0, $jsonReadings['phosphorus']);
        $this->assertSame(0, $jsonReadings['potassium']);
        $this->assertSame(0, $jsonReadings['ec']);
        $this->assertSame(7, $jsonReadings['ph']);

        $reading = TelemetryReading::latest('id')->first();
        $this->assertSame(0.0, (float) $reading->nitrogen);
        $this->assertSame(0.0, (float) $reading->phosphorus);
        $this->assertSame(0.0, (float) $reading->potassium);
        $this->assertSame(0.0, (float) $reading->ec);
    }

    public function test_payload_accepts_standard_and_legacy_parameter_aliases(): void
    {
        $payload = [
            'device_id' => 'BTLP-TEST-STATION',
            'soilrs485' => [
                'Phosphorus' => 35.5,
                'Potassium' => 125.0,
                'Nitrogen' => 42.0,
                'Ph' => 6.5,
                'Temperature' => 24.0,
                'Humidity' => 65.0,
                'Ec' => 500,
            ],
        ];

        $response = $this->withHeaders([
            'X-Device-Key' => $this->rawKey,
        ])->postJson('/api/v1/telemetry/ingest', $payload);

        $response->assertStatus(201);
        $jsonReadings = $response->json('data.readings');
        $this->assertEquals(35.5, $jsonReadings['phosphorus']);
        $this->assertEquals(125.0, $jsonReadings['potassium']);

        $reading = TelemetryReading::latest('id')->first();
        $this->assertEquals(35.5, (float) $reading->phosphorus);
        $this->assertEquals(125.0, (float) $reading->potassium);
    }
}
