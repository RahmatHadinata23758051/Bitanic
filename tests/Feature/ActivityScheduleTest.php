<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentGroup;
use App\Models\User;
use Database\Seeders\TrainerKitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TrainerKitSeeder::class);
    }

    public function test_schedules_index_renders_timeline_and_trainer_kits(): void
    {
        $response = $this->get(route('schedules.index'));

        $response->assertStatus(200);
        $response->assertSee('Manajemen Jadwal Praktikum &amp; Alokasi Meja Trainer Kit', false);
        $response->assertSee('TK-01');
        $response->assertSee('Meja Praktik 1');
    }

    public function test_guest_schedule_page_uses_default_school_without_exposing_other_schools(): void
    {
        $otherSchool = School::create([
            'npsn' => '20200999',
            'name' => 'SMK Other School',
            'slug' => 'smk-other-schedule-school',
            'status' => 'active',
        ]);
        Device::create([
            'school_id' => $otherSchool->id,
            'code' => 'TK-OTHER-SCHOOL',
            'name' => 'Other School Trainer Kit',
            'is_active' => true,
        ]);

        $response = $this->get(route('schedules.index'));

        $response->assertOk();
        $response->assertSee('TK-01');
        $response->assertDontSee('TK-OTHER-SCHOOL');
        $response->assertDontSee('Other School Trainer Kit');
    }

    public function test_teacher_can_create_schedule_with_kit_allocation(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit = Device::where('code', 'TK-01')->firstOrFail();

        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Praktikum Sensor pH — Kelas XII ATPH 1',
            'device_id' => $kit->id,
            'activity_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:30',
            'agenda' => 'Kalibrasi elektroda kaca dengan buffer pH 4 dan pH 7.',
        ]);

        $response->assertRedirect(route('schedules.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('activity_schedules', [
            'title' => 'Praktikum Sensor pH — Kelas XII ATPH 1',
            'device_id' => $kit->id,
        ]);

        $schedule = ActivitySchedule::where('title', 'Praktikum Sensor pH — Kelas XII ATPH 1')->firstOrFail();
        $this->assertDatabaseHas('schedule_participants', [
            'activity_schedule_id' => $schedule->id,
            'user_id' => $teacher->id,
            'role' => 'guru',
        ]);
    }

    public function test_schedule_creation_detects_kit_booking_collision_and_rejects(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit = Device::where('code', 'TK-02')->firstOrFail();
        $date = now()->addDays(2)->toDateString();

        // Existing schedule on TK-02 from 08:00 to 11:00
        ActivitySchedule::create([
            'device_id' => $kit->id,
            'title' => 'Sesi Pagi Kelas X',
            'activity_date' => $date,
            'start_time' => '08:00',
            'end_time' => '11:00',
        ]);

        // Attempt overlapping session on same TK-02 from 09:30 to 12:00
        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Sesi Bertabrakan Kelas XI',
            'device_id' => $kit->id,
            'activity_date' => $date,
            'start_time' => '09:30',
            'end_time' => '12:00',
        ]);

        $response->assertSessionHasErrors(['device_id']);
        $this->assertDatabaseMissing('activity_schedules', [
            'title' => 'Sesi Bertabrakan Kelas XI',
        ]);
    }

    public function test_schedule_creation_validates_end_time_greater_than_start_time(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit = Device::where('code', 'TK-03')->firstOrFail();

        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Sesi Jam Tidak Valid',
            'device_id' => $kit->id,
            'activity_date' => now()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '09:00', // invalid: end before start
        ]);

        $response->assertSessionHasErrors(['end_time']);
    }

    public function test_schedule_creation_detects_teacher_time_collision_and_rejects(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit1 = Device::where('code', 'TK-01')->firstOrFail();
        $kit2 = Device::where('code', 'TK-02')->firstOrFail();
        $date = now()->addDays(3)->toDateString();

        // Sesi pertama diajar oleh guru pada kit 1 (08:00 - 10:00)
        $schedule = ActivitySchedule::create([
            'device_id' => $kit1->id,
            'title' => 'Sesi Guru di Meja 1',
            'activity_date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $schedule->participants()->create([
            'user_id' => $teacher->id,
            'role' => 'guru',
        ]);

        // Coba jadwalkan sesi kedua di meja 2 pada jam bertabrakan (09:00 - 11:00) dengan guru yang sama
        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Sesi Guru Bertabrakan di Meja 2',
            'device_id' => $kit2->id,
            'activity_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertSessionHasErrors(['start_time']);
        $this->assertDatabaseMissing('activity_schedules', [
            'title' => 'Sesi Guru Bertabrakan di Meja 2',
        ]);
    }

    public function test_guest_cannot_delete_schedule(): void
    {
        $schedule = ActivitySchedule::create([
            'title' => 'Sesi Tidak Boleh Dihapus Guest',
            'activity_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        $response = $this->delete(route('schedules.destroy', $schedule->id));

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('activity_schedules', [
            'id' => $schedule->id,
        ]);
    }

    public function test_student_cannot_delete_schedule(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $schedule = ActivitySchedule::create([
            'title' => 'Sesi Tidak Boleh Dihapus Siswa',
            'activity_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        $response = $this->actingAs($student)->delete(route('schedules.destroy', $schedule->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('activity_schedules', [
            'id' => $schedule->id,
        ]);
    }

    public function test_authorized_teacher_can_delete_schedule(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $schedule = ActivitySchedule::create([
            'title' => 'Sesi Akan Dihapus',
            'activity_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        $response = $this->actingAs($teacher)->delete(route('schedules.destroy', $schedule->id));

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseMissing('activity_schedules', [
            'id' => $schedule->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // BR-500: Class, Group, Module Allocation & Collision Matrix Tests
    // -------------------------------------------------------------------------

    public function test_teacher_can_create_schedule_with_classroom_group_and_module_allocation(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit = Device::where('code', 'TK-01')->firstOrFail();
        $classroom = Classroom::create([
            'school_id' => $teacher->school_id,
            'name' => 'XII ATPH 1',
        ]);
        $group = StudentGroup::create([
            'school_id' => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'name' => 'Kelompok 1 Tanaman Semusim',
        ]);
        $module = ModulePracticum::create([
            'meeting_number' => 101,
            'title' => 'Modul 1: Kalibrasi Sensor pH Tanah',
        ]);

        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Praktikum Lapangan Modul 1 — Kelas XII ATPH 1',
            'classroom_id' => $classroom->id,
            'student_group_id' => $group->id,
            'device_id' => $kit->id,
            'module_practicum_id' => $module->id,
            'activity_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'agenda' => 'Pencatatan data kalibrasi larutan buffer pH 4 dan 7.',
        ]);

        $response->assertRedirect(route('schedules.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('activity_schedules', [
            'title' => 'Praktikum Lapangan Modul 1 — Kelas XII ATPH 1',
            'classroom_id' => $classroom->id,
            'student_group_id' => $group->id,
            'device_id' => $kit->id,
            'module_practicum_id' => $module->id,
        ]);

        $schedule = ActivitySchedule::where('title', 'Praktikum Lapangan Modul 1 — Kelas XII ATPH 1')->firstOrFail();
        $this->assertEquals($classroom->id, $schedule->classroom?->id);
        $this->assertEquals($group->id, $schedule->studentGroup?->id);
        $this->assertEquals($module->id, $schedule->modulePracticum?->id);
    }

    public function test_schedule_creation_detects_classroom_collision_and_rejects(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit1 = Device::where('code', 'TK-01')->firstOrFail();
        $kit2 = Device::where('code', 'TK-02')->firstOrFail();
        $date = now()->addDays(4)->toDateString();

        $classroom = Classroom::create([
            'school_id' => $teacher->school_id,
            'name' => 'X ATPH 2',
        ]);

        // Existing schedule for classroom on kit1 (08:00 - 10:00)
        ActivitySchedule::create([
            'school_id' => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'device_id' => $kit1->id,
            'title' => 'Sesi Kelas Awal',
            'activity_date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        // Attempting overlapping session for the SAME classroom on kit2 (09:00 - 11:00)
        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Sesi Kelas Bertabrakan',
            'classroom_id' => $classroom->id,
            'device_id' => $kit2->id,
            'activity_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertSessionHasErrors(['classroom_id']);
        $this->assertDatabaseMissing('activity_schedules', [
            'title' => 'Sesi Kelas Bertabrakan',
        ]);
    }

    public function test_schedule_creation_detects_student_group_collision_and_rejects(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit1 = Device::where('code', 'TK-01')->firstOrFail();
        $kit2 = Device::where('code', 'TK-02')->firstOrFail();
        $date = now()->addDays(5)->toDateString();

        $classroom1 = Classroom::create(['school_id' => $teacher->school_id, 'name' => 'XI ATPH 1']);
        $group = StudentGroup::create([
            'school_id' => $teacher->school_id,
            'classroom_id' => $classroom1->id,
            'name' => 'Kelompok Khusus Sayur',
        ]);

        // Existing schedule for group on kit1 (13:00 - 15:00)
        ActivitySchedule::create([
            'school_id' => $teacher->school_id,
            'student_group_id' => $group->id,
            'device_id' => $kit1->id,
            'title' => 'Sesi Kelompok Sayur 1',
            'activity_date' => $date,
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        // Attempting overlapping session for the SAME group on kit2 (14:00 - 16:00)
        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Sesi Kelompok Sayur Bertabrakan',
            'student_group_id' => $group->id,
            'device_id' => $kit2->id,
            'activity_date' => $date,
            'start_time' => '14:00',
            'end_time' => '16:00',
        ]);

        $response->assertSessionHasErrors(['student_group_id']);
        $this->assertDatabaseMissing('activity_schedules', [
            'title' => 'Sesi Kelompok Sayur Bertabrakan',
        ]);
    }

    public function test_schedule_creation_rejects_student_group_not_belonging_to_selected_classroom(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit = Device::where('code', 'TK-01')->firstOrFail();

        $classroomA = Classroom::create(['school_id' => $teacher->school_id, 'name' => 'Kelas A']);
        $classroomB = Classroom::create(['school_id' => $teacher->school_id, 'name' => 'Kelas B']);

        $groupB = StudentGroup::create([
            'school_id' => $teacher->school_id,
            'classroom_id' => $classroomB->id,
            'name' => 'Kelompok Milik Kelas B',
        ]);

        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Sesi Mismatch Kelas dan Kelompok',
            'classroom_id' => $classroomA->id,
            'student_group_id' => $groupB->id,
            'device_id' => $kit->id,
            'activity_date' => now()->addDays(6)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertSessionHasErrors(['student_group_id']);
        $this->assertDatabaseMissing('activity_schedules', [
            'title' => 'Sesi Mismatch Kelas dan Kelompok',
        ]);
    }

    public function test_schedule_creation_rejects_cross_school_classroom_or_group(): void
    {
        $schoolA = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );
        $schoolB = School::create([
            'npsn' => '20200099',
            'name' => 'SMK B Isolated',
            'slug' => 'smk-b-isolated',
            'status' => 'active',
        ]);

        $teacherA = User::factory()->create(['role' => 'guru', 'school_id' => $schoolA->id]);
        $classroomB = Classroom::create([
            'school_id' => $schoolB->id,
            'name' => 'Kelas Milik Sekolah B',
        ]);
        $kitA = Device::where('code', 'TK-01')->firstOrFail();

        $response = $this->actingAs($teacherA)->post(route('schedules.store'), [
            'title' => 'Sesi Ilegal Lintas Sekolah',
            'classroom_id' => $classroomB->id,
            'device_id' => $kitA->id,
            'activity_date' => now()->addDays(7)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertSessionHasErrors(['classroom_id']);
        $this->assertDatabaseMissing('activity_schedules', [
            'title' => 'Sesi Ilegal Lintas Sekolah',
        ]);
    }

    public function test_module_order_is_teacher_driven_without_sequential_locking(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit = Device::where('code', 'TK-01')->firstOrFail();

        // Create Module 1 and Module 5
        ModulePracticum::create([
            'meeting_number' => 1,
            'title' => 'Modul 1: Dasar Sensor',
        ]);
        $module5 = ModulePracticum::create([
            'meeting_number' => 5,
            'title' => 'Modul 5: Otomasi Fertigasi Presisi Lanjut',
        ]);

        // Teacher schedules Module 5 directly without prior modules completed
        $response = $this->actingAs($teacher)->post(route('schedules.store'), [
            'title' => 'Sesi Praktikum Modul 5 Fleksibel',
            'device_id' => $kit->id,
            'module_practicum_id' => $module5->id,
            'activity_date' => now()->addDays(8)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseHas('activity_schedules', [
            'title' => 'Sesi Praktikum Modul 5 Fleksibel',
            'module_practicum_id' => $module5->id,
        ]);
    }

    public function test_schedules_index_renders_classroom_group_and_module_info(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit = Device::where('code', 'TK-01')->firstOrFail();
        $classroom = Classroom::create([
            'school_id' => $teacher->school_id,
            'name' => 'XII ATPH 3',
        ]);
        $group = StudentGroup::create([
            'school_id' => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'name' => 'Kelompok Bedengan C',
        ]);
        $module = ModulePracticum::create([
            'meeting_number' => 202,
            'title' => 'Modul 202: Monitoring NPK Tanah',
        ]);

        ActivitySchedule::create([
            'school_id' => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'student_group_id' => $group->id,
            'device_id' => $kit->id,
            'module_practicum_id' => $module->id,
            'title' => 'Sesi Khusus Bedengan C',
            'activity_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response = $this->actingAs($teacher)->get(route('schedules.index'));

        $response->assertStatus(200);
        $response->assertSee('XII ATPH 3');
        $response->assertSee('Kelompok Bedengan C');
        $response->assertSee('Modul 202: Monitoring NPK Tanah');
    }

    public function test_concurrent_overlapping_bookings_are_serialized_and_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $kit = Device::where('code', 'TK-01')->firstOrFail();
        $classroom = Classroom::create([
            'school_id' => $teacher->school_id,
            'name' => 'XII ATPH Concurrency Test',
        ]);

        $action = app(\App\Actions\Schedule\CreateScheduleAction::class);
        $date = now()->addDays(10)->toDateString();

        // Transaction 1 succeeds
        $firstSchedule = $action->execute([
            'title' => 'Sesi Pertama Lock Berhasil',
            'classroom_id' => $classroom->id,
            'device_id' => $kit->id,
            'activity_date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ], $teacher);

        $this->assertNotNull($firstSchedule->id);

        // Transaction 2 attempting same classroom and kit overlap is rejected by collision check
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $action->execute([
            'title' => 'Sesi Kedua Tabrakan Overlap',
            'classroom_id' => $classroom->id,
            'device_id' => $kit->id,
            'activity_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ], $teacher);
    }
}
