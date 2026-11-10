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
}
