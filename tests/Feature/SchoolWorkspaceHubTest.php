<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseTerm;
use App\Models\StudentReport;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolWorkspaceHubTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $superuser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'npsn' => '20205555',
            'name' => 'SMK Pertanian Workspace Hub Testing',
            'slug' => 'smk-pertanian-workspace-hub-testing',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
        ]);
    }

    public function test_superuser_can_access_school_workspace_hub_and_view_all_tabs(): void
    {
        // 1. Setup License
        $license = SchoolLicense::create([
            'school_id' => $this->school->id,
            'license_number' => 'BITANIC-HUB-2026-TEST',
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'expiration_date' => now()->addMonths(11),
        ]);
        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'start_date' => now()->subMonth(),
            'expiration_date' => now()->addMonths(11),
        ]);

        // 2. Setup Teacher & Admin
        $teacher = User::factory()->create([
            'name' => 'Bambang Guru ATPH',
            'role' => 'guru',
            'school_id' => $this->school->id,
            'email' => 'bambang.atph@school.id',
        ]);
        UserProfile::create([
            'user_id' => $teacher->id,
            'type' => 'guru',
            'identifier_number' => '198501012010011001',
            'specialization' => 'Agronomi & IoT Pertanian',
        ]);

        $schoolAdmin = User::factory()->create([
            'name' => 'Siti Admin Sekolah',
            'role' => 'admin_sekolah',
            'school_id' => $this->school->id,
            'email' => 'admin.siti@school.id',
        ]);

        // 3. Setup Classroom & Student
        $classroom = Classroom::create([
            'school_id' => $this->school->id,
            'name' => 'XII ATPH 1 Unggulan',
            'department' => 'ATPH',
            'homeroom_teacher_id' => $teacher->id,
        ]);

        $student = User::factory()->create([
            'name' => 'Randi Siswa Praktikan',
            'role' => 'siswa',
            'school_id' => $this->school->id,
            'email' => 'randi.siswa@school.id',
        ]);
        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'classroom_id' => $classroom->id,
            'identifier_number' => '0098765432',
        ]);

        // 4. Setup Device (Trainer Kit)
        $device = Device::create([
            'school_id' => $this->school->id,
            'code' => 'TK-HUB-01',
            'name' => 'Trainer Kit Hub Alpha',
            'bench_location' => 'Meja Praktik A1 - Lahan Uji',
            'connection_type' => 'modbus_rs485',
            'status' => 'online',
            'last_seen_at' => now(),
            'is_active' => true,
        ]);

        // 5. Setup Schedule
        $schedule = ActivitySchedule::create([
            'school_id' => $this->school->id,
            'device_id' => $device->id,
            'classroom_id' => $classroom->id,
            'title' => 'Praktikum Sensor Tanah & pH Modbus',
            'activity_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);

        // 6. Setup Student Report
        $module = ModulePracticum::first() ?? ModulePracticum::create([
            'title' => 'Modul 01: Karakterisasi Tanah 7-in-1',
            'meeting_number' => 1,
            'is_active' => true,
        ]);

        StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'device_id' => $device->id,
            'activity_schedule_id' => $schedule->id,
            'final_grade' => 88.5,
            'status' => 'graded',
        ]);

        // Execute request to School Workspace Hub
        $response = $this->actingAs($this->superuser)
            ->get(route('master.schools.show', $this->school));

        $response->assertOk();

        // Hub title & metadata
        $response->assertSee('SMK Pertanian Workspace Hub Testing');
        $response->assertSee('20205555');
        $response->assertSee('School Workspace Hub');
        $response->assertSee(url('/?school_id='.$this->school->id));

        // Tab titles
        $response->assertSee('Profil &amp; Lisensi', false);
        $response->assertSee('Kelas &amp; Siswa', false);
        $response->assertSee('Guru &amp; Admin', false);
        $response->assertSee('Trainer Kit Meja', false);
        $response->assertSee('Jadwal Lab', false);
        $response->assertSee('Laporan LKS &amp; Rapor', false);

        // Content verification
        $response->assertSee('BITANIC-HUB-2026-TEST');
        $response->assertSee('XII ATPH 1 Unggulan');
        $response->assertSee('Randi Siswa Praktikan');
        $response->assertSee('0098765432');
        $response->assertSee('Bambang Guru ATPH');
        $response->assertSee('Siti Admin Sekolah');
        $response->assertSee('TK-HUB-01');
        $response->assertSee('Praktikum Sensor Tanah &amp; pH Modbus', false);
        $response->assertSee('88.5');
    }

    public function test_guest_and_unauthorized_roles_cannot_access_school_workspace_hub(): void
    {
        // Guest redirected to login
        $this->get(route('master.schools.show', $this->school))
            ->assertRedirect(route('login'));

        // Guru forbidden
        $teacher = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->school->id,
        ]);
        $this->actingAs($teacher)
            ->get(route('master.schools.show', $this->school))
            ->assertForbidden();

        // Siswa forbidden
        $student = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->school->id,
        ]);
        $this->actingAs($student)
            ->get(route('master.schools.show', $this->school))
            ->assertForbidden();
    }

    public function test_school_workspace_hub_displays_honest_empty_states_when_no_resources(): void
    {
        $emptySchool = School::create([
            'npsn' => '20206666',
            'name' => 'SMK Baru Belum Terisi',
            'slug' => 'smk-baru-belum-terisi',
            'city' => 'Tasikmalaya',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->superuser)
            ->get(route('master.schools.show', $emptySchool));

        $response->assertOk();
        $response->assertSee('SMK Baru Belum Terisi');
        $response->assertSee('Belum Memiliki Lisensi LMS Terdaftar');
        $response->assertSee('Belum ada kelas yang terdaftar');
        $response->assertSee('Belum ada data siswa terdaftar');
        $response->assertSee('Belum Ada Akun Admin Sekolah Terdaftar');
        $response->assertSee('Belum ada Trainer Kit yang dialokasikan');
        $response->assertSee('Belum ada agenda jadwal praktikum');
        $response->assertSee('Belum ada laporan LKS terkumpul');
    }

    public function test_superuser_can_provision_school_admin_account_from_hub(): void
    {
        $response = $this->actingAs($this->superuser)
            ->post(route('master.schools.admins.store', $this->school), [
                'name' => 'Ahmad Fathoni, M.Kom',
                'email' => 'ahmad.admin@smk-hub.sch.id',
                'password' => 'BitanicSecret2026!',
            ]);

        $response->assertRedirect(route('master.schools.show', ['school' => $this->school, 'tab' => 'teachers']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Ahmad Fathoni, M.Kom',
            'email' => 'ahmad.admin@smk-hub.sch.id',
            'role' => 'admin_sekolah',
            'school_id' => $this->school->id,
            'must_change_password' => true,
        ]);

        $adminUser = User::where('email', 'ahmad.admin@smk-hub.sch.id')->first();
        $this->assertNotNull($adminUser);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $adminUser->id,
            'type' => 'guru',
            'specialization' => 'Administrator Institusi',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'school_admin.provisioned',
            'target_id' => (string) $adminUser->id,
        ]);
    }

    public function test_school_admin_provisioning_enforces_unique_email_and_auth_gates(): void
    {
        // Non-superuser cannot provision
        $teacher = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->school->id,
        ]);

        $this->actingAs($teacher)
            ->post(route('master.schools.admins.store', $this->school), [
                'name' => 'Hacker Admin',
                'email' => 'hacker@school.id',
                'password' => 'secret12345',
            ])
            ->assertForbidden();

        // Duplicate email rejected
        User::factory()->create(['email' => 'existing.user@school.id']);

        $this->actingAs($this->superuser)
            ->post(route('master.schools.admins.store', $this->school), [
                'name' => 'Duplicate Admin',
                'email' => 'existing.user@school.id',
                'password' => 'secret12345',
            ])
            ->assertSessionHasErrors(['email']);
    }
}
