<?php

namespace Tests\Feature;

use App\Actions\Hardware\ActivateTrainerKitAction;
use App\Actions\Hardware\AssignTrainerKitDeviceAction;
use App\Actions\Hardware\RecordTrainerKitSaleAction;
use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use App\Models\TrainerKitSaleRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainerKitInventoryMasterTest extends TestCase
{
    use RefreshDatabase;

    protected School $schoolA;

    protected School $schoolB;

    protected User $superuser;

    protected User $adminSekolahA;

    protected User $adminSekolahB;

    protected User $guruA;

    protected User $siswaA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create([
            'name' => 'SMK Pertanian A',
            'slug' => 'smk-pertanian-a',
            'npsn' => '20100001',
            'address' => 'Jl. Agronomi No. 1',
            'city' => 'Sukaraja',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'name' => 'SMK Pertanian B',
            'slug' => 'smk-pertanian-b',
            'npsn' => '20100002',
            'address' => 'Jl. Hortikultura No. 2',
            'city' => 'Lembang',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $this->superuser = User::factory()->create([
            'name' => 'Super Administrator',
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

        $this->adminSekolahB = User::factory()->create([
            'name' => 'Admin Sekolah B',
            'email' => 'admin.b@bitanic.test',
            'role' => 'admin_sekolah',
            'school_id' => $this->schoolB->id,
            'is_active' => true,
        ]);

        $this->guruA = User::factory()->create([
            'name' => 'Guru A',
            'email' => 'guru.a@bitanic.test',
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);

        $this->siswaA = User::factory()->create([
            'name' => 'Siswa A',
            'email' => 'siswa.a@bitanic.test',
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);
    }

    public function test_superuser_can_view_global_trainer_kit_inventory_master(): void
    {
        TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-TEST01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'registered',
        ]);

        $response = $this->actingAs($this->superuser)->get(route('master.trainer-kits.index'));

        $response->assertStatus(200);
        $response->assertSee('Master Serial & Inventaris Trainer Kit');
        $response->assertSee('BTK-2026-TEST01');
    }

    public function test_tenant_admin_and_guru_cannot_access_global_trainer_kit_master(): void
    {
        $responseAdmin = $this->actingAs($this->adminSekolahA)->get(route('master.trainer-kits.index'));
        $responseAdmin->assertStatus(403);

        $responseGuru = $this->actingAs($this->guruA)->get(route('master.trainer-kits.index'));
        $responseGuru->assertStatus(403);

        $responseSiswa = $this->actingAs($this->siswaA)->get(route('master.trainer-kits.index'));
        $responseSiswa->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login_from_global_inventory_master(): void
    {
        $response = $this->get(route('master.trainer-kits.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_superuser_can_register_new_factory_serial(): void
    {
        $payload = [
            'serial_number' => 'BTK-2026-NEW01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'hardware_revision' => 'rev1.2',
            'batch_number' => 'BATCH-2026-Q1',
            'notes' => 'Unit uji coba QC pabrik',
        ];

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('trainer_kit_inventories', [
            'serial_number' => 'BTK-2026-NEW01',
            'status' => 'registered',
            'school_id' => null,
            'device_id' => null,
        ]);

        $this->assertDatabaseHas('trainer_kit_lifecycle_logs', [
            'action' => 'REGISTRATION',
            'to_status' => 'registered',
        ]);
    }

    public function test_duplicate_serial_number_registration_is_rejected(): void
    {
        TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-EXISTING',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'registered',
        ]);

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.store'), [
            'serial_number' => 'BTK-2026-EXISTING',
            'model_name' => 'Bitanic Trainer Kit v1.0',
        ]);

        $response->assertSessionHasErrors('serial_number');
    }

    public function test_superuser_can_record_sale_and_transfer_kit_to_school(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-SALE01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'registered',
        ]);

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.sale', $kit), [
            'school_id' => $this->schoolA->id,
            'sale_reference' => 'PO-2026-SMKA-01',
            'sold_at' => now()->toDateString(),
            'notes' => 'Pengadaan gelombang 1',
        ]);

        $response->assertRedirect(route('master.trainer-kits.show', $kit));

        $kit->refresh();
        $this->assertEquals($this->schoolA->id, $kit->school_id);
        $this->assertEquals(TrainerKitInventory::STATUS_SOLD, $kit->status);

        $this->assertDatabaseHas('trainer_kit_sale_records', [
            'inventory_id' => $kit->id,
            'school_id' => $this->schoolA->id,
            'sale_reference' => 'PO-2026-SMKA-01',
        ]);

        $this->assertDatabaseHas('trainer_kit_lifecycle_logs', [
            'inventory_id' => $kit->id,
            'action' => 'SALE_RECORDED',
            'to_school_id' => $this->schoolA->id,
        ]);
    }

    public function test_cannot_assign_device_from_different_school_than_kit_ownership(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-CROSS01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'sold',
            'school_id' => $this->schoolA->id,
        ]);

        // Device belongs to School B
        $deviceB = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-B01',
            'name' => 'Workstation School B',
            'is_active' => true,
        ]);

        $action = app(AssignTrainerKitDeviceAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($kit, ['device_id' => $deviceB->id]);
    }

    public function test_cannot_assign_device_already_bound_to_another_active_kit(): void
    {
        $deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-A01',
            'name' => 'Workstation 1',
            'is_active' => true,
        ]);

        $kit1 = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-BOUND1',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'assigned',
            'school_id' => $this->schoolA->id,
            'device_id' => $deviceA->id,
        ]);

        $kit2 = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-BOUND2',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'sold',
            'school_id' => $this->schoolA->id,
        ]);

        $action = app(AssignTrainerKitDeviceAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($kit2, ['device_id' => $deviceA->id]);
    }

    public function test_successful_device_binding_updates_status_and_logs_lifecycle(): void
    {
        $deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-A02',
            'name' => 'Workstation 2',
            'is_active' => true,
        ]);

        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-SUCCESS01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'sold',
            'school_id' => $this->schoolA->id,
        ]);

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.assign', $kit), [
            'device_id' => $deviceA->id,
        ]);

        $response->assertRedirect(route('master.trainer-kits.show', $kit));

        $kit->refresh();
        $this->assertEquals($deviceA->id, $kit->device_id);
        $this->assertEquals(TrainerKitInventory::STATUS_ASSIGNED, $kit->status);

        $this->assertDatabaseHas('trainer_kit_lifecycle_logs', [
            'inventory_id' => $kit->id,
            'action' => 'DEVICE_ASSIGNED',
            'to_device_id' => $deviceA->id,
        ]);
    }

    public function test_activation_sets_status_activated_and_is_idempotent(): void
    {
        $deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-A03',
            'name' => 'Workstation 3',
            'is_active' => false,
        ]);

        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-ACTIVATE01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'assigned',
            'school_id' => $this->schoolA->id,
            'device_id' => $deviceA->id,
        ]);

        // First activation
        $response1 = $this->actingAs($this->superuser)->post(route('master.trainer-kits.activate', $kit));
        $response1->assertRedirect(route('master.trainer-kits.show', $kit));

        $kit->refresh();
        $deviceA->refresh();
        $this->assertEquals(TrainerKitInventory::STATUS_ACTIVATED, $kit->status);
        $this->assertTrue($deviceA->is_active);

        $initialLogsCount = TrainerKitLifecycleLog::where('inventory_id', $kit->id)->where('action', 'ACTIVATED')->count();
        $this->assertEquals(1, $initialLogsCount);

        // Second activation (Idempotent)
        $response2 = $this->actingAs($this->superuser)->post(route('master.trainer-kits.activate', $kit));
        $response2->assertRedirect(route('master.trainer-kits.show', $kit));

        $afterLogsCount = TrainerKitLifecycleLog::where('inventory_id', $kit->id)->where('action', 'ACTIVATED')->count();
        $this->assertEquals(1, $afterLogsCount); // No duplicate log
    }

    public function test_decommissioned_kit_cannot_be_sold_or_activated(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-DECOM01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_DECOMMISSIONED,
            'school_id' => $this->schoolA->id,
        ]);

        $saleAction = app(RecordTrainerKitSaleAction::class);
        $activateAction = app(ActivateTrainerKitAction::class);

        $this->expectException(ValidationException::class);
        $saleAction->execute($kit, [
            'school_id' => $this->schoolB->id,
            'sale_reference' => 'PO-TRY',
        ]);
    }

    public function test_registered_factory_stock_without_sales_can_be_deleted(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-DEL01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'registered',
        ]);

        $response = $this->actingAs($this->superuser)->delete(route('master.trainer-kits.destroy', $kit));

        $response->assertRedirect(route('master.trainer-kits.index'));
        $this->assertDatabaseMissing('trainer_kit_inventories', [
            'id' => $kit->id,
        ]);
    }

    public function test_kit_with_sales_or_active_status_cannot_be_deleted(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-NODEL01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'sold',
            'school_id' => $this->schoolA->id,
        ]);

        TrainerKitSaleRecord::create([
            'inventory_id' => $kit->id,
            'school_id' => $this->schoolA->id,
            'sale_reference' => 'PO-IMMUTABLE',
        ]);

        $response = $this->actingAs($this->superuser)->delete(route('master.trainer-kits.destroy', $kit));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('trainer_kit_inventories', [
            'id' => $kit->id,
        ]);
    }

    public function test_telemetry_history_persists_intact_when_kit_reassigned(): void
    {
        $deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-A04',
            'name' => 'Workstation 4',
            'is_active' => true,
        ]);

        $telemetry = TelemetryReading::create([
            'device_id' => $deviceA->id,
            'sensor_type' => 'soil',
            'nitrogen' => 45.0,
            'phosphorus' => 30.0,
            'potassium' => 120.0,
            'ph' => 6.5,
            'temperature' => 25.0,
            'humidity' => 60.0,
            'ec' => 1.2,
            'recorded_at' => now(),
            'provenance' => 'hardware',
        ]);

        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-HIST01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'assigned',
            'school_id' => $this->schoolA->id,
            'device_id' => $deviceA->id,
        ]);

        // Unassign kit from device
        $assignAction = app(AssignTrainerKitDeviceAction::class);
        $assignAction->execute($kit, ['device_id' => null]);

        $kit->refresh();
        $this->assertNull($kit->device_id);

        // Verify telemetry record is unaffected
        $this->assertDatabaseHas('telemetry_readings', [
            'id' => $telemetry->id,
            'device_id' => $deviceA->id,
            'nitrogen' => 45.0,
        ]);
    }

    public function test_admin_sekolah_can_see_serial_number_on_own_school_workstation(): void
    {
        $deviceA = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-A05',
            'name' => 'Workstation 5',
            'is_active' => true,
        ]);

        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-VIEW01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => 'activated',
            'school_id' => $this->schoolA->id,
            'device_id' => $deviceA->id,
        ]);

        $response = $this->actingAs($this->adminSekolahA)->get(route('kits.index'));

        $response->assertStatus(200);
        $response->assertSee('BTK-2026-VIEW01');
    }
}
