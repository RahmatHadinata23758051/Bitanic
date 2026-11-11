<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolUserMasterTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $superuser;

    private User $adminA;

    private User $adminB;

    private User $guruA;

    private User $siswaA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create([
            'name' => 'SMK Negeri 1 Pertanian A',
            'slug' => 'smk-negeri-1-pertanian-a',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'name' => 'SMK Negeri 2 Pertanian B',
            'slug' => 'smk-negeri-2-pertanian-b',
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
            'name' => 'Admin Sekolah A',
            'email' => 'admin.a@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);

        $this->adminB = User::create([
            'name' => 'Admin Sekolah B',
            'email' => 'admin.b@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolB->id,
            'is_active' => true,
        ]);

        $this->guruA = User::create([
            'name' => 'Guru Pertanian A',
            'email' => 'guru.a@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);

        $this->siswaA = User::create([
            'name' => 'Siswa Praktikan A',
            'email' => 'siswa.a@smk.id',
            'password' => Hash::make('Secret123456!'),
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);
        UserProfile::create([
            'user_id' => $this->siswaA->id,
            'type' => 'siswa',
            'identifier_number' => '0011223344',
        ]);
    }

    public function test_admin_sekolah_can_create_student_with_optional_identifier(): void
    {
        $response = $this->actingAs($this->adminA)
            ->post(route('users.store'), [
                'name' => 'Siswa Baru Tanpa NISN',
                'email' => 'siswa.baru@smk.id',
                'role' => 'siswa',
                'identifier_number' => null, // Optional NISN
                'phone' => '081234567890',
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'siswa.baru@smk.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->schoolA->id, $user->school_id);
        $this->assertEquals('siswa', $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->is_active);

        $profile = $user->profile;
        $this->assertNotNull($profile);
        $this->assertNull($profile->identifier_number);
        $this->assertEquals('081234567890', $profile->phone);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->schoolA->id,
            'user_id' => $this->adminA->id,
            'action' => 'Pendaftaran Akun Pengguna',
        ]);
    }

    public function test_admin_sekolah_can_create_teacher_with_specialization(): void
    {
        $response = $this->actingAs($this->adminA)
            ->post(route('users.store'), [
                'name' => 'Guru Ahli Hidroponik',
                'email' => 'guru.hidro@smk.id',
                'role' => 'guru',
                'identifier_number' => '198501012010011001',
                'specialization' => 'Hidroponik Substrat IoT',
            ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'guru.hidro@smk.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->schoolA->id, $user->school_id);
        $this->assertEquals('guru', $user->role);
        $this->assertEquals('Hidroponik Substrat IoT', $user->profile?->specialization);
    }

    public function test_superuser_can_create_user_for_explicit_target_school(): void
    {
        $response = $this->actingAs($this->superuser)
            ->post(route('users.store'), [
                'school_id' => $this->schoolB->id,
                'name' => 'Siswa Sekolah B',
                'email' => 'siswa.b@smk.id',
                'role' => 'siswa',
            ]);

        $response->assertRedirect(route('users.index', ['school_id' => $this->schoolB->id]));

        $user = User::where('email', 'siswa.b@smk.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->schoolB->id, $user->school_id);
    }

    public function test_superuser_cannot_create_user_without_explicit_school(): void
    {
        $response = $this->actingAs($this->superuser)
            ->from(route('users.index'))
            ->post(route('users.store'), [
                'school_id' => null,
                'name' => 'Siswa Tanpa Sekolah',
                'email' => 'tanpa.sekolah@smk.id',
                'role' => 'siswa',
            ]);

        $response->assertSessionHasErrors('school_id');
        $this->assertDatabaseMissing('users', ['email' => 'tanpa.sekolah@smk.id']);
    }

    public function test_admin_sekolah_cannot_create_user_in_another_school(): void
    {
        // Admin A tries to pass school_id = School B
        $response = $this->actingAs($this->adminA)
            ->post(route('users.store'), [
                'school_id' => $this->schoolB->id,
                'name' => 'Siswa Siluman',
                'email' => 'siluman@smk.id',
                'role' => 'siswa',
            ]);

        // Controller strictly overrides targetSchoolId to Admin A's school_id
        $user = User::where('email', 'siluman@smk.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->schoolA->id, $user->school_id);
        $this->assertNotEquals($this->schoolB->id, $user->school_id);
    }

    public function test_admin_sekolah_can_update_user_in_own_school(): void
    {
        $response = $this->actingAs($this->adminA)
            ->put(route('users.update', $this->siswaA->id), [
                'name' => 'Siswa A Updated',
                'email' => 'siswa.a@smk.id',
                'role' => 'siswa',
                'identifier_number' => '9988776655',
                'phone' => '0899999999',
            ]);

        $response->assertRedirect(route('users.index'));
        $this->siswaA->refresh();
        $this->assertEquals('Siswa A Updated', $this->siswaA->name);
        $this->assertEquals('9988776655', $this->siswaA->profile->identifier_number);
        $this->assertEquals('0899999999', $this->siswaA->profile->phone);
    }

    public function test_admin_sekolah_cannot_update_user_in_another_school(): void
    {
        $userB = User::create([
            'name' => 'Target B',
            'email' => 'target.b@smk.id',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
            'school_id' => $this->schoolB->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminA)
            ->put(route('users.update', $userB->id), [
                'name' => 'Hacked Name',
                'email' => 'target.b@smk.id',
                'role' => 'siswa',
            ]);

        $response->assertStatus(403);
        $userB->refresh();
        $this->assertEquals('Target B', $userB->name);
    }

    public function test_admin_sekolah_can_toggle_user_active_status(): void
    {
        $this->assertTrue($this->siswaA->is_active);

        // Deactivate
        $response = $this->actingAs($this->adminA)
            ->patch(route('users.toggle-status', $this->siswaA->id));

        $response->assertRedirect();
        $this->siswaA->refresh();
        $this->assertFalse($this->siswaA->is_active);

        // Logout admin so we are guest
        $this->post(route('logout'));
        $this->assertGuest();

        // Deactivated user cannot log in
        $loginResponse = $this->post(route('login.store'), [
            'email' => 'siswa.a@smk.id',
            'password' => 'Secret123456!',
        ]);
        $loginResponse->assertSessionHasErrors('email');
        $this->assertGuest();

        // Reactivate
        $this->actingAs($this->adminA)
            ->patch(route('users.toggle-status', $this->siswaA->id));

        $this->siswaA->refresh();
        $this->assertTrue($this->siswaA->is_active);

        // Logout admin so we are guest
        $this->post(route('logout'));
        $this->assertGuest();

        // Can log in again
        $loginResponse2 = $this->post(route('login.store'), [
            'email' => 'siswa.a@smk.id',
            'password' => 'Secret123456!',
        ]);
        $loginResponse2->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->siswaA);
    }

    public function test_user_cannot_deactivate_or_delete_themselves(): void
    {
        $response = $this->actingAs($this->adminA)
            ->patch(route('users.toggle-status', $this->adminA->id));

        $response->assertSessionHasErrors('user');
        $this->adminA->refresh();
        $this->assertTrue($this->adminA->is_active);

        $response2 = $this->actingAs($this->adminA)
            ->delete(route('users.destroy', $this->adminA->id));

        $response2->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $this->adminA->id]);
    }

    public function test_user_with_reports_or_audit_logs_cannot_be_hard_deleted(): void
    {
        // Add audit log for Siswa A
        AuditLog::create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->siswaA->id,
            'actor_name' => $this->siswaA->name,
            'actor_role' => $this->siswaA->role,
            'action' => 'Simulasi Aktivitas Siswa',
            'details' => 'Aktivitas LKS',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->adminA)
            ->delete(route('users.destroy', $this->siswaA->id));

        $response->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $this->siswaA->id]);
    }

    public function test_clean_orphan_user_can_be_deleted(): void
    {
        $orphan = User::create([
            'name' => 'Orphan Siswa',
            'email' => 'orphan@smk.id',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);
        UserProfile::create([
            'user_id' => $orphan->id,
            'type' => 'siswa',
        ]);

        $response = $this->actingAs($this->adminA)
            ->delete(route('users.destroy', $orphan->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $orphan->id]);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $orphan->id]);
    }

    public function test_guru_is_strictly_denied_from_user_import_and_user_creation(): void
    {
        // Critical Bug Fix verification: Guru gets 403 on import preview
        $file = UploadedFile::fake()->create('users.csv', 100);

        $responsePreview = $this->actingAs($this->guruA)
            ->postJson(route('users.import.preview'), [
                'file' => $file,
            ]);
        $responsePreview->assertStatus(403);

        // Guru gets 403 on import execute
        $responseExecute = $this->actingAs($this->guruA)
            ->postJson(route('users.import.execute'), [
                'rows' => [
                    [
                        'name' => 'Test',
                        'email' => 'test@smk.id',
                        'role' => 'siswa',
                    ],
                ],
            ]);
        $responseExecute->assertStatus(403);

        // Guru gets 403 on user creation
        $responseStore = $this->actingAs($this->guruA)
            ->post(route('users.store'), [
                'name' => 'Hacker Student',
                'email' => 'hacker@smk.id',
                'role' => 'siswa',
            ]);
        $responseStore->assertStatus(403);
    }

    public function test_siswa_is_strictly_denied_from_user_management(): void
    {
        $response = $this->actingAs($this->siswaA)
            ->post(route('users.store'), [
                'name' => 'Unauthorized User',
                'email' => 'unauth@smk.id',
                'role' => 'siswa',
            ]);
        $response->assertStatus(403);

        $responseDir = $this->actingAs($this->siswaA)
            ->get(route('users.index'));
        $responseDir->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login_for_user_directory(): void
    {
        $response = $this->get(route('users.index'));
        $response->assertRedirect(route('login'));
    }
}
