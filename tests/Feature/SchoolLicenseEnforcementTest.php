<?php

namespace Tests\Feature;

use App\Actions\License\RenewSchoolLicenseAction;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseTerm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolLicenseEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_license_enforcement_disabled_by_default_permits_all_operations(): void
    {
        $this->assertFalse(config('license.enforcement_enabled'));

        $school = School::create([
            'npsn' => '88000001',
            'name' => 'SMK Pertanian Cibadak',
            'slug' => 'smk-pertanian-cibadak',
            'status' => 'active',
        ]);

        $guru = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        // License has expired
        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-EX01',
            'status' => SchoolLicense::STATUS_EXPIRED,
            'start_date' => '2025-01-01',
            'expiration_date' => '2026-01-01',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-01-01',
            'expiration_date' => '2026-01-01',
            'status' => 'expired',
        ]);

        // When enforcement is disabled (default), guru can still navigate
        $response = $this->actingAs($guru)->get('/schedules');
        $response->assertStatus(200);
    }

    public function test_enforcement_enabled_allows_superuser_everywhere(): void
    {
        config(['license.enforcement_enabled' => true]);

        $superuser = User::factory()->create([
            'school_id' => null,
            'role' => User::ROLE_SUPERUSER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($superuser)->get('/master/licenses');
        $response->assertStatus(200);

        $response = $this->actingAs($superuser)->get('/modules');
        $response->assertStatus(200);
    }

    public function test_enforcement_enabled_allows_unauthenticated_and_login_routes(): void
    {
        config(['license.enforcement_enabled' => true]);

        $response = $this->get('/login');
        $response->assertStatus(200);

        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_enforcement_enabled_redirects_guru_and_siswa_to_expired_notice(): void
    {
        config(['license.enforcement_enabled' => true]);

        $school = School::create([
            'npsn' => '88000002',
            'name' => 'SMK Pertanian Kadipaten',
            'slug' => 'smk-pertanian-kadipaten',
            'status' => 'active',
        ]);

        $guru = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $siswa = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_SISWA,
            'is_active' => true,
        ]);

        // License expired 1 month ago (2026-05-01)
        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-EX02',
            'status' => SchoolLicense::STATUS_EXPIRED,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
            'status' => 'expired',
        ]);

        // Guru attempting to access schedules is redirected to expired notice
        $responseGuru = $this->actingAs($guru)->get('/schedules');
        $responseGuru->assertRedirect(route('school.license.expired'));

        // Siswa attempting to access reports is redirected to expired notice
        $responseSiswa = $this->actingAs($siswa)->get('/reports');
        $responseSiswa->assertRedirect(route('school.license.expired'));

        // Viewing expired notice screen directly succeeds
        $noticeResponse = $this->actingAs($siswa)->get('/school/license-expired');
        $noticeResponse->assertStatus(200);
        $noticeResponse->assertSee('Masa Berlaku Lisensi Berakhir');
        $noticeResponse->assertSee('SMK Pertanian Kadipaten');
        $noticeResponse->assertSee('LIC-SMK-2026-EX02');
    }

    public function test_enforcement_enabled_allows_school_admin_to_view_license_but_blocks_lms_mutations(): void
    {
        config(['license.enforcement_enabled' => true]);

        $school = School::create([
            'npsn' => '88000003',
            'name' => 'SMK Pertanian Indramayu',
            'slug' => 'smk-pertanian-indramayu',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'is_active' => true,
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-EX03',
            'status' => SchoolLicense::STATUS_EXPIRED,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
            'status' => 'expired',
        ]);

        // Admin can view own school license page
        $licenseView = $this->actingAs($admin)->get('/school/license');
        $licenseView->assertStatus(200);
        $licenseView->assertSee('Masa Berlaku Lisensi Telah Berakhir');

        // Admin can view settings index
        $settingsView = $this->actingAs($admin)->get('/settings');
        $settingsView->assertStatus(200);

        // Admin accessing other areas like user directory gets redirected to license page with warning
        $userDir = $this->actingAs($admin)->get('/users-data');
        $userDir->assertRedirect(route('school.license.show'));
        $userDir->assertSessionHas('error');

        // Admin attempting to create user is blocked and redirected
        $userCreate = $this->actingAs($admin)->post('/users', [
            'name' => 'New Teacher',
            'email' => 'new@teacher.com',
            'role' => 'guru',
        ]);
        $userCreate->assertRedirect(route('school.license.show'));
        $userCreate->assertSessionHas('error');
    }

    public function test_json_requests_from_expired_school_receive_403_forbidden(): void
    {
        config(['license.enforcement_enabled' => true]);

        $school = School::create([
            'npsn' => '88000004',
            'name' => 'SMK Pertanian Tasikmalaya',
            'slug' => 'smk-pertanian-tasikmalaya',
            'status' => 'active',
        ]);

        $guru = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-EX04',
            'status' => SchoolLicense::STATUS_EXPIRED,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
            'status' => 'expired',
        ]);

        $response = $this->actingAs($guru)->getJson('/schedules');
        $response->assertStatus(403);
        $response->assertJson([
            'license_status' => 'expired',
        ]);
    }

    public function test_device_telemetry_api_ingress_is_not_blocked_by_license_enforcement(): void
    {
        config(['license.enforcement_enabled' => true]);

        $school = School::create([
            'npsn' => '88000005',
            'name' => 'SMK Ciamis',
            'slug' => 'smk-ciamis',
            'status' => 'active',
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-EX05',
            'status' => SchoolLicense::STATUS_EXPIRED,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
            'status' => 'expired',
        ]);

        $device = Device::create([
            'school_id' => $school->id,
            'code' => 'BITANIC-TK-99',
            'name' => 'Meja IoT Praktik 99',
            'status' => 'active',
            'is_active' => true,
        ]);

        $apiKey = 'bitanic_test_key_'.bin2hex(random_bytes(16));
        DeviceApiKey::create([
            'device_id' => $device->id,
            'key_hash' => hash('sha256', $apiKey),
            'last_used_at' => null,
            'is_revoked' => false,
        ]);

        // Device POST to API ingress must succeed
        $response = $this->withHeaders([
            'X-Device-Key' => $apiKey,
        ])->postJson('/api/v1/telemetry', [
            'device_id' => 'BITANIC-TK-99',
            'timestamp' => now()->toIso8601String(),
            'sensors' => [
                'nitrogen' => 45.2,
                'phosphorus' => 28.1,
                'potassium' => 120.5,
                'ph' => 6.8,
                'temperature' => 27.5,
                'humidity' => 65.0,
                'ec' => 1.4,
            ],
            'actuators' => [
                'relay_1' => 0,
                'relay_2' => 0,
                'relay_3' => 0,
                'relay_4' => 0,
            ],
        ]);

        // API ingress must not return 403 license expired
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_superuser_renewal_immediately_restores_tenant_access_without_data_reset(): void
    {
        config(['license.enforcement_enabled' => true]);

        $superuser = User::factory()->create([
            'school_id' => null,
            'role' => User::ROLE_SUPERUSER,
            'is_active' => true,
        ]);

        $school = School::create([
            'npsn' => '88000006',
            'name' => 'SMK Garut',
            'slug' => 'smk-garut',
            'status' => 'active',
        ]);

        $siswa = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_SISWA,
            'is_active' => true,
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-EX06',
            'status' => SchoolLicense::STATUS_EXPIRED,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-05-01',
            'expiration_date' => '2026-05-01',
            'status' => 'expired',
        ]);

        // Before renewal: Siswa is blocked
        $beforeResponse = $this->actingAs($siswa)->get('/reports');
        $beforeResponse->assertRedirect(route('school.license.expired'));

        // Superuser executes annual renewal for 1 year
        $renewAction = app(RenewSchoolLicenseAction::class);
        $renewAction->execute($license, [
            'duration_years' => 1,
            'reason' => 'Perpanjangan lisensi tahunan kemitraan SMK Garut',
            'notes' => 'Otorisasi resmi SPK-2026-GARUT',
            'authorized_by' => $superuser->id,
        ]);

        $license->refresh();
        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $license->computed_status);

        // Immediately on the next request, Siswa access is restored!
        $afterResponse = $this->actingAs($siswa)->get('/reports');
        $afterResponse->assertStatus(200);
    }
}
