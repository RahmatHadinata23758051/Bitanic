<?php

namespace Tests\Feature;

use App\Actions\License\ActivateFirstYearLicenseAction;
use App\Actions\License\InitializeSchoolLicenseAction;
use App\Models\Device;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\School;
use App\Models\TrainerKitInventory;
use App\Models\User;
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

        // School A has coordinates, School B does not
        $licA = $initAction->execute($this->schoolA);
        $actAction->execute($licA, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        $licB = $initAction->execute($this->schoolB);
        $actAction->execute($licB, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]);

        $response = $this->actingAs($this->superuser)->get(route('master.regions.index'));
        $response->assertOk();

        // Markers data contains School A coordinates
        $response->assertSee('-6.917464');
        $response->assertSee('107.619123');

        // School B is rendered with unmapped location indicator
        $response->assertSee('Lokasi belum dipetakan');
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
        $this->assertNotNull($school->coordinate_verified_at);

        // Update coordinates
        $resUpdate = $this->actingAs($this->superuser)->put(route('master.schools.update', $school), array_merge($payload, [
            'latitude' => -6.950000,
            'longitude' => 106.930000,
        ]));
        $resUpdate->assertRedirect(route('master.schools.index'));

        $school->refresh();
        $this->assertEquals(-6.950000, (float) $school->latitude);
        $this->assertEquals(106.930000, (float) $school->longitude);
    }
}
