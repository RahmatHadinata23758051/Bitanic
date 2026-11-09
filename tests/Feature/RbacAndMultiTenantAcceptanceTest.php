<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\Media;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentGroup;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAndMultiTenantAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $superuser;

    private User $adminA;

    private User $teacherA;

    private User $teacherB;

    private User $studentA;

    private User $studentB;

    private Device $deviceA;

    private Device $deviceB;

    private ModulePracticum $module1;

    private ModulePracticum $module2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup isolated School A & School B
        $this->schoolA = School::create([
            'npsn' => '20201001',
            'name' => 'SMK Pertanian Negeri 1 Mandiri',
            'slug' => 'smk-pertanian-negeri-1-mandiri',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'npsn' => '20201002',
            'name' => 'SMK Pertanian Swasta 2 Berdikari',
            'slug' => 'smk-pertanian-swasta-2-berdikari',
            'status' => 'active',
        ]);

        // 2. Setup Identities
        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Root Superuser',
            'email' => 'superuser@bitanic.test',
        ]);

        $this->adminA = User::factory()->create([
            'role' => 'admin',
            'school_id' => $this->schoolA->id,
            'name' => 'Admin Lab Sekolah A',
            'email' => 'admin.a@bitanic.test',
        ]);

        $this->teacherA = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'name' => 'Guru Agronomi A',
            'email' => 'guru.a@bitanic.test',
        ]);

        $this->teacherB = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolB->id,
            'name' => 'Guru Agronomi B',
            'email' => 'guru.b@bitanic.test',
        ]);

        $this->studentA = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'name' => 'Siswa Praktikan A',
            'email' => 'siswa.a@bitanic.test',
        ]);
        UserProfile::create([
            'user_id' => $this->studentA->id,
            'type' => 'siswa',
            'identifier_number' => '0091122334',
            'class_grade' => 'XI ATPH 1',
            'assigned_kit_id' => 'TK-TEST-A1',
        ]);

        $this->studentB = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolB->id,
            'name' => 'Siswa Praktikan B',
            'email' => 'siswa.b@bitanic.test',
        ]);
        UserProfile::create([
            'user_id' => $this->studentB->id,
            'type' => 'siswa',
            'identifier_number' => '0099988776',
            'class_grade' => 'XI ATPH 2',
        ]);

        // 3. Setup Hardware Fleet
        $this->deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-TEST-A1',
            'name' => 'Trainer Kit Lab A1',
            'bench_location' => 'Meja Praktikum 1',
            'is_active' => true,
        ]);

        $this->deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-TEST-B1',
            'name' => 'Trainer Kit Lab B1',
            'bench_location' => 'Meja Praktikum 1',
            'is_active' => true,
        ]);

        // 4. Setup Curriculum Modules
        $this->module1 = ModulePracticum::create([
            'meeting_number' => 1,
            'title' => 'Pengenalan Sensor 7-in-1 Modbus RS485',
            'badge' => 'Hardware & K3',
            'duration_minutes' => 120,
            'rubric' => [
                ['id' => 'aspek_k3', 'name' => 'K3 dan SOP', 'weight' => 30],
                ['id' => 'aspek_wiring', 'name' => 'Pengawatan RS485', 'weight' => 30],
                ['id' => 'aspek_analisis', 'name' => 'Analisis Data', 'weight' => 40],
            ],
        ]);

        $this->module2 = ModulePracticum::create([
            'meeting_number' => 2,
            'title' => 'Manajemen pH dan Lengas Media Tanam',
            'badge' => 'Agronomi Dasar',
            'duration_minutes' => 120,
        ]);
    }

    /**
     * TEST MATRIX 1: Full Role Authorization Matrix across all 5 implemented identities.
     */
    public function test_full_role_authorization_matrix_across_all_five_roles(): void
    {
        // A. Guest Access Matrix
        $publicRoutes = ['/', '/iot-monitoring', '/analytics', '/telemetry/history', '/modules', '/schedules', '/trainer-kits', '/raw-logs'];
        foreach ($publicRoutes as $uri) {
            $this->get($uri)->assertOk("Guest should access public route: {$uri}");
        }

        $protectedRoutes = ['/reports', '/assessment', '/assessment/export', '/users-data', '/classes', '/settings', '/media', '/media/create'];
        foreach ($protectedRoutes as $uri) {
            $this->get($uri)->assertRedirect('/login', "Guest must be redirected to /login for: {$uri}");
        }

        // B. Siswa (Student) Access Matrix
        $this->actingAs($this->studentA)->get('/reports')->assertOk();
        $this->actingAs($this->studentA)->get('/assessment')->assertOk();
        $this->actingAs($this->studentA)->get('/assessment/export')->assertOk();
        $this->actingAs($this->studentA)->get("/modules/{$this->module1->id}")->assertOk();

        // Siswa must be forbidden from administrative and teacher management routes
        $forbiddenStudentRoutes = ['/users-data', '/classes', '/settings', '/media', '/media/create'];
        foreach ($forbiddenStudentRoutes as $uri) {
            $this->actingAs($this->studentA)->get($uri)->assertForbidden("Student must be forbidden from: {$uri}");
        }
        $this->actingAs($this->studentA)->post('/schedules', [])->assertForbidden();
        $this->actingAs($this->studentA)->post("/trainer-kits/{$this->deviceA->id}/reboot")->assertForbidden();
        $this->actingAs($this->studentA)->post("/trainer-kits/{$this->deviceA->id}/calibration", [])->assertForbidden();
        $this->actingAs($this->studentA)->post("/trainer-kits/{$this->deviceA->id}/api-key/rotate")->assertForbidden();
        $this->actingAs($this->studentA)->post('/settings/school', [])->assertForbidden();
        $this->actingAs($this->studentA)->post('/raw-logs/inject', [])->assertForbidden();

        // C. Guru (Teacher) Access Matrix
        $this->actingAs($this->teacherA)->get('/classes')->assertOk();
        $this->actingAs($this->teacherA)->get('/media')->assertOk();
        $this->actingAs($this->teacherA)->get('/media/create')->assertOk();
        $this->actingAs($this->teacherA)->get('/schedules')->assertOk();
        $this->actingAs($this->teacherA)->get('/reports')->assertOk();
        $this->actingAs($this->teacherA)->get('/assessment')->assertOk();
        $this->actingAs($this->teacherA)->get('/assessment/export')->assertOk();
        $this->actingAs($this->teacherA)->get('/users-data')->assertOk();

        // Teachers are forbidden from superuser-level mutations
        $this->actingAs($this->teacherA)->get('/settings')->assertForbidden();
        $this->actingAs($this->teacherA)->post('/settings/school', [])->assertForbidden();
        $this->actingAs($this->teacherA)->post('/raw-logs/inject', [])->assertForbidden();
        $this->actingAs($this->teacherA)->post("/trainer-kits/{$this->deviceA->id}/api-key/rotate")->assertForbidden();

        // D. Legacy Admin Access Matrix
        // Admin inherits teacher and superuser permissions within their assigned school
        $this->actingAs($this->adminA)->get('/settings')->assertOk();
        $this->actingAs($this->adminA)->get('/classes')->assertOk();
        $this->actingAs($this->adminA)->get('/media')->assertOk();
        $this->actingAs($this->adminA)->get('/users-data')->assertOk();
        $this->actingAs($this->adminA)->get('/reports')->assertOk();
        $this->actingAs($this->adminA)->get('/assessment')->assertOk();

        // E. Superuser Access Matrix
        // Superuser has global access across all pages
        $this->actingAs($this->superuser)->get('/settings')->assertOk();
        $this->actingAs($this->superuser)->get('/classes')->assertOk();
        $this->actingAs($this->superuser)->get('/media')->assertOk();
        $this->actingAs($this->superuser)->get('/users-data')->assertOk();
        $this->actingAs($this->superuser)->get('/reports')->assertOk();
        $this->actingAs($this->superuser)->get('/assessment')->assertOk();
    }

    /**
     * TEST MATRIX 2: Strict Multi-School Isolation and IDOR Protection.
     */
    public function test_multi_school_strict_cross_school_idor_protection(): void
    {
        // 1. Directory Isolation
        $this->actingAs($this->teacherA)->get('/users-data')
            ->assertOk()
            ->assertSee($this->studentA->name)
            ->assertDontSee($this->studentB->name);

        // 2. Report Hub Isolation
        $reportA = StudentReport::create([
            'user_id' => $this->studentA->id,
            'module_id' => $this->module1->id,
            'status' => 'submitted',
            'lks_answers' => ['jawaban' => 'Sekolah A'],
        ]);
        $reportB = StudentReport::create([
            'user_id' => $this->studentB->id,
            'module_id' => $this->module1->id,
            'status' => 'submitted',
            'lks_answers' => ['jawaban' => 'Sekolah B'],
        ]);

        $this->actingAs($this->teacherA)->get('/reports')
            ->assertOk()
            ->assertSee($this->studentA->name)
            ->assertDontSee($this->studentB->name);

        // 3. Cross-School Grading IDOR Rejection
        $this->actingAs($this->teacherA)->post(route('modules.grade-report', $reportB->id), [
            'rubric_scores' => ['aspek1' => 3],
            'teacher_feedback' => 'Tampered cross school grade',
        ])->assertForbidden();

        $this->assertDatabaseHas('student_reports', [
            'id' => $reportB->id,
            'status' => 'submitted',
            'graded_by' => null,
        ]);

        // 4. Cross-School Hardware Calibration IDOR Rejection
        $this->actingAs($this->teacherA)->post(route('kits.calibration', $this->deviceB->id), [
            'phOffset' => 0.5,
            'moistureOffset' => 1.0,
            'ecOffset' => 10,
        ])->assertForbidden();

        // 5. Cross-School Hardware Reboot IDOR Rejection
        $this->actingAs($this->teacherA)->post(route('kits.reboot', $this->deviceB->id))
            ->assertForbidden();

        // 6. Cross-School Schedule Deletion IDOR Rejection
        $scheduleB = ActivitySchedule::create([
            'school_id' => $this->schoolB->id,
            'device_id' => $this->deviceB->id,
            'title' => 'Jadwal Eksklusif Sekolah B',
            'activity_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs($this->teacherA)->delete(route('schedules.destroy', $scheduleB->id))
            ->assertNotFound();
        $this->assertDatabaseHas('activity_schedules', ['id' => $scheduleB->id]);

        // 7. Cross-School Planting Media Mutation IDOR Rejection
        $mediumB = Media::create([
            'school_id' => $this->schoolB->id,
            'device_id' => $this->deviceB->id,
            'name' => 'Plot Cabai Sekolah B',
            'plants' => ['Cabai Rawit'],
            'plants_condition' => ['Cabai Rawit' => ['status' => 'optimal', 'notes' => 'kondisi baik']],
        ]);

        $this->actingAs($this->teacherA)->get(route('media.edit', $mediumB->id))
            ->assertNotFound();

        $this->actingAs($this->teacherA)->put(route('media.update', $mediumB->id), [
            'name' => 'Tampered Plot Name',
            'plants' => ['Cabai Rawit'],
        ])->assertNotFound();
    }

    /**
     * ACADEMIC SCENARIO A: Classroom, Student Group, and Allocation Lifecycle.
     */
    public function test_scenario_a_classroom_and_student_group_lifecycle(): void
    {
        // 1. Authorized Teacher creates a Classroom in School A
        $createClassResponse = $this->actingAs($this->teacherA)->post(route('classes.store'), [
            'name' => 'XII ATPH 1 Unggulan',
            'academic_year' => '2026/2027',
            'department' => 'Agribisnis Tanaman Pangan & Hortikultura',
            'description' => 'Kelas peminatan smart greenhouse',
        ]);
        $createClassResponse->assertRedirect(route('classes.index'));
        $createClassResponse->assertSessionHas('success');

        $classroom = Classroom::where('name', 'XII ATPH 1 Unggulan')->firstOrFail();
        $this->assertEquals($this->schoolA->id, $classroom->school_id);

        // 2. Teacher creates a Student Group within the Classroom
        $createGroupResponse = $this->actingAs($this->teacherA)->post(route('classes.groups.store', $classroom->id), [
            'name' => 'Kelompok 1 - Smart Drip',
            'assigned_kit' => $this->deviceA->code,
        ]);
        $createGroupResponse->assertRedirect(route('classes.index'));

        $group = StudentGroup::where('name', 'Kelompok 1 - Smart Drip')->firstOrFail();
        $this->assertEquals($classroom->id, $group->classroom_id);
        $this->assertEquals($this->schoolA->id, $group->school_id);

        // 3. Teacher allocates Student A to Classroom and Group
        $allocateResponse = $this->from(route('classes.index'))
            ->actingAs($this->teacherA)
            ->post(route('students.allocate', $this->studentA->id), [
                'classroom_id' => $classroom->id,
                'student_group_id' => $group->id,
            ]);
        $allocateResponse->assertRedirect(route('classes.index'));

        $this->studentA->profile->refresh();
        $this->assertEquals($classroom->id, $this->studentA->profile->classroom_id);
        $this->assertEquals($group->id, $this->studentA->profile->student_group_id);

        // 4. Cross-School Allocation Rejection: Teacher B cannot allocate Student A to School B
        $crossSchoolClass = Classroom::create([
            'school_id' => $this->schoolB->id,
            'name' => 'X ATPH Subang',
            'academic_year' => '2026/2027',
            'department' => 'ATPH',
        ]);

        $this->actingAs($this->teacherB)->post(route('students.allocate', $this->studentA->id), [
            'classroom_id' => $crossSchoolClass->id,
        ])->assertNotFound();
    }

    /**
     * ACADEMIC SCENARIO B: Scheduling and Conflict Prevention.
     */
    public function test_scenario_b_schedule_creation_and_conflict_prevention(): void
    {
        $practicumDate = now()->addDays(3)->toDateString();

        // 1. Teacher creates a valid practicum schedule
        $scheduleResponse = $this->actingAs($this->teacherA)->post(route('schedules.store'), [
            'title' => 'Praktikum Sensor pH Tanah',
            'activity_date' => $practicumDate,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'device_id' => $this->deviceA->id,
            'module_practicum_id' => $this->module1->id,
            'agenda' => 'Pemasangan probe dan logging',
        ]);
        $scheduleResponse->assertRedirect(route('schedules.index'));
        $scheduleResponse->assertSessionHas('success');

        $this->assertDatabaseHas('activity_schedules', [
            'school_id' => $this->schoolA->id,
            'title' => 'Praktikum Sensor pH Tanah',
            'device_id' => $this->deviceA->id,
        ]);

        // 2. Conflict Check 1: Overlapping time on the same Trainer Kit must be rejected
        $collisionResponse1 = $this->actingAs($this->teacherA)->post(route('schedules.store'), [
            'title' => 'Sesi Bentrok Kit',
            'activity_date' => $practicumDate,
            'start_time' => '09:00', // overlaps 08:00 - 10:00
            'end_time' => '11:00',
            'device_id' => $this->deviceA->id,
            'module_practicum_id' => $this->module2->id,
        ]);
        $collisionResponse1->assertSessionHasErrors('device_id');
        $this->assertDatabaseMissing('activity_schedules', ['title' => 'Sesi Bentrok Kit']);

        // 3. Conflict Check 2: End time must be greater than start time
        $invalidTimeResponse = $this->actingAs($this->teacherA)->post(route('schedules.store'), [
            'title' => 'Sesi Jam Terbalik',
            'activity_date' => $practicumDate,
            'start_time' => '14:00',
            'end_time' => '13:00', // invalid!
            'device_id' => $this->deviceA->id,
        ]);
        $invalidTimeResponse->assertSessionHasErrors('end_time');

        // 4. Conflict Check 3: Cross-school device booking must be rejected
        $crossDeviceResponse = $this->actingAs($this->teacherA)->post(route('schedules.store'), [
            'title' => 'Sesi Booking Kit Sekolah B',
            'activity_date' => $practicumDate,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'device_id' => $this->deviceB->id, // belongs to School B!
        ]);
        $crossDeviceResponse->assertSessionHasErrors('device_id');
    }

    /**
     * ACADEMIC SCENARIO C: Student LKS Submission and Teacher Rubric Grading.
     */
    public function test_scenario_c_practicum_lks_submission_and_rubric_grading(): void
    {
        // 1. Telemetry Reading fixture for snapshot evidence
        $reading = TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'ph' => 6.5,
            'humidity' => 55.0,
            'ec' => 1200,
            'temperature' => 25.0,
            'nitrogen' => 60,
            'phosphorus' => 35,
            'potassium' => 140,
            'provenance' => 'hardware',
            'recorded_at' => now(),
        ]);

        // 2. Student A submits LKS for Module 1
        $submitResponse = $this->actingAs($this->studentA)->post(route('modules.submit-lks', $this->module1->id), [
            'lks_answers' => [
                'langkah_1' => 'Kabel RS485 tersambung ke pin A dan B secara presisi.',
                'langkah_2' => 'Grounding terpasang untuk meredam derau listrik.',
            ],
            'observation_notes' => 'Pembacaan pH awal stabil pada 6.50.',
            'device_id' => $this->deviceA->id,
            'evidence_reading_id' => $reading->id,
        ]);
        $submitResponse->assertRedirect(route('modules.show', $this->module1->id));
        $submitResponse->assertSessionHas('success');

        $report = StudentReport::where('user_id', $this->studentA->id)
            ->where('module_id', $this->module1->id)
            ->firstOrFail();

        $this->assertEquals('submitted', $report->status);
        $this->assertEquals(6.5, $report->telemetry_snapshot['ph']);

        // 3. Student B cannot access or grade Student A's report
        $this->actingAs($this->studentB)->post(route('modules.grade-report', $report->id), [
            'rubric_scores' => ['r1' => 4],
        ])->assertForbidden();

        // 4. Authorized Teacher A grades the report with 4-scale rubric
        $gradeResponse = $this->actingAs($this->teacherA)->post(route('modules.grade-report', $report->id), [
            'rubric_scores' => [
                'aspek_k3' => 4, // 100
                'aspek_wiring' => 4, // 100
                'aspek_analisis' => 3, // 85
            ],
            'teacher_feedback' => 'Analisis data agronomi sangat sistematis dan rapi.',
        ]);
        $gradeResponse->assertRedirect(route('modules.show', $this->module1->id));
        $gradeResponse->assertSessionHas('success');

        $report->refresh();
        $this->assertEquals('graded', $report->status);
        $this->assertNotNull($report->final_grade);
        $this->assertEquals($this->teacherA->id, $report->graded_by);
        $this->assertEquals('Analisis data agronomi sangat sistematis dan rapi.', $report->teacher_feedback);

        // 5. Student cannot re-submit once report is graded
        $resubmitResponse = $this->actingAs($this->studentA)->post(route('modules.submit-lks', $this->module1->id), [
            'lks_answers' => ['q' => 'overwriting graded report attempt'],
        ]);
        $resubmitResponse->assertSessionHasErrors('lks_answers');
    }

    /**
     * ACADEMIC SCENARIO D: Verified Gradebook Aggregation and Export.
     */
    public function test_scenario_d_competency_assessment_gradebook_and_csv_export(): void
    {
        // 1. Create two distinct graded reports for Student A
        // Module 1 grade: 90.0, Module 2 grade: 100.0 => Average: 95.0 (Predikat A)
        StudentReport::create([
            'user_id' => $this->studentA->id,
            'module_id' => $this->module1->id,
            'status' => 'graded',
            'final_grade' => 90.0,
            'graded_by' => $this->teacherA->id,
            'graded_at' => now(),
        ]);
        StudentReport::create([
            'user_id' => $this->studentA->id,
            'module_id' => $this->module2->id,
            'status' => 'graded',
            'final_grade' => 100.0,
            'graded_by' => $this->teacherA->id,
            'graded_at' => now(),
        ]);

        // Student A profile has an old unverified grade of 65.0
        $this->studentA->profile->update([
            'average_grade' => 65.0,
            'competency_predicative' => 'C',
        ]);

        // 2. View Competency Assessment page
        $response = $this->actingAs($this->teacherA)->get('/assessment');
        $response->assertOk();
        $response->assertSee($this->studentA->name);

        // The page must display the live verified average (95.0), NOT the obsolete profile grade (65.0)
        $response->assertSee('95.0');
        $response->assertDontSee('65.0');

        // 3. Export Gradebook to CSV
        $export = $this->actingAs($this->teacherA)->get('/assessment/export');
        $export->assertOk();
        $csv = $export->streamedContent();

        $this->assertStringContainsString($this->studentA->name, $csv);
        $this->assertStringContainsString('95.0', $csv);
        $this->assertStringNotContainsString('65.0', $csv);
        // Cross-school student B must NOT appear in School A's export
        $this->assertStringNotContainsString($this->studentB->name, $csv);
    }

    /**
     * IOT SCENARIO A & B: Authenticated Telemetry Ingestion vs Invalid Ingress Rejection.
     */
    public function test_iot_telemetry_ingestion_authentication_and_invalid_ingress(): void
    {
        // 1. Generate legitimate device key for Device A
        $keyResult = DeviceApiKey::generateForDevice($this->deviceA);
        $plainKey = $keyResult['plain_key'];

        // 2. Valid Ingestion with X-Device-Key
        $validPayload = [
            'soilrs485' => [
                'Ph' => 6.4,
                'Humidity' => 58.0,
                'Ec' => 1400,
                'Temperature' => 26.0,
                'Nitrogen' => 70,
                'Phosphorus' => 40,
                'Potassium' => 150,
            ],
        ];

        $ingressResponse = $this->postJson('/api/v1/telemetry/ingest', $validPayload, [
            'X-Device-Key' => $plainKey,
        ]);
        $ingressResponse->assertStatus(201)->assertJsonPath('success', true);

        // Verify database persistence and hardware provenance
        $latestReading = TelemetryReading::where('device_id', $this->deviceA->id)->latest('id')->first();
        $this->assertNotNull($latestReading);
        $this->assertEquals('hardware', $latestReading->provenance);
        $this->assertEquals(6.4, (float) $latestReading->ph);

        // Verify last_seen_at was updated on hardware ingress
        $this->deviceA->refresh();
        $this->assertNotNull($this->deviceA->last_seen_at);
        $this->assertTrue($this->deviceA->last_seen_at->isToday());

        // 3. Invalid Ingress: Fake / Malformed Key must be rejected with 401
        $this->postJson('/api/v1/telemetry/ingest', $validPayload, [
            'X-Device-Key' => 'btk_invalid_fake_key_1234567890',
        ])->assertUnauthorized();

        // 4. Missing Header must be rejected with 401
        $this->postJson('/api/v1/telemetry/ingest', $validPayload)->assertUnauthorized();

        // 5. Ingress for Revoked Key must be rejected with 401
        $keyResult['api_key']->revoke();
        $this->postJson('/api/v1/telemetry/ingest', $validPayload, [
            'X-Device-Key' => $plainKey,
        ])->assertUnauthorized();
    }

    /**
     * IOT SCENARIO C & F: Truthful Simulation Provenance and Actuator Safeguards.
     */
    public function test_iot_simulation_truthfulness_and_actuator_safeguards(): void
    {
        // 1. Simulate telemetry reading
        $simReading = TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'ph' => 4.2, // extreme acid
            'humidity' => 20.0,
            'ec' => 3000,
            'temperature' => 32.0,
            'nitrogen' => 180,
            'phosphorus' => 90,
            'potassium' => 270,
            'provenance' => 'simulation',
            'recorded_at' => now(),
        ]);

        // Stale device last_seen_at remains unchanged by simulation
        $initialLastSeen = now()->subHours(5);
        $this->deviceA->update(['last_seen_at' => $initialLastSeen]);

        // Dashboard inspection: An offline device with simulation telemetry does NOT raise critical physical intervention
        $dashboardResponse = $this->actingAs($this->teacherA)->get('/');
        $dashboardResponse->assertOk();
        // Provenance is honest
        $this->assertEquals('simulation', $simReading->provenance);

        // 2. Hardware Actuator Safeguard: Reboot remains simulation-only without physical ACK
        $rebootResponse = $this->actingAs($this->teacherA)->post(route('kits.reboot', $this->deviceA->id));
        $rebootResponse->assertRedirect(route('kits.index'));
        $rebootResponse->assertSessionHas('warning');

        $warning = session('warning');
        $this->assertStringContainsString('simulasi reboot', strtolower($warning));

        // 3. Atomic Calibration: Updates offsets and logs audit entry
        $calibResponse = $this->actingAs($this->teacherA)->post(route('kits.calibration', $this->deviceA->id), [
            'phOffset' => 0.2,
            'moistureOffset' => -1.5,
            'ecOffset' => 50,
        ]);
        $calibResponse->assertRedirect(route('kits.index'));
        $calibResponse->assertSessionHas('success');

        $this->deviceA->refresh();
        $this->assertEquals(0.2, (float) $this->deviceA->calibration_offsets['ph']);
        $this->assertEquals(-1.5, (float) $this->deviceA->calibration_offsets['humidity']);
        $this->assertEquals(50.0, (float) $this->deviceA->calibration_offsets['ec']);
    }
}
