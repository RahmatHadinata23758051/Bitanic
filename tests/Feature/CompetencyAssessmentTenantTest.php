<?php

namespace Tests\Feature;

use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompetencyAssessmentTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_rejects_guest_users(): void
    {
        $this->get('/assessment')->assertRedirect('/login');
    }

    public function test_assessment_rejects_user_without_school(): void
    {
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => 1]);
        DB::table('users')->where('id', $teacher->id)->update(['school_id' => null]);

        $this->actingAs($teacher->fresh())->get('/assessment')->assertStatus(403);
    }

    public function test_assessment_rejects_user_with_inactive_school(): void
    {
        $school = School::create([
            'npsn' => '99990077',
            'name' => 'SMK Inaktif Asesmen',
            'slug' => 'smk-inaktif-asesmen',
            'status' => 'inactive',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);

        $this->actingAs($teacher)->get('/assessment')->assertStatus(403);
    }

    public function test_teacher_only_sees_students_and_metrics_from_their_school(): void
    {
        $schoolA = School::create([
            'npsn' => '20200301',
            'name' => 'SMK Assessment A',
            'slug' => 'smk-assessment-a',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200302',
            'name' => 'SMK Assessment B',
            'slug' => 'smk-assessment-b',
            'status' => 'active',
        ]);

        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $schoolA->id]);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolA->id, 'name' => 'Student Assessment A']);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id, 'name' => 'Student Assessment B']);

        UserProfile::create([
            'user_id' => $studentA->id,
            'type' => 'siswa',
            'class_grade' => 'XI ATPH 1',
            'average_grade' => 90,
            'competency_predicative' => 'A',
        ]);
        UserProfile::create([
            'user_id' => $studentB->id,
            'type' => 'siswa',
            'class_grade' => 'XI ATPH 2',
            'average_grade' => 40,
            'competency_predicative' => 'C',
        ]);

        $response = $this->actingAs($teacher)->get('/assessment');

        $response->assertOk();
        $response->assertSee('Student Assessment A');
        $response->assertDontSee('Student Assessment B');
        $response->assertSee('90.0');
        $response->assertDontSee('40.0');
    }

    public function test_superuser_can_select_a_school_and_stats_follow_filter(): void
    {
        $schoolA = School::create([
            'npsn' => '20200303',
            'name' => 'SMK Selected Assessment A',
            'slug' => 'smk-selected-assessment-a',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200304',
            'name' => 'SMK Selected Assessment B',
            'slug' => 'smk-selected-assessment-b',
            'status' => 'active',
        ]);

        $superuser = User::factory()->create(['role' => 'superuser']);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolA->id, 'name' => 'Selected Student A']);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id, 'name' => 'Selected Student B']);
        UserProfile::create([
            'user_id' => $studentA->id,
            'type' => 'siswa',
            'average_grade' => 91,
            'competency_predicative' => 'A',
        ]);
        UserProfile::create([
            'user_id' => $studentB->id,
            'type' => 'siswa',
            'average_grade' => 41,
            'competency_predicative' => 'C',
        ]);

        $response = $this->actingAs($superuser)->get('/assessment?school_id='.$schoolA->id);

        $response->assertOk();
        $response->assertSee('Selected Student A');
        $response->assertDontSee('Selected Student B');
        $response->assertSee('91.0');
        $response->assertDontSee('41.0');
    }

    public function test_assessment_export_csv_streams_accurate_school_scoped_data(): void
    {
        $school = School::create([
            'npsn' => '20200305',
            'name' => 'SMK Ekspor Nilai',
            'slug' => 'smk-ekspor-nilai',
            'status' => 'active',
        ]);
        $otherSchool = School::create([
            'npsn' => '20200306',
            'name' => 'SMK Ekspor Lain',
            'slug' => 'smk-ekspor-lain',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Ekspor']);
        $otherStudent = User::factory()->create(['role' => 'siswa', 'school_id' => $otherSchool->id, 'name' => 'Siswa Rahasia']);

        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'identifier_number' => '0098765432',
            'class_grade' => 'XII ATPH 1',
            'attendance_percent' => 97.5,
            'average_grade' => 88.0,
            'competency_predicative' => 'A',
            'trainer_certified' => true,
        ]);
        UserProfile::create([
            'user_id' => $otherStudent->id,
            'type' => 'siswa',
            'identifier_number' => '0087654321',
            'average_grade' => 45.0,
        ]);

        $response = $this->actingAs($teacher)->get('/assessment/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('NISN,Kelas', $content);
        $this->assertStringContainsString('Siswa Ekspor', $content);
        $this->assertStringContainsString('0098765432', $content);
        $this->assertStringContainsString('88.0', $content);
        $this->assertStringContainsString('Tersertifikasi', $content);
        $this->assertStringNotContainsString('Siswa Rahasia', $content);
        $this->assertStringNotContainsString('0087654321', $content);
        $this->assertStringNotContainsString('45.0', $content);
    }

    public function test_students_only_see_and_export_their_own_assessment(): void
    {
        $school = School::create([
            'npsn' => '20200307',
            'name' => 'SMK Rapor Siswa',
            'slug' => 'smk-rapor-siswa',
            'status' => 'active',
        ]);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Sendiri']);
        $classmate = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Lain']);

        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'average_grade' => 89.0,
        ]);
        UserProfile::create([
            'user_id' => $classmate->id,
            'type' => 'siswa',
            'average_grade' => 42.0,
        ]);

        $page = $this->actingAs($student)->get('/assessment');
        $page->assertOk();
        $page->assertSee('Siswa Sendiri');
        $page->assertDontSee('Siswa Lain');
        $page->assertSee('89.0');
        $page->assertDontSee('42.0');

        $export = $this->actingAs($student)->get('/assessment/export');
        $export->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString('Siswa Sendiri', $content);
        $this->assertStringNotContainsString('Siswa Lain', $content);
    }

    public function test_assessment_displays_honest_em_dash_when_no_students_have_grades(): void
    {
        $school = School::create([
            'npsn' => '20200308',
            'name' => 'SMK Tanpa Nilai',
            'slug' => 'smk-tanpa-nilai',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Baru']);

        // Student has no profile record yet, representing a newly registered student
        $response = $this->actingAs($teacher)->get('/assessment');
        $response->assertOk();
        $response->assertSee('Siswa Baru');
        // Average score and student grade should show em-dash '—'
        $response->assertSee('—');
    }

    public function test_assessment_csv_export_sanitizes_formula_injection(): void
    {
        $school = School::create([
            'npsn' => '20200309',
            'name' => 'SMK Uji Injeksi',
            'slug' => 'smk-uji-injeksi',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $student = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $school->id,
            'name' => '=cmd|\' /C calc\'!A0',
        ]);

        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'identifier_number' => '+628123456789',
            'class_grade' => '@XII ATPH 1',
            'average_grade' => 85.0,
        ]);

        $export = $this->actingAs($teacher)->get('/assessment/export');
        $export->assertOk();
        $content = $export->streamedContent();

        // Formula injection prefixes must be neutralized with a leading single quote
        $this->assertStringContainsString("'=cmd|", $content);
        $this->assertStringContainsString("'+628123456789", $content);
        $this->assertStringContainsString("'@XII ATPH 1", $content);
    }

    public function test_assessment_calculates_verified_average_from_graded_student_reports(): void
    {
        $school = School::create([
            'npsn' => '20200310',
            'name' => 'SMK Rapor Terverifikasi',
            'slug' => 'smk-rapor-terverifikasi',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id, 'name' => 'Siswa Riset']);

        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'class_grade' => 'X ATPH 1',
            // Profile has an unverified or old grade of 70.0
            'average_grade' => 70.0,
            'competency_predicative' => 'B',
        ]);

        $module1 = ModulePracticum::create([
            'meeting_number' => 1,
            'title' => 'Modul 1 Test',
        ]);
        $module2 = ModulePracticum::create([
            'meeting_number' => 2,
            'title' => 'Modul 2 Test',
        ]);

        // Create 2 graded student reports with grades 90.0 and 100.0 => average 95.0 (Predikat A)
        StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $module1->id,
            'lks_answers' => ['q1' => 'ans1'],
            'status' => 'graded',
            'final_grade' => 90.0,
            'graded_by' => $teacher->id,
            'graded_at' => now(),
        ]);
        StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $module2->id,
            'lks_answers' => ['q1' => 'ans2'],
            'status' => 'graded',
            'final_grade' => 100.0,
            'graded_by' => $teacher->id,
            'graded_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get('/assessment');
        $response->assertOk();
        $response->assertSee('Siswa Riset');
        // Verified average is 95.0, not the stale profile grade of 70.0
        $response->assertSee('95.0');
        $response->assertDontSee('70.0');

        $export = $this->actingAs($teacher)->get('/assessment/export');
        $export->assertOk();
        $exportContent = $export->streamedContent();
        $this->assertStringContainsString('95.0', $exportContent);
        $this->assertStringNotContainsString('70.0', $exportContent);
    }
}
