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
}
