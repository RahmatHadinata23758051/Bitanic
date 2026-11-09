<?php

namespace Tests\Feature;

use App\Actions\Telemetry\ExportTelemetryCsvAction;
use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Models\User;
use Carbon\Carbon;
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

    public function test_date_filtering_converts_wib_boundaries_to_utc_accurately(): void
    {
        // 2026-03-10 01:30:00 WIB = 2026-03-09 18:30:00 UTC
        $readingOnTargetWib = TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 6.5,
            'ec' => 1200.0,
            'humidity' => 60.0,
            'temperature' => 25.0,
            'nitrogen' => 50.0,
            'phosphorus' => 30.0,
            'potassium' => 120.0,
            'recorded_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2026-03-10 01:30:00', 'Asia/Jakarta')->setTimezone('UTC'),
        ]);

        // 2026-03-11 08:00:00 WIB = 2026-03-11 01:00:00 UTC (Next day)
        $readingNextDayWib = TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 6.4,
            'ec' => 1250.0,
            'humidity' => 59.0,
            'temperature' => 24.5,
            'nitrogen' => 49.0,
            'phosphorus' => 29.0,
            'potassium' => 119.0,
            'recorded_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2026-03-11 08:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        ]);

        $response = $this->actingAs($this->teacherA)->get('/telemetry/history?start_date=2026-03-10&end_date=2026-03-10');
        $response->assertOk();

        $readings = $response->viewData('readings');
        $readingIds = $readings->pluck('id')->all();

        $this->assertContains($readingOnTargetWib->id, $readingIds);
        $this->assertNotContains($readingNextDayWib->id, $readingIds);
    }

    public function test_validation_errors_fail_closed_and_render_user_friendly_alert(): void
    {
        // 1. Invalid date range: start_date > end_date
        $responseDateInverted = $this->actingAs($this->teacherA)->get('/telemetry/history?start_date=2026-03-20&end_date=2026-03-10');
        $responseDateInverted->assertOk();
        $responseDateInverted->assertSee('Tanggal mulai tidak boleh melebihi tanggal akhir.');
        $readingsInverted = $responseDateInverted->viewData('readings');
        $this->assertCount(0, $readingsInverted);

        // 2. Invalid status selection
        $responseInvalidStatus = $this->actingAs($this->teacherA)->get('/telemetry/history?status=unsupported_status');
        $responseInvalidStatus->assertOk();
        $responseInvalidStatus->assertSee('Pilihan status kondisi tidak valid.');
        $readingsInvalidStatus = $responseInvalidStatus->viewData('readings');
        $this->assertCount(0, $readingsInvalidStatus);

        // 3. Invalid provenance selection
        $responseInvalidProv = $this->actingAs($this->teacherA)->get('/telemetry/history?provenance=fake_origin');
        $responseInvalidProv->assertOk();
        $responseInvalidProv->assertSee('Pilihan asal data tidak valid.');
        $readingsInvalidProv = $responseInvalidProv->viewData('readings');
        $this->assertCount(0, $readingsInvalidProv);
    }

    public function test_stale_or_non_hardware_anomalies_do_not_trigger_active_alert_hub(): void
    {
        // Stale hardware anomaly (> 15 minutes / 900s ago)
        TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 4.2,
            'ec' => 2900.0,
            'humidity' => 30.0,
            'temperature' => 36.0,
            'nitrogen' => 10.0,
            'phosphorus' => 5.0,
            'potassium' => 20.0,
            'threshold_flags' => ['PH_LOW'],
            'recorded_at' => now()->subMinutes(30),
        ]);

        $responseStale = $this->actingAs($this->teacherA)->get('/telemetry/history');
        $responseStale->assertOk();
        // Stale reading appears in the historical log table
        $responseStale->assertSee('Anomali');
        // But NOT in the real-time active alert hub
        $responseStale->assertDontSee('Peringatan Deviasi Aktif');

        // Fresh simulation anomaly
        TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'simulation',
            'ph' => 4.0,
            'ec' => 3000.0,
            'humidity' => 25.0,
            'temperature' => 38.0,
            'nitrogen' => 5.0,
            'phosphorus' => 2.0,
            'potassium' => 10.0,
            'threshold_flags' => ['PH_LOW'],
            'recorded_at' => now()->subMinute(),
        ]);

        $responseSim = $this->actingAs($this->teacherA)->get('/telemetry/history');
        $responseSim->assertOk();
        $responseSim->assertDontSee('Peringatan Deviasi Aktif');
    }

    public function test_csv_export_sanitizes_formula_injection(): void
    {
        $maliciousDevice = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-MALICIOUS',
            'name' => '=cmd|"/C calc"!A0',
            'is_active' => true,
        ]);

        TelemetryReading::create([
            'device_id' => $maliciousDevice->id,
            'provenance' => 'hardware',
            'ph' => 6.5,
            'ec' => 1200.0,
            'humidity' => 60.0,
            'temperature' => 25.0,
            'nitrogen' => 50.0,
            'phosphorus' => 30.0,
            'potassium' => 120.0,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->teacherA)->get('/telemetry/history/export?device_id='.$maliciousDevice->id);
        $response->assertOk();

        $content = $response->streamedContent();
        // Cell should be prepended with a single quote to prevent DDE/Formula execution
        $this->assertStringContainsString("'=cmd", $content);
    }

    public function test_csv_export_attaches_truncation_notice_when_dataset_exceeds_limit(): void
    {
        // Ensure at least 2 readings exist on deviceA so limit of 1 is exceeded
        TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 6.8,
            'ec' => 1300.0,
            'humidity' => 63.0,
            'temperature' => 26.0,
            'nitrogen' => 55.0,
            'phosphorus' => 33.0,
            'potassium' => 128.0,
            'recorded_at' => now()->subMinutes(5),
        ]);

        $action = app(ExportTelemetryCsvAction::class);
        $action->setMaxRows(1); // Set limit to 1 row for boundary verification

        $query = TelemetryReading::query()
            ->where('device_id', $this->deviceA->id)
            ->orderByDesc('recorded_at');

        $response = $action->execute($query, 'test-limit.csv', $this->schoolA->id);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('[CATATAN: Ekspor dibatasi maksimal', $content);
    }

    public function test_telemetry_ordering_is_strictly_deterministic(): void
    {
        $sharedFutureTime = now()->addHour();

        $first = TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 6.5,
            'ec' => 1200.0,
            'humidity' => 60.0,
            'temperature' => 25.0,
            'nitrogen' => 50.0,
            'phosphorus' => 30.0,
            'potassium' => 120.0,
            'recorded_at' => $sharedFutureTime,
        ]);

        $second = TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 6.6,
            'ec' => 1210.0,
            'humidity' => 61.0,
            'temperature' => 25.2,
            'nitrogen' => 51.0,
            'phosphorus' => 31.0,
            'potassium' => 121.0,
            'recorded_at' => $sharedFutureTime,
        ]);

        $response = $this->actingAs($this->teacherA)->get('/telemetry/history?device_id='.$this->deviceA->id);
        $response->assertOk();

        $readings = $response->viewData('readings');
        $readingsList = $readings->items();

        // Higher ID must appear first when recorded_at is identical
        $this->assertEquals($second->id, $readingsList[0]->id);
        $this->assertEquals($first->id, $readingsList[1]->id);
    }
}
