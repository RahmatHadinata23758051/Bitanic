<?php

namespace Tests\Feature;

use App\Actions\Practicum\SubmitStudentReportAction;
use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\ScheduleParticipant;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\ModulePracticumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LksAllocationAndEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulePracticumSeeder::class);
    }

    public function test_unallocated_student_cannot_cherry_pick_arbitrary_device_telemetry(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'assigned_kit_id' => null, // Profil ada tapi belum dialokasikan meja
        ]);

        $device = Device::create([
            'code' => 'TK-UNALLOCATED-01',
            'name' => 'Meja Tak Dialokasikan',
            'is_active' => true,
        ]);
        $module = ModulePracticum::firstOrFail();

        // Student has no profile assignment and no schedule participant entry
        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'device_id' => $device->id,
            'lks_answers' => ['q1' => 'Mencoba ambil data meja lab sembarangan'],
        ]);

        $response->assertSessionHasErrors('device_id');
    }

    public function test_student_without_profile_cannot_cherry_pick_any_device(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-NO-PROFILE', 'name' => 'Meja Lain', 'is_active' => true]);
        $module = ModulePracticum::firstOrFail();

        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'device_id' => $device->id,
            'lks_answers' => ['q1' => 'Tanpa alokasi profil'],
        ]);

        $response->assertSessionHasErrors('device_id');
        $this->assertDatabaseMissing('student_reports', ['user_id' => $student->id, 'module_id' => $module->id]);
    }

    public function test_explicit_schedule_is_rejected_outside_its_session_window(): void
    {
        $this->travelTo(now()->setTime(14, 0));
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-SCHEDULE-TIME', 'name' => 'Meja Jadwal', 'is_active' => true]);
        $schedule = ActivitySchedule::create([
            'device_id' => $device->id,
            'title' => 'Praktikum pagi',
            'activity_date' => today(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);
        ScheduleParticipant::create([
            'activity_schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'role' => 'siswa',
        ]);

        $response = $this->actingAs($student)->post(route('modules.submit-lks', ModulePracticum::firstOrFail()->id), [
            'activity_schedule_id' => $schedule->id,
            'device_id' => $device->id,
            'lks_answers' => ['q1' => 'Jadwal sudah berakhir'],
        ]);

        $response->assertSessionHasErrors('activity_schedule_id');
    }

    public function test_student_cannot_submit_foreign_evidence_reading_id(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $kit1 = Device::create(['code' => 'TK-MY-KIT', 'name' => 'Meja Saya', 'is_active' => true]);
        $kit2 = Device::create(['code' => 'TK-OTHER-KIT', 'name' => 'Meja Orang Lain', 'is_active' => true]);

        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'assigned_kit_id' => 'TK-MY-KIT',
        ]);

        $foreignReading = TelemetryReading::create([
            'device_id' => $kit2->id,
            'ph' => 7.1,
            'temperature' => 26.0,
            'humidity' => 60.0,
            'ec' => 1500,
            'nitrogen' => 45,
            'phosphorus' => 30,
            'potassium' => 120,
            'recorded_at' => now(),
        ]);

        $module = ModulePracticum::firstOrFail();

        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'device_id' => $kit1->id,
            'evidence_reading_id' => $foreignReading->id,
            'lks_answers' => ['q1' => 'Mencoba injeksi reading ID meja lain'],
        ]);

        $response->assertSessionHasErrors('evidence_reading_id');
    }

    public function test_unallocated_student_without_device_submits_honest_empty_snapshot(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();

        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'lks_answers' => ['q1' => 'Laporan teori tanpa hardware kit'],
        ]);

        $response->assertRedirect(route('modules.show', $module->id));
        $report = StudentReport::where('user_id', $student->id)->where('module_id', $module->id)->firstOrFail();

        $this->assertNull($report->device_id);
        $this->assertNull($report->evidence_reading_id);
        $this->assertFalse($report->telemetry_snapshot['has_telemetry']);
        $this->assertStringContainsString('belum memiliki alokasi meja', $report->telemetry_snapshot['message']);
    }

    public function test_student_without_profile_or_schedule_does_not_get_a_fallback_device(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        Device::create(['code' => 'TK-FALLBACK', 'name' => 'Meja Bukan Alokasi', 'is_active' => true]);
        $module = ModulePracticum::firstOrFail();

        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'lks_answers' => ['q1' => 'Laporan tanpa alokasi resmi'],
        ]);

        $response->assertRedirect(route('modules.show', $module->id));
        $report = StudentReport::where('user_id', $student->id)->where('module_id', $module->id)->firstOrFail();

        $this->assertNull($report->device_id);
        $this->assertNull($report->evidence_reading_id);
        $this->assertFalse($report->telemetry_snapshot['has_telemetry']);
    }

    public function test_partial_sensor_snapshot_preserves_nulls_and_real_zero_values(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-PARTIAL-SNAPSHOT', 'name' => 'Meja Partial', 'is_active' => true]);
        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'assigned_kit_id' => $device->code,
        ]);
        $reading = TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'simulation',
            'nitrogen' => 0,
            'phosphorus' => null,
            'potassium' => 12.5,
            'ec' => null,
            'ph' => 6.4,
            'temperature' => null,
            'humidity' => 0,
            'recorded_at' => now(),
        ]);

        $report = app(SubmitStudentReportAction::class)->execute(
            $student,
            ModulePracticum::firstOrFail(),
            [
                'device_id' => $device->id,
                'evidence_reading_id' => $reading->id,
                'lks_answers' => ['q1' => 'Mencatat telemetri yang sebagian tersedia'],
            ]
        );

        $snapshot = $report->telemetry_snapshot;
        $this->assertSame(0.0, (float) $snapshot['nitrogen']);
        $this->assertNull($snapshot['phosphorus']);
        $this->assertSame(12.5, (float) $snapshot['potassium']);
        $this->assertNull($snapshot['ec']);
        $this->assertSame(6.4, (float) $snapshot['ph']);
        $this->assertNull($snapshot['temperature']);
        $this->assertSame(0.0, (float) $snapshot['humidity']);
        $this->assertSame('simulation', $snapshot['provenance']);
    }

    public function test_missing_reading_provenance_is_not_invented_as_hardware(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-FIXTURE-PROVENANCE', 'name' => 'Meja Fixture', 'is_active' => true]);
        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'assigned_kit_id' => $device->code,
        ]);
        $reading = TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'fixture',
            'ph' => 6.1,
            'recorded_at' => now(),
        ]);

        $report = app(SubmitStudentReportAction::class)->execute(
            $student,
            ModulePracticum::firstOrFail(),
            [
                'device_id' => $device->id,
                'evidence_reading_id' => $reading->id,
                'lks_answers' => ['q1' => 'Provenance fixture'],
            ]
        );

        $this->assertSame('fixture', $report->telemetry_snapshot['provenance']);
        $this->assertNotEquals('hardware', $report->telemetry_snapshot['provenance']);
    }

    public function test_scheduled_evidence_requires_timestamp_inside_current_practicum_window(): void
    {
        $this->travelTo(now()->setTime(10, 30));
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-WINDOW-EVIDENCE', 'name' => 'Meja Window', 'is_active' => true]);
        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'assigned_kit_id' => $device->code,
        ]);
        $schedule = ActivitySchedule::create([
            'device_id' => $device->id,
            'title' => 'Sesi aktif',
            'activity_date' => today(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);
        ScheduleParticipant::create([
            'activity_schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'role' => 'siswa',
        ]);
        $reading = TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'hardware',
            'ph' => 6.3,
            'recorded_at' => today()->setTime(8, 59),
        ]);

        $response = $this->actingAs($student)->post(route('modules.submit-lks', ModulePracticum::firstOrFail()->id), [
            'device_id' => $device->id,
            'activity_schedule_id' => $schedule->id,
            'evidence_reading_id' => $reading->id,
            'lks_answers' => ['q1' => 'Evidence before session'],
        ]);

        $response->assertSessionHasErrors('evidence_reading_id');
        $this->assertDatabaseMissing('student_reports', [
            'user_id' => $student->id,
            'module_id' => ModulePracticum::firstOrFail()->id,
        ]);
    }

    public function test_cross_school_evidence_reading_is_strictly_rejected(): void
    {
        $schoolA = School::create([
            'npsn' => '20200991',
            'name' => 'SMK Pertanian A',
            'slug' => 'smk-pertanian-a',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200992',
            'name' => 'SMK Pertanian B',
            'slug' => 'smk-pertanian-b',
            'status' => 'active',
        ]);

        $studentA = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $schoolA->id,
        ]);
        $kitA = Device::create([
            'school_id' => $schoolA->id,
            'code' => 'TK-SCHOOL-A',
            'name' => 'Meja Sekolah A',
            'is_active' => true,
        ]);
        $kitB = Device::create([
            'school_id' => $schoolB->id,
            'code' => 'TK-SCHOOL-B',
            'name' => 'Meja Sekolah B',
            'is_active' => true,
        ]);
        UserProfile::create([
            'user_id' => $studentA->id,
            'type' => 'siswa',
            'assigned_kit_id' => $kitA->code,
        ]);

        $foreignReading = TelemetryReading::create([
            'device_id' => $kitB->id,
            'provenance' => 'hardware',
            'ph' => 7.0,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($studentA)->post(route('modules.submit-lks', ModulePracticum::firstOrFail()->id), [
            'device_id' => $kitA->id,
            'evidence_reading_id' => $foreignReading->id,
            'lks_answers' => ['q1' => 'Mencoba injeksi telemetri sekolah lain'],
        ]);

        $response->assertSessionHasErrors('evidence_reading_id');
    }
}
