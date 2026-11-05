<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RouteHealthAndSecurityMatrixTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $student;

    private User $teacher;

    private User $superuser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'npsn' => '20200888',
            'name' => 'SMK Pertanian Matrix Test',
            'slug' => 'smk-pertanian-matrix-test',
            'status' => 'active',
        ]);

        $this->student = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->school->id,
            'name' => 'Siswa Matrix',
        ]);

        $this->teacher = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->school->id,
            'name' => 'Guru Matrix',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->school->id,
            'name' => 'Superuser Matrix',
        ]);

        Device::create([
            'school_id' => $this->school->id,
            'code' => 'TK-MATRIX-01',
            'name' => 'Meja Matrix 01',
            'is_active' => true,
        ]);
    }

    public function test_guest_public_routes_return_ok(): void
    {
        $publicRoutes = [
            '/',
            '/iot-monitoring',
            '/analytics',
            '/telemetry/history',
            '/modules',
            '/schedules',
            '/trainer-kits',
            '/raw-logs',
            '/login',
        ];

        foreach ($publicRoutes as $uri) {
            $response = $this->get($uri);
            $response->assertOk("Route {$uri} should be accessible to guests");
        }
    }

    public function test_guest_protected_routes_redirect_to_login(): void
    {
        $protectedRoutes = [
            '/reports',
            '/assessment',
            '/assessment/export',
            '/users-data',
            '/classes',
            '/settings',
        ];

        foreach ($protectedRoutes as $uri) {
            $response = $this->get($uri);
            $response->assertRedirect('/login', "Route {$uri} should redirect guests to /login");
        }
    }

    public function test_student_role_authorization_matrix(): void
    {
        // Students have access to student-scoped views
        $this->actingAs($this->student)->get('/reports')->assertOk();
        $this->actingAs($this->student)->get('/assessment')->assertOk();
        $this->actingAs($this->student)->get('/assessment/export')->assertOk();

        // Students are strictly blocked from educator and admin interfaces
        $this->actingAs($this->student)->get('/users-data')->assertForbidden();
        $this->actingAs($this->student)->get('/classes')->assertForbidden();
        $this->actingAs($this->student)->get('/settings')->assertForbidden();
        $this->actingAs($this->student)->post('/schedules', [])->assertForbidden();
        $this->actingAs($this->student)->post('/trainer-kits/1/reboot')->assertForbidden();
        $this->actingAs($this->student)->post('/settings/school', [])->assertForbidden();
        $this->actingAs($this->student)->post('/raw-logs/inject', [])->assertForbidden();
        $this->actingAs($this->student)->post('/trainer-kits/1/api-key/rotate')->assertForbidden();
    }

    public function test_teacher_role_authorization_matrix(): void
    {
        // Teachers have access to educational and academic management
        $this->actingAs($this->teacher)->get('/reports')->assertOk();
        $this->actingAs($this->teacher)->get('/assessment')->assertOk();
        $this->actingAs($this->teacher)->get('/assessment/export')->assertOk();
        $this->actingAs($this->teacher)->get('/users-data')->assertOk();
        $this->actingAs($this->teacher)->get('/classes')->assertOk();

        // Teachers are blocked from superuser-only mutation endpoints
        $this->actingAs($this->teacher)->get('/settings')->assertForbidden();
        $this->actingAs($this->teacher)->post('/settings/school', [])->assertForbidden();
        $this->actingAs($this->teacher)->post('/raw-logs/inject', [])->assertForbidden();
        $this->actingAs($this->teacher)->post('/trainer-kits/1/api-key/rotate')->assertForbidden();
    }

    public function test_superuser_role_authorization_matrix(): void
    {
        // Superusers have access to all routes including settings
        $this->actingAs($this->superuser)->get('/reports')->assertOk();
        $this->actingAs($this->superuser)->get('/assessment')->assertOk();
        $this->actingAs($this->superuser)->get('/assessment/export')->assertOk();
        $this->actingAs($this->superuser)->get('/users-data')->assertOk();
        $this->actingAs($this->superuser)->get('/classes')->assertOk();
        $this->actingAs($this->superuser)->get('/settings')->assertOk();
    }

    public function test_api_endpoints_security_matrix(): void
    {
        Auth::logout();

        // Unauthenticated device telemetry ingest is rejected
        $this->postJson('/api/v1/telemetry/ingest', [])->assertUnauthorized();
        $this->postJson('/api/rsc-data', [])->assertUnauthorized();

        // Relay toggle requires authenticated educator/superuser
        $this->post('/api/v1/devices/relay/toggle', ['device_id' => 1, 'relay' => 'relay1', 'state' => true])
            ->assertRedirect('/login');

        $this->actingAs($this->student)
            ->post('/api/v1/devices/relay/toggle', ['device_id' => 1, 'relay' => 'relay1', 'state' => true])
            ->assertForbidden();

        Auth::logout();

        // AI recommendation endpoints require authentication
        $this->postJson('/api/v1/recommendations/request', [])->assertUnauthorized();
        $this->getJson('/api/v1/recommendations/status/non-existent-ticket')->assertUnauthorized();
    }

    public function test_retired_legacy_subscription_routes_return_404(): void
    {
        $this->get('/subscription')->assertNotFound();
        $this->post('/subscription/checkout', [])->assertNotFound();
    }

    public function test_test_environment_is_isolated_from_production(): void
    {
        $connection = DB::getDefaultConnection();
        $databaseName = Config::get("database.connections.{$connection}.database");

        // Asserts test suite runs in an isolated SQLite environment or explicit test database
        $this->assertContains($connection, ['sqlite', 'pgsql_testing', 'testing']);
        $this->assertNotEquals('bitanic_production', $databaseName);
    }

    public function test_baseline_assets_remain_unmodified_and_intact(): void
    {
        $rootDir = dirname(base_path());
        $dumpSql = $rootDir.DIRECTORY_SEPARATOR.'dump_upiedge.sql';
        $upiEdgeDir = $rootDir.DIRECTORY_SEPARATOR.'upi-edge';

        $this->assertFileExists($dumpSql, 'dump_upiedge.sql baseline asset must exist');
        $this->assertDirectoryExists($upiEdgeDir, 'upi-edge baseline directory must exist');
        $this->assertGreaterThan(200000, filesize($dumpSql), 'dump_upiedge.sql must preserve complete historic dump size');
    }
}
