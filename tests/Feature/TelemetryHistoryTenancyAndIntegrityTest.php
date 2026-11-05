<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelemetryHistoryTenancyAndIntegrityTest extends TestCase
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
            'npsn' => '30300101',
            'name' => 'SMK Negeri 1 Agronomi',
            'slug' => 'smk-negeri-1-agronomi',
            'status' => 'active',
        ]);

        $this->schoolB = School::where('id', '!=', $this->schoolA->id)->first() ?? School::create([
            'npsn' => '30300102',
            'name' => 'SMK Negeri 2 Hortikultura',
            'slug' => 'smk-negeri-2-hortikultura',
            'status' => 'active',
        ]);
        $this->schoolB->update(['status' => 'active']);

        $this->teacherA = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'name' => 'Guru Agronomi A',
        ]);

        $this->teacherB = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolB->id,
            'name' => 'Guru Hortikultura B',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Superuser Administrator',
        ]);

        $this->deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-HIST-A1',
            'name' => 'Trainer Kit A1',
            'is_active' => true,
        ]);

        $this->deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-HIST-B1',
            'name' => 'Trainer Kit B1',
            'is_active' => true,
        ]);

        TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 6.4,
            'ec' => 1450.0,
            'humidity' => 62.0,
            'temperature' => 25.5,
            'nitrogen' => 65.0,
            'phosphorus' => 32.0,
            'potassium' => 125.0,
            'recorded_at' => now()->subMinutes(15),
        ]);

        TelemetryReading::create([
            'device_id' => $this->deviceB->id,
            'provenance' => 'simulation',
            'ph' => 4.1,
            'ec' => 2950.0,
            'humidity' => 32.0,
            'temperature' => 36.0,
            'nitrogen' => 15.0,
            'phosphorus' => 8.0,
            'potassium' => 30.0,
            'recorded_at' => now()->subMinutes(5),
        ]);
    }

    public function test_guest_can_access_telemetry_history_and_sees_default_school_data(): void
    {
        $response = $this->get('/telemetry/history');
        $response->assertOk();
        $response->assertSee('TK-HIST-A1');
        $response->assertDontSee('TK-HIST-B1');
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
            'npsn' => '30300998',
            'name' => 'SMK Inaktif Agronomi',
            'slug' => 'smk-inaktif-agronomi',
            'status' => 'inactive',
        ]);
        $teacherInactiveSchool = User::factory()->create([
            'role' => 'guru',
            'school_id' => $inactiveSchool->id,
        ]);

        $this->actingAs($teacherNoSchool)->get('/telemetry/history')->assertForbidden();
        $this->actingAs($teacherNoSchool)->get('/telemetry/history/export')->assertForbidden();

        $this->actingAs($teacherInactiveSchool)->get('/telemetry/history')->assertForbidden();
        $this->actingAs($teacherInactiveSchool)->get('/telemetry/history/export')->assertForbidden();
    }

    public function test_teacher_only_sees_their_own_school_devices_and_readings(): void
    {
        $responseA = $this->actingAs($this->teacherA)->get('/telemetry/history');
        $responseA->assertOk();
        $responseA->assertSee('TK-HIST-A1');
        $responseA->assertDontSee('TK-HIST-B1');

        $responseB = $this->actingAs($this->teacherB)->get('/telemetry/history');
        $responseB->assertOk();
        $responseB->assertSee('TK-HIST-B1');
        $responseB->assertDontSee('TK-HIST-A1');
    }

    public function test_cross_school_idor_device_tampering_is_rejected_and_isolated(): void
    {
        // Teacher A passes ?device_id= of School B
        $response = $this->actingAs($this->teacherA)->get("/telemetry/history?device_id={$this->deviceB->id}");
        $response->assertOk();

        // Must not leak School B device or readings
        $response->assertDontSee('TK-HIST-B1');
        $readings = $response->viewData('readings');
        $this->assertCount(0, $readings);
    }

    public function test_superuser_can_filter_telemetry_history_by_school(): void
    {
        $responseSchoolB = $this->actingAs($this->superuser)->get("/telemetry/history?school_id={$this->schoolB->id}");
        $responseSchoolB->assertOk();
        $responseSchoolB->assertSee('TK-HIST-B1');
        $responseSchoolB->assertDontSee('TK-HIST-A1');

        $responseAll = $this->actingAs($this->superuser)->get('/telemetry/history');
        $responseAll->assertOk();
        $responseAll->assertSee('TK-HIST-A1');
        $responseAll->assertSee('TK-HIST-B1');
    }

    public function test_csv_export_respects_school_tenancy_and_includes_provenance(): void
    {
        // Teacher A export
        $responseA = $this->actingAs($this->teacherA)->get('/telemetry/history/export');
        $responseA->assertOk();
        $contentA = $responseA->streamedContent();

        $this->assertStringContainsString('Asal Data', $contentA);
        $this->assertStringContainsString('TK-HIST-A1', $contentA);
        $this->assertStringContainsString('hardware', $contentA);
        $this->assertStringNotContainsString('TK-HIST-B1', $contentA);

        // Teacher B export
        $responseB = $this->actingAs($this->teacherB)->get('/telemetry/history/export');
        $responseB->assertOk();
        $contentB = $responseB->streamedContent();

        $this->assertStringContainsString('TK-HIST-B1', $contentB);
        $this->assertStringContainsString('simulation', $contentB);
        $this->assertStringNotContainsString('TK-HIST-A1', $contentB);
    }

    public function test_csv_export_rejects_cross_school_device_tampering(): void
    {
        // Teacher A attempts to export device B via ?device_id=
        $response = $this->actingAs($this->teacherA)->get("/telemetry/history/export?device_id={$this->deviceB->id}");
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringNotContainsString('TK-HIST-B1', $content);
    }

    public function test_empty_state_renders_honest_message_when_no_readings_match(): void
    {
        $response = $this->actingAs($this->teacherA)->get('/telemetry/history?start_date=2020-01-01&end_date=2020-01-02');
        $response->assertOk();
        $response->assertSee('Tidak ada log telemetri yang sesuai kriteria filter.');
    }
}
