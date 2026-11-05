<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainerKitManagerTenancyAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $teacherA;

    private User $teacherB;

    private User $superuser;

    private Device $deviceA;

    private Device $deviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::query()->where('status', 'active')->orderBy('id')->first() ?? School::create([
            'npsn' => '40400101',
            'name' => 'SMK Negeri 1 Pertanian',
            'slug' => 'smk-negeri-1-pertanian',
            'status' => 'active',
        ]);

        $this->schoolB = School::where('id', '!=', $this->schoolA->id)->first() ?? School::create([
            'npsn' => '40400102',
            'name' => 'SMK Negeri 2 Bioindustri',
            'slug' => 'smk-negeri-2-bioindustri',
            'status' => 'active',
        ]);
        $this->schoolB->update(['status' => 'active']);

        $this->teacherA = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'name' => 'Guru SMK A',
        ]);

        $this->teacherB = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolB->id,
            'name' => 'Guru SMK B',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Superuser Pusat',
        ]);

        $this->deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-FLEET-A1',
            'name' => 'Trainer Kit Lab A1',
            'is_active' => true,
        ]);
        DeviceApiKey::generateForDevice($this->deviceA);

        $this->deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-FLEET-B1',
            'name' => 'Trainer Kit Lab B1',
            'is_active' => true,
        ]);
        DeviceApiKey::generateForDevice($this->deviceB);
    }

    public function test_guest_can_access_kits_index_and_sees_default_school_kits(): void
    {
        $response = $this->get(route('kits.index'));
        $response->assertOk();
        $response->assertSee('TK-FLEET-A1');
        $response->assertDontSee('TK-FLEET-B1');
    }

    public function test_teacher_without_school_or_with_inactive_school_fails_closed(): void
    {
        $teacherNoSchool = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
        ]);
        DB::table('users')->where('id', $teacherNoSchool->id)->update(['school_id' => null]);
        $teacherNoSchool->refresh();

        $inactiveSchool = School::create([
            'npsn' => '40400999',
            'name' => 'SMK Inaktif Pertanian',
            'slug' => 'smk-inaktif-pertanian',
            'status' => 'inactive',
        ]);
        $teacherInactiveSchool = User::factory()->create([
            'role' => 'guru',
            'school_id' => $inactiveSchool->id,
        ]);

        $this->actingAs($teacherNoSchool)->get(route('kits.index'))->assertForbidden();
        $this->actingAs($teacherInactiveSchool)->get(route('kits.index'))->assertForbidden();
    }

    public function test_teacher_only_sees_their_own_school_kits(): void
    {
        $responseA = $this->actingAs($this->teacherA)->get(route('kits.index'));
        $responseA->assertOk();
        $responseA->assertSee('TK-FLEET-A1');
        $responseA->assertDontSee('TK-FLEET-B1');

        $responseB = $this->actingAs($this->teacherB)->get(route('kits.index'));
        $responseB->assertOk();
        $responseB->assertSee('TK-FLEET-B1');
        $responseB->assertDontSee('TK-FLEET-A1');
    }

    public function test_superuser_can_filter_trainer_kits_by_school(): void
    {
        $responseB = $this->actingAs($this->superuser)->get(route('kits.index', ['school_id' => $this->schoolB->id]));
        $responseB->assertOk();
        $responseB->assertSee('TK-FLEET-B1');
        $responseB->assertDontSee('TK-FLEET-A1');

        $responseAll = $this->actingAs($this->superuser)->get(route('kits.index'));
        $responseAll->assertOk();
        $responseAll->assertSee('TK-FLEET-A1');
        $responseAll->assertSee('TK-FLEET-B1');
    }

    public function test_teacher_cannot_reboot_device_from_another_school(): void
    {
        // Teacher A attempts to reboot device belonging to School B
        $response = $this->actingAs($this->teacherA)->post(route('kits.reboot', $this->deviceB));
        $response->assertForbidden();

        // Teacher A reboots own device
        $responseOwn = $this->actingAs($this->teacherA)->post(route('kits.reboot', $this->deviceA));
        $responseOwn->assertRedirect(route('kits.index'));
        $responseOwn->assertSessionHas('warning');
    }

    public function test_teacher_cannot_calibrate_device_from_another_school(): void
    {
        $payload = [
            'phOffset' => 0.2,
            'moistureOffset' => -1.5,
            'ecOffset' => 50,
            'reason' => 'Uji kalibrasi sensor meja',
        ];

        // Teacher A attempts to calibrate device belonging to School B
        $response = $this->actingAs($this->teacherA)->post(route('kits.calibration', $this->deviceB), $payload);
        $response->assertForbidden();

        // Teacher A calibrates own device
        $responseOwn = $this->actingAs($this->teacherA)->post(route('kits.calibration', $this->deviceA), $payload);
        $responseOwn->assertRedirect(route('kits.index'));
        $responseOwn->assertSessionHas('success');
    }

    public function test_non_superuser_cannot_rotate_api_key(): void
    {
        $response = $this->actingAs($this->teacherA)->post(route('kits.api-key.rotate', $this->deviceA));
        $response->assertForbidden();
    }
}
