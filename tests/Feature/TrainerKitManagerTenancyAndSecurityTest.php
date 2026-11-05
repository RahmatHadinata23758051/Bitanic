<?php

namespace Tests\Feature;

use App\Actions\Hardware\CreateSensorCalibrationProfileAction;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\School;
use App\Models\SensorCalibrationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainerKitManagerTenancyAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $teacherA;

    private User $teacherB;

    private User $superuser;

    private Device $deviceA;

    private Device $deviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::query()->where('status', 'active')->orderBy('id')->first() ?? School::create([
            'npsn' => '40400101',
            'name' => 'SMK Negeri 1 Pertanian',
            'slug' => 'smk-negeri-1-pertanian',
            'status' => 'active',
        ]);

        $this->schoolB = School::where('id', '!=', $this->schoolA->id)->first() ?? School::create([
            'npsn' => '40400102',
            'name' => 'SMK Negeri 2 Bioindustri',
            'slug' => 'smk-negeri-2-bioindustri',
            'status' => 'active',
        ]);
        $this->schoolB->update(['status' => 'active']);

        $this->teacherA = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'name' => 'Guru SMK A',
        ]);

        $this->teacherB = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolB->id,
            'name' => 'Guru SMK B',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Superuser Pusat',
        ]);

        $this->deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-FLEET-A1',
            'name' => 'Trainer Kit Lab A1',
            'ip_address' => '10.14.0.21',
            'mac_address' => '02:00:00:00:00:21',
            'firmware_version' => 'ESP32-PRIVATE-2.4',
            'protocol' => 'Modbus RS485 RTU',
            'calibration_offsets' => ['ph' => 0.2],
            'is_active' => true,
        ]);
        DeviceApiKey::generateForDevice($this->deviceA);

        $this->deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-FLEET-B1',
            'name' => 'Trainer Kit Lab B1',
            'ip_address' => '10.14.0.22',
            'mac_address' => '02:00:00:00:00:22',
            'firmware_version' => 'ESP32-PRIVATE-2.5',
            'protocol' => 'Modbus RS485 RTU',
            'is_active' => true,
        ]);
        DeviceApiKey::generateForDevice($this->deviceB);
    }

    public function test_guest_can_access_kits_index_and_sees_default_school_kits(): void
    {
        $response = $this->get(route('kits.index'));
        $response->assertOk();
        $response->assertSee('TK-FLEET-A1');
        $response->assertDontSee('TK-FLEET-B1');
    }

    public function test_teacher_without_school_or_with_inactive_school_fails_closed(): void
    {
        $teacherNoSchool = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);
        DB::table('users')->where('id', $teacherNoSchool->id)->update(['school_id' => null]);
        $teacherNoSchool->refresh();

        $inactiveSchool = School::create([
            'npsn' => '40400999',
            'name' => 'SMK Inaktif Pertanian',
            'slug' => 'smk-inaktif-pertanian',
            'status' => 'inactive',
        ]);
        $teacherInactiveSchool = User::factory()->create([
            'role' => 'guru',
            'school_id' => $inactiveSchool->id,
        ]);

        $this->actingAs($teacherNoSchool)->get(route('kits.index'))->assertForbidden();
        $this->actingAs($teacherInactiveSchool)->get(route('kits.index'))->assertForbidden();
    }

    public function test_teacher_only_sees_their_own_school_kits(): void
    {
        $responseA = $this->actingAs($this->teacherA)->get(route('kits.index'));
        $responseA->assertOk();
        $responseA->assertSee('TK-FLEET-A1');
        $responseA->assertDontSee('TK-FLEET-B1');

        $responseB = $this->actingAs($this->teacherB)->get(route('kits.index'));
        $responseB->assertOk();
        $responseB->assertSee('TK-FLEET-B1');
        $responseB->assertDontSee('TK-FLEET-A1');
    }

    public function test_superuser_can_filter_trainer_kits_by_school(): void
    {
        $responseB = $this->actingAs($this->superuser)->get(route('kits.index', ['school_id' => $this->schoolB->id]));
        $responseB->assertOk();
        $responseB->assertSee('TK-FLEET-B1');
        $responseB->assertDontSee('TK-FLEET-A1');

        $responseAll = $this->actingAs($this->superuser)->get(route('kits.index'));
        $responseAll->assertOk();
        $responseAll->assertSee('TK-FLEET-A1');
        $responseAll->assertSee('TK-FLEET-B1');
    }

    public function test_teacher_cannot_reboot_device_from_another_school(): void
    {
        // Teacher A attempts to reboot device belonging to School B
        $response = $this->actingAs($this->teacherA)->post(route('kits.reboot', $this->deviceB));
        $response->assertForbidden();

        // Teacher A reboots own device
        $responseOwn = $this->actingAs($this->teacherA)->post(route('kits.reboot', $this->deviceA));
        $responseOwn->assertRedirect(route('kits.index'));
        $responseOwn->assertSessionHas('warning');
    }

    public function test_teacher_cannot_calibrate_device_from_another_school(): void
    {
        $payload = [
            'phOffset' => 0.2,
            'moistureOffset' => -1.5,
            'ecOffset' => 50,
            'reason' => 'Uji kalibrasi sensor meja',
        ];

        // Teacher A attempts to calibrate device belonging to School B
        $response = $this->actingAs($this->teacherA)->post(route('kits.calibration', $this->deviceB), $payload);
        $response->assertForbidden();

        // Teacher A calibrates own device
        $responseOwn = $this->actingAs($this->teacherA)->post(route('kits.calibration', $this->deviceA), $payload);
        $responseOwn->assertRedirect(route('kits.index'));
        $responseOwn->assertSessionHas('success');
    }

    public function test_non_superuser_cannot_rotate_api_key(): void
    {
        $response = $this->actingAs($this->teacherA)->post(route('kits.api-key.rotate', $this->deviceA));
        $response->assertForbidden();
    }

    public function test_guest_and_student_never_receive_sensitive_device_metadata(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'name' => 'Siswa Lab A',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'school_id' => $this->schoolA->id,
            'name' => 'Admin Lab A',
        ]);

        $activeKeyPrefix = $this->deviceA->activeApiKey->key_prefix;

        // 1. Guest access
        $guestResponse = $this->get(route('kits.index'));
        $guestResponse->assertOk();
        $guestResponse->assertSee('TK-FLEET-A1');
        $guestResponse->assertDontSee('10.14.0.21');
        $guestResponse->assertDontSee('02:00:00:00:00:21');
        $guestResponse->assertDontSee('ESP32-PRIVATE-2.4');
        $guestResponse->assertDontSee($activeKeyPrefix);
        $guestResponse->assertSee('Dikelola Guru / Teknisi Lab');

        $guestKit = $guestResponse->viewData('kits')->first();
        $this->assertNull($guestKit->ip_address);
        $this->assertNull($guestKit->mac_address);
        $this->assertNull($guestKit->firmware_version);
        $this->assertFalse($guestKit->relationLoaded('activeApiKey'));
        $guestKitJson = $guestKit->toJson();
        $this->assertStringNotContainsString('ip_address', $guestKitJson);
        $this->assertStringNotContainsString('mac_address', $guestKitJson);
        $this->assertStringNotContainsString('firmware_version', $guestKitJson);
        $this->assertStringNotContainsString('activeApiKey', $guestKitJson);

        // 2. Student access
        $studentResponse = $this->actingAs($student)->get(route('kits.index'));
        $studentResponse->assertOk();
        $studentResponse->assertSee('TK-FLEET-A1');
        $studentResponse->assertDontSee('10.14.0.21');
        $studentResponse->assertDontSee('02:00:00:00:00:21');
        $studentResponse->assertDontSee('ESP32-PRIVATE-2.4');
        $studentResponse->assertDontSee($activeKeyPrefix);
        $studentResponse->assertSee('Dikelola Guru / Teknisi Lab');

        $studentKit = $studentResponse->viewData('kits')->first();
        $this->assertNull($studentKit->ip_address);
        $this->assertNull($studentKit->mac_address);
        $this->assertNull($studentKit->firmware_version);
        $this->assertFalse($studentKit->relationLoaded('activeApiKey'));
        $studentKitJson = $studentKit->toJson();
        $this->assertStringNotContainsString('ip_address', $studentKitJson);
        $this->assertStringNotContainsString('mac_address', $studentKitJson);
        $this->assertStringNotContainsString('firmware_version', $studentKitJson);
        $this->assertStringNotContainsString('activeApiKey', $studentKitJson);

        // 3. Teacher access
        $teacherResponse = $this->actingAs($this->teacherA)->get(route('kits.index'));
        $teacherResponse->assertOk();
        $teacherResponse->assertSee('10.14.0.21');
        $teacherResponse->assertSee('02:00:00:00:00:21');
        $teacherResponse->assertSee('ESP32-PRIVATE-2.4');
        $teacherResponse->assertSee($activeKeyPrefix);

        // 4. Superuser access
        $superuserResponse = $this->actingAs($this->superuser)->get(route('kits.index'));
        $superuserResponse->assertOk();
        $superuserResponse->assertSee('10.14.0.21');
        $superuserResponse->assertSee('02:00:00:00:00:21');
        $superuserResponse->assertSee('ESP32-PRIVATE-2.4');
        $superuserResponse->assertSee($activeKeyPrefix);

        // 5. Admin access
        $adminResponse = $this->actingAs($admin)->get(route('kits.index'));
        $adminResponse->assertOk();
        $adminResponse->assertSee('10.14.0.21');
        $adminResponse->assertSee('02:00:00:00:00:21');
        $adminResponse->assertSee('ESP32-PRIVATE-2.4');
        $adminResponse->assertSee($activeKeyPrefix);
    }

    public function test_button_visibility_by_role_and_honest_simulation_labels(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'name' => 'Siswa Lab A',
        ]);

        // 1. Guest cannot see reboot or rotate key actions
        $guestResponse = $this->get(route('kits.index'));
        $guestResponse->assertOk();
        $guestResponse->assertDontSee(route('kits.reboot', $this->deviceA));
        $guestResponse->assertDontSee(route('kits.api-key.rotate', $this->deviceA));

        // 2. Student cannot see reboot or rotate key actions
        $studentResponse = $this->actingAs($student)->get(route('kits.index'));
        $studentResponse->assertOk();
        $studentResponse->assertDontSee(route('kits.reboot', $this->deviceA));
        $studentResponse->assertDontSee(route('kits.api-key.rotate', $this->deviceA));

        // 3. Teacher can see reboot action with honest simulation label, but cannot see rotate key action
        $teacherResponse = $this->actingAs($this->teacherA)->get(route('kits.index'));
        $teacherResponse->assertOk();
        $teacherResponse->assertSee(route('kits.reboot', $this->deviceA));
        $teacherResponse->assertSee('Catat Simulasi Soft-Reboot (Transport Hardware Belum Tersedia)');
        $teacherResponse->assertDontSee('Kirim perintah reboot via Modbus');
        $teacherResponse->assertDontSee(route('kits.api-key.rotate', $this->deviceA));

        // 4. Superuser can see both reboot action and rotate key action
        $superuserResponse = $this->actingAs($this->superuser)->get(route('kits.index'));
        $superuserResponse->assertOk();
        $superuserResponse->assertSee(route('kits.reboot', $this->deviceA));
        $superuserResponse->assertSee(route('kits.api-key.rotate', $this->deviceA));
    }

    public function test_guest_and_student_blocked_from_all_hardware_mutations(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'name' => 'Siswa Lab A',
        ]);

        $calibPayload = [
            'phOffset' => 0.1,
            'moistureOffset' => 1.0,
            'ecOffset' => 10,
        ];

        // Guest mutations blocked
        $this->post(route('kits.reboot', $this->deviceA))->assertRedirect(route('login'));
        $this->post(route('kits.calibration', $this->deviceA), $calibPayload)->assertRedirect(route('login'));
        $this->post(route('kits.api-key.rotate', $this->deviceA))->assertRedirect(route('login'));

        // Student mutations blocked (403)
        $this->actingAs($student)->post(route('kits.reboot', $this->deviceA))->assertForbidden();
        $this->actingAs($student)->post(route('kits.calibration', $this->deviceA), $calibPayload)->assertForbidden();
        $this->actingAs($student)->post(route('kits.api-key.rotate', $this->deviceA))->assertForbidden();
    }

    public function test_simulated_reboot_truthful_feedback_and_preserves_hardware_integrity(): void
    {
        $this->deviceA->update([
            'status' => 'online',
            'last_seen_at' => now()->subMinute(),
            'relays_state' => ['irrigationPump' => false],
        ]);
        $this->deviceA->refresh();
        $originalStatus = $this->deviceA->getRawOriginal('status');
        $originalLastSeen = $this->deviceA->getRawOriginal('last_seen_at');
        $originalRelays = $this->deviceA->getRawOriginal('relays_state');

        $response = $this->actingAs($this->teacherA)->post(route('kits.reboot', $this->deviceA));
        $response->assertRedirect(route('kits.index'));
        $response->assertSessionHas('warning');

        $warning = session('warning');
        $this->assertStringContainsString('Permintaan simulasi reboot dicatat', $warning);
        $this->assertStringContainsString('belum memiliki transport hardware', $warning);

        // Hardware state and heartbeats are untouched
        $this->deviceA->refresh();
        $this->assertSame($originalStatus, $this->deviceA->status);
        $this->assertEquals($originalLastSeen, $this->deviceA->getRawOriginal('last_seen_at'));
        $this->assertSame($originalRelays, $this->deviceA->getRawOriginal('relays_state'));

        // Audit log created with full tenant and target metadata
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->schoolA->id,
            'user_id' => $this->teacherA->id,
            'action' => 'Simulasi Soft-Reboot (Transport Hardware Belum Tersedia)',
            'target_type' => Device::class,
            'target_id' => (string) $this->deviceA->id,
        ]);
    }

    public function test_atomic_multi_parameter_calibration_success_and_injected_failure_rollback(): void
    {
        $payload = [
            'phOffset' => 0.4,
            'moistureOffset' => -1.8,
            'ecOffset' => 75,
            'reason' => 'Kalibrasi multi-parameter berkala',
        ];

        // 1. Success case: atomic execution
        $response = $this->actingAs($this->teacherA)->post(route('kits.calibration', $this->deviceA), $payload);
        $response->assertRedirect(route('kits.index'));
        $response->assertSessionHas('success');

        $this->deviceA->refresh();
        $offsets = $this->deviceA->calibration_offsets;
        $this->assertEquals(0.4, $offsets['ph']);
        $this->assertEquals(-1.8, $offsets['humidity']);
        $this->assertEquals(75, $offsets['ec']);

        $profilesCount = SensorCalibrationProfile::where('device_id', $this->deviceA->id)->count();
        $this->assertSame(3, $profilesCount);

        // 2. Injected failure case: partial failure rolls back everything
        $mockAction = $this->mock(CreateSensorCalibrationProfileAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->withArgs(fn ($device, $sensorModel, $param) => $param === 'ph')
            ->andReturn(new SensorCalibrationProfile);

        $mockAction->shouldReceive('execute')
            ->once()
            ->withArgs(fn ($device, $sensorModel, $param) => $param === 'humidity')
            ->andReturn(new SensorCalibrationProfile);

        $mockAction->shouldReceive('execute')
            ->once()
            ->withArgs(fn ($device, $sensorModel, $param) => $param === 'ec')
            ->andThrow(new \RuntimeException('Injected Modbus validation failure on EC parameter'));

        $failPayload = [
            'phOffset' => 0.9,
            'moistureOffset' => 5.0,
            'ecOffset' => 200,
            'reason' => 'Kalibrasi yang gagal pada EC',
        ];

        $failResponse = $this->actingAs($this->teacherA)->post(route('kits.calibration', $this->deviceA), $failPayload);
        $failResponse->assertRedirect(route('kits.index'));
        $failResponse->assertSessionHas('error');

        // Verify rollback: offsets did not change to failPayload values
        $this->deviceA->refresh();
        $this->assertEquals(0.4, $this->deviceA->calibration_offsets['ph']);
        $this->assertEquals(-1.8, $this->deviceA->calibration_offsets['humidity']);
        $this->assertEquals(75, $this->deviceA->calibration_offsets['ec']);
    }

    public function test_device_audit_trail_attribution_and_school_isolation(): void
    {
        // Superuser rotates keys on deviceA (School A) and deviceB (School B)
        $this->actingAs($this->superuser)->post(route('kits.api-key.rotate', $this->deviceA));
        $this->actingAs($this->superuser)->post(route('kits.api-key.rotate', $this->deviceB));

        $logA = AuditLog::where('target_id', (string) $this->deviceA->id)
            ->where('action', 'Rotasi Kunci API Perangkat')
            ->first();
        $this->assertNotNull($logA);
        $this->assertEquals($this->schoolA->id, $logA->school_id);
        $this->assertEquals(Device::class, $logA->target_type);
        $this->assertArrayHasKey('new_prefix', $logA->diff);

        $logB = AuditLog::where('target_id', (string) $this->deviceB->id)
            ->where('action', 'Rotasi Kunci API Perangkat')
            ->first();
        $this->assertNotNull($logB);
        $this->assertEquals($this->schoolB->id, $logB->school_id);
        $this->assertEquals(Device::class, $logB->target_type);

        // School A query cannot see School B's log
        $schoolALogs = AuditLog::where('school_id', $this->schoolA->id)->get();
        $this->assertTrue($schoolALogs->contains('id', $logA->id));
        $this->assertFalse($schoolALogs->contains('id', $logB->id));

        // AuditLog append-only immutability invariant
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AuditLog is append-only');
        $logA->update(['details' => 'Tampered details']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = AuditLog::create([
            'school_id' => $this->schoolA->id,
            'actor_name' => 'Tester',
            'action' => 'Test Action',
            'details' => 'Immutability test',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AuditLog is append-only');
        $log->delete();
    }

    public function test_superuser_cross_school_mutation_policy(): void
    {
        // 1. Superuser mutating active device in School A is allowed
        $resA = $this->actingAs($this->superuser)->post(route('kits.reboot', $this->deviceA));
        $resA->assertRedirect(route('kits.index'));
        $resA->assertSessionHas('warning');

        // 2. Superuser mutating active device in School B is allowed
        $resB = $this->actingAs($this->superuser)->post(route('kits.reboot', $this->deviceB));
        $resB->assertRedirect(route('kits.index'));
        $resB->assertSessionHas('warning');

        // 3. Mark School B as inactive
        $this->schoolB->update(['status' => 'inactive']);

        // 4. Superuser is blocked (403) from mutating device in inactive School B
        $this->actingAs($this->superuser)->post(route('kits.reboot', $this->deviceB))->assertForbidden();

        $this->actingAs($this->superuser)->post(route('kits.calibration', $this->deviceB), [
            'phOffset' => 0.1,
            'moistureOffset' => 1.0,
            'ecOffset' => 10,
        ])->assertForbidden();

        $this->actingAs($this->superuser)->post(route('kits.api-key.rotate', $this->deviceB))->assertForbidden();
    }
}
