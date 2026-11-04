<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\ScheduleParticipant;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\ModulePracticumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
}
