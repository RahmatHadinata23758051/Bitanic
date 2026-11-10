<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PracticumReportHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_hub_requires_authentication(): void
    {
        $response = $this->get('/reports');
        $response->assertRedirect('/login');
    }

    public function test_user_without_school_is_rejected_on_reports_hub(): void
    {
        $user = User::factory()->create(['role' => 'guru', 'school_id' => 1]);
        DB::table('users')->where('id', $user->id)->update(['school_id' => null]);

        $this->actingAs($user->fresh())->get('/reports')->assertStatus(403);
    }

    public function test_user_with_inactive_school_is_rejected_on_reports_hub(): void
    {
        $school = School::create([
            'npsn' => '99990088',
            'name' => 'SMK Inaktif Laporan',
            'slug' => 'smk-inaktif-laporan',
            'status' => 'inactive',
        ]);
        $teacher = User::factory()->create([
            'role' => 'guru',
            'school_id' => $school->id,
        ]);

        $this->actingAs($teacher)->get('/reports')->assertStatus(403);
    }

    public function test_student_only_sees_own_reports(): void
    {
        $school = School::create([
            'npsn' => '20200201',
            'name' => 'SMK ATPH 1',
            'slug' => 'smk-atph-1',
            'status' => 'active',
        ]);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Ahmad Praktikan']);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Bambang Siswa']);

        $module = ModulePracticum::create([
            'meeting_number' => 1,
            'title' => 'Pengenalan Sensor Tanah',
            'duration_minutes' => 90,
            'badge' => 'Badge 1',
        ]);

        StudentReport::create([
            'user_id' => $studentA->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => ['q1' => 'Jawaban Ahmad'],
        ]);

        StudentReport::create([
            'user_id' => $studentB->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => ['q1' => 'Jawaban Bambang'],
        ]);

        $response = $this->actingAs($studentA)->get('/reports');

        $response->assertOk();
        $response->assertSee('Ahmad Praktikan');
        $response->assertDontSee('Bambang Siswa');
    }

    public function test_teacher_cannot_see_reports_from_another_school(): void
    {
        $schoolA = School::create([
            'npsn' => '20200202',
            'name' => 'SMK Pertanian A',
            'slug' => 'smk-pertanian-a',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200203',
            'name' => 'SMK Pertanian B',
            'slug' => 'smk-pertanian-b',
            'status' => 'active',
        ]);

        $teacherA = User::factory()->create(['role' => 'guru', 'school_id' => $schoolA->id]);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolA->id, 'name' => 'Siswa Sekolah A']);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id, 'name' => 'Siswa Sekolah B']);

        $module = ModulePracticum::create([
            'meeting_number' => 2,
            'title' => 'Kalibrasi Sensor pH',
            'duration_minutes' => 90,
            'badge' => 'Badge 2',
        ]);

        StudentReport::create([
            'user_id' => $studentA->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);
        StudentReport::create([
            'user_id' => $studentB->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);

        $response = $this->actingAs($teacherA)->get('/reports');

        $response->assertOk();
        $response->assertSee('Siswa Sekolah A');
        $response->assertDontSee('Siswa Sekolah B');
    }

    public function test_superuser_can_see_all_or_filter_by_school(): void
    {
        $schoolA = School::create([
            'npsn' => '20200204',
            'name' => 'SMK Sukaraja',
            'slug' => 'smk-sukaraja',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200205',
            'name' => 'SMK Cianjur',
            'slug' => 'smk-cianjur',
            'status' => 'active',
        ]);

        $superuser = User::factory()->create(['role' => 'superuser']);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolA->id, 'name' => 'Siswa Sukaraja']);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id, 'name' => 'Siswa Cianjur']);

        $module = ModulePracticum::create([
            'meeting_number' => 3,
            'title' => 'Monitoring Salinitas EC',
            'duration_minutes' => 90,
            'badge' => 'Badge 3',
        ]);

        StudentReport::create([
            'user_id' => $studentA->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);
        StudentReport::create([
            'user_id' => $studentB->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);

        // Unfiltered superuser sees both
        $all = $this->actingAs($superuser)->get('/reports');
        $all->assertOk();
        $all->assertSee('Siswa Sukaraja');
        $all->assertSee('Siswa Cianjur');

        // Filtered superuser only sees target school
        $filtered = $this->actingAs($superuser)->get('/reports?school_id='.$schoolA->id);
        $filtered->assertOk();
        $filtered->assertSee('Siswa Sukaraja');
        $filtered->assertDontSee('Siswa Cianjur');
    }

    public function test_reports_hub_filters_by_status_and_module(): void
    {
        $school = School::create([
            'npsn' => '20200206',
            'name' => 'SMK Filter Test',
            'slug' => 'smk-filter-test',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Filter']);

        $mod1 = ModulePracticum::create([
            'meeting_number' => 1,
            'title' => 'Modul Satu Alpha',
            'duration_minutes' => 90,
            'badge' => 'BadgeAlphaUnique',
        ]);
        $mod2 = ModulePracticum::create([
            'meeting_number' => 2,
            'title' => 'Modul Dua Beta',
            'duration_minutes' => 90,
            'badge' => 'BadgeBetaUnique',
        ]);

        StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $mod1->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);
        StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $mod2->id,
            'status' => 'graded',
            'final_grade' => 88.5,
            'lks_answers' => [],
        ]);
        StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $mod1->id,
            'status' => 'revision',
            'lks_answers' => [],
        ]);

        // Filter status=submitted
        $resSubmitted = $this->actingAs($teacher)->get('/reports?status=submitted');
        $resSubmitted->assertOk();
        $resSubmitted->assertSee('BadgeAlphaUnique');
        $resSubmitted->assertDontSee('/100');

        // Filter status=graded
        $resGraded = $this->actingAs($teacher)->get('/reports?status=graded');
        $resGraded->assertOk();
        $resGraded->assertSee('BadgeBetaUnique');
        $resGraded->assertSee('/100');
        $resGraded->assertDontSee('BadgeAlphaUnique');

        // Filter status=revision (and revision_needed)
        $resRev = $this->actingAs($teacher)->get('/reports?status=revision');
        $resRev->assertOk();
        $resRev->assertSee('BadgeAlphaUnique');
        $resRev->assertDontSee('BadgeBetaUnique');

        $resRevNeeded = $this->actingAs($teacher)->get('/reports?status=revision_needed');
        $resRevNeeded->assertOk();
        $resRevNeeded->assertSee('BadgeAlphaUnique');
        $resRevNeeded->assertDontSee('BadgeBetaUnique');

        // Filter module_id
        $resMod2 = $this->actingAs($teacher)->get('/reports?module_id='.$mod2->id);
        $resMod2->assertOk();
        $resMod2->assertSee('BadgeBetaUnique');
        $resMod2->assertDontSee('BadgeAlphaUnique');
    }

    public function test_reports_hub_filters_by_classroom(): void
    {
        $school = School::create([
            'npsn' => '20200207',
            'name' => 'SMK Class Filter',
            'slug' => 'smk-class-filter',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);

        $classroom1 = Classroom::create([
            'school_id' => $school->id,
            'name' => 'XII ATPH 1',
            'grade_level' => 12,
            'major' => 'ATPH',
            'academic_year' => '2026/2027',
        ]);
        $classroom2 = Classroom::create([
            'school_id' => $school->id,
            'name' => 'XII ATPH 2',
            'grade_level' => 12,
            'major' => 'ATPH',
            'academic_year' => '2026/2027',
        ]);

        $student1 = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Kelas 1']);
        UserProfile::create([
            'user_id' => $student1->id,
            'type' => 'siswa',
            'classroom_id' => $classroom1->id,
            'class_grade' => 'XII ATPH 1',
        ]);

        $student2 = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Kelas 2']);
        UserProfile::create([
            'user_id' => $student2->id,
            'type' => 'siswa',
            'classroom_id' => $classroom2->id,
            'class_grade' => 'XII ATPH 2',
        ]);

        $module = ModulePracticum::create([
            'meeting_number' => 4,
            'title' => 'Pengujian Fosfor',
            'duration_minutes' => 90,
            'badge' => 'B4',
        ]);

        StudentReport::create([
            'user_id' => $student1->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);
        StudentReport::create([
            'user_id' => $student2->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);

        $response = $this->actingAs($teacher)->get('/reports?class=XII ATPH 1');
        $response->assertOk();
        $response->assertSee('Siswa Kelas 1');
        $response->assertDontSee('Siswa Kelas 2');
    }

    public function test_reports_hub_displays_telemetry_snapshot_honestly(): void
    {
        $school = School::create([
            'npsn' => '20200208',
            'name' => 'SMK Telemetry Honest',
            'slug' => 'smk-telemetry-honest',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Dengan Telemetri']);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Tanpa Telemetri']);

        $module = ModulePracticum::create([
            'meeting_number' => 5,
            'title' => 'Snapshot Telemetri Test',
            'duration_minutes' => 90,
            'badge' => 'B5',
        ]);

        // Report with real telemetry snapshot
        StudentReport::create([
            'user_id' => $studentA->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
            'telemetry_snapshot' => [
                'ph' => 6.25,
                'moisture' => 55.4,
                'ec' => 1420,
            ],
        ]);

        // Report with NO telemetry snapshot
        StudentReport::create([
            'user_id' => $studentB->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
            'telemetry_snapshot' => null,
        ]);

        $response = $this->actingAs($teacher)->get('/reports');

        $response->assertOk();
        $response->assertSee('pH: 6.25');
        $response->assertSee('Lengas: 55.4%');
        $response->assertSee('EC: 1420');
        $response->assertSee('Tidak ada snapshot');
    }
}
