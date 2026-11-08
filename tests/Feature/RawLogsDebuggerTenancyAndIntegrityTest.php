<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\RawDataLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RawLogsDebuggerTenancyAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $teacherA;

    private User $teacherB;

    private User $studentA;

    private User $superuser;

    private Device $deviceA1;

    private Device $deviceB1;

    private RawDataLog $logA1;

    private RawDataLog $logB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::query()->where('status', 'active')->orderBy('id')->first() ?? School::create([
            'npsn' => '30300001',
            'name' => 'SMK Pertanian Sukaraja',
            'slug' => 'smk-pertanian-sukaraja',
            'status' => 'active',
        ]);

        $this->schoolB = School::where('id', '!=', $this->schoolA->id)->first() ?? School::create([
            'npsn' => '30300002',
            'name' => 'SMK Pertanian Subang',
            'slug' => 'smk-pertanian-subang',
            'status' => 'active',
        ]);
        $this->schoolB->update(['status' => 'active']);

        $this->teacherA = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'name' => 'Guru Sukaraja',
        ]);

        $this->teacherB = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolB->id,
            'name' => 'Guru Subang',
        ]);

        $this->studentA = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'name' => 'Siswa Sukaraja',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Superuser Lab',
        ]);

        $this->deviceA1 = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-RAW-A1',
            'name' => 'Meja Raw A1',
            'is_active' => true,
        ]);

        $this->deviceB1 = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-RAW-B1',
            'name' => 'Meja Raw B1',
            'is_active' => true,
        ]);

        $this->logA1 = RawDataLog::create([
            'device_id' => $this->deviceA1->id,
            'kit_code' => $this->deviceA1->code,
            'packet_hex' => '01030E019A002805DC001E0078001400288899',
            'protocol' => 'Modbus RS485 RTU',
            'level' => 'INFO',
            'parsed_payload' => [
                'source' => 'sensor_gateway',
                'is_simulated' => false,
                'ph' => 6.5,
            ],
            'recorded_at' => now()->subMinutes(10),
        ]);

        $this->logB1 = RawDataLog::create([
            'device_id' => $this->deviceB1->id,
            'kit_code' => $this->deviceB1->code,
            'packet_hex' => '02030E012C001E03E8001400500010001E7766',
            'protocol' => 'Modbus RS485 RTU',
            'level' => 'ERROR',
            'parsed_payload' => [
                'source' => 'sensor_gateway',
                'is_simulated' => false,
                'ph' => 4.2,
            ],
            'recorded_at' => now()->subMinutes(5),
        ]);
    }

    public function test_guest_can_access_raw_logs_and_sees_default_school_stream(): void
    {
        $response = $this->get('/raw-logs');
        $response->assertOk();
        $response->assertViewIs('pages.rawlogs.raw-logs');
        $response->assertSee('TK-RAW-A1');
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
            'npsn' => '30300888',
            'name' => 'SMK Inactive Agri',
            'slug' => 'smk-inactive-agri',
            'status' => 'inactive',
        ]);

        $teacherInactiveSchool = User::factory()->create([
            'role' => 'guru',
            'school_id' => $inactiveSchool->id,
        ]);

        $this->actingAs($teacherNoSchool)->get('/raw-logs')->assertForbidden();
        $this->actingAs($teacherInactiveSchool)->get('/raw-logs')->assertForbidden();
    }

    public function test_teacher_a_sees_only_school_a_devices_and_logs(): void
    {
        $response = $this->actingAs($this->teacherA)->get('/raw-logs');
        $response->assertOk();

        // Must see School A logs and device
        $response->assertSee('TK-RAW-A1');
        $response->assertSee('01030E019A002805DC001E0078001400288899');

        // Must NOT see School B logs and device
        $response->assertDontSee('TK-RAW-B1');
        $response->assertDontSee('02030E012C001E03E8001400500010001E7766');

        $logs = $response->viewData('logs');
        $this->assertTrue($logs->pluck('kit_code')->contains('TK-RAW-A1'));
        $this->assertFalse($logs->pluck('kit_code')->contains('TK-RAW-B1'));
    }

    public function test_cross_school_idor_kit_code_filter_returns_empty_and_does_not_leak(): void
    {
        // Teacher A attempts to view School B's kit code logs
        $response = $this->actingAs($this->teacherA)->get('/raw-logs?kit_code=TK-RAW-B1');
        $response->assertOk();

        // Must not leak School B logs
        $response->assertDontSee('02030E012C001E03E8001400500010001E7766');
        $logs = $response->viewData('logs');
        $this->assertCount(0, $logs);
    }

    public function test_superuser_can_filter_logs_by_school(): void
    {
        // Superuser filtering by School B
        $response = $this->actingAs($this->superuser)->get("/raw-logs?school_id={$this->schoolB->id}");
        $response->assertOk();
        $response->assertSee('TK-RAW-B1');
        $response->assertDontSee('TK-RAW-A1');

        // Superuser with no school filter sees both
        $responseAll = $this->actingAs($this->superuser)->get('/raw-logs');
        $responseAll->assertOk();
        $responseAll->assertSee('TK-RAW-A1');
        $responseAll->assertSee('TK-RAW-B1');
    }

    public function test_non_superuser_cannot_inject_modbus_frames(): void
    {
        $payload = [
            'kit_code' => 'TK-RAW-A1',
            'packet_hex' => '01030E019A002805DC001E0078001400288899',
            'level' => 'INFO',
        ];

        // Guest redirects to login
        $this->post('/raw-logs/inject', $payload)->assertRedirect('/login');

        // Student is forbidden
        $this->actingAs($this->studentA)->post('/raw-logs/inject', $payload)->assertForbidden();

        // Teacher is forbidden
        $this->actingAs($this->teacherA)->post('/raw-logs/inject', $payload)->assertForbidden();
    }

    public function test_superuser_can_inject_simulated_frame_with_honest_provenance(): void
    {
        $payload = [
            'kit_code' => $this->deviceA1->code,
            'packet_hex' => '01 03 0E 01 A0 00 30 06 00 00 20 00 80 00 15 00 30 AA BB',
            'level' => 'INFO',
        ];

        $response = $this->actingAs($this->superuser)->post('/raw-logs/inject', $payload);
        $response->assertRedirect('/raw-logs');
        $response->assertSessionHas('success');

        $injectedLog = RawDataLog::where('packet_hex', '01030E01A0003006000020008000150030AABB')->first();
        $this->assertNotNull($injectedLog);
        $this->assertEquals($this->deviceA1->id, $injectedLog->device_id);
        $this->assertEquals('INFO', $injectedLog->level);

        // Verify honest simulation provenance and no fake physical IO claims
        $parsed = $injectedLog->parsed_payload;
        $this->assertTrue($parsed['injected_manually'] ?? false);
        $this->assertTrue($parsed['is_simulated'] ?? false);
        $this->assertStringContainsString('Simulasi', $parsed['hardware_io_disclaimer'] ?? '');
    }

    public function test_superuser_injecting_for_non_existent_kit_returns_error(): void
    {
        $payload = [
            'kit_code' => 'TK-NON-EXISTENT-999',
            'packet_hex' => '01030E00000000000000000000000000000000',
            'level' => 'INFO',
        ];

        $response = $this->actingAs($this->superuser)->post('/raw-logs/inject', $payload);
        $response->assertRedirect('/raw-logs');
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('raw_data_logs', [
            'kit_code' => 'TK-NON-EXISTENT-999',
        ]);
    }
}
