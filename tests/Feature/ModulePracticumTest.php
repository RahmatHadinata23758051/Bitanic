<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use Database\Seeders\ModulePracticumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePracticumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulePracticumSeeder::class);
    }

    public function test_catalog_displays_all_12_vocational_modules(): void
    {
        $response = $this->get(route('modules.index'));

        $response->assertStatus(200);
        $response->assertSee('12 Modul Praktikum Pertanian Presisi');
        $response->assertSee('Pengenalan Trainer Kit BITANIC');
        $response->assertSee('Capstone Project');
    }

    public function test_module_detail_displays_theory_wiring_and_lks(): void
    {
        $module = ModulePracticum::where('meeting_number', 1)->firstOrFail();

        $response = $this->get(route('modules.show', $module->id));

        $response->assertStatus(200);
        $response->assertSee($module->title);
        $response->assertSee('Skema Terminal');
        $response->assertSee('Form Lembar Kerja Siswa (LKS)');
    }

    public function test_student_can_submit_lks_with_automatic_telemetry_snapshot(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa',
        ]);

        $device = Device::create([
            'code' => 'TRAINER-KIT-01',
            'name' => 'Meja Praktik Laboratorium 1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        TelemetryReading::create([
            'device_id' => $device->id,
            'nitrogen' => 45.0,
            'phosphorus' => 25.0,
            'potassium' => 110.0,
            'ec' => 1250.0,
            'ph' => 6.2,
            'temperature' => 24.5,
            'humidity' => 65.0,
            'raw_payload' => ['sample' => 'test'],
            'recorded_at' => now(),
        ]);

        $module = ModulePracticum::where('meeting_number', 2)->firstOrFail();

        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'lks_answers' => [
                'q1' => 'MAC: 24:6F:28:AB:CD:EF, IP: 192.168.1.105',
                'q2' => 'Dashboard guru dapat memantau seluruh meja dan memberi nilai, sedangkan siswa hanya meja kelompoknya.',
            ],
            'observation_notes' => 'Pengujian dilakukan di tanah humus basah.',
        ]);

        $response->assertRedirect(route('modules.show', $module->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('student_reports', [
            'user_id' => $student->id,
            'module_id' => $module->id,
            'status' => 'submitted',
        ]);

        $report = StudentReport::where('user_id', $student->id)->where('module_id', $module->id)->firstOrFail();
        $this->assertEquals(6.2, $report->telemetry_snapshot['ph']);
        $this->assertEquals(24.5, $report->telemetry_snapshot['temperature']);
    }

    public function test_teacher_can_grade_student_report_using_4_scale_rubric(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $teacher = User::factory()->create(['role' => 'guru']);
        $module = ModulePracticum::where('meeting_number', 1)->firstOrFail();

        $report = StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'lks_answers' => ['lks-1-1' => 'Jawaban test'],
            'telemetry_snapshot' => ['ph' => 6.5],
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($teacher)->post(route('modules.grade-report', $report->id), [
            'rubric_scores' => [
                'r1' => 4, // 100 * 0.40 = 40.0
                'r2' => 3, // 85 * 0.35 = 29.75
                'r3' => 4, // 100 * 0.25 = 25.0
            ],
            'teacher_feedback' => 'Instalasi grounding sangat rapi dan aman sesuai standar PUIL.',
        ]);

        $response->assertRedirect(route('modules.show', $module->id));
        $response->assertSessionHas('success');

        $report->refresh();
        $this->assertEquals('graded', $report->status);
        $this->assertEquals($teacher->id, $report->graded_by);
        // Total: 25.0 + 29.75 + 40.0 = 94.75
        $this->assertEquals(94.75, (float) $report->final_grade);
        $this->assertEquals('Instalasi grounding sangat rapi dan aman sesuai standar PUIL.', $report->teacher_feedback);
    }

    public function test_student_cannot_grade_student_report(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::where('meeting_number', 1)->firstOrFail();

        $report = StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'lks_answers' => ['lks-1-1' => 'Jawaban test'],
            'telemetry_snapshot' => ['ph' => 6.5],
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($student)->post(route('modules.grade-report', $report->id), [
            'rubric_scores' => ['r1' => 4],
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_grade_or_submit_lks(): void
    {
        $module = ModulePracticum::where('meeting_number', 1)->firstOrFail();

        $response = $this->post(route('modules.submit-lks', $module->id), [
            'lks_answers' => ['q1' => 'test'],
        ]);
        $response->assertRedirect('/login');

        $report = StudentReport::create([
            'user_id' => User::factory()->create(['role' => 'siswa'])->id,
            'module_id' => $module->id,
            'lks_answers' => ['lks-1-1' => 'Jawaban test'],
            'telemetry_snapshot' => ['ph' => 6.5],
            'status' => 'submitted',
        ]);

        $gradeResponse = $this->post(route('modules.grade-report', $report->id), [
            'rubric_scores' => ['r1' => 4],
        ]);
        $gradeResponse->assertRedirect('/login');
    }

    public function test_student_submission_scopes_telemetry_to_allocated_device_with_evidence_link(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        $kit1 = Device::create([
            'code' => 'TK-LAB-01',
            'name' => 'Meja Praktikum 1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        $kit2 = Device::create([
            'code' => 'TK-LAB-02',
            'name' => 'Meja Praktikum 2',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        $readingKit1 = TelemetryReading::create([
            'device_id' => $kit1->id,
            'nitrogen' => 30.0,
            'phosphorus' => 15.0,
            'potassium' => 80.0,
            'ec' => 900.0,
            'ph' => 5.8,
            'temperature' => 23.0,
            'humidity' => 70.0,
            'raw_payload' => ['kit' => 1],
            'recorded_at' => now()->subMinute(),
        ]);

        $readingKit2 = TelemetryReading::create([
            'device_id' => $kit2->id,
            'nitrogen' => 85.0,
            'phosphorus' => 45.0,
            'potassium' => 160.0,
            'ec' => 1800.0,
            'ph' => 7.4,
            'temperature' => 28.0,
            'humidity' => 40.0,
            'raw_payload' => ['kit' => 2],
            'recorded_at' => now(), // newer reading on kit 2!
        ]);

        $module = ModulePracticum::where('meeting_number', 3)->firstOrFail();

        // Siswa di meja 1 mengumpulkan LKS dengan device_id kit 1
        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'device_id' => $kit1->id,
            'lks_answers' => [
                'q1' => 'Hasil observasi tanah bedengan 1.',
            ],
        ]);

        $response->assertRedirect(route('modules.show', $module->id));
        $response->assertSessionHas('success');

        $report = StudentReport::where('user_id', $student->id)->where('module_id', $module->id)->firstOrFail();
        $this->assertEquals($kit1->id, $report->device_id);
        $this->assertEquals($readingKit1->id, $report->evidence_reading_id);
        $this->assertEquals(5.8, $report->telemetry_snapshot['ph']);
        $this->assertEquals('TK-LAB-01', $report->telemetry_snapshot['device_code']);
    }

    public function test_student_lks_submission_records_honest_empty_state_without_telemetry(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        $kitEmpty = Device::create([
            'code' => 'TK-EMPTY',
            'name' => 'Meja Praktikum Tanpa Transmisi',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        $module = ModulePracticum::where('meeting_number', 4)->firstOrFail();

        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'device_id' => $kitEmpty->id,
            'lks_answers' => [
                'q1' => 'Sensor belum aktif atau transmitter belum dinyalakan.',
            ],
        ]);

        $response->assertRedirect(route('modules.show', $module->id));

        $report = StudentReport::where('user_id', $student->id)->where('module_id', $module->id)->firstOrFail();
        $this->assertNull($report->evidence_reading_id);
        $this->assertFalse($report->telemetry_snapshot['has_telemetry']);
        $this->assertArrayNotHasKey('ph', $report->telemetry_snapshot);
    }

    public function test_student_cannot_overwrite_or_resubmit_graded_lks_report(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $teacher = User::factory()->create(['role' => 'guru']);
        $module = ModulePracticum::where('meeting_number', 5)->firstOrFail();

        // Laporan sudah dinilai guru
        $report = StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'lks_answers' => ['q1' => 'Jawaban asli yang sudah dinilai.'],
            'status' => 'graded',
            'final_grade' => 95.0,
            'teacher_feedback' => 'Sangat bagus.',
            'graded_by' => $teacher->id,
            'attempt_count' => 1,
        ]);

        // Siswa mencoba submit ulang untuk menimpa
        $response = $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'lks_answers' => ['q1' => 'Mencoba merubah jawaban setelah dinilai!'],
        ]);

        $response->assertSessionHasErrors(['lks_answers']);

        $report->refresh();
        $this->assertEquals('Jawaban asli yang sudah dinilai.', $report->lks_answers['q1']);
        $this->assertEquals(95.0, (float) $report->final_grade);
    }

    public function test_student_resubmission_increments_attempt_count(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::where('meeting_number', 6)->firstOrFail();

        // Submit 1
        $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'lks_answers' => ['q1' => 'Jawaban percobaan 1'],
        ]);

        $report = StudentReport::where('user_id', $student->id)->where('module_id', $module->id)->firstOrFail();
        $this->assertEquals(1, $report->attempt_count);

        // Submit 2 (revisi sebelum dinilai)
        $this->actingAs($student)->post(route('modules.submit-lks', $module->id), [
            'lks_answers' => ['q1' => 'Jawaban percobaan 2 yang diperbaiki'],
            'revision_notes' => 'Perbaikan analisis EC',
        ]);

        $report->refresh();
        $this->assertEquals(2, $report->attempt_count);
        $this->assertEquals('Jawaban percobaan 2 yang diperbaiki', $report->lks_answers['q1']);
        $this->assertEquals('Perbaikan analisis EC', $report->revision_notes);
    }
}
