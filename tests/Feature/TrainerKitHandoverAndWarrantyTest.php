<?php

namespace Tests\Feature;

use App\Actions\Hardware\RecordTrainerKitHandoverAction;
use App\Actions\Hardware\RecordTrainerKitSaleAction;
use App\Models\Device;
use App\Models\School;
use App\Models\TrainerKitHandover;
use App\Models\TrainerKitInventory;
use App\Models\TrainerKitLifecycleLog;
use App\Models\TrainerKitSaleRecord;
use App\Models\TrainerKitWarrantyLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainerKitHandoverAndWarrantyTest extends TestCase
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
            'name' => 'SMK Pertanian Negeri 1',
            'slug' => 'smk-pertanian-negeri-1',
            'npsn' => '20100011',
            'address' => 'Jl. Agronomi No. 1',
            'city' => 'Sukaraja',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $this->schoolB = School::create([
            'name' => 'SMK Pertanian Swasta 2',
            'slug' => 'smk-pertanian-swasta-2',
            'npsn' => '20100012',
            'address' => 'Jl. Hortikultura No. 2',
            'city' => 'Lembang',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $this->superuser = User::factory()->create([
            'name' => 'Super Administrator Platform',
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
            'name' => 'Guru Agronomi A',
            'email' => 'guru.a@bitanic.test',
            'role' => 'guru',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);

        $this->siswaA = User::factory()->create([
            'name' => 'Siswa Praktikan A',
            'email' => 'siswa.a@bitanic.test',
            'role' => 'siswa',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);
    }

    public function test_superuser_can_record_physical_handover_with_one_calendar_year_warranty(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-HND01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
        ]);

        $handoverDate = Carbon::today()->subDays(5)->toDateString();

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.handover', $kit), [
            'bast_number' => 'BAST/2026/TK-01/SMK1',
            'handover_date' => $handoverDate,
            'recipient_name' => 'Bambang Suryo, S.Pd',
            'recipient_title' => 'Kepala Laboratorium IoT',
            'recipient_phone' => '0812-3456-7890',
            'start_warranty_now' => 1,
            'notes' => 'Serah terima kit lengkap 7-in-1 modbus dan relai.',
        ]);

        $response->assertRedirect(route('master.trainer-kits.show', $kit));
        $response->assertSessionHas('status');

        $kit->refresh();
        $this->assertEquals($handoverDate, $kit->handover_date->toDateString());
        $this->assertEquals($handoverDate, $kit->warranty_start_date->toDateString());

        // 1 calendar year calculation
        $expectedExpiration = Carbon::parse($handoverDate)->addYearNoOverflow()->toDateString();
        $this->assertEquals($expectedExpiration, $kit->warranty_expiration_date->toDateString());
        $this->assertEquals('active', $kit->warranty_status);
        $this->assertEquals('Aktif', $kit->warranty_status_label);

        $this->assertDatabaseHas('trainer_kit_handovers', [
            'inventory_id' => $kit->id,
            'school_id' => $this->schoolA->id,
            'bast_number' => 'BAST/2026/TK-01/SMK1',
            'recipient_name' => 'Bambang Suryo, S.Pd',
        ]);

        $this->assertDatabaseHas('trainer_kit_lifecycle_logs', [
            'inventory_id' => $kit->id,
            'action' => 'HANDOVER_RECORDED',
        ]);

        $this->assertDatabaseHas('trainer_kit_lifecycle_logs', [
            'inventory_id' => $kit->id,
            'action' => 'WARRANTY_COMMENCED',
        ]);
    }

    public function test_warranty_duration_calculates_exact_one_calendar_year_with_leap_year(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2024-LEAP01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
        ]);

        $action = app(RecordTrainerKitHandoverAction::class);

        // Leap year date: 2024-02-29 + 1 year => 2025-02-28
        $handover = $action->execute($kit, [
            'bast_number' => 'BAST/LEAP/2024',
            'handover_date' => '2024-02-29',
            'recipient_name' => 'Petugas Lab',
            'start_warranty_now' => true,
        ]);

        $this->assertEquals('2024-02-29', $handover->warranty_start_date->toDateString());
        $this->assertEquals('2025-02-28', $handover->warranty_expiration_date->toDateString());

        $kit->refresh();
        $this->assertEquals('2025-02-28', $kit->warranty_expiration_date->toDateString());
    }

    public function test_handover_requires_valid_bast_number_and_recipient(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-VAL01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
        ]);

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.handover', $kit), [
            'bast_number' => '',
            'handover_date' => now()->toDateString(),
            'recipient_name' => '',
        ]);

        $response->assertSessionHasErrors(['bast_number', 'recipient_name']);
    }

    public function test_handover_rejects_future_dates(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-FUT01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
        ]);

        $futureDate = Carbon::today()->addDays(10)->toDateString();

        $action = app(RecordTrainerKitHandoverAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($kit, [
            'bast_number' => 'BAST/FUTURE/01',
            'handover_date' => $futureDate,
            'recipient_name' => 'Petugas Lab',
        ]);
    }

    public function test_handover_rejects_unowned_factory_stock_or_decommissioned_kits(): void
    {
        $stockKit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-STOCK01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_REGISTERED,
            'school_id' => null,
        ]);

        $action = app(RecordTrainerKitHandoverAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($stockKit, [
            'bast_number' => 'BAST/STOCK/01',
            'handover_date' => now()->toDateString(),
            'recipient_name' => 'Petugas',
        ]);
    }

    public function test_duplicate_handover_registration_is_rejected(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-DUP01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
        ]);

        $action = app(RecordTrainerKitHandoverAction::class);

        $action->execute($kit, [
            'bast_number' => 'BAST/ORIGINAL/01',
            'handover_date' => now()->toDateString(),
            'recipient_name' => 'Petugas Asli',
        ]);

        $this->expectException(ValidationException::class);
        $action->execute($kit, [
            'bast_number' => 'BAST/DUPLICATE/02',
            'handover_date' => now()->toDateString(),
            'recipient_name' => 'Petugas Duplikat',
        ]);
    }

    public function test_superuser_can_correct_warranty_dates_with_mandatory_reason_and_audit_log(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-CORR01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
            'handover_date' => '2026-01-10',
            'warranty_start_date' => '2026-01-10',
            'warranty_expiration_date' => '2027-01-10',
        ]);

        TrainerKitHandover::create([
            'inventory_id' => $kit->id,
            'school_id' => $this->schoolA->id,
            'bast_number' => 'BAST/2026/CORR-01',
            'handover_date' => '2026-01-10',
            'recipient_name' => 'Petugas Lab BAST',
            'warranty_start_date' => '2026-01-10',
            'warranty_expiration_date' => '2027-01-10',
            'recorded_by' => $this->superuser->id,
        ]);

        $newStart = '2026-02-01';
        $reason = 'Koreksi penyesuaian tanggal komisioning lapangan sesuai addendum BAST nomor 02.';

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.warranty.correct', $kit), [
            'warranty_start_date' => $newStart,
            'correction_reason' => $reason,
        ]);

        $response->assertRedirect(route('master.trainer-kits.show', $kit));
        $response->assertSessionHas('status');

        $kit->refresh();
        $this->assertEquals($newStart, $kit->warranty_start_date->toDateString());
        $this->assertEquals('2027-02-01', $kit->warranty_expiration_date->toDateString());

        $this->assertDatabaseHas('trainer_kit_warranty_logs', [
            'inventory_id' => $kit->id,
            'reason' => $reason,
        ]);

        $log = TrainerKitWarrantyLog::where('inventory_id', $kit->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals($newStart, $log->new_start_date->toDateString());
        $this->assertEquals('2027-02-01', $log->new_expiration_date->toDateString());

        $this->assertDatabaseHas('trainer_kit_lifecycle_logs', [
            'inventory_id' => $kit->id,
            'action' => 'WARRANTY_CORRECTED',
        ]);
    }

    public function test_warranty_correction_rejects_empty_or_short_reason(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-SHORT01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
            'handover_date' => '2026-01-10',
            'warranty_start_date' => '2026-01-10',
            'warranty_expiration_date' => '2027-01-10',
        ]);

        TrainerKitHandover::create([
            'inventory_id' => $kit->id,
            'school_id' => $this->schoolA->id,
            'bast_number' => 'BAST/2026/SHORT-01',
            'handover_date' => '2026-01-10',
            'recipient_name' => 'Petugas Lab BAST',
            'warranty_start_date' => '2026-01-10',
            'warranty_expiration_date' => '2027-01-10',
            'recorded_by' => $this->superuser->id,
        ]);

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.warranty.correct', $kit), [
            'warranty_start_date' => '2026-02-01',
            'correction_reason' => 'abc', // less than 5 chars
        ]);

        $response->assertSessionHasErrors(['correction_reason']);
    }

    public function test_warranty_correction_rejected_when_no_bast_handover_exists(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-NOBAST01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
            'handover_date' => null,
            'warranty_start_date' => null,
            'warranty_expiration_date' => null,
        ]);

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.warranty.correct', $kit), [
            'warranty_start_date' => '2026-02-01',
            'correction_reason' => 'Koreksi tanggal garansi tanpa serah terima fisik sah.',
        ]);

        $response->assertSessionHasErrors(['warranty_start_date']);

        $kit->refresh();
        $this->assertNull($kit->warranty_start_date);
        $this->assertNull($kit->warranty_expiration_date);
        $this->assertDatabaseMissing('trainer_kit_warranty_logs', [
            'inventory_id' => $kit->id,
        ]);
    }

    public function test_warranty_correction_rejected_when_school_ownership_is_inconsistent(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-INCONSIST01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
            'handover_date' => '2026-01-10',
            'warranty_start_date' => '2026-01-10',
            'warranty_expiration_date' => '2027-01-10',
        ]);

        // Handover points to schoolB while kit is owned by schoolA
        TrainerKitHandover::create([
            'inventory_id' => $kit->id,
            'school_id' => $this->schoolB->id,
            'bast_number' => 'BAST/2026/INCONSIST-01',
            'handover_date' => '2026-01-10',
            'recipient_name' => 'Petugas Lab Asing',
            'warranty_start_date' => '2026-01-10',
            'warranty_expiration_date' => '2027-01-10',
            'recorded_by' => $this->superuser->id,
        ]);

        $response = $this->actingAs($this->superuser)->post(route('master.trainer-kits.warranty.correct', $kit), [
            'warranty_start_date' => '2026-02-01',
            'correction_reason' => 'Koreksi dengan data kepemilikan sekolah inkonsisten.',
        ]);

        $response->assertSessionHasErrors(['school_id']);

        $kit->refresh();
        $this->assertEquals('2026-01-10', $kit->warranty_start_date->toDateString());
        $this->assertDatabaseMissing('trainer_kit_warranty_logs', [
            'inventory_id' => $kit->id,
            'reason' => 'Koreksi dengan data kepemilikan sekolah inkonsisten.',
        ]);
    }

    public function test_future_warranty_start_date_evaluates_to_pending_not_active(): void
    {
        $futureStart = Carbon::today()->addDays(14)->toDateString();
        $futureExp = Carbon::parse($futureStart)->addYearNoOverflow()->toDateString();

        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-FUTURE01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
            'handover_date' => Carbon::today()->toDateString(),
            'warranty_start_date' => $futureStart,
            'warranty_expiration_date' => $futureExp,
        ]);

        $this->assertEquals('pending', $kit->warranty_status);
        $this->assertEquals('Menunggu Efektif', $kit->warranty_status_label);
        $this->assertStringContainsString('amber', $kit->warranty_status_badge_class);
    }

    public function test_unapproved_resale_and_transfer_is_rejected_device_sale_state_01(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-RESALE01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
        ]);

        TrainerKitSaleRecord::create([
            'inventory_id' => $kit->id,
            'school_id' => $this->schoolA->id,
            'sale_reference' => 'PO-ORIGINAL-01',
            'sold_at' => now(),
        ]);

        $action = app(RecordTrainerKitSaleAction::class);

        // Attempting to sell/transfer to School B must be rejected
        $this->expectException(ValidationException::class);
        $action->execute($kit, [
            'school_id' => $this->schoolB->id,
            'sale_reference' => 'PO-UNAPPROVED-TRANSFER',
        ]);
    }

    public function test_idempotent_sale_retry_succeeds_without_error_or_duplicate_logs_device_sale_state_01(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-RETRY01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_REGISTERED,
            'school_id' => null,
        ]);

        $action = app(RecordTrainerKitSaleAction::class);

        // 1. First execution
        $firstResult = $action->execute($kit, [
            'school_id' => $this->schoolA->id,
            'sale_reference' => 'PO-IDEMPOTENT-01',
            'notes' => 'Pencatatan pertama',
        ]);

        $this->assertEquals(TrainerKitInventory::STATUS_SOLD, $firstResult->status);
        $this->assertEquals($this->schoolA->id, $firstResult->school_id);
        $this->assertEquals(1, TrainerKitSaleRecord::where('inventory_id', $kit->id)->count());
        $this->assertEquals(1, TrainerKitLifecycleLog::where('inventory_id', $kit->id)->where('action', 'SALE_RECORDED')->count());

        // 2. Exact same sale retry
        $secondResult = $action->execute($kit, [
            'school_id' => $this->schoolA->id,
            'sale_reference' => 'PO-IDEMPOTENT-01',
            'notes' => 'Pencatatan kedua (retry)',
        ]);

        $this->assertEquals($firstResult->id, $secondResult->id);
        // Verify no duplicate records created
        $this->assertEquals(1, TrainerKitSaleRecord::where('inventory_id', $kit->id)->count());
        $this->assertEquals(1, TrainerKitLifecycleLog::where('inventory_id', $kit->id)->where('action', 'SALE_RECORDED')->count());
    }

    public function test_computed_warranty_statuses_active_expired_not_recorded(): void
    {
        // 1. Not recorded
        $kitUnrecorded = TrainerKitInventory::create([
            'serial_number' => 'BTK-STATUS-01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_REGISTERED,
        ]);
        $this->assertEquals('not_recorded', $kitUnrecorded->warranty_status);
        $this->assertEquals('Belum Dicatat', $kitUnrecorded->warranty_status_label);

        // 2. Active warranty
        $kitActive = TrainerKitInventory::create([
            'serial_number' => 'BTK-STATUS-02',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_ACTIVATED,
            'school_id' => $this->schoolA->id,
            'warranty_start_date' => Carbon::today()->subMonths(3)->toDateString(),
            'warranty_expiration_date' => Carbon::today()->addMonths(9)->toDateString(),
        ]);
        $this->assertEquals('active', $kitActive->warranty_status);
        $this->assertEquals('Aktif', $kitActive->warranty_status_label);

        // 3. Expired warranty
        $kitExpired = TrainerKitInventory::create([
            'serial_number' => 'BTK-STATUS-03',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_ACTIVATED,
            'school_id' => $this->schoolA->id,
            'warranty_start_date' => Carbon::today()->subYears(2)->toDateString(),
            'warranty_expiration_date' => Carbon::today()->subYear()->toDateString(),
        ]);
        $this->assertEquals('expired', $kitExpired->warranty_status);
        $this->assertEquals('Kedaluwarsa', $kitExpired->warranty_status_label);
    }

    public function test_admin_sekolah_can_view_own_school_trainer_kit_warranty_information(): void
    {
        $deviceA = Device::create([
            'code' => 'TK-OWN-01',
            'name' => 'Workstation Lab A',
            'school_id' => $this->schoolA->id,
            'is_active' => true,
        ]);

        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-OWN01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_ACTIVATED,
            'school_id' => $this->schoolA->id,
            'device_id' => $deviceA->id,
            'handover_date' => '2026-03-01',
            'warranty_start_date' => '2026-03-01',
            'warranty_expiration_date' => '2027-03-01',
        ]);

        TrainerKitHandover::create([
            'inventory_id' => $kit->id,
            'school_id' => $this->schoolA->id,
            'bast_number' => 'BAST-OWN-001',
            'handover_date' => '2026-03-01',
            'recipient_name' => 'Drs. Supriyanto',
            'warranty_start_date' => '2026-03-01',
            'warranty_expiration_date' => '2027-03-01',
        ]);

        $response = $this->actingAs($this->adminSekolahA)->get(route('kits.index'));
        $response->assertStatus(200);
        $response->assertSee('BTK-2026-OWN01');
        $response->assertSee('BAST-OWN-001');
        $response->assertSee('Aktif');
    }

    public function test_admin_sekolah_cannot_view_or_mutate_other_school_warranty_idor_protection(): void
    {
        $kitB = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-SCHB01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_ACTIVATED,
            'school_id' => $this->schoolB->id,
            'handover_date' => '2026-03-01',
            'warranty_start_date' => '2026-03-01',
            'warranty_expiration_date' => '2027-03-01',
        ]);

        // 1. Direct mutation attempt on other school's kit
        $response = $this->actingAs($this->adminSekolahA)->post(route('master.trainer-kits.warranty.correct', $kitB), [
            'warranty_start_date' => '2026-04-01',
            'correction_reason' => 'Percobaan tampering dari admin sekolah A',
        ]);
        $response->assertStatus(403);

        // 2. Direct handover attempt on other school's kit
        $responseHandover = $this->actingAs($this->adminSekolahA)->post(route('master.trainer-kits.handover', $kitB), [
            'bast_number' => 'BAST-ILLEGAL',
            'handover_date' => now()->toDateString(),
            'recipient_name' => 'Hacker',
        ]);
        $responseHandover->assertStatus(403);
    }

    public function test_guru_and_siswa_cannot_mutate_handover_or_warranty(): void
    {
        $kit = TrainerKitInventory::create([
            'serial_number' => 'BTK-2026-MUT01',
            'model_name' => 'Bitanic Trainer Kit v1.0',
            'status' => TrainerKitInventory::STATUS_SOLD,
            'school_id' => $this->schoolA->id,
        ]);

        $this->actingAs($this->guruA)
            ->post(route('master.trainer-kits.handover', $kit), [
                'bast_number' => 'BAST-GURU-01',
                'handover_date' => now()->toDateString(),
                'recipient_name' => 'Guru A',
            ])->assertStatus(403);

        $this->actingAs($this->siswaA)
            ->post(route('master.trainer-kits.handover', $kit), [
                'bast_number' => 'BAST-SISWA-01',
                'handover_date' => now()->toDateString(),
                'recipient_name' => 'Siswa A',
            ])->assertStatus(403);
    }

    public function test_filter_by_warranty_status_in_master_inventory_index(): void
    {
        TrainerKitInventory::create([
            'serial_number' => 'BTK-WARR-ACTIVE',
            'model_name' => 'Kit Aktif',
            'status' => TrainerKitInventory::STATUS_ACTIVATED,
            'school_id' => $this->schoolA->id,
            'warranty_start_date' => now()->subMonth()->toDateString(),
            'warranty_expiration_date' => now()->addMonths(11)->toDateString(),
        ]);

        TrainerKitInventory::create([
            'serial_number' => 'BTK-WARR-EXPIRED',
            'model_name' => 'Kit Kedaluwarsa',
            'status' => TrainerKitInventory::STATUS_ACTIVATED,
            'school_id' => $this->schoolA->id,
            'warranty_start_date' => now()->subYears(2)->toDateString(),
            'warranty_expiration_date' => now()->subYear()->toDateString(),
        ]);

        // 1. Filter active
        $resActive = $this->actingAs($this->superuser)->get(route('master.trainer-kits.index', ['warranty_status' => 'active']));
        $resActive->assertStatus(200);
        $resActive->assertSee('BTK-WARR-ACTIVE');
        $resActive->assertDontSee('BTK-WARR-EXPIRED');

        // 2. Filter expired
        $resExpired = $this->actingAs($this->superuser)->get(route('master.trainer-kits.index', ['warranty_status' => 'expired']));
        $resExpired->assertStatus(200);
        $resExpired->assertSee('BTK-WARR-EXPIRED');
        $resExpired->assertDontSee('BTK-WARR-ACTIVE');
    }
}
