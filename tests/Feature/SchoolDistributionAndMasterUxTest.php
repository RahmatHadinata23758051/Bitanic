<?php

namespace Tests\Feature;

use App\Actions\License\ActivateFirstYearLicenseAction;
use App\Actions\License\InitializeSchoolLicenseAction;
use App\Models\Device;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseTerm;
use App\Models\TrainerKitInventory;
use App\Models\User;
use App\Services\License\SchoolLicenseResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolDistributionAndMasterUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $superuser;

    protected Province $provinceJabar;

    protected Regency $regencySukabumi;

    protected District $districtCikembar;

    protected School $schoolA;

    protected School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provinceJabar = Province::firstOrCreate(
            ['code' => '32'],
            ['name' => 'JAWA BARAT']
        );

        $this->regencySukabumi = Regency::firstOrCreate(
            ['code' => '3202'],
            ['province_code' => '32', 'name' => 'KABUPATEN SUKABUMI']
        );

        $this->districtCikembar = District::firstOrCreate(
            ['code' => '320201'],
            ['regency_code' => '3202', 'name' => 'CIKEMBAR']
        );

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => null,
            'is_active' => true,
        ]);

        $this->schoolA = School::factory()->create([
            'name' => 'SMKN 1 Sukaraja GIS',
            'slug' => 'smkn-1-sukaraja-gis',
            'npsn' => '88000001',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '320201',
            'status' => 'active',
            'latitude' => -6.917464,
            'longitude' => 107.619123,
            'coordinate_source' => 'manual_input',
            'coordinate_verified_at' => now(),
            'coordinate_verified_by' => $this->superuser->id,
        ]);

        $this->schoolB = School::factory()->create([
            'name' => 'SMKN 2 Cikembar Unmapped',
            'slug' => 'smkn-2-cikembar-unmapped',
            'npsn' => '88000002',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '320201',
            'status' => 'active',
            'latitude' => null,
            'longitude' => null,
            'coordinate_verified_at' => null,
            'coordinate_verified_by' => null,
        ]);
    }

    public function test_superuser_can_view_partner_school_distribution_dashboard(): void
    {
        // Give School A an active license
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $licA = $initAction->execute($this->schoolA);
        $actAction->execute($licA, ['start_date' => Carbon::today()->subMonths(2)->toDateString()]);

        $response = $this->actingAs($this->superuser)->get(route('master.regions.index'));

        $response->assertOk();
        $response->assertSee('Persebaran Sekolah Mitra BITANIC');
        $response->assertSee('Total Sekolah Mitra');
        $response->assertSee('id="school-distribution-map"', false);
        $response->assertSee($this->schoolA->name);
    }

    public function test_partner_school_distribution_filters_by_license_status(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);

        // School A: Active
        $licA = $initAction->execute($this->schoolA);
        $actAction->execute($licA, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        // School B: Pending (future)
        $licB = $initAction->execute($this->schoolB);
        $actAction->execute($licB, ['start_date' => Carbon::today()->addMonths(1)->toDateString()]);

        // Filter active
        $resActive = $this->actingAs($this->superuser)->get(route('master.regions.index', ['status' => 'active']));
        $resActive->assertOk();
        $resActive->assertSee($this->schoolA->name);
        $resActive->assertDontSee($this->schoolB->name);

        // Filter pending
        $resPending = $this->actingAs($this->superuser)->get(route('master.regions.index', ['status' => 'pending']));
        $resPending->assertOk();
        $resPending->assertSee($this->schoolB->name);
        $resPending->assertDontSee($this->schoolA->name);
    }

    public function test_school_with_coordinates_included_in_map_markers_and_unmapped_displayed_honestly(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);

        // School A has verified coordinates, School B does not
        $licA = $initAction->execute($this->schoolA);
        $actAction->execute($licA, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        $licB = $initAction->execute($this->schoolB);
        $actAction->execute($licB, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        $response = $this->actingAs($this->superuser)->get(route('master.regions.index'));
        $response->assertOk();

        // Markers data contains School A coordinates
        $response->assertSee('-6.917464');
        $response->assertSee('107.619123');

        // School B is rendered with honest missing coordinate indicator
        $response->assertSee('Lokasi belum dicatat');
    }

    public function test_unverified_coordinates_excluded_from_map_and_reported_honestly(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);

        // School C: Has coordinates recorded (-6.500000, 107.100000), but coordinate_verified_at is NULL
        $schoolC = School::factory()->create([
            'name' => 'SMKN 3 Pelabuhan Unverified',
            'slug' => 'smkn-3-pelabuhan-unverified',
            'npsn' => '88000003',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '320201',
            'status' => 'active',
            'latitude' => -6.500000,
            'longitude' => 107.100000,
            'coordinate_source' => 'manual_input',
            'coordinate_verified_at' => null,
            'coordinate_verified_by' => null,
        ]);

        $licA = $initAction->execute($this->schoolA);
        $actAction->execute($licA, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        $licC = $initAction->execute($schoolC);
        $actAction->execute($licC, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        $response = $this->actingAs($this->superuser)->get(route('master.regions.index'));
        $response->assertOk();

        // Verified School A appears in Leaflet map markers
        $response->assertSee($this->schoolA->name);
        $response->assertSee('-6.917464');
        $response->assertSee('Terverifikasi');

        // Unverified School C appears in Directory with 'Menunggu Verifikasi', but NOT in map markers
        $response->assertSee($schoolC->name);
        $response->assertSee('Menunggu Verifikasi');
        $response->assertSee('1 sekolah mitra');
        $response->assertSee('belum memiliki koordinat geografis terverifikasi');
    }

    public function test_geographic_drilldown_filters_schools_and_markers_linked_hierarchy(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);

        // Create Regency Bandung & District Coblong
        $regencyBandung = Regency::firstOrCreate(
            ['code' => '3273'],
            ['province_code' => '32', 'name' => 'KOTA BANDUNG']
        );
        $districtCoblong = District::firstOrCreate(
            ['code' => '327301'],
            ['regency_code' => '3273', 'name' => 'COBLONG']
        );

        // School A is in Sukabumi (3202)
        $licA = $initAction->execute($this->schoolA);
        $actAction->execute($licA, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        // School D is in Kota Bandung (3273)
        $schoolD = School::factory()->create([
            'name' => 'SMKN 7 Bandung Mitra',
            'slug' => 'smkn-7-bandung-mitra',
            'npsn' => '88000004',
            'province_code' => '32',
            'regency_code' => '3273',
            'district_code' => '327301',
            'status' => 'active',
            'latitude' => -6.890000,
            'longitude' => 107.610000,
            'coordinate_verified_at' => now(),
            'coordinate_verified_by' => $this->superuser->id,
        ]);
        $licD = $initAction->execute($schoolD);
        $actAction->execute($licD, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        // 1. Unfiltered: Both schools appear in directory table
        $resAll = $this->actingAs($this->superuser)->get(route('master.regions.index'));
        $resAll->assertOk();
        $resAll->assertSee('id="school-row-'.$this->schoolA->id.'"', false);
        $resAll->assertSee('id="school-row-'.$schoolD->id.'"', false);

        // 2. Filter by Sukabumi: Only School A appears in directory table, School D excluded
        $resSukabumi = $this->actingAs($this->superuser)->get(route('master.regions.index', [
            'province_code' => '32',
            'regency_code' => '3202',
        ]));
        $resSukabumi->assertOk();
        $resSukabumi->assertSee('id="school-row-'.$this->schoolA->id.'"', false);
        $resSukabumi->assertDontSee('id="school-row-'.$schoolD->id.'"', false);
        $resSukabumi->assertSee('KABUPATEN SUKABUMI');
        $resSukabumi->assertSee('Tampilkan Seluruh Wilayah');

        // 3. Filter by Bandung: Only School D appears in directory table, School A excluded
        $resBandung = $this->actingAs($this->superuser)->get(route('master.regions.index', [
            'province_code' => '32',
            'regency_code' => '3273',
        ]));
        $resBandung->assertOk();
        $resBandung->assertSee('id="school-row-'.$schoolD->id.'"', false);
        $resBandung->assertDontSee('id="school-row-'.$this->schoolA->id.'"', false);
        $resBandung->assertSee('KOTA BANDUNG');
    }

    public function test_superuser_can_verify_and_unverify_school_coordinates(): void
    {
        $school = School::factory()->create([
            'name' => 'SMKN 4 Cisaat Geo',
            'slug' => 'smkn-4-cisaat-geo',
            'npsn' => '88000005',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '320201',
            'status' => 'active',
            'latitude' => -6.920000,
            'longitude' => 106.910000,
            'coordinate_source' => 'manual_input',
            'coordinate_verified_at' => null,
            'coordinate_verified_by' => null,
        ]);

        $this->assertFalse($school->hasVerifiedCoordinates());

        // 1. Superuser verifies coordinates
        $resVerify = $this->actingAs($this->superuser)->post(route('master.schools.verify-coordinates', $school));
        $resVerify->assertRedirect();
        $resVerify->assertSessionHas('success');

        $school->refresh();
        $this->assertTrue($school->hasVerifiedCoordinates());
        $this->assertNotNull($school->coordinate_verified_at);
        $this->assertEquals($this->superuser->id, $school->coordinate_verified_by);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'school.coordinates_verified',
            'user_id' => $this->superuser->id,
            'school_id' => $school->id,
        ]);

        // 2. Superuser unverifies coordinates
        $resUnverify = $this->actingAs($this->superuser)->post(route('master.schools.unverify-coordinates', $school));
        $resUnverify->assertRedirect();
        $resUnverify->assertSessionHas('success');

        $school->refresh();
        $this->assertFalse($school->hasVerifiedCoordinates());
        $this->assertNull($school->coordinate_verified_at);
        $this->assertNull($school->coordinate_verified_by);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'school.coordinates_unverified',
            'user_id' => $this->superuser->id,
            'school_id' => $school->id,
        ]);
    }

    public function test_malicious_school_names_are_properly_escaped_preventing_xss(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);

        $maliciousSchool = School::factory()->create([
            'name' => 'SMK Pertanian <script>alert("xss")</script> & <img src=x onerror=alert(1)>',
            'slug' => 'smk-pertanian-malicious-xss',
            'npsn' => '88000999',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '320201',
            'status' => 'active',
            'latitude' => -6.917000,
            'longitude' => 107.619000,
            'coordinate_verified_at' => now(),
            'coordinate_verified_by' => $this->superuser->id,
        ]);

        $lic = $initAction->execute($maliciousSchool);
        $actAction->execute($lic, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        $response = $this->actingAs($this->superuser)->get(route('master.regions.index'));
        $response->assertOk();

        // In Blade HTML output, characters must be properly escaped
        $response->assertDontSee('<script>alert("xss")</script>', false);
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }

    public function test_license_registry_consistency_across_all_entitlement_states(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $resolver = app(SchoolLicenseResolver::class);
        $today = Carbon::today('Asia/Jakarta');

        // State 1: No License (School 1)
        $school1 = School::factory()->create(['name' => 'SMK State 1 No License']);

        // State 2: Unactivated (School 2 - License initialized without terms)
        $school2 = School::factory()->create(['name' => 'SMK State 2 Unactivated']);
        $lic2 = $initAction->execute($school2);

        // State 3: Pending Future Term (School 3)
        $school3 = School::factory()->create(['name' => 'SMK State 3 Future Pending']);
        $lic3 = $initAction->execute($school3);
        $actAction->execute($lic3, ['start_date' => $today->copy()->addMonths(1)->toDateString()]);

        // State 4: Active Entitlement (School 4)
        $school4 = School::factory()->create(['name' => 'SMK State 4 Active Current']);
        $lic4 = $initAction->execute($school4);
        $actAction->execute($lic4, ['start_date' => $today->copy()->subMonths(2)->toDateString()]);

        // State 5: Expired Entitlement (School 5)
        $school5 = School::factory()->create(['name' => 'SMK State 5 Expired Past']);
        $lic5 = $initAction->execute($school5);
        $actAction->execute($lic5, ['start_date' => $today->copy()->subYears(2)->toDateString()]);

        // State 6: Expired with Future Renewal Gap (School 6)
        $school6 = School::factory()->create(['name' => 'SMK State 6 Renewal Gap']);
        $lic6 = $initAction->execute($school6);
        $actAction->execute($lic6, ['start_date' => $today->copy()->subYears(2)->toDateString()]);
        // Add future renewal starting next month
        SchoolLicenseTerm::create([
            'license_id' => $lic6->id,
            'term_type' => 'annual_renewal',
            'start_date' => $today->copy()->addMonths(1)->toDateString(),
            'expiration_date' => $today->copy()->addMonths(13)->toDateString(),
            'notes' => 'Renewal with gap',
            'authorized_by' => $this->superuser->id,
        ]);

        // State 7: Multiple Consecutive Renewals (School 7)
        $school7 = School::factory()->create(['name' => 'SMK State 7 Consecutive']);
        $lic7 = $initAction->execute($school7);
        $actAction->execute($lic7, ['start_date' => $today->copy()->subMonths(6)->toDateString()]);
        // Add consecutive renewal
        SchoolLicenseTerm::create([
            'license_id' => $lic7->id,
            'term_type' => 'annual_renewal',
            'start_date' => $today->copy()->addMonths(6)->toDateString(),
            'expiration_date' => $today->copy()->addMonths(18)->toDateString(),
            'notes' => 'Consecutive renewal',
            'authorized_by' => $this->superuser->id,
        ]);

        // Verify resolver results
        $res2 = $resolver->resolve($lic2->fresh(), $today);
        $res3 = $resolver->resolve($lic3->fresh(), $today);
        $res4 = $resolver->resolve($lic4->fresh(), $today);
        $res5 = $resolver->resolve($lic5->fresh(), $today);
        $res6 = $resolver->resolve($lic6->fresh(), $today);
        $res7 = $resolver->resolve($lic7->fresh(), $today);

        $this->assertEquals(SchoolLicense::STATUS_PENDING, $res2['status']);
        $this->assertEquals(SchoolLicense::STATUS_PENDING, $res3['status']);
        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $res4['status']);
        $this->assertEquals(SchoolLicense::STATUS_EXPIRED, $res5['status']);
        $this->assertEquals(SchoolLicense::STATUS_EXPIRED, $res6['status']); // Gap must be expired!
        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $res7['status']);

        // Test /master/licenses table and KPIs
        $response = $this->actingAs($this->superuser)->get(route('master.licenses.index'));
        $response->assertOk();

        // 1. One row per school in directory: all 7 schools + 2 setup schools must appear
        $totalDbSchools = School::count();
        $this->assertEquals($totalDbSchools, $response->viewData('totalSchools'));
        $this->assertEquals($totalDbSchools, $response->viewData('schools')->total());

        $response->assertSee($school1->name);
        $response->assertSee($school2->name);
        $response->assertSee($school3->name);
        $response->assertSee($school4->name);
        $response->assertSee($school5->name);
        $response->assertSee($school6->name);
        $response->assertSee($school7->name);

        // 2. Canonical KPI counts and disjoint partition check
        $totalSchools = $response->viewData('totalSchools');
        $totalLicenses = $response->viewData('totalLicenses');
        $activeLicenses = $response->viewData('activeLicenses');
        $pendingLicenses = $response->viewData('pendingLicenses');
        $expiredLicenses = $response->viewData('expiredLicenses');
        $uninitializedCount = $response->viewData('uninitializedSchoolsCount');
        $unentitledCount = $response->viewData('unentitledSchoolsCount');

        $this->assertEquals(2, $activeLicenses); // School 4 and School 7
        $this->assertEquals(2, $pendingLicenses); // School 2 (unactivated) and School 3 (future)
        $this->assertEquals(2, $expiredLicenses); // School 5 (expired past) and School 6 (renewal gap)
        $this->assertEquals(4, $uninitializedCount); // Baseline migration school + School A + School B + School 1
        $this->assertEquals(6, $totalLicenses); // Lic 2, 3, 4, 5, 6, 7

        // Mathematical partition assertion: Active + Pending + Expired + Uninitialized == Total
        $this->assertEquals($totalSchools, $activeLicenses + $pendingLicenses + $expiredLicenses + $uninitializedCount);
        // Tanpa Hak Aktif assertion: Total - Active == Pending + Expired + Uninitialized
        $this->assertEquals($totalSchools - $activeLicenses, $unentitledCount);
        $this->assertEquals($pendingLicenses + $expiredLicenses + $uninitializedCount, $unentitledCount);

        // 3. Verify Status Filter: Active
        $resActive = $this->actingAs($this->superuser)->get(route('master.licenses.index', ['status' => 'active']));
        $resActive->assertOk();
        $this->assertEquals(2, $resActive->viewData('schools')->total());
        $activeIds = $resActive->viewData('schools')->pluck('id')->all();
        $this->assertContains($school4->id, $activeIds);
        $this->assertContains($school7->id, $activeIds);
        $this->assertNotContains($school1->id, $activeIds);
        $this->assertNotContains($school2->id, $activeIds);
        $this->assertNotContains($school3->id, $activeIds);
        $this->assertNotContains($school5->id, $activeIds);
        $this->assertNotContains($school6->id, $activeIds);
        $resActive->assertSee('id="school-license-row-'.$school4->id.'"', false);
        $resActive->assertSee('id="school-license-row-'.$school7->id.'"', false);
        $resActive->assertDontSee('id="school-license-row-'.$school1->id.'"', false);
        $resActive->assertDontSee('id="school-license-row-'.$school2->id.'"', false);
        $resActive->assertDontSee('id="school-license-row-'.$school3->id.'"', false);
        $resActive->assertDontSee('id="school-license-row-'.$school5->id.'"', false);
        $resActive->assertDontSee('id="school-license-row-'.$school6->id.'"', false);

        // 4. Verify Status Filter: Pending / Unactivated (future terms or initialized without terms)
        $resPending = $this->actingAs($this->superuser)->get(route('master.licenses.index', ['status' => 'pending']));
        $resPending->assertOk();
        $this->assertEquals(2, $resPending->viewData('schools')->total());
        $pendingIds = $resPending->viewData('schools')->pluck('id')->all();
        $this->assertContains($school2->id, $pendingIds);
        $this->assertContains($school3->id, $pendingIds);
        $this->assertNotContains($school1->id, $pendingIds);
        $this->assertNotContains($school4->id, $pendingIds);
        $this->assertNotContains($school5->id, $pendingIds);
        $this->assertNotContains($school6->id, $pendingIds); // Renewal gap must NOT appear in pending!
        $this->assertNotContains($school7->id, $pendingIds);
        $resPending->assertSee('id="school-license-row-'.$school2->id.'"', false);
        $resPending->assertSee('id="school-license-row-'.$school3->id.'"', false);
        $resPending->assertDontSee('id="school-license-row-'.$school1->id.'"', false);
        $resPending->assertDontSee('id="school-license-row-'.$school4->id.'"', false);
        $resPending->assertDontSee('id="school-license-row-'.$school5->id.'"', false);
        $resPending->assertDontSee('id="school-license-row-'.$school6->id.'"', false);
        $resPending->assertDontSee('id="school-license-row-'.$school7->id.'"', false);

        // 5. Verify Status Filter: Expired (past term or renewal gap)
        $resExpired = $this->actingAs($this->superuser)->get(route('master.licenses.index', ['status' => 'expired']));
        $resExpired->assertOk();
        $this->assertEquals(2, $resExpired->viewData('schools')->total());
        $expiredIds = $resExpired->viewData('schools')->pluck('id')->all();
        $this->assertContains($school5->id, $expiredIds);
        $this->assertContains($school6->id, $expiredIds); // Critical Case: expired-to-future renewal gap is EXPIRED
        $this->assertNotContains($school1->id, $expiredIds);
        $this->assertNotContains($school2->id, $expiredIds);
        $this->assertNotContains($school3->id, $expiredIds);
        $this->assertNotContains($school4->id, $expiredIds);
        $this->assertNotContains($school7->id, $expiredIds);
        $resExpired->assertSee('id="school-license-row-'.$school5->id.'"', false);
        $resExpired->assertSee('id="school-license-row-'.$school6->id.'"', false);
        $resExpired->assertDontSee('id="school-license-row-'.$school1->id.'"', false);
        $resExpired->assertDontSee('id="school-license-row-'.$school2->id.'"', false);
        $resExpired->assertDontSee('id="school-license-row-'.$school3->id.'"', false);
        $resExpired->assertDontSee('id="school-license-row-'.$school4->id.'"', false);
        $resExpired->assertDontSee('id="school-license-row-'.$school7->id.'"', false);

        // 6. Verify Status Filter: Uninitialized (no license initialized)
        $resUninitialized = $this->actingAs($this->superuser)->get(route('master.licenses.index', ['status' => 'uninitialized']));
        $resUninitialized->assertOk();
        $this->assertEquals(4, $resUninitialized->viewData('schools')->total());
        $uninitIds = $resUninitialized->viewData('schools')->pluck('id')->all();
        $this->assertContains($school1->id, $uninitIds);
        $this->assertNotContains($school2->id, $uninitIds);
        $this->assertNotContains($school3->id, $uninitIds);
        $this->assertNotContains($school4->id, $uninitIds);
        $this->assertNotContains($school5->id, $uninitIds);
        $this->assertNotContains($school6->id, $uninitIds);
        $this->assertNotContains($school7->id, $uninitIds);
        $resUninitialized->assertSee('id="school-license-row-'.$school1->id.'"', false);
        $resUninitialized->assertDontSee('id="school-license-row-'.$school2->id.'"', false);
        $resUninitialized->assertDontSee('id="school-license-row-'.$school3->id.'"', false);
        $resUninitialized->assertDontSee('id="school-license-row-'.$school4->id.'"', false);
        $resUninitialized->assertDontSee('id="school-license-row-'.$school5->id.'"', false);
        $resUninitialized->assertDontSee('id="school-license-row-'.$school6->id.'"', false);
        $resUninitialized->assertDontSee('id="school-license-row-'.$school7->id.'"', false);

        // 7. Verify Filter: Tanpa Hak Aktif (unentitled_only = 1)
        // MUST include: uninitialized (school1, setup), pending (school2, school3), expired (school5, school6)
        // MUST NOT include: active (school4, school7)
        $resUnentitled = $this->actingAs($this->superuser)->get(route('master.licenses.index', ['unentitled_only' => '1']));
        $resUnentitled->assertOk();
        $this->assertEquals($unentitledCount, $resUnentitled->viewData('schools')->total());
        $unentitledIds = $resUnentitled->viewData('schools')->pluck('id')->all();
        $this->assertContains($school1->id, $unentitledIds);
        $this->assertContains($school2->id, $unentitledIds);
        $this->assertContains($school3->id, $unentitledIds);
        $this->assertContains($school5->id, $unentitledIds);
        $this->assertContains($school6->id, $unentitledIds); // Critical Case: School 6 renewal gap MUST appear in Tanpa Hak Aktif
        $this->assertNotContains($school4->id, $unentitledIds); // Active MUST NOT appear
        $this->assertNotContains($school7->id, $unentitledIds); // Active MUST NOT appear
        $resUnentitled->assertSee('id="school-license-row-'.$school1->id.'"', false);
        $resUnentitled->assertSee('id="school-license-row-'.$school2->id.'"', false);
        $resUnentitled->assertSee('id="school-license-row-'.$school3->id.'"', false);
        $resUnentitled->assertSee('id="school-license-row-'.$school5->id.'"', false);
        $resUnentitled->assertSee('id="school-license-row-'.$school6->id.'"', false);
        $resUnentitled->assertDontSee('id="school-license-row-'.$school4->id.'"', false);
        $resUnentitled->assertDontSee('id="school-license-row-'.$school7->id.'"', false);
    }

    public function test_administrative_reference_view_preserved(): void
    {
        // 1. Direct view parameter
        $resRef = $this->actingAs($this->superuser)->get(route('master.regions.index', ['view' => 'reference']));
        $resRef->assertOk();
        $resRef->assertSee('Master Wilayah Indonesia');
        $resRef->assertSee('JAWA BARAT');

        // 2. Backward compatibility with ?level=provinces
        $resLevel = $this->actingAs($this->superuser)->get(route('master.regions.index', ['level' => 'provinces']));
        $resLevel->assertOk();
        $resLevel->assertSee('Master Wilayah Indonesia');
        $resLevel->assertSee('JAWA BARAT');
    }

    public function test_trainer_kit_inventory_accurate_kpis_and_operational_tabs(): void
    {
        // Create 3 kits:
        // Kit 1: factory stock (registered)
        TrainerKitInventory::create([
            'serial_number' => 'BITANIC-TK-2026-0001',
            'model_name' => 'BITANIC Standard 7-in-1',
            'manufacturing_date' => '2026-01-01',
            'status' => 'registered',
        ]);

        // Kit 2: sold to school A, no device (sold)
        TrainerKitInventory::create([
            'serial_number' => 'BITANIC-TK-2026-0002',
            'model_name' => 'BITANIC Standard 7-in-1',
            'manufacturing_date' => '2026-01-01',
            'school_id' => $this->schoolA->id,
            'sale_date' => '2026-02-01',
            'status' => 'sold',
        ]);

        // Kit 3: activated with device at school A (activated)
        $device = Device::factory()->create(['school_id' => $this->schoolA->id]);
        TrainerKitInventory::create([
            'serial_number' => 'BITANIC-TK-2026-0003',
            'model_name' => 'BITANIC Pro 7-in-1',
            'manufacturing_date' => '2026-01-01',
            'school_id' => $this->schoolA->id,
            'device_id' => $device->id,
            'sale_date' => '2026-02-01',
            'status' => 'activated',
        ]);

        $response = $this->actingAs($this->superuser)->get(route('master.trainer-kits.index'));
        $response->assertOk();

        // 5 KPI cards
        $response->assertSee('Total Unit Fisik');
        $response->assertSee('Stok Belum Terjual');
        $response->assertSee('Unit Milik Sekolah');
        $response->assertSee('Terhubung Workstation');
        $response->assertSee('Aktif Operasional');

        // Test tabs
        $resStock = $this->actingAs($this->superuser)->get(route('master.trainer-kits.index', ['tab' => 'stock']));
        $resStock->assertOk();
        $resStock->assertSee('BITANIC-TK-2026-0001');
        $resStock->assertDontSee('BITANIC-TK-2026-0002');

        $resSchool = $this->actingAs($this->superuser)->get(route('master.trainer-kits.index', ['tab' => 'school_owned']));
        $resSchool->assertOk();
        $resSchool->assertSee('BITANIC-TK-2026-0002');
        $resSchool->assertSee('BITANIC-TK-2026-0003');
        $resSchool->assertDontSee('BITANIC-TK-2026-0001');
    }

    public function test_master_license_table_is_rooted_in_school_directory(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);

        // School A: has active license
        $licA = $initAction->execute($this->schoolA);
        $actAction->execute($licA, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        // School B: has NO license initialized
        $this->assertNull($this->schoolB->fresh()->license);

        $response = $this->actingAs($this->superuser)->get(route('master.licenses.index'));
        $response->assertOk();

        // School A has active license
        $response->assertSee($this->schoolA->name);
        $response->assertSee($licA->license_number);
        $response->assertSee('Lisensi Aktif');

        // School B appears in table with "Belum Memiliki Lisensi" and "Inisialisasi" button
        $response->assertSee($this->schoolB->name);
        $response->assertSee('Belum Memiliki Lisensi');
        $response->assertSee('Inisialisasi');
    }

    public function test_school_crud_stores_and_updates_optional_gis_coordinates(): void
    {
        $payload = [
            'name' => 'SMKN 3 Sukabumi GIS',
            'npsn' => '88000003',
            'slug' => 'smkn-3-sukabumi-gis',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '320201',
            'address' => 'Jl. Pelabuhan II KM 5',
            'status' => 'active',
            'latitude' => -6.938812,
            'longitude' => 106.921455,
        ];

        $resCreate = $this->actingAs($this->superuser)->post(route('master.schools.store'), $payload);
        $resCreate->assertRedirect(route('master.schools.index'));

        $school = School::where('slug', 'smkn-3-sukabumi-gis')->firstOrFail();
        $this->assertEquals(-6.938812, (float) $school->latitude);
        $this->assertEquals(106.921455, (float) $school->longitude);
        $this->assertEquals('manual_input', $school->coordinate_source);
        // Under verified coordinates doctrine, new coordinates start unverified
        $this->assertNull($school->coordinate_verified_at);
        $this->assertNull($school->coordinate_verified_by);

        // Update coordinates
        $resUpdate = $this->actingAs($this->superuser)->put(route('master.schools.update', $school), array_merge($payload, [
            'latitude' => -6.950000,
            'longitude' => 106.930000,
        ]));
        $resUpdate->assertRedirect(route('master.schools.index'));

        $school->refresh();
        $this->assertEquals(-6.950000, (float) $school->latitude);
        $this->assertEquals(106.930000, (float) $school->longitude);
        // Still unverified
        $this->assertNull($school->coordinate_verified_at);
        $this->assertNull($school->coordinate_verified_by);
    }
}
