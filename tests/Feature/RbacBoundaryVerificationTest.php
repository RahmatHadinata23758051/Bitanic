<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacBoundaryVerificationTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private School $inactiveSchool;

    private User $superuser;

    private User $adminA;

    private User $adminB;

    private User $teacherA;

    private User $teacherB;

    private User $studentA;

    private User $studentB;

    private Device $deviceA;

    private Device $deviceB;

    private Classroom $classroomA;

    private Classroom $classroomB;

    private ActivitySchedule $scheduleA;

    private ActivitySchedule $scheduleB;

    private ModulePracticum $module;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(ValidateCsrfToken::class)) {
            $this->withoutMiddleware(ValidateCsrfToken::class);
        }

        $this->schoolA = School::create([
            'npsn' => '20202001',
            'name' => 'SMK Pertanian A (Rbac Verification)',
            'slug' => 'smk-pertanian-a-rbac',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'npsn' => '20202002',
            'name' => 'SMK Pertanian B (Rbac Verification)',
            'slug' => 'smk-pertanian-b-rbac',
            'status' => 'active',
        ]);

        $this->inactiveSchool = School::create([
            'npsn' => '20202099',
            'name' => 'SMK Non-Aktif (Rbac Test)',
            'slug' => 'smk-nonaktif-rbac',
            'status' => 'inactive',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Superuser Global',
            'email' => 'su.rbac@bitanic.test',
        ]);

        $this->adminA = User::factory()->create([
            'role' => 'admin',
            'school_id' => $this->schoolA->id,
            'name' => 'Admin Sekolah A',
            'email' => 'admin.rbac.a@bitanic.test',
        ]);

        $this->adminB = User::factory()->create([
            'role' => 'admin',
            'school_id' => $this->schoolB->id,
            'name' => 'Admin Sekolah B',
            'email' => 'admin.rbac.b@bitanic.test',
        ]);

        $this->teacherA = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'name' => 'Guru Sekolah A',
            'email' => 'guru.rbac.a@bitanic.test',
        ]);

        $this->teacherB = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolB->id,
            'name' => 'Guru Sekolah B',
            'email' => 'guru.rbac.b@bitanic.test',
        ]);

        $this->studentA = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'name' => 'Siswa Sekolah A',
            'email' => 'siswa.rbac.a@bitanic.test',
        ]);

        $this->studentB = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolB->id,
            'name' => 'Siswa Sekolah B',
            'email' => 'siswa.rbac.b@bitanic.test',
        ]);

        $this->classroomA = Classroom::create([
            'school_id' => $this->schoolA->id,
            'name' => 'X ATPH A',
            'academic_year' => '2026/2027',
        ]);

        $this->classroomB = Classroom::create([
            'school_id' => $this->schoolB->id,
            'name' => 'X ATPH B',
            'academic_year' => '2026/2027',
        ]);

        UserProfile::create([
            'user_id' => $this->studentA->id,
            'type' => 'siswa',
            'identifier_number' => 'NISN-A001',
            'classroom_id' => $this->classroomA->id,
            'class_grade' => 'X ATPH A',
        ]);

        UserProfile::create([
            'user_id' => $this->studentB->id,
            'type' => 'siswa',
            'identifier_number' => 'NISN-B001',
            'classroom_id' => $this->classroomB->id,
            'class_grade' => 'X ATPH B',
        ]);

        $this->deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-RBAC-A1',
            'name' => 'Kit Lab A1',
            'is_active' => true,
        ]);

        $this->deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-RBAC-B1',
            'name' => 'Kit Lab B1',
            'is_active' => true,
        ]);

        TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'ph' => 6.5,
            'ec' => 1500,
            'humidity' => 55,
            'temperature' => 26,
            'nitrogen' => 60,
            'phosphorus' => 35,
            'potassium' => 120,
            'provenance' => 'hardware',
            'recorded_at' => now(),
        ]);

        TelemetryReading::create([
            'device_id' => $this->deviceB->id,
            'ph' => 7.1,
            'ec' => 1800,
            'humidity' => 60,
            'temperature' => 28,
            'nitrogen' => 70,
            'phosphorus' => 40,
            'potassium' => 140,
            'provenance' => 'hardware',
            'recorded_at' => now(),
        ]);

        $this->module = ModulePracticum::firstOrCreate(
            ['meeting_number' => 1],
            [
                'title' => 'Pengenalan Sensor Tanah Modbus RS485',
                'description' => 'Modul 1 ATPH 4.0',
                'duration_minutes' => 90,
                'rubric' => [
                    ['id' => 'asp_1', 'label' => 'Persiapan Alat', 'weight' => 25],
                    ['id' => 'asp_2', 'label' => 'Pengukuran pH/EC', 'weight' => 25],
                    ['id' => 'asp_3', 'label' => 'Analisis Data', 'weight' => 25],
                    ['id' => 'asp_4', 'label' => 'Kesimpulan LKS', 'weight' => 25],
                ],
            ]
        );

        $this->scheduleA = ActivitySchedule::create([
            'school_id' => $this->schoolA->id,
            'title' => 'Praktikum Tanah A',
            'classroom_id' => $this->classroomA->id,
            'device_id' => $this->deviceA->id,
            'module_practicum_id' => $this->module->id,
            'activity_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $this->scheduleB = ActivitySchedule::create([
            'school_id' => $this->schoolB->id,
            'title' => 'Praktikum Tanah B',
            'classroom_id' => $this->classroomB->id,
            'device_id' => $this->deviceB->id,
            'module_practicum_id' => $this->module->id,
            'activity_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        StudentReport::create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->studentA->id,
            'module_id' => $this->module->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        StudentReport::create([
            'school_id' => $this->schoolB->id,
            'user_id' => $this->studentB->id,
            'module_id' => $this->module->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * 0. IDENTITY SEMANTICS: User::isSuperuser() strictly returns true for superuser only.
     */
    public function test_identity_semantics_separate_platform_superuser_from_legacy_admin(): void
    {
        $this->assertTrue($this->superuser->isSuperuser(), 'Superuser must return isSuperuser() true');
        $this->assertFalse($this->adminA->isSuperuser(), 'Legacy admin must NEVER return isSuperuser() true');
        $this->assertTrue($this->adminA->isAdmin(), 'Admin must return isAdmin() true');
        $this->assertTrue($this->adminA->isSchoolAdmin(), 'Admin must return isSchoolAdmin() true');
        $this->assertFalse($this->teacherA->isSuperuser());
        $this->assertFalse($this->studentA->isSuperuser());
    }

    /**
     * 1. MIDDLEWARE PRIVILEGE ISOLATION: Superuser-only routes strictly deny legacy admin.
     */
    public function test_ensure_user_has_role_rejects_admin_on_platform_superuser_routes(): void
    {
        // 1. Raw logs manual frame injection (platform-only operation)
        $this->actingAs($this->adminA)->post(route('rawlogs.inject'), [
            'kit_code' => $this->deviceA->code,
            'packet_hex' => '010300000002C40B',
            'level' => 'INFO',
        ])->assertForbidden();

        // 2. Hardware API key rotation (platform-only operation)
        $this->actingAs($this->adminA)->post(route('kits.api-key.rotate', $this->deviceA))->assertForbidden();

        // 3. Superuser CAN access platform-only operations
        $this->actingAs($this->superuser)->post(route('rawlogs.inject'), [
            'kit_code' => $this->deviceA->code,
            'packet_hex' => '010300000002C40B',
            'level' => 'INFO',
        ])->assertRedirect(route('rawlogs.index'));
    }

    /**
     * 2. TENANT-SCOPED SETTINGS: Admin and Superuser can access own-school settings, but teacher and student cannot.
     */
    public function test_settings_accessible_to_admin_for_own_school_but_denied_to_others(): void
    {
        // Admin A can access /settings for own school
        $this->actingAs($this->adminA)->get(route('settings.index'))->assertOk();

        // Admin A can update own school settings
        $this->actingAs($this->adminA)->post(route('settings.update'), [
            'school_name' => $this->schoolA->name,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru Pengampu A',
            'thresholds' => [
                'minMoisture' => 40,
                'maxMoisture' => 80,
                'minPH' => 6.0,
                'maxPH' => 7.0,
                'minEC' => 1000,
                'maxEC' => 2000,
                'minNitrogen' => 50,
                'maxNitrogen' => 150,
            ],
        ])->assertRedirect(route('settings.index'));

        // Teacher and Student are strictly forbidden from settings
        $this->actingAs($this->teacherA)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->studentA)->get(route('settings.index'))->assertForbidden();
    }

    /**
     * TEST A: Admin A requests School B Dashboard.
     * Expected: Cross-school request fails closed (403), zero School B data returned.
     */
    public function test_a_admin_a_requests_school_b_dashboard_fails_closed(): void
    {
        // 1. Cross-school request with ?school_id=B fails closed with 403 Forbidden
        $crossResponse = $this->actingAs($this->adminA)->get('/?school_id='.$this->schoolB->id);
        $crossResponse->assertForbidden();

        // 2. Normal access without parameter returns School A data only
        $ownResponse = $this->actingAs($this->adminA)->get('/');
        $ownResponse->assertOk();
        $ownResponse->assertSee($this->deviceA->code);
        $ownResponse->assertDontSee($this->deviceB->code);

        // UI check: school selector form is NOT rendered for tenant-bound admin
        $ownResponse->assertDontSee('id="dashboard-school-form"', false);
    }

    /**
     * TEST B: Admin A requests School B Directory.
     * Expected: Denied with 403, or safely constrained to School A with zero School B users.
     */
    public function test_b_admin_a_requests_school_b_directory_fails_closed(): void
    {
        // 1. Cross-school query fails closed
        $crossResponse = $this->actingAs($this->adminA)->get('/users-data?school_id='.$this->schoolB->id);
        $crossResponse->assertForbidden();

        // 2. Directory access without parameter returns School A members only
        $ownResponse = $this->actingAs($this->adminA)->get('/users-data');
        $ownResponse->assertOk();
        $ownResponse->assertSee($this->studentA->name);
        $ownResponse->assertDontSee($this->studentB->name);
        $ownResponse->assertSee('NISN-A001');
        $ownResponse->assertDontSee('NISN-B001');

        // UI check: school dropdown is NOT rendered
        $this->assertEmpty($ownResponse->viewData('schools'));
    }

    /**
     * TEST C: Admin A requests School B Assessment.
     * Expected: Denied with 403, zero School B students or grades returned.
     */
    public function test_c_admin_a_requests_school_b_assessment_fails_closed(): void
    {
        // 1. Cross-school query fails closed
        $crossResponse = $this->actingAs($this->adminA)->get('/assessment?school_id='.$this->schoolB->id);
        $crossResponse->assertForbidden();

        // 2. Assessment access without parameter returns School A only
        $ownResponse = $this->actingAs($this->adminA)->get('/assessment');
        $ownResponse->assertOk();
        $ownResponse->assertSee($this->studentA->name);
        $ownResponse->assertDontSee($this->studentB->name);
        $this->assertEmpty($ownResponse->viewData('schools'));
    }

    /**
     * TEST D: Admin A requests School B Trainer Kits.
     * Expected: Denied with 403, zero School B devices or metadata.
     */
    public function test_d_admin_a_requests_school_b_trainer_kits_fails_closed(): void
    {
        // 1. Cross-school query fails closed
        $crossResponse = $this->actingAs($this->adminA)->get('/trainer-kits?school_id='.$this->schoolB->id);
        $crossResponse->assertForbidden();

        // 2. Normal access shows School A kit, canRotateApiKey is FALSE
        $ownResponse = $this->actingAs($this->adminA)->get('/trainer-kits');
        $ownResponse->assertOk();
        $ownResponse->assertSee($this->deviceA->code);
        $ownResponse->assertDontSee($this->deviceB->code);
        $this->assertFalse($ownResponse->viewData('canRotateApiKey'), 'canRotateApiKey must be false for legacy admin');
        $this->assertEmpty($ownResponse->viewData('schools'));
    }

    /**
     * TEST E: Admin A requests School B telemetry export.
     * Expected: Denied with 403, zero foreign records exported.
     */
    public function test_e_admin_a_requests_school_b_telemetry_export_fails_closed(): void
    {
        // 1. Telemetry history export for School B fails closed
        $crossTelemetryExport = $this->actingAs($this->adminA)->get('/telemetry/history/export?school_id='.$this->schoolB->id);
        $crossTelemetryExport->assertForbidden();

        // 2. Assessment CSV export for School B fails closed
        $crossAssessmentExport = $this->actingAs($this->adminA)->get('/assessment/export?school_id='.$this->schoolB->id);
        $crossAssessmentExport->assertForbidden();

        // 3. Telemetry export for School A contains only deviceA readings
        $ownTelemetryExport = $this->actingAs($this->adminA)->get('/telemetry/history/export');
        $ownTelemetryExport->assertOk();
        ob_start();
        $ownTelemetryExport->sendContent();
        $telemetryCsv = ob_get_clean();
        $this->assertStringContainsString($this->deviceA->code, $telemetryCsv);
        $this->assertStringNotContainsString($this->deviceB->code, $telemetryCsv);

        // 4. Assessment export for School A contains only studentA
        $ownAssessmentExport = $this->actingAs($this->adminA)->get('/assessment/export');
        $ownAssessmentExport->assertOk();
        ob_start();
        $ownAssessmentExport->sendContent();
        $assessmentCsv = ob_get_clean();
        $this->assertStringContainsString($this->studentA->name, $assessmentCsv);
        $this->assertStringNotContainsString($this->studentB->name, $assessmentCsv);
    }

    /**
     * TEST F: Admin A attempts direct foreign resource IDs.
     * Expected: 403/404 and no state mutation.
     */
    public function test_f_admin_a_attempts_direct_foreign_resource_mutation(): void
    {
        // 1. Cannot reboot School B device
        $this->actingAs($this->adminA)->post(route('kits.reboot', $this->deviceB))->assertForbidden();

        // 2. Cannot calibrate School B device
        $this->actingAs($this->adminA)->post(route('kits.calibration', $this->deviceB), [
            'phOffset' => 0.1,
            'moistureOffset' => 0.2,
            'ecOffset' => 10,
        ])->assertForbidden();

        // 3. Cannot delete School B schedule (returns 404 because query scopes to school)
        $this->actingAs($this->adminA)->delete(route('schedules.destroy', $this->scheduleB->id))->assertNotFound();
        $this->assertDatabaseHas('activity_schedules', ['id' => $this->scheduleB->id]);

        // 4. Cannot update School B classroom (returns 404)
        $this->actingAs($this->adminA)->put(route('classes.update', $this->classroomB), [
            'name' => 'Hacked Class',
            'academic_year' => '2026/2027',
        ])->assertNotFound();
        $this->assertDatabaseMissing('classrooms', ['name' => 'Hacked Class']);
    }

    /**
     * TEST G: Superuser explicitly selects School B.
     * Expected: Authorized cross-school access works.
     */
    public function test_g_superuser_explicitly_selects_school_b(): void
    {
        // 1. Dashboard: School B filter works
        $dashResponse = $this->actingAs($this->superuser)->get('/?school_id='.$this->schoolB->id);
        $dashResponse->assertOk();
        $this->assertEquals($this->schoolB->id, $dashResponse->viewData('selectedSchoolId'));
        $dashResponse->assertSee($this->deviceB->code);

        // 2. Directory: School B filter works
        $dirResponse = $this->actingAs($this->superuser)->get('/users-data?school_id='.$this->schoolB->id);
        $dirResponse->assertOk();
        $dirResponse->assertSee($this->studentB->name);

        // 3. Trainer Kits: School B filter works
        $kitsResponse = $this->actingAs($this->superuser)->get('/trainer-kits?school_id='.$this->schoolB->id);
        $kitsResponse->assertOk();
        $kitsResponse->assertSee($this->deviceB->code);
        $this->assertTrue($kitsResponse->viewData('canRotateApiKey'));

        // 4. Global view when no school_id passed
        $globalResponse = $this->actingAs($this->superuser)->get('/');
        $globalResponse->assertOk();
        $this->assertNull($globalResponse->viewData('selectedSchoolId'));
        $this->assertGreaterThanOrEqual(2, $globalResponse->viewData('schools')->count());
        $this->assertTrue($globalResponse->viewData('schools')->contains('id', $this->schoolA->id));
        $this->assertTrue($globalResponse->viewData('schools')->contains('id', $this->schoolB->id));
    }

    /**
     * TEST H: Admin A accesses authorized School A resources.
     * Expected: Existing functionality remains 100% operational.
     */
    public function test_h_admin_a_accesses_authorized_school_a_resources(): void
    {
        // 1. Passing own school_id is permitted and returns School A
        $dashOwn = $this->actingAs($this->adminA)->get('/?school_id='.$this->schoolA->id);
        $dashOwn->assertOk();
        $dashOwn->assertSee($this->deviceA->code);

        // 2. Trainer kits for School A
        $kitsOwn = $this->actingAs($this->adminA)->get('/trainer-kits?school_id='.$this->schoolA->id);
        $kitsOwn->assertOk();
        $kitsOwn->assertSee($this->deviceA->code);

        // 3. Reboot device in School A
        $rebootRes = $this->actingAs($this->adminA)->post(route('kits.reboot', $this->deviceA));
        $rebootRes->assertRedirect(route('kits.index'));
        $rebootRes->assertSessionHas('warning');

        // 4. Schedule creation in School A
        $scheduleRes = $this->actingAs($this->adminA)->post(route('schedules.store'), [
            'title' => 'Praktikum Mandiri Admin A',
            'device_id' => $this->deviceA->id,
            'classroom_id' => $this->classroomA->id,
            'module_practicum_id' => $this->module->id,
            'activity_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);
        $scheduleRes->assertRedirect(route('schedules.index'));
        $this->assertDatabaseHas('activity_schedules', [
            'title' => 'Praktikum Mandiri Admin A',
            'school_id' => $this->schoolA->id,
        ]);
    }

    /**
     * TEST I: Guru and Siswa isolation.
     * Expected: Existing correct access remains intact, cross-school queries fail closed.
     */
    public function test_i_guru_and_siswa_cross_school_queries_fail_closed(): void
    {
        // Teacher A cross-school query fails closed
        $this->actingAs($this->teacherA)->get('/?school_id='.$this->schoolB->id)->assertForbidden();
        $this->actingAs($this->teacherA)->get('/users-data?school_id='.$this->schoolB->id)->assertForbidden();
        $this->actingAs($this->teacherA)->get('/assessment?school_id='.$this->schoolB->id)->assertForbidden();

        // Student A cross-school query fails closed
        $this->actingAs($this->studentA)->get('/?school_id='.$this->schoolB->id)->assertForbidden();
        $this->actingAs($this->studentA)->get('/reports?school_id='.$this->schoolB->id)->assertForbidden();

        // Teacher A own-school operations work
        $this->actingAs($this->teacherA)->get('/')->assertOk();
        $this->actingAs($this->teacherA)->get('/schedules')->assertOk();

        // Student A own-school operations work
        $this->actingAs($this->studentA)->get('/')->assertOk();
        $this->actingAs($this->studentA)->get('/modules')->assertOk();
    }

    /**
     * TEST J: Guest public access.
     * Expected: Public pages return OK for default school, credentials and sensitive metadata stripped.
     */
    public function test_j_guest_public_access_no_credentials_or_sensitive_metadata(): void
    {
        // Public pages return 200
        $this->get('/')->assertOk();
        $this->get('/iot-monitoring')->assertOk();
        $this->get('/analytics')->assertOk();
        $this->get('/telemetry/history')->assertOk();
        $this->get('/modules')->assertOk();
        $this->get('/trainer-kits')->assertOk();
        $this->get('/raw-logs')->assertOk();

        // Sensitive metadata stripped in trainer-kits
        $kitsRes = $this->get('/trainer-kits');
        $kitsRes->assertOk();
        $kitsRes->assertDontSee('ip_address');
        $kitsRes->assertDontSee('activeApiKey');

        // Protected pages redirect to login
        $this->get('/users-data')->assertRedirect('/login');
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/assessment')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

    /**
     * HOSTILE REQUEST PARAMETERS: Array parameters, malformed strings, nonexistent schools, and inactive schools fail closed.
     */
    public function test_hostile_request_parameters_fail_closed(): void
    {
        // 1. Array parameters (e.g. ?school_id[]=1)
        $this->actingAs($this->adminA)->get('/?school_id[]=1')->assertForbidden();
        $this->actingAs($this->superuser)->get('/?school_id[]=1')->assertNotFound();

        // 2. Malformed non-numeric string (e.g. ?school_id=evil_string)
        $this->actingAs($this->adminA)->get('/?school_id=evil_string')->assertForbidden();
        $this->actingAs($this->superuser)->get('/?school_id=evil_string')->assertNotFound();

        // 3. Nonexistent school ID (e.g. ?school_id=999999)
        $this->actingAs($this->adminA)->get('/?school_id=999999')->assertForbidden();
        $this->actingAs($this->superuser)->get('/?school_id=999999')->assertNotFound();

        // 4. Inactive school ID (e.g. ?school_id=inactiveSchool->id)
        $this->actingAs($this->adminA)->get('/?school_id='.$this->inactiveSchool->id)->assertForbidden();
        $this->actingAs($this->superuser)->get('/?school_id='.$this->inactiveSchool->id)->assertNotFound();
    }
}
