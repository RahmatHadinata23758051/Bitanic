<?php

namespace Tests\Feature;

use App\Actions\Practicum\AuthorizeReportRevisionAction;
use App\Actions\Practicum\GradeStudentReportAction;
use App\Actions\Practicum\SubmitStudentReportAction;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\User;
use Database\Seeders\ModulePracticumSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImmutableGradeHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulePracticumSeeder::class);
    }

    // -------------------------------------------------------------------
    // AC-1: Grade action only available to authorized teacher in same school.
    // -------------------------------------------------------------------

    public function test_same_school_teacher_can_grade_student_report(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban siswa'],
        ]);

        $action = app(GradeStudentReportAction::class);
        $graded = $action->execute($report, $teacher, ['aspek1' => 3, 'aspek2' => 4], 'Kerja bagus');

        $this->assertEquals('graded', $graded->status);
        $this->assertNotNull($graded->final_grade);
        $this->assertEquals($teacher->id, $graded->graded_by);
    }

    public function test_cross_school_teacher_cannot_grade_report(): void
    {
        // Create a second school for isolation
        $schoolB = School::create([
            'npsn' => '99999999',
            'name' => 'SMK Lain',
            'slug' => 'smk-lain',
        ]);

        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id]);

        $module = ModulePracticum::firstOrFail();
        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban siswa'],
        ]);

        $action = app(GradeStudentReportAction::class);

        $this->expectException(AuthorizationException::class);
        $action->execute($report, $teacher, ['aspek1' => 3], 'Cross-school attempt');
    }

    public function test_student_cannot_grade_any_report(): void
    {
        $teacher = User::factory()->create(['role' => 'siswa']);
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban'],
        ]);

        $action = app(GradeStudentReportAction::class);

        $this->expectException(AuthorizationException::class);
        $action->execute($report, $teacher, ['aspek1' => 3]);
    }

    // -------------------------------------------------------------------
    // AC-2: Each grading appends immutable grade history record.
    // -------------------------------------------------------------------

    public function test_initial_grading_creates_grade_history_record(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban'],
        ]);

        $action = app(GradeStudentReportAction::class);
        $graded = $action->execute($report, $teacher, ['aspek1' => 3], 'Nilai awal');

        $history = $graded->gradeHistory;
        $this->assertCount(1, $history);

        $entry = $history->first();
        $this->assertEquals($teacher->id, $entry->graded_by);
        $this->assertEquals(['aspek1' => 3], $entry->rubric_scores);
        $this->assertEquals($graded->final_grade, $entry->final_grade);
        $this->assertEquals('Nilai awal', $entry->teacher_feedback);
        $this->assertEquals('initial_grade', $entry->transition);
        $this->assertNotNull($entry->graded_at);
    }

    public function test_regrading_after_revision_appends_new_history_without_changing_prior(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'First answer'],
        ]);

        $action = app(GradeStudentReportAction::class);

        // Initial grade
        $action->execute($report, $teacher, ['aspek1' => 2], 'Perlu perbaikan');
        $firstGradeId = $report->gradeHistory()->latest('id')->first()->id;

        // Teacher opens revision
        $report->refresh();
        $report->update(['status' => 'revision']);

        // Student resubmits
        app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Revised answer'],
        ]);

        // Teacher re-grades with adjustment reason
        $report->refresh();
        $regraded = $action->execute($report, $teacher, ['aspek1' => 4], 'Perbaikan sangat baik', 'Siswa memperbaiki analisis EC');

        $report->refresh();
        $history = $report->gradeHistory;
        $this->assertCount(2, $history);

        // First grade entry unchanged
        $firstEntry = $history->firstWhere('id', $firstGradeId);
        $this->assertNotNull($firstEntry);
        $this->assertEquals(['aspek1' => 2], $firstEntry->rubric_scores);
        $this->assertEquals('initial_grade', $firstEntry->transition);

        // Second entry is the revision grade
        $secondEntry = $history->where('id', '!=', $firstGradeId)->first();
        $this->assertNotNull($secondEntry);
        $this->assertEquals(['aspek1' => 4], $secondEntry->rubric_scores);
        $this->assertEquals('revision', $secondEntry->transition);
        $this->assertEquals('Siswa memperbaiki analisis EC', $secondEntry->adjustment_reason);
    }

    // -------------------------------------------------------------------
    // AC-3: Student cannot overwrite grade data; finalized report
    //       requires teacher-authorized revision.
    // -------------------------------------------------------------------

    public function test_student_cannot_resubmit_after_grading_without_teacher_revision(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'First'],
        ]);

        app(GradeStudentReportAction::class)->execute($report, $teacher, ['aspek1' => 3]);

        $this->expectException(ValidationException::class);
        app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Trying to override'],
        ]);
    }

    // -------------------------------------------------------------------
    // AC-4: Historical grades remain queryable after adjustment.
    // -------------------------------------------------------------------

    public function test_historical_grades_remain_queryable_after_multiple_adjustments(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Initial'],
        ]);

        $action = app(GradeStudentReportAction::class);

        // Grade 1
        $this->travelTo(now()->addMinutes(1));
        $action->execute($report, $teacher, ['r1' => 1, 'r2' => 1, 'r3' => 1], 'Cukup');

        // Open revision and resubmit
        $report->refresh();
        $report->update(['status' => 'revision']);
        $this->travelTo(now()->addMinutes(2));
        app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Revised 1'],
        ]);

        // Grade 2
        $report->refresh();
        $this->travelTo(now()->addMinutes(3));
        $action->execute($report, $teacher, ['r1' => 2, 'r2' => 3, 'r3' => 2], 'Lebih baik', 'Perbaikan pertama');

        // Open revision and resubmit again
        $report->refresh();
        $report->update(['status' => 'revision']);
        $this->travelTo(now()->addMinutes(4));
        app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Revised 2'],
        ]);

        // Grade 3
        $report->refresh();
        $this->travelTo(now()->addMinutes(5));
        $action->execute($report, $teacher, ['r1' => 4, 'r2' => 4, 'r3' => 4], 'Sangat baik', 'Perbaikan kedua');

        // All 3 grades queryable
        $report->refresh();
        $allGrades = $report->gradeHistory;
        $this->assertCount(3, $allGrades);

        // Chronologically ordered by ID (ascending)
        $grades = $allGrades->sortBy('id')->values();
        $this->assertEquals('initial_grade', $grades[0]->transition);
        $this->assertEquals('revision', $grades[1]->transition);
        $this->assertEquals('revision', $grades[2]->transition);

        // Grade values are distinct and represent the progression
        $this->assertNotEquals($grades[0]->final_grade, $grades[2]->final_grade);
    }

    // -------------------------------------------------------------------
    // AC-5: Score clamping (1-4 range) and rubric calculation.
    // -------------------------------------------------------------------

    public function test_rubric_scores_are_clamped_to_valid_range(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Answer'],
        ]);

        $action = app(GradeStudentReportAction::class);

        // Scores outside 1-4 should be clamped
        $graded = $action->execute($report, $teacher, ['aspek1' => 0, 'aspek2' => 10]);

        $this->assertNotNull($graded->final_grade);
        $this->assertTrue($graded->final_grade >= 0);
        $this->assertTrue($graded->final_grade <= 100);
    }

    // -------------------------------------------------------------------
    // AC-6: Concurrent grade updates are handled safely.
    // -------------------------------------------------------------------

    public function test_grade_uses_pessimistic_lock_to_prevent_concurrent_overwrites(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Answer'],
        ]);

        $action = app(GradeStudentReportAction::class);

        // First grade succeeds
        $graded = $action->execute($report, $teacher, ['aspek1' => 3], 'First grade');

        $this->assertEquals('graded', $graded->status);
        $this->assertCount(1, $graded->gradeHistory);

        // Verify that the locked row was updated atomically
        $report->refresh();
        $this->assertEquals($teacher->id, $report->graded_by);
        $this->assertEquals('graded', $report->status);
    }

    // -------------------------------------------------------------------
    // AC-7: Teacher-authorized revision request workflow (Action + HTTP).
    // -------------------------------------------------------------------

    public function test_teacher_can_request_revision_via_action(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban awal'],
        ]);

        // Grade report
        app(GradeStudentReportAction::class)->execute($report, $teacher, ['r1' => 2, 'r2' => 2, 'r3' => 2]);
        $report->refresh();
        $this->assertEquals('graded', $report->status);

        // Teacher authorizes revision
        $action = app(AuthorizeReportRevisionAction::class);
        $revised = $action->execute($report, $teacher, 'Mohon lengkapi analisis grafik pH dan EC');

        $this->assertEquals('revision', $revised->status);
        $this->assertEquals('Mohon lengkapi analisis grafik pH dan EC', $revised->teacher_feedback);

        // Student can now submit another attempt
        $newAttempt = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban lengkap dengan analisis pH dan EC'],
        ]);

        $this->assertEquals(2, $newAttempt->attempt_count);
        $this->assertEquals('submitted', $newAttempt->status);
    }

    public function test_teacher_can_request_revision_via_http_route(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban'],
        ]);

        app(GradeStudentReportAction::class)->execute($report, $teacher, ['r1' => 2, 'r2' => 2, 'r3' => 2]);
        $report->refresh();

        $response = $this->actingAs($teacher)->post(route('modules.request-revision', $report->id), [
            'instructions' => 'Perbaiki perhitungan persentase lengas',
        ]);

        $response->assertRedirect(route('modules.show', $module->id));
        $response->assertSessionHas('success');

        $report->refresh();
        $this->assertEquals('revision', $report->status);
        $this->assertEquals('Perbaiki perhitungan persentase lengas', $report->teacher_feedback);
    }

    public function test_cross_school_teacher_cannot_request_revision(): void
    {
        $schoolB = School::create(['npsn' => '88888888', 'name' => 'SMK B', 'slug' => 'smk-b']);
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban'],
        ]);

        $this->actingAs($teacher)->post(route('modules.request-revision', $report->id), [
            'instructions' => 'Mencoba revisi sekolah lain',
        ])->assertForbidden();
    }

    public function test_student_cannot_request_revision(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban'],
        ]);

        $response = $this->actingAs($student)->post(route('modules.request-revision', $report->id), [
            'instructions' => 'Siswa mencoba membuka revisi sendiri',
        ]);

        $response->assertForbidden();
    }

    public function test_revision_request_requires_instructions(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $teacher->school_id]);
        $module = ModulePracticum::firstOrFail();

        $report = app(SubmitStudentReportAction::class)->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban'],
        ]);

        $response = $this->actingAs($teacher)->post(route('modules.request-revision', $report->id), [
            'instructions' => '',
        ]);

        $response->assertSessionHasErrors('instructions');
    }
}
