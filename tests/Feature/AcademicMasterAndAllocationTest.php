<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicMasterAndAllocationTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $superuser;

    private User $adminA;

    private User $guruA;

    private User $guruB;

    private User $siswaA;

    private User $siswaB;

    private Device $deviceA;

    private Device $deviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create([
            'name' => 'SMK Negeri 1 Sukaraja',
            'slug' => 'smk-negeri-1-sukaraja',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'name' => 'SMK Negeri 2 Sukaraja',
            'slug' => 'smk-negeri-2-sukaraja',
            'status' => 'active',
        ]);

        $this->superuser = User::create([
            'name' => 'Global Superuser',
            'email' => 'superuser@bitanic.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'superuser',
            'school_id' => null,
            'is_active' => true,
        ]);

        $this->adminA = User::create([
            'name' => 'Admin A',
            'email' => 'admin.a@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);

        $this->guruA = User::create([
            'name' => 'Guru A',
            'email' => 'guru.a@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);

        $this->guruB = User::create([
            'name' => 'Guru B',
            'email' => 'guru.b@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'guru',
            'school_id' => $this->schoolB->id,
            'is_active' => true,
        ]);

        $this->siswaA = User::create([
            'name' => 'Siswa A',
            'email' => 'siswa.a@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);
        UserProfile::create(['user_id' => $this->siswaA->id, 'type' => 'siswa']);

        $this->siswaB = User::create([
            'name' => 'Siswa B',
            'email' => 'siswa.b@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'siswa',
            'school_id' => $this->schoolB->id,
            'is_active' => true,
        ]);
        UserProfile::create(['user_id' => $this->siswaB->id, 'type' => 'siswa']);

        $this->deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-01',
            'name' => 'Trainer Kit 01',
            'is_active' => true,
        ]);

        $this->deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-B1',
            'name' => 'Trainer Kit B1',
            'is_active' => true,
        ]);
    }

    public function test_teacher_can_create_classroom_with_homeroom_teacher(): void
    {
        $response = $this->actingAs($this->guruA)
            ->post(route('classes.store'), [
                'name' => 'XII ATPH 1',
                'department' => 'Agribisnis Tanaman Pangan & Hortikultura',
                'academic_year' => '2026/2027',
                'homeroom_teacher_id' => $this->guruA->id,
            ]);

        $response->assertRedirect(route('classes.index'));
        $classroom = Classroom::where('name', 'XII ATPH 1')->first();
        $this->assertNotNull($classroom);
        $this->assertEquals($this->schoolA->id, $classroom->school_id);
        $this->assertEquals($this->guruA->id, $classroom->homeroom_teacher_id);
        $this->assertEquals('2026/2027', $classroom->academic_year);
    }

    public function test_classroom_creation_rejects_cross_school_homeroom_teacher(): void
    {
        // Guru A tries to assign Guru B (from School B)
        $response = $this->actingAs($this->guruA)
            ->post(route('classes.store'), [
                'name' => 'XII ATPH Cross',
                'academic_year' => '2026/2027',
                'homeroom_teacher_id' => $this->guruB->id,
            ]);

        $response->assertSessionHasErrors('homeroom_teacher_id');
        $this->assertDatabaseMissing('classrooms', ['name' => 'XII ATPH Cross']);
    }

    public function test_superuser_must_explicitly_select_active_school_for_class_creation(): void
    {
        $response = $this->actingAs($this->superuser)
            ->from(route('classes.index'))
            ->post(route('classes.store'), [
                'school_id' => null,
                'name' => 'Orphan Class',
                'academic_year' => '2026/2027',
            ]);

        $response->assertSessionHasErrors('school_id');
        $this->assertDatabaseMissing('classrooms', ['name' => 'Orphan Class']);

        // Explicit school B
        $response2 = $this->actingAs($this->superuser)
            ->post(route('classes.store'), [
                'school_id' => $this->schoolB->id,
                'name' => 'Kelas Sekolah B',
                'academic_year' => '2026/2027',
            ]);

        $response2->assertRedirect(route('classes.index', ['school_id' => $this->schoolB->id]));
        $classB = Classroom::where('name', 'Kelas Sekolah B')->first();
        $this->assertNotNull($classB);
        $this->assertEquals($this->schoolB->id, $classB->school_id);
    }

    public function test_classroom_booted_has_no_arbitrary_default_school_fallback(): void
    {
        // Unauthenticated creation without school_id
        $classroom = new Classroom([
            'name' => 'Pure Isolated Class',
            'academic_year' => '2026/2027',
        ]);
        $classroom->save();

        // Must NOT fallback to School::first() or smk-ppn-1-sukaraja
        $this->assertNull($classroom->school_id);
    }

    public function test_student_group_booted_derives_school_from_parent_classroom(): void
    {
        $classroom = Classroom::create([
            'school_id' => $this->schoolB->id,
            'name' => 'XI ATPH B',
            'academic_year' => '2026/2027',
        ]);

        $group = new StudentGroup([
            'classroom_id' => $classroom->id,
            'name' => 'Kelompok Mandiri B',
        ]);
        $group->save();

        $this->assertEquals($this->schoolB->id, $group->school_id);
    }

    public function test_student_group_creation_rejects_cross_school_device_and_leader(): void
    {
        $classroomA = Classroom::create([
            'school_id' => $this->schoolA->id,
            'name' => 'XII ATPH A',
            'academic_year' => '2026/2027',
        ]);

        // Attempt to assign deviceB (from School B)
        $response = $this->actingAs($this->guruA)
            ->post(route('classes.groups.store', $classroomA->id), [
                'name' => 'Kelompok A1',
                'device_id' => $this->deviceB->id,
            ]);

        $response->assertSessionHasErrors('device_id');

        // Attempt to assign siswaB (from School B)
        $response2 = $this->actingAs($this->guruA)
            ->post(route('classes.groups.store', $classroomA->id), [
                'name' => 'Kelompok A2',
                'leader_id' => $this->siswaB->id,
            ]);

        $response2->assertSessionHasErrors('leader_id');

        // Valid assignment of deviceA and siswaA
        $response3 = $this->actingAs($this->guruA)
            ->post(route('classes.groups.store', $classroomA->id), [
                'name' => 'Kelompok A Valid',
                'device_id' => $this->deviceA->id,
                'leader_id' => $this->siswaA->id,
            ]);

        $response3->assertRedirect(route('classes.index'));
        $group = StudentGroup::where('name', 'Kelompok A Valid')->first();
        $this->assertNotNull($group);
        $this->assertEquals($this->schoolA->id, $group->school_id);
        $this->assertEquals($this->deviceA->id, $group->device_id);
        $this->assertEquals($this->siswaA->id, $group->leader_id);
    }

    public function test_student_allocation_to_class_and_group(): void
    {
        $classroomA = Classroom::create([
            'school_id' => $this->schoolA->id,
            'name' => 'XII ATPH A',
            'academic_year' => '2026/2027',
        ]);

        $groupA = StudentGroup::create([
            'school_id' => $this->schoolA->id,
            'classroom_id' => $classroomA->id,
            'device_id' => $this->deviceA->id,
            'name' => 'Kelompok Bedengan 1',
        ]);

        $response = $this->actingAs($this->guruA)
            ->post(route('students.allocate', $this->siswaA->id), [
                'classroom_id' => $classroomA->id,
                'student_group_id' => $groupA->id,
                'assigned_kit_id' => $this->deviceA->code,
            ]);

        $response->assertRedirect();
        $profile = $this->siswaA->refresh()->profile;
        $this->assertEquals($classroomA->id, $profile->classroom_id);
        $this->assertEquals('XII ATPH A', $profile->class_grade);
        $this->assertEquals($groupA->id, $profile->student_group_id);
        $this->assertEquals('Kelompok Bedengan 1', $profile->group_name);
        $this->assertEquals('TK-01', $profile->assigned_kit_id);
    }

    public function test_student_allocation_rejects_cross_school_entities(): void
    {
        $classroomB = Classroom::create([
            'school_id' => $this->schoolB->id,
            'name' => 'XII ATPH B',
            'academic_year' => '2026/2027',
        ]);

        // Trying to allocate Siswa A (School A) into Classroom B (School B)
        $response = $this->actingAs($this->guruA)
            ->post(route('students.allocate', $this->siswaA->id), [
                'classroom_id' => $classroomB->id,
            ]);

        $response->assertSessionHasErrors('classroom_id');
    }

    public function test_academic_year_settings_update(): void
    {
        $response = $this->actingAs($this->adminA)
            ->post(route('classes.academic-year.update'), [
                'academic_year' => '2027/2028',
                'semester' => 'Genap',
            ]);

        $response->assertRedirect(route('classes.index'));
        $setting = SchoolSetting::where('school_id', $this->schoolA->id)->first();
        $this->assertNotNull($setting);
        $this->assertEquals('2027/2028', $setting->academic_year);
        $this->assertEquals('Genap', $setting->semester);
    }
}
