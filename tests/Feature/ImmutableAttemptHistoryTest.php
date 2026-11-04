<?php

namespace Tests\Feature;

use App\Actions\Practicum\SubmitStudentReportAction;
use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\ScheduleParticipant;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\ModulePracticumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImmutableAttemptHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulePracticumSeeder::class);
    }

    // -------------------------------------------------------------------
    // AC-1: Each submission appends a unique attempt; prior attempts remain unchanged.
    // -------------------------------------------------------------------

    public function test_first_submission_creates_report_with_attempt_record(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();

        $action = app(SubmitStudentReportAction::class);
        $report = $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban pertama'],
        ]);

        $this->assertEquals(1, $report->attempt_count);
        $this->assertEquals('submitted', $report->status);

        // Must have exactly 1 attempt record
        $this->assertCount(1, $report->attempts);
        $attempt = $report->attempts->first();
        $this->assertEquals(1, $attempt->attempt_number);
        $this->assertEquals(['q1' => 'Jawaban pertama'], $attempt->lks_answers);
        $this->assertNotNull($attempt->submitted_at);
    }

    public function test_second_submission_appends_new_attempt_without_mutating_first(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();

        $action = app(SubmitStudentReportAction::class);

        // First submission
        $report1 = $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban pertama'],
        ]);
        $firstAttemptId = $report1->attempts->first()->id;
        $firstAttemptAnswers = $report1->attempts->first()->lks_answers;

        // Second submission
        $report2 = $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban revisi kedua'],
        ]);

        // Report should still be the same record
        $this->assertEquals($report1->id, $report2->id);
        $this->assertEquals(2, $report2->attempt_count);

        // Must have exactly 2 attempt records
        $report2->load('attempts');
        $this->assertCount(2, $report2->attempts);

        // First attempt unchanged
        $firstAttempt = $report2->attempts->firstWhere('attempt_number', 1);
        $this->assertNotNull($firstAttempt);
        $this->assertEquals($firstAttemptId, $firstAttempt->id);
        $this->assertEquals($firstAttemptAnswers, $firstAttempt->lks_answers);

        // Second attempt has new data
        $secondAttempt = $report2->attempts->firstWhere('attempt_number', 2);
        $this->assertNotNull($secondAttempt);
        $this->assertEquals(['q1' => 'Jawaban revisi kedua'], $secondAttempt->lks_answers);
    }

    public function test_three_consecutive_submissions_build_complete_history(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        $answers = [
            ['q1' => 'Attempt 1'],
            ['q1' => 'Attempt 2'],
            ['q1' => 'Attempt 3'],
        ];

        foreach ($answers as $data) {
            $action->execute($student, $module, ['lks_answers' => $data]);
        }

        $report = StudentReport::where('user_id', $student->id)
            ->where('module_id', $module->id)
            ->firstOrFail();

        $this->assertEquals(3, $report->attempt_count);

        $attempts = $report->attempts;
        $this->assertCount(3, $attempts);

        foreach ($answers as $i => $expected) {
            $attempt = $attempts->firstWhere('attempt_number', $i + 1);
            $this->assertNotNull($attempt, 'Attempt #'.($i + 1).' must exist');
            $this->assertEquals($expected, $attempt->lks_answers);
        }
    }

    // -------------------------------------------------------------------
    // AC-2: Attempt records store telemetry evidence snapshot and device binding.
    // -------------------------------------------------------------------

    public function test_attempt_captures_device_and_evidence_reading_snapshot(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-ATT-01', 'name' => 'Meja Attempt Test', 'is_active' => true]);
        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'assigned_kit_id' => 'TK-ATT-01',
        ]);

        $reading = TelemetryReading::create([
            'device_id' => $device->id,
            'ph' => 6.5,
            'temperature' => 25.0,
            'humidity' => 70.0,
            'ec' => 1200,
            'nitrogen' => 40,
            'phosphorus' => 25,
            'potassium' => 100,
            'recorded_at' => now(),
        ]);

        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        $report = $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Dengan data telemetri'],
            'device_id' => $device->id,
            'evidence_reading_id' => $reading->id,
        ]);

        $attempt = $report->attempts->first();
        $this->assertNotNull($attempt);
        $this->assertEquals($device->id, $attempt->device_id);
        $this->assertEquals($reading->id, $attempt->evidence_reading_id);
        $this->assertTrue($attempt->telemetry_snapshot['has_telemetry']);
    }

    public function test_attempt_without_telemetry_captures_honest_empty_snapshot(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        $report = $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Tanpa hardware'],
        ]);

        $attempt = $report->attempts->first();
        $this->assertNotNull($attempt);
        $this->assertNull($attempt->device_id);
        $this->assertNull($attempt->evidence_reading_id);
        $this->assertFalse($attempt->telemetry_snapshot['has_telemetry']);
    }

    // -------------------------------------------------------------------
    // AC-3: Final/graded reports reject student modification.
    // -------------------------------------------------------------------

    public function test_graded_report_rejects_student_resubmission(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        // First submission
        $report = $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban awal'],
        ]);

        // Teacher grades it
        $report->update(['status' => 'graded', 'final_grade' => 85.00, 'graded_by' => User::factory()->create(['role' => 'guru'])->id]);

        // Student tries to resubmit
        $this->expectException(ValidationException::class);
        $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Mencoba mengubah setelah dinilai'],
        ]);
    }

    public function test_graded_report_preserves_existing_attempts_on_rejection(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        // Two submissions
        $action->execute($student, $module, ['lks_answers' => ['q1' => 'First']]);
        $report = $action->execute($student, $module, ['lks_answers' => ['q1' => 'Second']]);

        // Teacher grades
        $report->update(['status' => 'graded', 'final_grade' => 90.00, 'graded_by' => User::factory()->create(['role' => 'guru'])->id]);

        // Student tries to resubmit — should fail
        try {
            $action->execute($student, $module, ['lks_answers' => ['q1' => 'Third — should fail']]);
        } catch (ValidationException $e) {
            // Expected
        }

        // Verify only 2 attempts exist (no 3rd was appended)
        $report->refresh();
        $this->assertCount(2, $report->attempts);
        $this->assertEquals(2, $report->attempt_count);
    }

    // -------------------------------------------------------------------
    // AC-4: Teacher-authorized revision reopens submission.
    // -------------------------------------------------------------------

    public function test_teacher_authorized_revision_allows_new_attempt(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        // Submit and get graded
        $report = $action->execute($student, $module, ['lks_answers' => ['q1' => 'Initial answer']]);
        $report->update(['status' => 'graded', 'final_grade' => 70.00, 'graded_by' => User::factory()->create(['role' => 'guru'])->id]);

        // Teacher transitions to revision status
        $report->update(['status' => 'revision']);

        // Student should now be able to submit again
        $revised = $action->execute($student, $module, ['lks_answers' => ['q1' => 'Revised answer after teacher authorization']]);

        $this->assertEquals($report->id, $revised->id);
        $this->assertEquals('submitted', $revised->status);
        $this->assertEquals(2, $revised->attempt_count);

        $revised->load('attempts');
        $this->assertCount(2, $revised->attempts);

        $secondAttempt = $revised->attempts->firstWhere('attempt_number', 2);
        $this->assertEquals(['q1' => 'Revised answer after teacher authorization'], $secondAttempt->lks_answers);
    }

    // -------------------------------------------------------------------
    // AC-5: Session schedule binding on attempt record.
    // -------------------------------------------------------------------

    public function test_attempt_captures_schedule_binding(): void
    {
        $this->travelTo(now()->setTime(10, 30));
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-SCHED-ATT', 'name' => 'Meja Jadwal', 'is_active' => true]);
        $schedule = ActivitySchedule::create([
            'device_id' => $device->id,
            'title' => 'Praktikum sesi pagi',
            'activity_date' => today(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);
        ScheduleParticipant::create([
            'activity_schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'role' => 'siswa',
        ]);

        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        $report = $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban dengan jadwal'],
            'device_id' => $device->id,
            'activity_schedule_id' => $schedule->id,
        ]);

        $attempt = $report->attempts->first();
        $this->assertNotNull($attempt);
        $this->assertEquals($schedule->id, $attempt->activity_schedule_id);
        $this->assertEquals($device->id, $attempt->device_id);
    }

    // -------------------------------------------------------------------
    // AC-6: Duplicate submission within same attempt_number is prevented.
    // -------------------------------------------------------------------

    public function test_attempt_numbers_are_strictly_sequential(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        $action->execute($student, $module, ['lks_answers' => ['q1' => 'A']]);
        $action->execute($student, $module, ['lks_answers' => ['q1' => 'B']]);
        $report = $action->execute($student, $module, ['lks_answers' => ['q1' => 'C']]);

        $report->load('attempts');
        $attemptNumbers = $report->attempts->pluck('attempt_number')->toArray();
        $this->assertEquals([1, 2, 3], $attemptNumbers);
    }

    // -------------------------------------------------------------------
    // AC-7: Immutability — attempt records cannot be modified after creation.
    // -------------------------------------------------------------------

    public function test_existing_attempt_records_are_never_updated_by_subsequent_submissions(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        $report = $action->execute($student, $module, ['lks_answers' => ['q1' => 'Original']]);
        $firstAttempt = $report->attempts->first();
        $originalCreatedAt = $firstAttempt->created_at->toIso8601String();
        $originalAnswers = $firstAttempt->lks_answers;

        // Wait a moment and submit again
        $this->travel(5)->minutes();
        $action->execute($student, $module, ['lks_answers' => ['q1' => 'Updated']]);

        // Reload and verify first attempt is untouched
        $firstAttempt->refresh();
        $this->assertEquals($originalAnswers, $firstAttempt->lks_answers);
        $this->assertEquals($originalCreatedAt, $firstAttempt->created_at->toIso8601String());
    }

    // -------------------------------------------------------------------
    // AC-8: Session window evidence scoping (stale/out-of-window rejected).
    // -------------------------------------------------------------------

    public function test_evidence_reading_outside_schedule_session_window_is_rejected(): void
    {
        $this->travelTo(now()->setTime(10, 30));
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-OUT-WINDOW', 'name' => 'Meja Window Test', 'is_active' => true]);

        $schedule = ActivitySchedule::create([
            'device_id' => $device->id,
            'title' => 'Praktikum sesi pagi',
            'activity_date' => today(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);
        ScheduleParticipant::create([
            'activity_schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'role' => 'siswa',
        ]);

        // Reading recorded yesterday (outside session window)
        $staleReading = TelemetryReading::create([
            'device_id' => $device->id,
            'ph' => 6.8,
            'temperature' => 24.0,
            'humidity' => 65.0,
            'ec' => 1100,
            'nitrogen' => 35,
            'phosphorus' => 20,
            'potassium' => 90,
            'recorded_at' => today()->subDay()->setTime(10, 0),
        ]);

        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Mencoba submit bukti di luar jam sesi'],
            'device_id' => $device->id,
            'activity_schedule_id' => $schedule->id,
            'evidence_reading_id' => $staleReading->id,
        ]);
    }

    public function test_auto_resolved_reading_ignores_stale_reading_before_session_window(): void
    {
        $this->travelTo(now()->setTime(10, 30));
        $student = User::factory()->create(['role' => 'siswa']);
        $device = Device::create(['code' => 'TK-NO-STALE', 'name' => 'Meja Stale Check', 'is_active' => true]);

        $schedule = ActivitySchedule::create([
            'device_id' => $device->id,
            'title' => 'Praktikum sesi pagi',
            'activity_date' => today(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);
        ScheduleParticipant::create([
            'activity_schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'role' => 'siswa',
        ]);

        // Reading was recorded at 07:00 (before session started at 09:00)
        TelemetryReading::create([
            'device_id' => $device->id,
            'ph' => 6.8,
            'temperature' => 24.0,
            'humidity' => 65.0,
            'ec' => 1100,
            'nitrogen' => 35,
            'phosphorus' => 20,
            'potassium' => 90,
            'recorded_at' => today()->setTime(7, 0),
        ]);

        $module = ModulePracticum::firstOrFail();
        $action = app(SubmitStudentReportAction::class);

        // Submit without explicit reading ID — should not auto-pick the 07:00 reading from outside window
        $report = $action->execute($student, $module, [
            'lks_answers' => ['q1' => 'Jawaban saat sesi'],
            'device_id' => $device->id,
            'activity_schedule_id' => $schedule->id,
        ]);

        // Evidence reading should be null and honest empty snapshot recorded
        $this->assertNull($report->evidence_reading_id);
        $this->assertFalse($report->telemetry_snapshot['has_telemetry']);
    }
}
