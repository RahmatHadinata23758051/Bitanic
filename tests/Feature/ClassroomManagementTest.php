<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_crud_classrooms(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);

        // 1. Create Class
        $response = $this->actingAs($teacher)->post(route('classes.store'), [
            'name' => 'XII ATPH 1',
            'department' => 'Agribisnis Tanaman Pangan & Hortikultura',
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $response->assertRedirect(route('classes.index'));
        $this->assertDatabaseHas('classrooms', ['name' => 'XII ATPH 1']);

        $classroom = Classroom::where('name', 'XII ATPH 1')->firstOrFail();

        // 2. Update Class
        $updateResponse = $this->actingAs($teacher)->put(route('classes.update', $classroom->id), [
            'name' => 'XII ATPH 1 Unggulan',
            'department' => 'Agribisnis Tanaman Pangan & Hortikultura',
            'academic_year' => '2026/2027',
        ]);
        $updateResponse->assertRedirect(route('classes.index'));
        $this->assertDatabaseHas('classrooms', ['name' => 'XII ATPH 1 Unggulan']);

        // 3. Delete Class
        $deleteResponse = $this->actingAs($teacher)->delete(route('classes.destroy', $classroom->id));
        $deleteResponse->assertRedirect(route('classes.index'));
        $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
    }

    public function test_teacher_can_crud_student_groups_with_trainer_kit(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $classroom = Classroom::create(['name' => 'XI ATPH 2']);
        $device = Device::create(['code' => 'TK-GRP-01', 'name' => 'Meja Kelompok 1', 'is_active' => true]);

        // 1. Create Group
        $response = $this->actingAs($teacher)->post(route('classes.groups.store', $classroom->id), [
            'name' => 'Kelompok 1 (Bedengan Melati)',
            'device_id' => $device->id,
            'notes' => 'Fokus komoditas tomat beef',
        ]);
        $response->assertRedirect(route('classes.index'));
        $this->assertDatabaseHas('student_groups', [
            'classroom_id' => $classroom->id,
            'name' => 'Kelompok 1 (Bedengan Melati)',
            'device_id' => $device->id,
        ]);

        $group = StudentGroup::where('name', 'Kelompok 1 (Bedengan Melati)')->firstOrFail();

        // 2. Update Group
        $updateResponse = $this->actingAs($teacher)->put(route('groups.update', $group->id), [
            'name' => 'Kelompok 1 (Bedengan Mawar)',
            'device_id' => $device->id,
        ]);
        $updateResponse->assertRedirect(route('classes.index'));
        $this->assertDatabaseHas('student_groups', ['name' => 'Kelompok 1 (Bedengan Mawar)']);

        // 3. Delete Group
        $deleteResponse = $this->actingAs($teacher)->delete(route('groups.destroy', $group->id));
        $deleteResponse->assertRedirect(route('classes.index'));
        $this->assertDatabaseMissing('student_groups', ['id' => $group->id]);
    }

    public function test_teacher_can_allocate_student_to_class_group_and_trainer_kit(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        UserProfile::create(['user_id' => $student->id, 'type' => 'siswa']);

        $classroom = Classroom::create(['name' => 'X ATPH 3']);
        $device = Device::create(['code' => 'TK-ALLOC-01', 'name' => 'Meja Praktik 1', 'is_active' => true]);
        $group = StudentGroup::create([
            'classroom_id' => $classroom->id,
            'name' => 'Kelompok Bedengan 3A',
            'device_id' => $device->id,
        ]);

        $response = $this->actingAs($teacher)->post(route('students.allocate', $student->id), [
            'classroom_id' => $classroom->id,
            'student_group_id' => $group->id,
            'assigned_kit_id' => 'TK-ALLOC-01',
        ]);

        $response->assertRedirect();
        $student->refresh();
        $this->assertEquals($classroom->id, $student->profile->classroom_id);
        $this->assertEquals($group->id, $student->profile->student_group_id);
        $this->assertEquals('X ATPH 3', $student->profile->class_grade);
        $this->assertEquals('Kelompok Bedengan 3A', $student->profile->group_name);
        $this->assertEquals('TK-ALLOC-01', $student->profile->assigned_kit_id);
    }

    public function test_teacher_can_view_classes_index(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        Classroom::create(['name' => 'X ATPH 1']);

        $response = $this->actingAs($teacher)->get(route('classes.index'));
        $response->assertStatus(200);
        $response->assertSee('X ATPH 1');
        $response->assertSee('Manajemen Kelas');
    }

    public function test_cannot_delete_classroom_with_assigned_students(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $classroom = Classroom::create(['name' => 'XII ATPH 3']);

        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'classroom_id' => $classroom->id,
        ]);

        $response = $this->actingAs($teacher)->delete(route('classes.destroy', $classroom->id));
        $response->assertSessionHasErrors(['classroom']);
        $this->assertDatabaseHas('classrooms', ['id' => $classroom->id]);
    }

    public function test_student_allocation_validates_group_kit_mismatch(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        UserProfile::create(['user_id' => $student->id, 'type' => 'siswa']);

        $classroom = Classroom::create(['name' => 'X ATPH 4']);
        $device1 = Device::create(['code' => 'TK-GRP-01', 'name' => 'Meja 1', 'is_active' => true]);
        $device2 = Device::create(['code' => 'TK-GRP-02', 'name' => 'Meja 2', 'is_active' => true]);

        $group = StudentGroup::create([
            'classroom_id' => $classroom->id,
            'name' => 'Kelompok 1',
            'device_id' => $device1->id,
        ]);

        // Attempt to allocate student to group 1 but with kit 2 (mismatch!)
        $response = $this->actingAs($teacher)->post(route('students.allocate', $student->id), [
            'classroom_id' => $classroom->id,
            'student_group_id' => $group->id,
            'assigned_kit_id' => 'TK-GRP-02',
        ]);

        $response->assertSessionHasErrors(['assigned_kit_id']);
    }
}
