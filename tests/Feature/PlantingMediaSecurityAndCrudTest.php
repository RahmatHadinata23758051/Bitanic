<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Media;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantingMediaSecurityAndCrudTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $teacherA;

    private User $teacherB;

    private User $studentA;

    private User $superuser;

    private Device $deviceA;

    private Device $deviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create([
            'npsn' => '20200111',
            'name' => 'SMK Pertanian Negeri 1 Sukaraja',
            'slug' => 'smk-pertanian-negeri-1-sukaraja',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'npsn' => '20200222',
            'name' => 'SMK Negeri 2 Subang Pertanian',
            'slug' => 'smk-negeri-2-subang-pertanian',
            'status' => 'active',
        ]);

        $this->teacherA = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'name' => 'Guru Sukaraja',
        ]);

        $this->teacherB = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->schoolB->id,
            'name' => 'Guru Subang',
        ]);

        $this->studentA = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'name' => 'Siswa Sukaraja',
        ]);

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Superuser Agronomy',
        ]);

        $this->deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-MEDIA-A',
            'name' => 'Meja Praktik A1 Sukaraja',
            'is_active' => true,
        ]);

        $this->deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-MEDIA-B',
            'name' => 'Meja Praktik B1 Subang',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_any_media_endpoint_and_is_redirected_to_login(): void
    {
        $media = Media::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Plot Guest Check',
            'plants' => ['Cabai Rawit'],
        ]);

        $this->get('/media')->assertRedirect('/login');
        $this->get('/media/create')->assertRedirect('/login');
        $this->post('/media', ['name' => 'Test', 'plants' => ['Tomat']])->assertRedirect('/login');
        $this->get("/media/{$media->id}/edit")->assertRedirect('/login');
        $this->put("/media/{$media->id}", ['name' => 'Update', 'plants' => ['Tomat']])->assertRedirect('/login');
        $this->delete("/media/{$media->id}")->assertRedirect('/login');
    }

    public function test_student_role_is_strictly_forbidden_from_all_media_endpoints(): void
    {
        $media = Media::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Plot Student Check',
            'plants' => ['Cabai Rawit'],
        ]);

        $this->actingAs($this->studentA)->get('/media')->assertForbidden();
        $this->actingAs($this->studentA)->get('/media/create')->assertForbidden();
        $this->actingAs($this->studentA)->post('/media', ['name' => 'Hack', 'plants' => ['Tomat']])->assertForbidden();
        $this->actingAs($this->studentA)->get("/media/{$media->id}/edit")->assertForbidden();
        $this->actingAs($this->studentA)->put("/media/{$media->id}", ['name' => 'Hack', 'plants' => ['Tomat']])->assertForbidden();
        $this->actingAs($this->studentA)->delete("/media/{$media->id}")->assertForbidden();
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
            'npsn' => '20200999',
            'name' => 'SMK Inactive',
            'slug' => 'smk-inactive',
            'status' => 'inactive',
        ]);

        $teacherInactiveSchool = User::factory()->create([
            'role' => 'guru',
            'school_id' => $inactiveSchool->id,
        ]);

        $this->actingAs($teacherNoSchool)->get('/media')->assertForbidden();
        $this->actingAs($teacherNoSchool)->post('/media', ['name' => 'Fail', 'plants' => ['Cabai']])->assertForbidden();

        $this->actingAs($teacherInactiveSchool)->get('/media')->assertForbidden();
        $this->actingAs($teacherInactiveSchool)->post('/media', ['name' => 'Fail', 'plants' => ['Cabai']])->assertForbidden();
    }

    public function test_functional_views_render_successfully_for_authorized_educator(): void
    {
        $media = Media::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Bedengan Hidroponik Selada Sukaraja',
            'device_id' => $this->deviceA->id,
            'plants' => ['Selada Keriting', 'Pakcoy'],
            'plants_condition' => [
                ['tanaman' => 'Selada Keriting', 'kondisi' => 'Kondisi tanah ideal'],
            ],
            'uploaded_by' => $this->teacherA->id,
        ]);

        // 1. Index view
        $indexResponse = $this->actingAs($this->teacherA)->get('/media');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('pages.media.index');
        $indexResponse->assertSee('Bedengan Hidroponik Selada Sukaraja');
        $indexResponse->assertSee('TK-MEDIA-A');
        $indexResponse->assertSee('Selada Keriting');

        // 2. Create view
        $createResponse = $this->actingAs($this->teacherA)->get('/media/create');
        $createResponse->assertOk();
        $createResponse->assertViewIs('pages.media.create');
        $createResponse->assertSee('TK-MEDIA-A');
        $createResponse->assertDontSee('TK-MEDIA-B'); // School B device excluded

        // 3. Edit view
        $editResponse = $this->actingAs($this->teacherA)->get("/media/{$media->id}/edit");
        $editResponse->assertOk();
        $editResponse->assertViewIs('pages.media.edit');
        $editResponse->assertSee('Bedengan Hidroponik Selada Sukaraja');
        $editResponse->assertSee('TK-MEDIA-A');
        $editResponse->assertDontSee('TK-MEDIA-B');
    }

    public function test_teacher_can_create_media_tanam_with_agronomy_evaluation(): void
    {
        TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 6.20,
            'ec' => 1400.0,
            'humidity' => 68.5,
            'temperature' => 26.5,
            'nitrogen' => 85.0,
            'phosphorus' => 45.0,
            'potassium' => 120.0,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->teacherA)->post('/media', [
            'name' => 'Plot Cabai & Tomat Sukaraja',
            'device_id' => $this->deviceA->id,
            'plants' => ['Cabai Rawit', 'Tomat Cherry'],
        ]);

        $response->assertRedirect('/media');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('media', [
            'name' => 'Plot Cabai & Tomat Sukaraja',
            'school_id' => $this->schoolA->id,
            'device_id' => $this->deviceA->id,
            'uploaded_by' => $this->teacherA->id,
        ]);

        $created = Media::where('name', 'Plot Cabai & Tomat Sukaraja')->firstOrFail();
        $this->assertIsArray($created->plants);
        $this->assertCount(2, $created->plants);
        $this->assertIsArray($created->plants_condition);
        $this->assertCount(2, $created->plants_condition);
        $this->assertStringContainsString('pH 6.2', $created->plants_condition[0]['kondisi']);
    }

    public function test_teacher_can_update_media_tanam_and_reevaluate_conditions(): void
    {
        $media = Media::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Plot Awal',
            'device_id' => $this->deviceA->id,
            'plants' => ['Cabai Rawit'],
            'uploaded_by' => $this->teacherA->id,
        ]);

        TelemetryReading::create([
            'device_id' => $this->deviceA->id,
            'provenance' => 'hardware',
            'ph' => 7.10,
            'ec' => 1600.0,
            'humidity' => 72.0,
            'temperature' => 28.0,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->teacherA)->put("/media/{$media->id}", [
            'name' => 'Plot Terupdate Sukaraja',
            'device_id' => $this->deviceA->id,
            'plants' => ['Melon Golden', 'Semangka'],
        ]);

        $response->assertRedirect('/media');
        $response->assertSessionHas('success');

        $media->refresh();
        $this->assertEquals('Plot Terupdate Sukaraja', $media->name);
        $this->assertEquals(['Melon Golden', 'Semangka'], $media->plants);
        $this->assertStringContainsString('Melon Golden', $media->plants_condition[0]['tanaman']);
        $this->assertStringContainsString('pH 7.1', $media->plants_condition[0]['kondisi']);
    }

    public function test_teacher_can_delete_media_tanam(): void
    {
        $media = Media::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Plot Dihapus',
            'plants' => ['Terong Ungu'],
            'uploaded_by' => $this->teacherA->id,
        ]);

        $this->assertDatabaseHas('media', ['id' => $media->id]);

        $response = $this->actingAs($this->teacherA)->delete("/media/{$media->id}");
        $response->assertRedirect('/media');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_cross_school_idor_protection_for_edit_update_destroy(): void
    {
        $mediaB = Media::create([
            'school_id' => $this->schoolB->id,
            'name' => 'Plot Privat Sekolah B',
            'device_id' => $this->deviceB->id,
            'plants' => ['Kangkung Darat'],
            'uploaded_by' => $this->teacherB->id,
        ]);

        // Teacher A cannot edit School B's media
        $this->actingAs($this->teacherA)
            ->get("/media/{$mediaB->id}/edit")
            ->assertNotFound();

        // Teacher A cannot update School B's media
        $this->actingAs($this->teacherA)
            ->put("/media/{$mediaB->id}", [
                'name' => 'IDOR Overwrite',
                'plants' => ['Bawang Merah'],
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('media', [
            'id' => $mediaB->id,
            'name' => 'Plot Privat Sekolah B',
        ]);

        // Teacher A cannot delete School B's media
        $this->actingAs($this->teacherA)
            ->delete("/media/{$mediaB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('media', ['id' => $mediaB->id]);
    }

    public function test_device_selection_is_strictly_scoped_to_teacher_school(): void
    {
        // Teacher A attempts to bind Device B (belongs to School B)
        $response = $this->actingAs($this->teacherA)->post('/media', [
            'name' => 'Cross-School Device Binding Attempt',
            'device_id' => $this->deviceB->id,
            'plants' => ['Cabai Rawit'],
        ]);

        $response->assertSessionHasErrors('device_id');
        $this->assertDatabaseMissing('media', ['name' => 'Cross-School Device Binding Attempt']);
    }

    public function test_telemetry_reading_is_isolated_to_school_devices_and_never_leaks_cross_school(): void
    {
        // Device B has acidic readings
        TelemetryReading::create([
            'device_id' => $this->deviceB->id,
            'provenance' => 'hardware',
            'ph' => 4.10,
            'humidity' => 30.0,
            'temperature' => 38.0,
            'recorded_at' => now(),
        ]);

        // School A creates media without specifying a device (should use baseline, NOT School B telemetry!)
        $response = $this->actingAs($this->teacherA)->post('/media', [
            'name' => 'Plot Sukaraja No Workstation',
            'device_id' => null,
            'plants' => ['Cabai Rawit'],
        ]);

        $response->assertRedirect('/media');

        $created = Media::where('name', 'Plot Sukaraja No Workstation')->firstOrFail();
        $this->assertStringNotContainsString('pH 4.1', $created->plants_condition[0]['kondisi']);
        $this->assertStringContainsString('Data telemetri sensor stasiun belum tercatat', $created->plants_condition[0]['kondisi']);
    }

    public function test_superuser_has_management_access_to_media(): void
    {
        $media = Media::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Plot Superuser Sukaraja',
            'plants' => ['Cabai Rawit'],
        ]);

        $this->actingAs($this->superuser)->get('/media')->assertOk();
        $this->actingAs($this->superuser)->get('/media/create')->assertOk();
        $this->actingAs($this->superuser)->get("/media/{$media->id}/edit")->assertOk();
    }
}
