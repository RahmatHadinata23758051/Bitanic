<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_school_is_created_and_associated_with_users_and_devices(): void
    {
        $defaultSchool = School::where('slug', 'smk-ppn-1-sukaraja')->firstOrFail();
        $this->assertEquals('20200001', $defaultSchool->npsn);
        $this->assertEquals('SMK Pertanian Pembangunan Negeri 1 Sukaraja', $defaultSchool->name);
        $this->assertEquals('active', $defaultSchool->status);

        $user = User::factory()->create(['role' => 'guru']);
        $this->assertEquals($defaultSchool->id, $user->school_id);
        $this->assertInstanceOf(School::class, $user->school);

        $device = Device::create([
            'code' => 'TK-TENANCY-01',
            'name' => 'Meja Praktikum Tenancy 1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);
        $this->assertEquals($defaultSchool->id, $device->school_id);
        $this->assertInstanceOf(School::class, $device->school);
    }

    public function test_multi_school_ready_isolation_allows_distinct_schools_without_conflict(): void
    {
        $schoolA = School::where('slug', 'smk-ppn-1-sukaraja')->firstOrFail();

        $schoolB = School::create([
            'npsn' => '20200002',
            'name' => 'SMK Negeri 2 Subang (Pertanian)',
            'slug' => 'smk-n-2-subang',
            'city' => 'Subang',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $deviceA = Device::create([
            'school_id' => $schoolA->id,
            'code' => 'TK-SCH-A',
            'name' => 'Kit Sukaraja #1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        $deviceB = Device::create([
            'school_id' => $schoolB->id,
            'code' => 'TK-SCH-B',
            'name' => 'Kit Subang #1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        $this->assertCount(1, $schoolA->devices()->where('code', 'TK-SCH-A')->get());
        $this->assertCount(0, $schoolA->devices()->where('code', 'TK-SCH-B')->get());
        $this->assertCount(1, $schoolB->devices()->where('code', 'TK-SCH-B')->get());
        $this->assertCount(0, $schoolB->devices()->where('code', 'TK-SCH-A')->get());
    }
}
