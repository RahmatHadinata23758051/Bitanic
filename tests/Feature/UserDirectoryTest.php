<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_directory_rejects_guest_users(): void
    {
        $this->get('/users-data')->assertRedirect('/login');
    }

    public function test_user_directory_rejects_student_users(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($student)->get('/users-data')->assertForbidden();
    }

    public function test_user_directory_rejects_teacher_without_school(): void
    {
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => 1]);
        DB::table('users')->where('id', $teacher->id)->update(['school_id' => null]);

        $this->actingAs($teacher->fresh())->get('/users-data')->assertForbidden();
    }

    public function test_user_directory_rejects_teacher_with_inactive_school(): void
    {
        $school = School::create([
            'npsn' => '99990088',
            'name' => 'SMK Inaktif Direktori',
            'slug' => 'smk-inaktif-direktori',
            'status' => 'inactive',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);

        $this->actingAs($teacher)->get('/users-data')->assertForbidden();
    }

    public function test_teacher_only_sees_academic_members_from_their_school(): void
    {
        $schoolA = School::create([
            'npsn' => '20200401',
            'name' => 'SMK Direktori A',
            'slug' => 'smk-direktori-a',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200402',
            'name' => 'SMK Direktori B',
            'slug' => 'smk-direktori-b',
            'status' => 'active',
        ]);

        $teacherA = User::factory()->create(['role' => 'guru', 'school_id' => $schoolA->id, 'name' => 'Guru Sekolah A']);
        $teacherB = User::factory()->create(['role' => 'guru', 'school_id' => $schoolB->id, 'name' => 'Guru Sekolah B']);

        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolA->id, 'name' => 'Siswa Sekolah A']);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id, 'name' => 'Siswa Sekolah B']);

        UserProfile::create(['user_id' => $teacherA->id, 'type' => 'guru', 'identifier_number' => 'NIP-001A']);
        UserProfile::create(['user_id' => $teacherB->id, 'type' => 'guru', 'identifier_number' => 'NIP-002B']);
        UserProfile::create(['user_id' => $studentA->id, 'type' => 'siswa', 'identifier_number' => 'NISN-101A']);
        UserProfile::create(['user_id' => $studentB->id, 'type' => 'siswa', 'identifier_number' => 'NISN-102B']);

        $response = $this->actingAs($teacherA)->get('/users-data');

        $response->assertOk();
        $response->assertSee('Guru Sekolah A');
        $response->assertSee('Siswa Sekolah A');
        $response->assertSee('NIP-001A');
        $response->assertSee('NISN-101A');

        $response->assertDontSee('Guru Sekolah B');
        $response->assertDontSee('Siswa Sekolah B');
        $response->assertDontSee('NIP-002B');
        $response->assertDontSee('NISN-102B');
    }

    public function test_teacher_can_filter_users_by_role_tab(): void
    {
        $school = School::create([
            'npsn' => '20200403',
            'name' => 'SMK Tab Filter',
            'slug' => 'smk-tab-filter',
            'status' => 'active',
        ]);

        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id, 'name' => 'Guru Tab Filter']);
        $otherTeacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id, 'name' => 'Guru Lain Dalam Direktori']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Tab Filter']);

        // Student tab excludes other teacher records; the signed-in teacher may still appear in the shared navigation.
        $response = $this->actingAs($teacher)->get('/users-data?role=siswa');
        $response->assertOk();
        $response->assertSee('Siswa Tab Filter');
        $response->assertDontSee('Guru Lain Dalam Direktori');

        // Teacher tab includes teachers and excludes students.
        $response = $this->actingAs($teacher)->get('/users-data?role=guru');
        $response->assertOk();
        $response->assertSee('Guru Lain Dalam Direktori');
        $response->assertDontSee('Siswa Tab Filter');
    }

    public function test_teacher_can_search_by_name_or_identifier(): void
    {
        $school = School::create([
            'npsn' => '20200404',
            'name' => 'SMK Search Filter',
            'slug' => 'smk-search-filter',
            'status' => 'active',
        ]);

        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id, 'name' => 'Pak Budi Hartono']);
        $student1 = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Ahmad Syarifudin']);
        $student2 = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Cantika Dewi']);

        UserProfile::create(['user_id' => $student1->id, 'type' => 'siswa', 'identifier_number' => '0011223344']);
        UserProfile::create(['user_id' => $student2->id, 'type' => 'siswa', 'identifier_number' => '9988776655']);

        // Search by partial name
        $response = $this->actingAs($teacher)->get('/users-data?q=Ahmad');
        $response->assertOk();
        $response->assertSee('Ahmad Syarifudin');
        $response->assertDontSee('Cantika Dewi');

        // Search by identifier
        $response = $this->actingAs($teacher)->get('/users-data?q=998877');
        $response->assertOk();
        $response->assertSee('Cantika Dewi');
        $response->assertDontSee('Ahmad Syarifudin');
    }

    public function test_superuser_can_filter_directory_by_school(): void
    {
        $schoolA = School::create([
            'npsn' => '20200405',
            'name' => 'SMK Admin View A',
            'slug' => 'smk-admin-view-a',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200406',
            'name' => 'SMK Admin View B',
            'slug' => 'smk-admin-view-b',
            'status' => 'active',
        ]);

        $superuser = User::factory()->create(['role' => 'superuser']);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolA->id, 'name' => 'Student School A']);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id, 'name' => 'Student School B']);

        $response = $this->actingAs($superuser)->get('/users-data?school_id='.$schoolA->id);

        $response->assertOk();
        $response->assertSee('Student School A');
        $response->assertDontSee('Student School B');
    }

    public function test_admin_and_superuser_roles_are_correctly_labelled_and_filtered(): void
    {
        $school = School::create([
            'npsn' => '20200407',
            'name' => 'SMK Admin Role Test',
            'slug' => 'smk-admin-role-test',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'school_id' => $school->id,
            'name' => 'Administrator Bitanic',
        ]);

        $teacher = User::factory()->create([
            'role' => 'guru',
            'school_id' => $school->id,
            'name' => 'Guru Penguji',
        ]);

        $otherTeacher = User::factory()->create([
            'role' => 'guru',
            'school_id' => $school->id,
            'name' => 'Guru Kedua Lainnya',
        ]);

        $response = $this->actingAs($teacher)->get('/users-data');
        $response->assertOk();
        $response->assertSee('Administrator Bitanic');
        $response->assertSee('Admin');

        // Filter by role=admin excludes other teachers from directory table
        $adminFilterResponse = $this->actingAs($teacher)->get('/users-data?role=admin');
        $adminFilterResponse->assertOk();
        $adminFilterResponse->assertSee('Administrator Bitanic');
        $adminFilterResponse->assertDontSee('Guru Kedua Lainnya');
    }
}
