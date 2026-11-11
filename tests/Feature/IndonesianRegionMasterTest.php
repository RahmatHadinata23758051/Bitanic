<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\RegionDatasetMetadata;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IndonesianRegionMasterTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $superuser;

    private User $adminSekolah;

    private User $guru;

    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::where('slug', 'smk-ppn-1-sukaraja')->first() ?? School::create([
            'name' => 'SMK PPN 1 Sukaraja',
            'slug' => 'smk-ppn-1-sukaraja',
            'status' => 'active',
            'province' => 'Jawa Barat',
            'city' => 'Sukabumi',
            'address' => 'Jl. Raya Sukaraja No. 45',
        ]);

        $this->superuser = User::create([
            'name' => 'Platform Superuser',
            'email' => 'superuser.master@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => null,
        ]);

        $this->adminSekolah = User::create([
            'name' => 'Admin Sekolah A',
            'email' => 'admin.a@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->school->id,
        ]);

        $this->guru = User::create([
            'name' => 'Guru Pertanian',
            'email' => 'guru.master@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->school->id,
        ]);

        $this->siswa = User::create([
            'name' => 'Siswa Praktikan',
            'email' => 'siswa.master@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->school->id,
        ]);

        // Sync local dataset
        Artisan::call('bitanic:sync-indonesian-regions');
    }

    public function test_region_hierarchy_relationships(): void
    {
        $province = Province::find('32');
        $this->assertNotNull($province, 'Province 32 (Jawa Barat) must exist');
        $this->assertSame('JAWA BARAT', $province->name);

        $regency = Regency::find('3202');
        $this->assertNotNull($regency, 'Regency 3202 (Kabupaten Sukabumi) must exist');
        $this->assertSame('32', $regency->province_code);
        $this->assertSame('KABUPATEN SUKABUMI', $regency->name);
        $this->assertSame($province->code, $regency->province->code);

        $district = District::find('3202170');
        $this->assertNotNull($district, 'District 3202170 (Sukaraja) must exist');
        $this->assertSame('3202', $district->regency_code);
        $this->assertSame('SUKARAJA', $district->name);
        $this->assertSame($regency->code, $district->regency->code);
    }

    public function test_duplicate_region_code_rejected(): void
    {
        $this->expectException(QueryException::class);

        Province::create([
            'code' => '32',
            'name' => 'DUPLICATE PROVINCE',
        ]);
    }

    public function test_invalid_parent_child_relationship_rejected(): void
    {
        $this->expectException(QueryException::class);

        Regency::create([
            'code' => '9999',
            'province_code' => '99', // Non-existent province code
            'name' => 'KABUPATEN FIKTIF',
        ]);
    }

    public function test_idempotent_sync_command(): void
    {
        $initialProvinces = Province::count();
        $initialRegencies = Regency::count();
        $initialDistricts = District::count();

        // Second run should recognize checksum and succeed idempotently
        $exitCode = Artisan::call('bitanic:sync-indonesian-regions');
        $this->assertSame(0, $exitCode);

        $this->assertSame($initialProvinces, Province::count());
        $this->assertSame($initialRegencies, Regency::count());
        $this->assertSame($initialDistricts, District::count());
    }

    public function test_region_dataset_metadata_recorded(): void
    {
        $metadata = RegionDatasetMetadata::where('status', 'active')->latest()->first();

        $this->assertNotNull($metadata);
        $this->assertSame('Kepmendagri No 050-145 Tahun 2022 / BPS Indonesia', $metadata->source_name);
        $this->assertSame('2024.1', $metadata->dataset_version);
        $this->assertGreaterThanOrEqual(34, $metadata->total_provinces);
        $this->assertGreaterThanOrEqual(500, $metadata->total_regencies);
        $this->assertNotEmpty($metadata->checksum);
        $this->assertNotNull($metadata->imported_at);
    }

    public function test_unauthorized_users_cannot_sync_regions(): void
    {
        // 1. Guest rejected
        $this->post(route('master.regions.sync'))
            ->assertRedirect(route('login'));

        // 2. Siswa rejected (403)
        $this->actingAs($this->siswa)
            ->post(route('master.regions.sync'))
            ->assertForbidden();

        // 3. Guru rejected (403)
        $this->actingAs($this->guru)
            ->post(route('master.regions.sync'))
            ->assertForbidden();

        // 4. Admin Sekolah rejected (403)
        $this->actingAs($this->adminSekolah)
            ->post(route('master.regions.sync'))
            ->assertForbidden();

        // 5. Superuser allowed (302 redirect with success flash)
        $this->actingAs($this->superuser)
            ->post(route('master.regions.sync'))
            ->assertRedirect(route('master.regions.index'))
            ->assertSessionHas('success');
    }

    public function test_superuser_can_view_regions_master_and_tabs(): void
    {
        $this->actingAs($this->superuser)
            ->get(route('master.regions.index', ['level' => 'provinces']))
            ->assertOk()
            ->assertSee('Master Wilayah Indonesia')
            ->assertSee('JAWA BARAT');

        $this->actingAs($this->superuser)
            ->get(route('master.regions.index', ['level' => 'regencies', 'province_code' => '32']))
            ->assertOk()
            ->assertSee('KABUPATEN SUKABUMI');

        $this->actingAs($this->superuser)
            ->get(route('master.regions.index', ['level' => 'districts', 'regency_code' => '3202', 'search' => 'SUKARAJA']))
            ->assertOk()
            ->assertSee('SUKARAJA');
    }

    public function test_cascading_api_endpoints(): void
    {
        // 1. Provinces
        $provResp = $this->actingAs($this->guru)
            ->getJson(route('api.regions.provinces'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($provResp->json('data'));

        // 2. Regencies
        $this->actingAs($this->guru)
            ->getJson(route('api.regions.regencies'))
            ->assertStatus(422);

        $regResp = $this->actingAs($this->guru)
            ->getJson(route('api.regions.regencies', ['province_code' => '32']))
            ->assertOk()
            ->assertJsonPath('success', true);

        $regCodes = collect($regResp->json('data'))->pluck('code');
        $this->assertTrue($regCodes->contains('3202'));

        // 3. Districts
        $this->actingAs($this->guru)
            ->getJson(route('api.regions.districts'))
            ->assertStatus(422);

        $distResp = $this->actingAs($this->guru)
            ->getJson(route('api.regions.districts', ['regency_code' => '3202']))
            ->assertOk()
            ->assertJsonPath('success', true);

        $distCodes = collect($distResp->json('data'))->pluck('code');
        $this->assertTrue($distCodes->contains('3202170'));
    }

    public function test_region_search_and_filter(): void
    {
        $response = $this->actingAs($this->superuser)
            ->get(route('master.regions.index', [
                'level' => 'regencies',
                'province_code' => '32',
                'search' => 'SUKABUMI',
            ]))
            ->assertOk();

        $response->assertSee('KABUPATEN SUKABUMI');
        $response->assertSee('KOTA SUKABUMI');
    }
}
