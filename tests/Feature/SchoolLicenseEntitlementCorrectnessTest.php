<?php

namespace Tests\Feature;

use App\Actions\License\ActivateFirstYearLicenseAction;
use App\Actions\License\InitializeSchoolLicenseAction;
use App\Actions\License\RenewSchoolLicenseAction;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseTerm;
use App\Models\User;
use App\Services\License\SchoolLicenseResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolLicenseEntitlementCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    protected School $schoolA;

    protected School $schoolB;

    protected User $superuser;

    protected User $adminSekolahA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::factory()->create([
            'name' => 'SMK Pertanian Negeri 1 Sukaraja',
            'npsn' => '20201001',
            'status' => 'active',
        ]);

        $this->schoolB = School::factory()->create([
            'name' => 'SMK Swasta 2 Berdikari',
            'npsn' => '20201002',
            'status' => 'active',
        ]);

        $this->superuser = User::factory()->create([
            'name' => 'Super Administrator Bitanic',
            'email' => 'superuser@bitanic.test',
            'role' => 'superuser',
            'school_id' => null,
            'is_active' => true,
        ]);

        $this->adminSekolahA = User::factory()->create([
            'name' => 'Admin Sekolah A',
            'email' => 'admin.a@bitanic.test',
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);
    }

    public function test_active_term_evaluates_to_active_with_accurate_days_remaining(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $resolver = app(SchoolLicenseResolver::class);

        $license = $initAction->execute($this->schoolA);
        $license = $actAction->execute($license, ['start_date' => '2026-01-01']); // exp: 2027-01-01
        $license->refresh();

        $evalDate = Carbon::parse('2026-06-01');
        $resolved = $resolver->resolve($license, $evalDate);

        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $resolved['status']);
        $this->assertTrue($resolved['is_active']);
        $this->assertNotNull($resolved['active_term']);
        $this->assertEquals(1, $resolved['active_term']->term_number);
        // Days remaining to 2027-01-01 from 2026-06-01 (214 days)
        $this->assertEquals(214, $resolved['days_remaining']);
    }

    public function test_gap_between_expired_term_and_future_renewal_evaluates_to_expired(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $resolver = app(SchoolLicenseResolver::class);

        $license = $initAction->execute($this->schoolA);
        // Term 1: 2026-01-01 to 2027-01-01
        $license = $actAction->execute($license, ['start_date' => '2026-01-01']);

        // Create Term 2 manually with a gap: starts 2027-02-01 to 2028-02-01
        // Gap is 2027-01-01 to 2027-02-01 (1 month unentitled)
        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 2,
            'term_type' => SchoolLicenseTerm::TYPE_ANNUAL_RENEWAL,
            'start_date' => '2027-02-01',
            'expiration_date' => '2028-02-01',
            'status' => 'pending',
            'authorized_by' => $this->superuser->id,
            'notes' => 'Perpanjangan efektif bulan depan',
            'created_at' => now(),
        ]);

        $license->expiration_date = '2028-02-01';
        $license->save();
        $license->refresh();

        // 1. During Term 1 (2026-11-12) -> ACTIVE
        $resolvedDuringT1 = $resolver->resolve($license, Carbon::parse('2026-11-12'));
        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $resolvedDuringT1['status']);
        $this->assertTrue($resolvedDuringT1['is_active']);

        // 2. In the gap (2027-01-15) -> EXPIRED (even though aggregate expiration_date is 2028-02-01!)
        $resolvedGap = $resolver->resolve($license, Carbon::parse('2027-01-15'));
        $this->assertEquals(SchoolLicense::STATUS_EXPIRED, $resolvedGap['status']);
        $this->assertFalse($resolvedGap['is_active']);
        $this->assertNull($resolvedGap['active_term']);
        $this->assertEquals(0, $resolvedGap['days_remaining']);

        // 3. When Term 2 starts (2027-02-01) -> ACTIVE under Term 2
        $resolvedT2 = $resolver->resolve($license, Carbon::parse('2027-02-01'));
        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $resolvedT2['status']);
        $this->assertTrue($resolvedT2['is_active']);
        $this->assertNotNull($resolvedT2['active_term']);
        $this->assertEquals(2, $resolvedT2['active_term']->term_number);
    }

    public function test_continuous_chained_renewal_extends_coverage_without_gap(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $renewAction = app(RenewSchoolLicenseAction::class);
        $resolver = app(SchoolLicenseResolver::class);

        $license = $initAction->execute($this->schoolA);
        // Term 1: 2026-01-01 to 2027-01-01
        $license = $actAction->execute($license, ['start_date' => '2026-01-01']);

        // Term 2: 2027-01-01 to 2028-01-01 (seamlessly chained)
        $license = $renewAction->execute($license, ['notes' => 'Chained renewal']);
        $license->refresh();

        // On 2026-12-01 (during Term 1) -> ACTIVE, continuous expiration extends to 2028-01-01
        $evalDate = Carbon::parse('2026-12-01');
        $resolved = $resolver->resolve($license, $evalDate);

        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $resolved['status']);
        $this->assertTrue($resolved['is_active']);
        $this->assertEquals('2028-01-01', $resolved['continuous_expiration_date']->toDateString());
        // Days remaining to 2028-01-01 (396 days)
        $this->assertEquals(396, $resolved['days_remaining']);

        // On exact boundary 2027-01-01 -> ACTIVE under Term 2
        $boundaryResolved = $resolver->resolve($license, Carbon::parse('2027-01-01'));
        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $boundaryResolved['status']);
        $this->assertEquals(2, $boundaryResolved['active_term']->term_number);
    }

    public function test_leap_year_boundary_and_half_open_interval(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $resolver = app(SchoolLicenseResolver::class);

        $license = $initAction->execute($this->schoolA);
        // Leap year start: 2024-02-29 -> expires: 2025-02-28
        $license = $actAction->execute($license, ['start_date' => '2024-02-29']);
        $license->refresh();

        $this->assertEquals('2025-02-28', $license->expiration_date->toDateString());

        // 1. Day of start (2024-02-29) -> ACTIVE
        $this->assertTrue($resolver->isActive($license, Carbon::parse('2024-02-29')));

        // 2. Day before expiration (2025-02-27) -> ACTIVE
        $this->assertTrue($resolver->isActive($license, Carbon::parse('2025-02-27')));

        // 3. Exact expiration date (2025-02-28) -> EXPIRED (half-open [start, exp) convention)
        $this->assertFalse($resolver->isActive($license, Carbon::parse('2025-02-28')));
        $this->assertEquals(SchoolLicense::STATUS_EXPIRED, $resolver->resolveStatus($license, Carbon::parse('2025-02-28')));
    }

    public function test_superuser_access_to_school_license_without_school_id_redirects_to_master_registry(): void
    {
        // Platform Superuser has school_id = null
        $this->assertNull($this->superuser->school_id);

        $response = $this->actingAs($this->superuser)->get(route('school.license.show'));

        // Must redirect to global registry, NOT fall back to School::first()
        $response->assertRedirect(route('master.licenses.index'));
    }

    public function test_superuser_access_to_school_license_with_explicit_school_id_succeeds(): void
    {
        $response = $this->actingAs($this->superuser)->get(route('school.license.show', ['school_id' => $this->schoolA->id]));

        $response->assertOk();
        $response->assertSee($this->schoolA->name);
    }
}
