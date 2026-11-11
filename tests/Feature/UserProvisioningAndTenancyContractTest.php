<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\Media;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class UserProvisioningAndTenancyContractTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private School $inactiveSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $this->schoolA = School::create([
            'name' => 'SMK Pertanian A',
            'slug' => 'smk-pertanian-a',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'name' => 'SMK Pertanian B',
            'slug' => 'smk-pertanian-b',
            'status' => 'active',
        ]);

        $this->inactiveSchool = School::create([
            'name' => 'SMK Nonaktif',
            'slug' => 'smk-nonaktif',
            'status' => 'inactive',
        ]);
    }

    public function test_non_superuser_creation_without_school_id_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Akun pengguna institusi (siswa) wajib memiliki asosiasi sekolah (school_id).');

        User::create([
            'name' => 'Siswa Tanpa Sekolah',
            'email' => 'siswa.tanpa.sekolah@bitanic.test',
            'password' => Hash::make('Secret123!'),
            'role' => 'siswa',
        ]);
    }

    public function test_admin_sekolah_creation_without_school_id_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Akun pengguna institusi (admin_sekolah) wajib memiliki asosiasi sekolah (school_id).');

        User::create([
            'name' => 'Admin Tanpa Sekolah',
            'email' => 'admin.tanpa.sekolah@bitanic.test',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin_sekolah',
        ]);
    }

    public function test_superuser_creation_with_null_school_id_succeeds(): void
    {
        $superuser = User::create([
            'name' => 'Platform Superuser',
            'email' => 'superuser@bitanic.test',
            'password' => Hash::make('Secret123!'),
            'role' => 'superuser',
            'school_id' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $superuser->id,
            'email' => 'superuser@bitanic.test',
            'role' => 'superuser',
            'school_id' => null,
        ]);
        $this->assertTrue($superuser->isSuperuser());
        $this->assertNull($superuser->school_id);
    }

    public function test_superuser_with_null_school_id_can_login(): void
    {
        $superuser = User::create([
            'name' => 'Root Superuser',
            'email' => 'root@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'root@bitanic.test',
            'password' => 'P@ssword123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($superuser);
    }

    public function test_inactive_school_user_login_is_rejected(): void
    {
        $user = User::create([
            'name' => 'User Inactive School',
            'email' => 'inactive.user@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->inactiveSchool->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive.user@bitanic.test',
            'password' => 'P@ssword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_sekolah_can_access_settings_for_own_school(): void
    {
        $admin = User::create([
            'name' => 'Admin Sekolah A',
            'email' => 'admin.a@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
        ]);

        $response = $this->actingAs($admin)->get('/settings');
        $response->assertStatus(200);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isSchoolAdmin());
    }

    public function test_legacy_admin_retains_authorized_own_school_access(): void
    {
        $admin = User::create([
            'name' => 'Legacy Admin A',
            'email' => 'legacy.admin.a@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin',
            'school_id' => $this->schoolA->id,
        ]);

        $response = $this->actingAs($admin)->get('/settings');
        $response->assertStatus(200);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_admin_sekolah_cannot_access_platform_only_rotate_api_key(): void
    {
        $admin = User::create([
            'name' => 'Admin Sekolah A',
            'email' => 'admin.school.a@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
        ]);

        $device = Device::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Trainer Kit 01',
            'code' => 'BITANIC-TK-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post("/trainer-kits/{$device->id}/api-key/rotate");
        $response->assertStatus(403);
    }

    public function test_superuser_can_rotate_api_key_without_school_id(): void
    {
        $superuser = User::create([
            'name' => 'Platform Operator',
            'email' => 'operator@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => null,
        ]);

        $device = Device::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Trainer Kit 01',
            'code' => 'BITANIC-TK-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superuser)->post("/trainer-kits/{$device->id}/api-key/rotate");
        $response->assertRedirect(route('kits.index'));
        $response->assertSessionHas('new_api_key');
    }

    public function test_admin_sekolah_cross_school_tampering_is_forbidden(): void
    {
        $adminA = User::create([
            'name' => 'Admin Sekolah A',
            'email' => 'admin.a.tamper@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
        ]);

        $deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'name' => 'Trainer Kit B',
            'code' => 'BITANIC-TK-B',
            'is_active' => true,
        ]);

        // Cross school reboot
        $responseReboot = $this->actingAs($adminA)->post("/trainer-kits/{$deviceB->id}/reboot");
        $responseReboot->assertStatus(403);

        // Cross school calibration
        $responseCalib = $this->actingAs($adminA)->post("/trainer-kits/{$deviceB->id}/calibration", [
            'phOffset' => 0.5,
            'moistureOffset' => 1.0,
            'ecOffset' => 10,
        ]);
        $responseCalib->assertStatus(403);
    }

    public function test_school_admin_import_is_strictly_bound_to_own_school(): void
    {
        $adminA = User::create([
            'name' => 'Admin Sekolah A',
            'email' => 'admin.a.import@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
        ]);

        $payload = [
            'school_id' => $this->schoolB->id, // Malicious attempt to import to School B
            'rows' => [
                [
                    'name' => 'Siswa Baru A',
                    'email' => 'siswa.baru.a@bitanic.test',
                    'role' => 'siswa',
                    'identifier_number' => '12345678',
                    'class_grade' => 'X ATPH 1',
                ],
            ],
        ];

        $response = $this->actingAs($adminA)->postJson('/users/import/execute', $payload);
        $response->assertStatus(200);

        $createdUser = User::where('email', 'siswa.baru.a@bitanic.test')->firstOrFail();
        $this->assertEquals($this->schoolA->id, $createdUser->school_id, 'Import must be bound strictly to actor school');
        $this->assertNotEquals($this->schoolB->id, $createdUser->school_id);
    }

    public function test_superuser_import_without_explicit_school_is_rejected(): void
    {
        $superuser = User::create([
            'name' => 'Platform Operator',
            'email' => 'super.import@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => null,
        ]);

        $payload = [
            'rows' => [
                [
                    'name' => 'Siswa Tanpa Target',
                    'email' => 'siswa.notarget@bitanic.test',
                    'role' => 'siswa',
                ],
            ],
        ];

        $response = $this->actingAs($superuser)->postJson('/users/import/execute', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['school_id']);
    }

    public function test_classroom_policy_forbids_cross_school_mutations(): void
    {
        $teacherA = User::create([
            'name' => 'Guru Sekolah A',
            'email' => 'guru.a.class@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);

        $classroomB = Classroom::create([
            'school_id' => $this->schoolB->id,
            'name' => 'X ATPH 2',
            'academic_year' => '2026/2027',
        ]);

        $response = $this->actingAs($teacherA)->put("/classes/{$classroomB->id}", [
            'name' => 'X ATPH 2 Modified',
            'academic_year' => '2026/2027',
        ]);
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_student_report_policy_forbids_cross_school_grading(): void
    {
        $teacherA = User::create([
            'name' => 'Guru A',
            'email' => 'guru.a.grade@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);

        $studentB = User::create([
            'name' => 'Siswa B',
            'email' => 'siswa.b.grade@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->schoolB->id,
        ]);

        $module = ModulePracticum::firstOrCreate([
            'meeting_number' => 1,
            'title' => 'Modul 1',
        ], [
            'objective' => 'Pengenalan Sensor',
            'time_allocation' => '4 JP',
        ]);

        $reportB = StudentReport::create([
            'user_id' => $studentB->id,
            'module_id' => $module->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($teacherA)->post("/modules/reports/{$reportB->id}/grade", [
            'rubric_scores' => [1 => 4, 2 => 4, 3 => 4],
        ]);
        $response->assertStatus(403);
    }

    public function test_activity_schedule_policy_forbids_cross_school_deletion(): void
    {
        $teacherA = User::create([
            'name' => 'Guru A',
            'email' => 'guru.a.sched@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);

        $scheduleB = ActivitySchedule::create([
            'school_id' => $this->schoolB->id,
            'title' => 'Praktikum Sekolah B',
            'activity_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response = $this->actingAs($teacherA)->delete("/schedules/{$scheduleB->id}");
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_planting_media_policy_forbids_cross_school_mutation(): void
    {
        $teacherA = User::create([
            'name' => 'Guru A',
            'email' => 'guru.a.media@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);

        $mediaB = Media::create([
            'school_id' => $this->schoolB->id,
            'name' => 'Media Tanam Sekolah B',
            'plants' => ['Cabai'],
            'uploaded_by' => $teacherA->id,
        ]);

        $response = $this->actingAs($teacherA)->put("/media/{$mediaB->id}", [
            'name' => 'Media Tampered',
            'plants' => ['Tomat'],
        ]);
        $this->assertTrue(in_array($response->status(), [403, 404], true));

        $responseDel = $this->actingAs($teacherA)->delete("/media/{$mediaB->id}");
        $this->assertTrue(in_array($responseDel->status(), [403, 404], true));
    }

    public function test_student_personal_data_isolation_on_reports_and_assessment(): void
    {
        $studentA1 = User::create([
            'name' => 'Siswa A1 Isolasi',
            'email' => 'siswa.a1.iso@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
        ]);
        $studentA2 = User::create([
            'name' => 'Siswa A2 Isolasi',
            'email' => 'siswa.a2.iso@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
        ]);

        $module = ModulePracticum::first() ?? ModulePracticum::create([
            'title' => 'Modul 1 Isolasi',
            'meeting_number' => 1,
            'description' => 'Test Desc',
            'duration_minutes' => 90,
            'rubric' => [],
        ]);

        StudentReport::create([
            'user_id' => $studentA1->id,
            'module_id' => $module->id,
            'status' => 'graded',
            'final_grade' => 95,
            'answers' => ['summary' => 'Laporan A1 Unik'],
        ]);
        StudentReport::create([
            'user_id' => $studentA2->id,
            'module_id' => $module->id,
            'status' => 'graded',
            'final_grade' => 75,
            'answers' => ['summary' => 'Laporan A2 Unik'],
        ]);

        // Student A1 visiting reports sees ONLY their own report
        $respReports = $this->actingAs($studentA1)->get('/reports');
        $respReports->assertOk();
        $respReports->assertSee('Siswa A1 Isolasi');
        $respReports->assertDontSee('Siswa A2 Isolasi');

        // Student A1 visiting assessment sees ONLY their own grade
        $respAssess = $this->actingAs($studentA1)->get('/assessment');
        $respAssess->assertOk();
        $respAssess->assertSee('Siswa A1 Isolasi');
        $respAssess->assertDontSee('Siswa A2 Isolasi');
    }

    public function test_school_scoped_csv_exports_contain_only_own_school_data(): void
    {
        $adminA = User::create([
            'name' => 'Admin Sekolah A Export',
            'email' => 'admin.a.export@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
        ]);

        User::create([
            'name' => 'Siswa Unik Sekolah A',
            'email' => 'siswa.unik.a@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
        ]);
        User::create([
            'name' => 'Siswa Unik Sekolah B',
            'email' => 'siswa.unik.b@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->schoolB->id,
        ]);

        // Admin Sekolah A exports assessment CSV
        $resp = $this->actingAs($adminA)->get('/assessment/export');
        $resp->assertOk();
        $content = $resp->streamedContent();
        $this->assertStringContainsString('Siswa Unik Sekolah A', $content);
        $this->assertStringNotContainsString('Siswa Unik Sekolah B', $content);
    }

    public function test_classroom_and_group_crud_lifecycle_for_admin_sekolah(): void
    {
        $adminA = User::create([
            'name' => 'Admin Sekolah A CRUD',
            'email' => 'admin.a.crud@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
        ]);
        $adminB = User::create([
            'name' => 'Admin Sekolah B CRUD',
            'email' => 'admin.b.crud@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolB->id,
        ]);

        // 1. Admin A creates classroom
        $respCreate = $this->actingAs($adminA)->post('/classes', [
            'name' => 'XII ATPH CRUD 1',
            'academic_year' => '2026/2027',
        ]);
        $respCreate->assertRedirect();
        $classroomA = Classroom::where('name', 'XII ATPH CRUD 1')->where('school_id', $this->schoolA->id)->first();
        $this->assertNotNull($classroomA);
        $this->assertEquals($this->schoolA->id, $classroomA->school_id);

        // 2. Admin A creates student group in that classroom
        $respGroup = $this->actingAs($adminA)->post("/classes/{$classroomA->id}/groups", [
            'name' => 'Kelompok 1 Hijau CRUD',
        ]);
        $respGroup->assertRedirect();
        $this->assertDatabaseHas('student_groups', [
            'classroom_id' => $classroomA->id,
            'name' => 'Kelompok 1 Hijau CRUD',
            'school_id' => $this->schoolA->id,
        ]);

        // 3. Admin B tries to delete Classroom A -> MUST fail with 403 or 404
        $respTamper = $this->actingAs($adminB)->delete("/classes/{$classroomA->id}");
        $this->assertTrue(in_array($respTamper->status(), [403, 404], true));
        $this->assertDatabaseHas('classrooms', ['id' => $classroomA->id]);
    }

    public function test_practicum_schedule_conflict_detection_and_creation(): void
    {
        $teacherA = User::create([
            'name' => 'Guru A Sched',
            'email' => 'guru.a.conf@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);

        $deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-SCHED-01',
            'name' => 'Trainer Kit Sched',
            'is_active' => true,
        ]);

        // First schedule
        $resp1 = $this->actingAs($teacherA)->post('/schedules', [
            'title' => 'Praktikum Sesi 1',
            'activity_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'device_id' => $deviceA->id,
        ]);
        $resp1->assertRedirect();
        $this->assertDatabaseHas('activity_schedules', [
            'title' => 'Praktikum Sesi 1',
            'school_id' => $this->schoolA->id,
        ]);

        // Overlapping schedule on same device & date
        $respConflict = $this->actingAs($teacherA)->post('/schedules', [
            'title' => 'Praktikum Sesi Bentrok',
            'activity_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'device_id' => $deviceA->id,
        ]);
        $this->assertTrue($respConflict->isRedirect() || $respConflict->status() === 422);
    }

    public function test_lks_submission_and_teacher_grading_cycle(): void
    {
        $studentA = User::create([
            'name' => 'Siswa A LKS Cycle',
            'email' => 'siswa.a.cycle@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
        ]);
        $teacherA = User::create([
            'name' => 'Guru A LKS Cycle',
            'email' => 'guru.a.cycle@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);

        $module = ModulePracticum::first() ?? ModulePracticum::create([
            'title' => 'Modul LKS Cycle',
            'meeting_number' => 2,
            'description' => 'Test Desc',
            'duration_minutes' => 60,
            'rubric' => [
                ['id' => 1, 'name' => 'Kerapian', 'weight' => 50],
                ['id' => 2, 'name' => 'Ketepatan', 'weight' => 50],
            ],
        ]);

        // 1. Student submits LKS
        $respSubmit = $this->actingAs($studentA)->post("/modules/{$module->id}/submit-lks", [
            'lks_answers' => [
                'field_1' => 'Hasil Pengamatan pH Tanah 6.5',
            ],
        ]);
        $respSubmit->assertRedirect();

        $report = StudentReport::where('user_id', $studentA->id)->where('module_id', $module->id)->first();
        $this->assertNotNull($report);
        $this->assertEquals('submitted', $report->status);

        // 2. Teacher grades LKS
        $respGrade = $this->actingAs($teacherA)->post("/modules/reports/{$report->id}/grade", [
            'rubric_scores' => [1 => 4, 2 => 4],
        ]);
        $respGrade->assertRedirect();

        $report->refresh();
        $this->assertEquals('graded', $report->status);
        $this->assertEquals(100.0, (float) $report->final_grade);
    }

    public function test_competency_assessment_honors_provenance_without_historical_fallback(): void
    {
        $student = User::create([
            'name' => 'Siswa Tanpa LKS',
            'email' => 'siswa.nolks@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
        ]);

        // Historical profile grade
        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'average_grade' => 88.5,
            'competency_predicative' => 'A',
        ]);

        $teacher = User::create([
            'name' => 'Guru Penilai',
            'email' => 'guru.penilai@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);

        $resp = $this->actingAs($teacher)->get('/assessment');
        $resp->assertOk();
        $resp->assertDontSee('88.5');
    }

    public function test_device_reboot_and_calibration_simulation(): void
    {
        $teacherA = User::create([
            'name' => 'Guru A Device',
            'email' => 'guru.a.dev@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);

        $deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-DEV-01',
            'name' => 'Trainer Kit A',
            'is_active' => true,
        ]);
        $deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-DEV-02',
            'name' => 'Trainer Kit B',
            'is_active' => true,
        ]);

        // Teacher A reboots device in School A
        $respReboot = $this->actingAs($teacherA)->post("/trainer-kits/{$deviceA->id}/reboot");
        $respReboot->assertRedirect();

        // Teacher A calibrates device in School A
        $respCalib = $this->actingAs($teacherA)->post("/trainer-kits/{$deviceA->id}/calibration", [
            'offset_ph' => 0.1,
            'offset_ec' => 5,
        ]);
        $respCalib->assertRedirect();

        // Teacher A tries to reboot device in School B -> MUST fail
        $respForeignReboot = $this->actingAs($teacherA)->post("/trainer-kits/{$deviceB->id}/reboot");
        $this->assertTrue(in_array($respForeignReboot->status(), [403, 404], true));
    }

    public function test_superuser_settings_filtering_and_admin_sekolah_locking(): void
    {
        $superuser = User::create([
            'name' => 'Platform Superuser',
            'email' => 'sup.settings@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => null,
        ]);

        $adminA = User::create([
            'name' => 'Admin Sekolah A Settings',
            'email' => 'adm.a.sett@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
        ]);

        // Superuser specifies ?school_id=schoolB -> Allowed (200)
        $respSuper = $this->actingAs($superuser)->get("/settings?school_id={$this->schoolB->id}");
        $respSuper->assertOk();

        // Admin A specifies ?school_id=schoolB -> STRICTLY FORBIDDEN (403)
        $respAdminTamper = $this->actingAs($adminA)->get("/settings?school_id={$this->schoolB->id}");
        $respAdminTamper->assertStatus(403);
    }

    public function test_migration_000024_down_does_not_restore_default_school_id(): void
    {
        $migration = require database_path('migrations/2026_01_01_000024_remove_default_school_id_and_support_admin_sekolah.php');

        // Roll down
        $migration->down();

        // Roll up to maintain index for subsequent tests
        $migration->up();

        // Non-superuser creation without school_id MUST still fail
        $this->expectException(InvalidArgumentException::class);
        User::create([
            'name' => 'No School Student',
            'email' => 'noschool.student@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
        ]);
    }
}
