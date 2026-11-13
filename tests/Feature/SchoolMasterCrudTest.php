<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Province;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolMasterCrudTest extends TestCase
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

        // 1. Seed region master
        Artisan::call('bitanic:sync-indonesian-regions');

        // 2. Baseline existing school
        $this->school = School::where('slug', 'smk-ppn-1-sukaraja')->first() ?? School::create([
            'npsn' => '20200001',
            'name' => 'SMK Pertanian Pembangunan Negeri 1 Sukaraja',
            'slug' => 'smk-ppn-1-sukaraja',
            'status' => 'active',
            'province' => 'Jawa Barat',
            'city' => 'Sukabumi',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '3202170',
            'address' => 'Jl. Raya Sukaraja No. 45',
            'phone' => '0266-221133',
        ]);

        // 3. User identities
        $this->superuser = User::create([
            'name' => 'Platform Superuser',
            'email' => 'superuser.schools@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => null, // Disassociated per Baseline v1.0
        ]);

        $this->adminSekolah = User::create([
            'name' => 'Admin Sekolah Sukaraja',
            'email' => 'admin.sukaraja@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin_sekolah',
            'school_id' => $this->school->id,
        ]);

        $this->guru = User::create([
            'name' => 'Guru Sukaraja',
            'email' => 'guru.sukaraja@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'guru',
            'school_id' => $this->school->id,
        ]);

        $this->siswa = User::create([
            'name' => 'Siswa Sukaraja',
            'email' => 'siswa.sukaraja@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'siswa',
            'school_id' => $this->school->id,
        ]);
    }

    public function test_superuser_can_view_all_schools_and_search(): void
    {
        $response = $this->actingAs($this->superuser)
            ->get(route('master.schools.index'))
            ->assertOk()
            ->assertSee('Master Sekolah (Tenant Registry)')
            ->assertSee('SMK Pertanian Pembangunan Negeri 1 Sukaraja')
            ->assertSee('20200001');

        // Test search
        $this->actingAs($this->superuser)
            ->get(route('master.schools.index', ['search' => 'Sukaraja']))
            ->assertOk()
            ->assertSee('SMK Pertanian Pembangunan Negeri 1 Sukaraja');
    }

    public function test_superuser_can_create_new_school_with_valid_hierarchy(): void
    {
        $payload = [
            'name' => 'SMK Negeri 1 Cibadak',
            'npsn' => '20202021',
            'slug' => 'smkn-1-cibadak',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '3202210', // CIBADAK in 3202
            'address' => 'Jl. Al-Muwahhidin No. 1, Cibadak',
            'phone' => '0266-531234',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->superuser)
            ->post(route('master.schools.store'), $payload);

        $response->assertRedirect(route('master.schools.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('schools', [
            'name' => 'SMK Negeri 1 Cibadak',
            'npsn' => '20202021',
            'slug' => 'smkn-1-cibadak',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '3202210',
            'city' => 'KABUPATEN SUKABUMI',
            'province' => 'JAWA BARAT',
            'status' => 'active',
        ]);

        $createdSchool = School::where('slug', 'smkn-1-cibadak')->first();
        $this->assertNotNull($createdSchool);

        // Verify initial SchoolSetting was provisioned
        $this->assertDatabaseHas('school_settings', [
            'school_id' => $createdSchool->id,
        ]);

        // Verify AuditLog was recorded
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $createdSchool->id,
            'action' => 'school.created',
            'actor_role' => 'superuser',
        ]);
    }

    public function test_superuser_can_create_new_school_with_auto_license_activation(): void
    {
        $payload = [
            'name' => 'SMK Pertanian Auto Lisensi',
            'npsn' => '20202099',
            'slug' => 'smk-pertanian-auto-lisensi',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '3202210',
            'status' => 'active',
            'auto_activate_license' => '1',
        ];

        $response = $this->actingAs($this->superuser)
            ->post(route('master.schools.store'), $payload);

        $response->assertRedirect(route('master.schools.index'))
            ->assertSessionHas('success');

        $createdSchool = School::where('slug', 'smk-pertanian-auto-lisensi')->first();
        $this->assertNotNull($createdSchool);

        $license = $createdSchool->license;
        $this->assertNotNull($license);
        $this->assertSame('active', $license->status);
        $this->assertCount(1, $license->terms);
        $this->assertSame('initial_included', $license->terms->first()->term_type);
    }

    public function test_unique_npsn_validation(): void
    {
        $payload = [
            'name' => 'SMK Duplikasi NPSN',
            'npsn' => '20200001', // Already belongs to school 1
            'slug' => 'smk-duplikasi',
            'province_code' => '32',
            'regency_code' => '3202',
            'status' => 'active',
        ];

        $this->actingAs($this->superuser)
            ->post(route('master.schools.store'), $payload)
            ->assertSessionHasErrors(['npsn']);
    }

    public function test_optional_npsn_accepted(): void
    {
        $payload = [
            'name' => 'SMK Pertanian Mandiri',
            'npsn' => null, // Optional NPSN
            'slug' => 'smk-pertanian-mandiri',
            'province_code' => '32',
            'regency_code' => '3202',
            'status' => 'active',
        ];

        $this->actingAs($this->superuser)
            ->post(route('master.schools.store'), $payload)
            ->assertRedirect(route('master.schools.index'));

        $this->assertDatabaseHas('schools', [
            'slug' => 'smk-pertanian-mandiri',
            'npsn' => null,
        ]);
    }

    public function test_unique_slug_validation_and_auto_generation(): void
    {
        // 1. Auto-generate slug when empty
        $payload = [
            'name' => 'SMK Bina Agrikultur',
            'slug' => null,
            'province_code' => '32',
            'regency_code' => '3202',
            'status' => 'active',
        ];

        $this->actingAs($this->superuser)
            ->post(route('master.schools.store'), $payload)
            ->assertRedirect(route('master.schools.index'));

        $this->assertDatabaseHas('schools', [
            'name' => 'SMK Bina Agrikultur',
            'slug' => 'smk-bina-agrikultur',
        ]);

        // 2. Reject duplicate slug when manually provided
        $payload['slug'] = 'smk-bina-agrikultur';
        $this->actingAs($this->superuser)
            ->post(route('master.schools.store'), $payload)
            ->assertSessionHasErrors(['slug']);
    }

    public function test_invalid_geographical_relationship_rejected(): void
    {
        // 3101 (Kepulauan Seribu) belongs to province 31 (DKI Jakarta), NOT province 32 (Jawa Barat)
        $payload = [
            'name' => 'SMK Geografi Rusak',
            'province_code' => '32', // Jawa Barat
            'regency_code' => '3101', // Kepulauan Seribu
            'status' => 'active',
        ];

        $this->actingAs($this->superuser)
            ->post(route('master.schools.store'), $payload)
            ->assertSessionHasErrors(['regency_code']);
    }

    public function test_invalid_district_relationship_rejected(): void
    {
        // 3202170 (Sukaraja) belongs to 3202 (Kab Sukabumi), NOT 3201 (Kab Bogor)
        $payload = [
            'name' => 'SMK Kecamatan Rusak',
            'province_code' => '32',
            'regency_code' => '3201', // Kab Bogor
            'district_code' => '3202170', // Sukaraja (in Kab Sukabumi)
            'status' => 'active',
        ];

        $this->actingAs($this->superuser)
            ->post(route('master.schools.store'), $payload)
            ->assertSessionHasErrors(['district_code']);
    }

    public function test_school_activation_and_deactivation_toggle(): void
    {
        $this->assertSame('active', $this->school->status);

        // Deactivate
        $this->actingAs($this->superuser)
            ->patch(route('master.schools.toggle-status', $this->school))
            ->assertRedirect();

        $this->school->refresh();
        $this->assertSame('inactive', $this->school->status);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'school.status_changed',
        ]);

        // Re-activate
        $this->actingAs($this->superuser)
            ->patch(route('master.schools.toggle-status', $this->school))
            ->assertRedirect();

        $this->school->refresh();
        $this->assertSame('active', $this->school->status);
    }

    public function test_school_update_preserves_existing_data(): void
    {
        $payload = [
            'name' => 'SMK PPN 1 Sukaraja (Kampus Utama)',
            'npsn' => '20200001',
            'slug' => 'smk-ppn-1-sukaraja',
            'province_code' => '32',
            'regency_code' => '3202',
            'district_code' => '3202170',
            'address' => 'Jl. Raya Sukaraja No. 45-B',
            'phone' => '0266-221144',
            'status' => 'active',
        ];

        $this->actingAs($this->superuser)
            ->put(route('master.schools.update', $this->school), $payload)
            ->assertRedirect(route('master.schools.index'))
            ->assertSessionHas('success');

        $this->school->refresh();
        $this->assertSame('SMK PPN 1 Sukaraja (Kampus Utama)', $this->school->name);
        $this->assertSame('Jl. Raya Sukaraja No. 45-B', $this->school->address);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'school.updated',
        ]);
    }

    public function test_school_update_can_activate_unlicensed_school(): void
    {
        $newSchool = School::create([
            'name' => 'SMK Tanpa Lisensi Awal',
            'slug' => 'smk-tanpa-lisensi-awal',
            'province_code' => '32',
            'regency_code' => '3202',
            'status' => 'active',
        ]);

        $this->assertNull($newSchool->license);

        $payload = [
            'name' => 'SMK Tanpa Lisensi Awal (Aktifkan)',
            'slug' => 'smk-tanpa-lisensi-awal',
            'province_code' => '32',
            'regency_code' => '3202',
            'status' => 'active',
            'auto_activate_license' => '1',
        ];

        $response = $this->actingAs($this->superuser)
            ->put(route('master.schools.update', $newSchool), $payload);

        $response->assertRedirect(route('master.schools.index'))
            ->assertSessionHas('success');

        $newSchool->refresh();
        $license = $newSchool->license;
        $this->assertNotNull($license);
        $this->assertSame('active', $license->status);
        $this->assertCount(1, $license->terms);
    }

    public function test_school_with_associated_records_cannot_be_hard_deleted(): void
    {
        // $this->school has users ($this->guru, $this->siswa, $this->adminSekolah)
        $this->actingAs($this->superuser)
            ->delete(route('master.schools.destroy', $this->school))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('schools', [
            'id' => $this->school->id,
        ]);
    }

    public function test_empty_school_can_be_deleted(): void
    {
        $emptySchool = School::create([
            'name' => 'SMK Kosong Fiktif',
            'slug' => 'smk-kosong-fiktif',
            'province_code' => '32',
            'regency_code' => '3202',
            'status' => 'inactive',
        ]);

        $this->actingAs($this->superuser)
            ->delete(route('master.schools.destroy', $emptySchool))
            ->assertRedirect(route('master.schools.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('schools', [
            'id' => $emptySchool->id,
        ]);
    }

    public function test_admin_sekolah_cannot_access_global_school_crud(): void
    {
        // 1. Cannot view master list
        $this->actingAs($this->adminSekolah)
            ->get(route('master.schools.index'))
            ->assertForbidden();

        // 2. Cannot view create form
        $this->actingAs($this->adminSekolah)
            ->get(route('master.schools.create'))
            ->assertForbidden();

        // 3. Cannot store school
        $this->actingAs($this->adminSekolah)
            ->post(route('master.schools.store'), [
                'name' => 'SMK Hack',
                'province_code' => '32',
                'regency_code' => '3202',
                'status' => 'active',
            ])
            ->assertForbidden();

        // 4. Cannot toggle status
        $this->actingAs($this->adminSekolah)
            ->patch(route('master.schools.toggle-status', $this->school))
            ->assertForbidden();

        // 5. Cannot delete school
        $this->actingAs($this->adminSekolah)
            ->delete(route('master.schools.destroy', $this->school))
            ->assertForbidden();
    }

    public function test_guru_and_siswa_cannot_access_global_school_crud(): void
    {
        $this->actingAs($this->guru)
            ->get(route('master.schools.index'))
            ->assertForbidden();

        $this->actingAs($this->siswa)
            ->get(route('master.schools.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_global_school_crud(): void
    {
        $this->get(route('master.schools.index'))
            ->assertRedirect(route('login'));

        $this->get(route('master.schools.create'))
            ->assertRedirect(route('login'));
    }

    public function test_superuser_with_school_id_null_can_access_and_manage_all_masters(): void
    {
        $this->assertNull($this->superuser->school_id);

        $this->actingAs($this->superuser)
            ->get(route('master.schools.index'))
            ->assertOk();

        $this->actingAs($this->superuser)
            ->get(route('master.schools.show', $this->school))
            ->assertOk();

        $this->actingAs($this->superuser)
            ->get(route('master.schools.edit', $this->school))
            ->assertOk();
    }

    public function test_existing_school_backfill_integrity(): void
    {
        $school = School::where('slug', 'smk-ppn-1-sukaraja')->first();
        $this->assertNotNull($school);
        $this->assertSame('32', $school->province_code);
        $this->assertSame('3202', $school->regency_code);
        $this->assertSame('3202170', $school->district_code);
        $this->assertNotNull($school->provinceRelation);
        $this->assertSame('JAWA BARAT', $school->provinceRelation->name);
        $this->assertNotNull($school->regencyRelation);
        $this->assertSame('KABUPATEN SUKABUMI', $school->regencyRelation->name);
        $this->assertNotNull($school->districtRelation);
        $this->assertSame('SUKARAJA', $school->districtRelation->name);
    }
}
