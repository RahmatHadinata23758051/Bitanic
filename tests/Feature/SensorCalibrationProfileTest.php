<?php

namespace Tests\Feature;

use App\Actions\Hardware\CreateSensorCalibrationProfileAction;
use App\Actions\Telemetry\IngestTelemetryAction;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\School;
use App\Models\SensorCalibrationProfile;
use App\Models\TelemetryReading;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SensorCalibrationProfileTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // AC-1: Documented Sensor Specifications & Register Mapping
    // -------------------------------------------------------------------------

    public function test_sensor_specification_catalog_contains_documented_registers_units_and_ranges(): void
    {
        $models = config('sensors.models');
        $this->assertIsArray($models);
        $this->assertArrayHasKey('RS485-SOIL-7IN1', $models);

        $spec = $models['RS485-SOIL-7IN1'];
        $this->assertEquals('ESP32-BTLP-v1.0+', $spec['firmware_compatibility']);
        $this->assertFalse($spec['downlink_supported']);
        $this->assertEquals('BLOCKED', $spec['downlink_status']);

        // Check pH specification
        $this->assertArrayHasKey('ph', $spec['parameters']);
        $phSpec = $spec['parameters']['ph'];
        $this->assertEquals('pH', $phSpec['unit']);
        $this->assertEquals('0x0006', $phSpec['register_address']);
        $this->assertEquals(3.0, $phSpec['min_range']);
        $this->assertEquals(10.0, $phSpec['max_range']);
        $this->assertContains('offset', $phSpec['supported_methods']);
        $this->assertContains('two_point_vector', $phSpec['supported_methods']);

        // Check EC specification
        $this->assertArrayHasKey('ec', $spec['parameters']);
        $ecSpec = $spec['parameters']['ec'];
        $this->assertEquals('µS/cm', $ecSpec['unit']);
        $this->assertEquals('0x0005', $ecSpec['register_address']);
        $this->assertEquals(0.0, $ecSpec['min_range']);
        $this->assertEquals(20000.0, $ecSpec['max_range']);
    }

    // -------------------------------------------------------------------------
    // AC-2: Rejection of Universal / Arbitrary Formulas without Specification
    // -------------------------------------------------------------------------

    public function test_universal_arbitrary_formulas_are_rejected_without_specification(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Model sensor 'UNKNOWN-SENSOR-XYZ' tidak terdaftar");

        SensorCalibrationProfile::validateSpecification(
            'UNKNOWN-SENSOR-XYZ',
            'ph',
            'offset',
            ['offset' => 0.5]
        );
    }

    public function test_unsupported_calibration_methods_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Metode kalibrasi 'polynomial_curve' tidak diizinkan");

        SensorCalibrationProfile::validateSpecification(
            'RS485-SOIL-7IN1',
            'ph',
            'polynomial_curve',
            ['formula' => 'raw * 1.5 + 2']
        );
    }

    public function test_out_of_boundary_offsets_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("berada di luar batas aman spesifikasi");

        // pH offset allows [-2.0, 2.0], 5.0 is dangerous/invalid
        SensorCalibrationProfile::validateSpecification(
            'RS485-SOIL-7IN1',
            'ph',
            'offset',
            ['offset' => 5.0]
        );
    }

    public function test_invalid_two_point_reference_vector_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("raw_high (4) harus lebih besar dari raw_low (7)");

        SensorCalibrationProfile::validateSpecification(
            'RS485-SOIL-7IN1',
            'ph',
            'two_point_vector',
            [
                'raw_low' => 7.0,
                'raw_high' => 4.0, // inverted!
                'ref_low' => 4.01,
                'ref_high' => 7.00,
            ]
        );
    }

    // -------------------------------------------------------------------------
    // AC-3: Versioning, Auditability, and BLOCKED Downlink Status
    // -------------------------------------------------------------------------

    public function test_creates_audited_calibration_profile_with_versioning_and_downlink_blocked(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-CALIB-01',
            'name' => 'Meja Praktik Kalibrasi 1',
            'is_active' => true,
        ]);

        $action = app(CreateSensorCalibrationProfileAction::class);

        // Version 1
        $profileV1 = $action->execute(
            device: $device,
            sensorModel: 'RS485-SOIL-7IN1',
            parameter: 'ph',
            method: 'offset',
            parameters: ['offset' => 0.25],
            reason: 'Kalibrasi rutin awal semester ganjil',
            profileName: 'Profil pH Rutin',
            user: $teacher
        );

        $this->assertEquals(1, $profileV1->version);
        $this->assertEquals('RS485-SOIL-7IN1', $profileV1->sensor_model);
        $this->assertTrue($profileV1->isActive());
        $this->assertNull($profileV1->effective_until);

        // Audit log created with explicit BLOCKED downlink status
        $audit = AuditLog::where('action', 'Kalibrasi Sensor Profil')->latest('id')->firstOrFail();
        $this->assertStringContainsString('TK-CALIB-01', $audit->details);
        $this->assertStringContainsString('Status downlink hardware: BLOCKED', $audit->details);
        $this->assertEquals($teacher->id, $audit->user_id);

        // Version 2 on same parameter
        $profileV2 = $action->execute(
            device: $device,
            sensorModel: 'RS485-SOIL-7IN1',
            parameter: 'ph',
            method: 'offset',
            parameters: ['offset' => -0.15],
            reason: 'Koreksi sensor setelah pembersihan probe',
            profileName: 'Profil pH Pasca Pembersihan',
            user: $teacher
        );

        $this->assertEquals(2, $profileV2->version);
        $this->assertTrue($profileV2->isActive());

        // Profile V1 is now archived with effective_until set
        $profileV1->refresh();
        $this->assertNotNull($profileV1->effective_until);
        $this->assertFalse($profileV1->isActive(now()->addMinute()));
    }

    // -------------------------------------------------------------------------
    // AC-4: Telemetry Ingestion Records Calibration Profile & Preserves Raw Values
    // -------------------------------------------------------------------------

    public function test_telemetry_ingestion_applies_active_profile_and_preserves_immutable_raw_values(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-CALIB-INGEST',
            'name' => 'Meja Praktik Ingest',
            'is_active' => true,
        ]);

        $rawKey = 'ingest-key-calib-test';
        DeviceApiKey::create([
            'device_id' => $device->id,
            'key_hash' => hash('sha256', $rawKey),
        ]);

        // Activate pH offset +0.35 profile
        $action = app(CreateSensorCalibrationProfileAction::class);
        $profile = $action->execute(
            device: $device,
            sensorModel: 'RS485-SOIL-7IN1',
            parameter: 'ph',
            method: 'offset',
            parameters: ['offset' => 0.35],
            reason: 'Kalibrasi pH larutan buffer',
            profileName: 'Profil Kalibrasi Buffer pH',
            user: $teacher
        );

        // Ingest telemetry reading with raw pH 6.00
        $response = $this->withHeaders(['X-Device-Key' => $rawKey])->postJson('/api/v1/telemetry/ingest', [
            'device_id' => $device->code,
            'soilrs485' => [
                'Ph' => 6.00,
                'Humidity' => 65.0,
                'Ec' => 1200,
                'Temperature' => 26.0,
                'Nitrogen' => 45,
                'Phosporus' => 25,
                'Kalium' => 110,
            ],
        ]);

        $response->assertStatus(201);

        $reading = TelemetryReading::where('device_id', $device->id)->latest('id')->firstOrFail();

        // Raw value preserved
        $this->assertEquals(6.00, (float) ($reading->raw_metrics['ph'] ?? $reading->raw_payload['soilrs485']['Ph']));

        // Calibrated value applied: 6.00 + 0.35 = 6.35
        $this->assertEquals(6.35, (float) $reading->ph);

        // Profile lineage recorded
        $this->assertEquals($profile->id, $reading->calibration_profile_id);
        $this->assertEquals(1, $reading->calibration_version);
        $this->assertNotNull($reading->calibration_applied_at);
        $this->assertEquals(0.35, (float) $reading->applied_offsets['ph']);
    }

    // -------------------------------------------------------------------------
    // AC-5: Two-Point Reference Vector Calculation
    // -------------------------------------------------------------------------

    public function test_two_point_reference_vector_calibration_math_is_accurate(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-VECTOR-TEST',
            'name' => 'Meja Vektor Kalibrasi',
            'is_active' => true,
        ]);

        // Standard 2-point buffer test:
        // Sensor reads 4.10 for pH 4.01 buffer standard, and 7.20 for pH 7.00 buffer standard.
        // Slope = (7.00 - 4.01) / (7.20 - 4.10) = 2.99 / 3.10 = 0.964516
        // For raw 5.50: calibrated = 4.01 + (5.50 - 4.10) * 0.964516 = 4.01 + 1.35032 = 5.36
        $action = app(CreateSensorCalibrationProfileAction::class);
        $profile = $action->execute(
            device: $device,
            sensorModel: 'RS485-SOIL-7IN1',
            parameter: 'ph',
            method: 'two_point_vector',
            parameters: [
                'raw_low' => 4.10,
                'raw_high' => 7.20,
                'ref_low' => 4.01,
                'ref_high' => 7.00,
            ],
            reason: 'Kalibrasi larutan buffer pH 4.01 & 7.00 laboratorium',
            profileName: 'Vektor 2-Titik pH Standar',
            user: $teacher
        );

        $calibrated = $profile->calibrate(5.50);
        $this->assertEquals(5.36, $calibrated);

        // Test boundary clamping: raw input producing value outside 3.0-10.0 is clamped safely
        $lowClamp = $profile->calibrate(1.0); // should clamp to min 3.0
        $this->assertEquals(3.0, $lowClamp);
    }

    // -------------------------------------------------------------------------
    // AC-6: Activating New Profile Does Not Rewrite Historical Telemetry
    // -------------------------------------------------------------------------

    public function test_activating_new_calibration_profile_does_not_rewrite_historical_readings(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-IMMUTABLE-HIST',
            'name' => 'Meja Uji Imutabilitas Riwayat',
            'is_active' => true,
        ]);

        $action = app(CreateSensorCalibrationProfileAction::class);
        $ingest = app(IngestTelemetryAction::class);

        // 1. Activate profile v1 with +0.20
        $profileV1 = $action->execute(
            device: $device,
            sensorModel: 'RS485-SOIL-7IN1',
            parameter: 'ph',
            method: 'offset',
            parameters: ['offset' => 0.20],
            reason: 'Profil v1',
            profileName: 'V1',
            user: $teacher
        );

        // 2. Ingest first reading with raw 6.00 -> calibrated 6.20
        $reading1 = $ingest->execute($device, ['soilrs485' => ['Ph' => 6.00]], 'fixture');
        $this->assertEquals(6.20, (float) $reading1->ph);
        $this->assertEquals($profileV1->id, $reading1->calibration_profile_id);

        // 3. Teacher changes calibration to profile v2 with -0.50
        $profileV2 = $action->execute(
            device: $device,
            sensorModel: 'RS485-SOIL-7IN1',
            parameter: 'ph',
            method: 'offset',
            parameters: ['offset' => -0.50],
            reason: 'Profil v2',
            profileName: 'V2',
            user: $teacher
        );

        // 4. Verify historical reading #1 was NOT modified!
        $reading1->refresh();
        $this->assertEquals(6.20, (float) $reading1->ph);
        $this->assertEquals($profileV1->id, $reading1->calibration_profile_id);
        $this->assertEquals(1, $reading1->calibration_version);

        // 5. New reading #2 uses profile v2: raw 6.00 - 0.50 = 5.50
        $reading2 = $ingest->execute($device, ['soilrs485' => ['Ph' => 6.00]], 'fixture');
        $this->assertEquals(5.50, (float) $reading2->ph);
        $this->assertEquals($profileV2->id, $reading2->calibration_profile_id);
        $this->assertEquals(2, $reading2->calibration_version);
    }

    // -------------------------------------------------------------------------
    // AC-7: School Tenancy & Cross-School Authorization
    // -------------------------------------------------------------------------

    public function test_cross_school_teacher_cannot_create_or_modify_calibration_profile(): void
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

        $teacherB = User::factory()->create(['role' => 'guru', 'school_id' => $schoolB->id]);
        $deviceA = Device::create([
            'school_id' => $schoolA->id,
            'code' => 'TK-SCH-A',
            'name' => 'Kit Sukaraja',
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pengguna tidak memiliki wewenang');

        app(CreateSensorCalibrationProfileAction::class)->execute(
            device: $deviceA,
            sensorModel: 'RS485-SOIL-7IN1',
            parameter: 'ph',
            method: 'offset',
            parameters: ['offset' => 0.1],
            reason: 'Ilegal cross school',
            profileName: 'Ilegal',
            user: $teacherB
        );
    }

    // -------------------------------------------------------------------------
    // AC-8: Web Endpoint In Trainer Kit Manager Integrates Profile Creation
    // -------------------------------------------------------------------------

    public function test_web_calibration_endpoint_creates_audited_profile_with_honest_downlink_status(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-WEB-FORM',
            'name' => 'Meja Web Form',
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->post(route('kits.calibration', $device->id), [
            'phOffset' => 0.40,
            'moistureOffset' => 5.0,
            'ecOffset' => 150,
            'reason' => 'Kalibrasi rutin berkala mingguan lab',
        ]);

        $response->assertRedirect(route('kits.index'));
        $response->assertSessionHas('success');
        $successMessage = session('success');
        $this->assertStringContainsString('BLOCKED', $successMessage);

        $device->refresh();
        $this->assertEquals(0.40, (float) $device->calibration_offsets['ph']);

        // Check profiles were created in database
        $phProfile = $device->getActiveCalibrationProfile('ph');
        $this->assertNotNull($phProfile);
        $this->assertEquals(0.40, (float) $phProfile->parameters['offset']);

        $ecProfile = $device->getActiveCalibrationProfile('ec');
        $this->assertNotNull($ecProfile);
        $this->assertEquals(150, (float) $ecProfile->parameters['offset']);

        $humProfile = $device->getActiveCalibrationProfile('humidity');
        $this->assertNotNull($humProfile);
        $this->assertEquals(5.0, (float) $humProfile->parameters['offset']);
    }

    public function test_expired_or_future_profile_is_not_applied_during_ingestion(): void
    {
        $device = Device::create([
            'code' => 'TK-TIME-TEST',
            'name' => 'Meja Uji Waktu Kalibrasi',
            'is_active' => true,
        ]);

        $paramSpec = config('sensors.models.RS485-SOIL-7IN1.parameters.ph');

        // 1. Expired profile
        $expiredProfile = SensorCalibrationProfile::create([
            'device_id' => $device->id,
            'sensor_model' => 'RS485-SOIL-7IN1',
            'profile_name' => 'Expired Profile',
            'version' => 1,
            'parameter' => 'ph',
            'method' => 'offset',
            'parameters' => ['offset' => 1.0],
            'unit' => $paramSpec['unit'],
            'register_address' => $paramSpec['register_address'],
            'min_range' => $paramSpec['min_range'],
            'max_range' => $paramSpec['max_range'],
            'effective_from' => now()->subDays(10),
            'effective_until' => now()->subDay(), // Expired yesterday
            'reason' => 'Profil lama kedaluwarsa',
        ]);

        $this->assertFalse($expiredProfile->isActive());
        $this->assertNull($device->getActiveCalibrationProfile('ph'));

        // Ingest data: raw pH 6.00 should NOT receive +1.0 offset
        $reading = app(IngestTelemetryAction::class)->execute($device, ['soilrs485' => ['Ph' => 6.00]], 'fixture');
        $this->assertEquals(6.00, (float) $reading->ph);
        $this->assertNull($reading->calibration_profile_id);

        // 2. Future profile
        $futureProfile = SensorCalibrationProfile::create([
            'device_id' => $device->id,
            'sensor_model' => 'RS485-SOIL-7IN1',
            'profile_name' => 'Future Profile',
            'version' => 2,
            'parameter' => 'ph',
            'method' => 'offset',
            'parameters' => ['offset' => 0.5],
            'unit' => $paramSpec['unit'],
            'register_address' => $paramSpec['register_address'],
            'min_range' => $paramSpec['min_range'],
            'max_range' => $paramSpec['max_range'],
            'effective_from' => now()->addDays(5), // Starts 5 days later
            'effective_until' => null,
            'reason' => 'Profil terjadwal masa depan',
        ]);

        $this->assertFalse($futureProfile->isActive());
        $this->assertNull($device->getActiveCalibrationProfile('ph'));

        // Ingest data again: raw pH 6.00 still does not apply future profile
        $readingFuture = app(IngestTelemetryAction::class)->execute($device, ['soilrs485' => ['Ph' => 6.00]], 'fixture');
        $this->assertEquals(6.00, (float) $readingFuture->ph);
        $this->assertNull($readingFuture->calibration_profile_id);
    }
}
