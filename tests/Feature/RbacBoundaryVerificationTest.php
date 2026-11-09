<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacBoundaryVerificationTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $superuser;

    private User $adminA;

    private User $teacherA;

    private User $studentA;

    private Device $deviceA;

    private Device $deviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create([
            'npsn' => '20202001',
            'name' => 'SMK Pertanian A (Rbac Verification)',
            'slug' => 'smk-pertanian-a-rbac',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'npsn' => '20202002',
            'name' => 'SMK Pertanian B (Rbac Verification)',
            'slug' => 'smk-pertanian-b-rbac',
            'status' => 'active',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Superuser Global',
            'email' => 'su.rbac@bitanic.test',
        ]);

        $this->adminA = User::factory()->create([
            'role' => 'admin',
            'school_id' => $this->schoolA->id,
            'name' => 'Admin Sekolah A',
            'email' => 'admin.rbac.a@bitanic.test',
        ]);

        $this->teacherA = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'name' => 'Guru Sekolah A',
            'email' => 'guru.rbac.a@bitanic.test',
        ]);

        $this->studentA = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'name' => 'Siswa Sekolah A',
            'email' => 'siswa.rbac.a@bitanic.test',
        ]);

        $this->deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-RBAC-A1',
            'name' => 'Kit Lab A1',
            'is_active' => true,
        ]);

        $this->deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-RBAC-B1',
            'name' => 'Kit Lab B1',
            'is_active' => true,
        ]);
    }

    /**
     * AUDIT CHECK 1: EnsureUserHasRole middleware privilege inheritance.
     * Verified Fact: The middleware explicitly treats 'admin' as an allowed role on 'role:superuser' routes.
     */
    public function test_ensure_user_has_role_allows_admin_on_superuser_routes(): void
    {
        // 1. Settings route (middleware: ['auth', 'role:superuser'])
        $this->actingAs($this->adminA)->get(route('settings.index'))->assertOk();

        // 2. Settings update (middleware: ['auth', 'role:superuser'])
        $this->actingAs($this->adminA)->post(route('settings.update'), [
            'school_name' => $this->schoolA->name,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru Pengampu',
            'thresholds' => [
                'minMoisture' => 40,
                'maxMoisture' => 80,
                'minPH' => 6.0,
                'maxPH' => 7.0,
                'minEC' => 1000,
                'maxEC' => 2000,
                'minNitrogen' => 50,
                'maxNitrogen' => 150,
            ],
        ])->assertRedirect(route('settings.index'));

        // 3. Raw logs manual frame injection (middleware: ['auth', 'role:superuser'])
        $this->actingAs($this->adminA)->post(route('rawlogs.inject'), [
            'kit_code' => $this->deviceA->code,
            'packet_hex' => '010300000002C40B',
            'level' => 'INFO',
        ])->assertRedirect(route('rawlogs.index'));
    }

    /**
     * AUDIT CHECK 2: API Key Rotation Boundary.
     * Verified Fact on Current Implementation:
     * - In User model: isSuperuser() returns true for both 'superuser' and 'admin'.
     * - In TrainerKitManagerController@index: $canRotateApiKey is (bool) $user?->isSuperuser(), which evaluates to TRUE for admin.
     * - In Backend Route: The route middleware is 'role:superuser', allowing 'admin' via EnsureUserHasRole line 32.
     * - In Controller (rotateApiKey): It checks $device->school_id === $user->school_id.
     *   Therefore, Admin A CAN rotate keys for devices in School A, but is REJECTED with 403 for School B.
     */
    public function test_api_key_rotation_rbac_boundary_and_tenant_isolation(): void
    {
        // UI layer: Current code grants rotation button to admin because isSuperuser() includes 'admin'
        $indexResponse = $this->actingAs($this->adminA)->get(route('kits.index'));
        $indexResponse->assertOk();
        $this->assertTrue($indexResponse->viewData('canRotateApiKey'), 'Current implementation treats admin as superuser alias in isSuperuser()');

        // Backend layer for Own School device: Allowed by EnsureUserHasRole legacy mapping and matching school_id
        $rotateOwnResponse = $this->actingAs($this->adminA)->post(route('kits.api-key.rotate', $this->deviceA));
        $rotateOwnResponse->assertRedirect(route('kits.index'));
        $rotateOwnResponse->assertSessionHas('new_api_key');

        // Backend layer for Cross School device: Strictly REJECTED at controller tenant check (403)
        $rotateCrossResponse = $this->actingAs($this->adminA)->post(route('kits.api-key.rotate', $this->deviceB));
        $rotateCrossResponse->assertForbidden();
    }

    /**
     * AUDIT CHECK 3: Multi-School Query Scoping for Legacy Admin.
     * Verified Architectural Gap:
     * Because User::isSuperuser() returns true for 'admin', the controllers that check $user->isSuperuser()
     * allow the legacy admin to pass ?school_id= to filter across schools, behaving like a Superuser.
     * This violates the locked Architecture Baseline v1.0 (where Admin Sekolah must be strictly tenant-scoped).
     */
    public function test_legacy_admin_platform_query_behavior_audit(): void
    {
        // 1. User::isSuperuser() returns true for role 'admin'
        $this->assertTrue($this->adminA->isSuperuser(), 'User::isSuperuser() currently aliases admin to superuser');

        // 2. Dashboard: Because isSuperuser() is true, admin can query school B
        $dashboardResponse = $this->actingAs($this->adminA)->get('/?school_id='.$this->schoolB->id);
        $dashboardResponse->assertOk();
        $this->assertEquals($this->schoolB->id, $dashboardResponse->viewData('selectedSchoolId'));

        // 3. User Directory: Admin can view school B users when passing ?school_id=
        $dirResponse = $this->actingAs($this->adminA)->get('/users-data?school_id='.$this->schoolB->id);
        $dirResponse->assertOk();
        $this->assertEquals($this->schoolB->id, $dirResponse->viewData('selectedSchoolId'));

        // 4. Competency Assessment: Admin can filter by school B
        $assessmentResponse = $this->actingAs($this->adminA)->get('/assessment?school_id='.$this->schoolB->id);
        $assessmentResponse->assertOk();
        $this->assertEquals($this->schoolB->id, $assessmentResponse->viewData('selectedSchoolId'));

        // 5. Trainer Kits: Admin can filter by school B
        $kitsResponse = $this->actingAs($this->adminA)->get('/trainer-kits?school_id='.$this->schoolB->id);
        $kitsResponse->assertOk();
        $this->assertEquals($this->schoolB->id, $kitsResponse->viewData('selectedSchoolId'));
    }

    /**
     * AUDIT CHECK 4: Cross-School Mutation Protection (IDOR) for Legacy Admin.
     * Verified Fact: Admin A cannot mutate any resource belonging to School B.
     */
    public function test_legacy_admin_cannot_mutate_cross_school_resources(): void
    {
        // 1. Cannot reboot School B kit
        $this->actingAs($this->adminA)->post(route('kits.reboot', $this->deviceB->id))->assertForbidden();

        // 2. Cannot calibrate School B kit
        $this->actingAs($this->adminA)->post(route('kits.calibration', $this->deviceB->id), [
            'phOffset' => 0.1,
            'moistureOffset' => 0.2,
            'ecOffset' => 10,
        ])->assertForbidden();

        // 3. Teacher and Student cannot access settings under any circumstances
        $this->actingAs($this->teacherA)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->studentA)->get(route('settings.index'))->assertForbidden();
    }
}
