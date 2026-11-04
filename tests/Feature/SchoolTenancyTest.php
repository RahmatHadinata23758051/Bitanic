<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_school_is_created_and_associated_with_users_and_devices(): void
    {
        $defaultSchool = School::where('slug', 'smk-ppn-1-sukaraja')->firstOrFail();
        $this->assertEquals('20200001', $defaultSchool->npsn);
        $this->assertEquals('SMK Pertanian Pembangunan Negeri 1 Sukaraja', $defaultSchool->name);
        $this->assertEquals('active', $defaultSchool->status);

        $user = User::factory()->create(['role' => 'guru']);
        $this->assertEquals($defaultSchool->id, $user->school_id);
        $this->assertInstanceOf(School::class, $user->school);

        $device = Device::create([
            'code' => 'TK-TENANCY-01',
            'name' => 'Meja Praktikum Tenancy 1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);
        $this->assertEquals($defaultSchool->id, $device->school_id);
        $this->assertInstanceOf(School::class, $device->school);
    }

    public function test_multi_school_ready_isolation_allows_distinct_schools_without_conflict(): void
    {
        $schoolA = School::where('slug', 'smk-ppn-1-sukaraja')->firstOrFail();

        $schoolB = School::create([
            'npsn' => '20200002',
            'name' => 'SMK Negeri 2 Subang (Pertanian)',
            'slug' => 'smk-n-2-subang',
            'city' => 'Subang',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $deviceA = Device::create([
            'school_id' => $schoolA->id,
            'code' => 'TK-SCH-A',
            'name' => 'Kit Sukaraja #1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        $deviceB = Device::create([
            'school_id' => $schoolB->id,
            'code' => 'TK-SCH-B',
            'name' => 'Kit Subang #1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        $this->assertCount(1, $schoolA->devices()->where('code', 'TK-SCH-A')->get());
        $this->assertCount(0, $schoolA->devices()->where('code', 'TK-SCH-B')->get());
        $this->assertCount(1, $schoolB->devices()->where('code', 'TK-SCH-B')->get());
        $this->assertCount(0, $schoolB->devices()->where('code', 'TK-SCH-A')->get());
    }

    public function test_teacher_views_and_mutations_are_scoped_to_their_school(): void
    {
        $schoolA = School::where('slug', 'smk-ppn-1-sukaraja')->firstOrFail();
        $schoolB = School::create([
            'npsn' => '20200003',
            'name' => 'SMK Negeri 3 Subang',
            'slug' => 'smk-n-3-subang',
            'status' => 'active',
        ]);

        $teacherA = User::factory()->create(['role' => 'guru', 'school_id' => $schoolA->id]);
        $teacherB = User::factory()->create(['role' => 'guru', 'school_id' => $schoolB->id]);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolA->id]);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id]);
        $deviceA = Device::create([
            'school_id' => $schoolA->id,
            'code' => 'TK-IDOR-A',
            'name' => 'Kit Sekolah A',
            'is_active' => true,
        ]);
        $deviceB = Device::create([
            'school_id' => $schoolB->id,
            'code' => 'TK-IDOR-B',
            'name' => 'Kit Sekolah B',
            'is_active' => true,
        ]);
        $module = ModulePracticum::create([
            'meeting_number' => 1,
            'title' => 'Modul Tenancy Test',
            'duration_minutes' => 120,
            'badge' => 'Test',
        ]);
        $reportA = StudentReport::create([
            'user_id' => $studentA->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);
        $reportB = StudentReport::create([
            'user_id' => $studentB->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);
        $scheduleB = ActivitySchedule::create([
            'school_id' => $schoolB->id,
            'device_id' => $deviceB->id,
            'title' => 'Sesi privat sekolah B',
            'activity_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs($teacherA)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($studentA->name)
            ->assertDontSee($studentB->name);

        $this->actingAs($teacherA)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee($studentA->name)
            ->assertDontSee($studentB->name);

        $this->actingAs($teacherA)
            ->get(route('assessment.index'))
            ->assertOk()
            ->assertSee($studentA->name)
            ->assertDontSee($studentB->name);

        $this->actingAs($teacherA)
            ->get(route('schedules.index'))
            ->assertOk()
            ->assertDontSee('Sesi privat sekolah B');

        $this->actingAs($teacherA)
            ->post(route('schedules.store'), [
                'title' => 'Cross-school booking attempt',
                'device_id' => $deviceB->id,
                'activity_date' => now()->addDays(2)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
            ])
            ->assertSessionHasErrors('device_id');
        $this->assertDatabaseMissing('activity_schedules', ['title' => 'Cross-school booking attempt']);

        $this->actingAs($teacherA)
            ->delete(route('schedules.destroy', $scheduleB->id))
            ->assertNotFound();
        $this->assertDatabaseHas('activity_schedules', ['id' => $scheduleB->id]);

        $this->actingAs($teacherA)
            ->post(route('modules.grade-report', $reportB->id), [
                'rubric_scores' => ['aspect-1' => 2],
                'teacher_feedback' => 'Unauthorized cross-school grade',
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('student_reports', [
            'id' => $reportB->id,
            'status' => 'submitted',
            'graded_by' => null,
        ]);

        $this->assertDatabaseHas('student_reports', ['id' => $reportA->id, 'status' => 'submitted']);
    }
}
