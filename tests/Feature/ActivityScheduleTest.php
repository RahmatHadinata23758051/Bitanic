<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Device;
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

    public function test_schedule_can_be_deleted(): void
    {
        $schedule = ActivitySchedule::create([
            'title' => 'Sesi Akan Dihapus',
            'activity_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        $response = $this->delete(route('schedules.destroy', $schedule->id));

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseMissing('activity_schedules', [
            'id' => $schedule->id,
        ]);
    }
}
