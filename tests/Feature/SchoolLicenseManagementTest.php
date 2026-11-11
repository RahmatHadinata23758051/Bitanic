<?php

namespace Tests\Feature;

use App\Actions\License\ActivateFirstYearLicenseAction;
use App\Actions\License\InitializeSchoolLicenseAction;
use App\Actions\License\RenewSchoolLicenseAction;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseLog;
use App\Models\SchoolLicenseTerm;
use App\Models\TrainerKitInventory;
use App\Models\TrainerKitSaleRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolLicenseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superuser;

    protected User $adminSekolahA;

    protected User $adminSekolahB;

    protected User $guruA;

    protected User $siswaA;

    protected School $schoolA;

    protected School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::factory()->create([
            'name' => 'SMK Negeri 1 Pertanian Sukaraja',
            'npsn' => '20201001',
            'status' => 'active',
        ]);

        $this->schoolB = School::factory()->create([
            'name' => 'SMK Pertanian Swasta 2 Berdikari',
            'npsn' => '20201002',
            'status' => 'active',
        ]);

        $this->superuser = User::factory()->create([
            'name' => 'Superuser Platform',
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

    public function test_one_school_has_one_canonical_license(): void
    {
        $action = app(InitializeSchoolLicenseAction::class);

        // 1. Initial creation
        $license1 = $action->execute($this->schoolA, ['notes' => 'Lisensi awal']);
        $this->assertEquals($this->schoolA->id, $license1->school_id);
        $this->assertEquals(SchoolLicense::STATUS_PENDING, $license1->status);

        // 2. Repeated initialization returns same record (idempotent)
        $license2 = $action->execute($this->schoolA, ['notes' => 'Panggilan kedua']);
        $this->assertEquals($license1->id, $license2->id);

        $this->assertEquals(1, SchoolLicense::where('school_id', $this->schoolA->id)->count());

        // 3. Database unique constraint enforcement
        $this->expectException(\Exception::class);
        SchoolLicense::create([
            'school_id' => $this->schoolA->id,
            'license_number' => 'LIC-DUPLICATE-01',
            'status' => 'pending',
        ]);
    }

    public function test_multiple_trainer_kits_do_not_create_additional_lms_licenses(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $license = $initAction->execute($this->schoolA);

        // School A purchases 5 Trainer Kits
        for ($i = 1; $i <= 5; $i++) {
            $kit = TrainerKitInventory::create([
                'serial_number' => "BTK-FLEET-0{$i}",
                'model_name' => 'Bitanic Trainer Kit v1.0',
                'status' => TrainerKitInventory::STATUS_SOLD,
                'school_id' => $this->schoolA->id,
            ]);

            TrainerKitSaleRecord::create([
                'inventory_id' => $kit->id,
                'school_id' => $this->schoolA->id,
                'sale_reference' => "PO-2026-MULTI-0{$i}",
                'sold_at' => now(),
            ]);
        }

        $this->assertEquals(5, TrainerKitInventory::where('school_id', $this->schoolA->id)->count());
        // LMS license count strictly remains 1
        $this->assertEquals(1, SchoolLicense::where('school_id', $this->schoolA->id)->count());
        $this->assertEquals($license->id, $this->schoolA->fresh()->license->id);
    }

    public function test_explicit_superuser_activation_required_no_automatic_trigger(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $license = $initAction->execute($this->schoolA);

        // Initialized state is pending and has no dates
        $this->assertEquals(SchoolLicense::STATUS_PENDING, $license->status);
        $this->assertNull($license->start_date);
        $this->assertNull($license->expiration_date);
        $this->assertEquals(0, $license->terms()->count());

        // Superuser explicitly activates first year entitlement
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $startDate = '2026-11-12';
        $activatedLicense = $actAction->execute($license, [
            'start_date' => $startDate,
            'notes' => 'Aktivasi resmi Superuser',
            'recorded_by' => $this->superuser->id,
        ]);

        $this->assertEquals($startDate, $activatedLicense->start_date->toDateString());
        $this->assertEquals('2027-11-12', $activatedLicense->expiration_date->toDateString());
        $this->assertEquals(1, $activatedLicense->terms()->count());

        $term1 = $activatedLicense->terms()->first();
        $this->assertEquals(1, $term1->term_number);
        $this->assertEquals(SchoolLicenseTerm::TYPE_INITIAL_INCLUDED, $term1->term_type);
        $this->assertEquals($this->superuser->id, $term1->authorized_by);

        $this->assertDatabaseHas('school_license_logs', [
            'license_id' => $license->id,
            'action' => SchoolLicenseLog::ACTION_ACTIVATED,
        ]);
    }

    public function test_calendar_year_calculation_and_leap_year_handling(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);

        // Standard year: 2026-11-12 -> 2027-11-12
        $licenseA = $initAction->execute($this->schoolA);
        $actAction->execute($licenseA, ['start_date' => '2026-11-12']);
        $this->assertEquals('2027-11-12', $licenseA->fresh()->expiration_date->toDateString());

        // Leap year start: 2024-02-29 -> 2025-02-28
        $licenseB = $initAction->execute($this->schoolB);
        $actAction->execute($licenseB, ['start_date' => '2024-02-29']);
        $this->assertEquals('2025-02-28', $licenseB->fresh()->expiration_date->toDateString());
    }

    public function test_date_only_convention_pending_active_expired_boundary(): void
    {
        // 1. Pending (today < start_date)
        $licenseFuture = SchoolLicense::create([
            'school_id' => $this->schoolA->id,
            'license_number' => 'LIC-FUTURE-01',
            'start_date' => Carbon::today()->addDays(7)->toDateString(),
            'expiration_date' => Carbon::today()->addYear()->addDays(7)->toDateString(),
        ]);
        $this->assertEquals(SchoolLicense::STATUS_PENDING, $licenseFuture->computed_status);
        $this->assertEquals('Menunggu Efektif', $licenseFuture->status_label);
        $this->assertFalse($licenseFuture->isActive());

        // 2. Active (start_date <= today < expiration_date)
        $licenseActive = SchoolLicense::create([
            'school_id' => $this->schoolB->id,
            'license_number' => 'LIC-ACTIVE-01',
            'start_date' => Carbon::today()->subMonths(3)->toDateString(),
            'expiration_date' => Carbon::today()->addMonths(9)->toDateString(),
        ]);
        $this->assertEquals(SchoolLicense::STATUS_ACTIVE, $licenseActive->computed_status);
        $this->assertEquals('Aktif', $licenseActive->status_label);
        $this->assertTrue($licenseActive->isActive());
        $this->assertGreaterThan(0, $licenseActive->days_remaining);

        // 3. Expired (today >= expiration_date)
        $schoolC = School::factory()->create(['status' => 'active']);
        $licenseExpired = SchoolLicense::create([
            'school_id' => $schoolC->id,
            'license_number' => 'LIC-EXPIRED-01',
            'start_date' => Carbon::today()->subYear()->subDay()->toDateString(),
            'expiration_date' => Carbon::today()->toDateString(), // expired today at 00:00:00
        ]);
        $this->assertEquals(SchoolLicense::STATUS_EXPIRED, $licenseExpired->computed_status);
        $this->assertEquals('Kedaluwarsa', $licenseExpired->status_label);
        $this->assertEquals(0, $licenseExpired->days_remaining);
        $this->assertFalse($licenseExpired->isActive());
    }

    public function test_superuser_can_correct_license_dates_with_mandatory_reason(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $license = $initAction->execute($this->schoolA);
        $actAction->execute($license, ['start_date' => '2026-01-10']);

        $newStart = '2026-02-01';
        $reason = 'Koreksi penyesuaian tanggal serah terima fisik lapangan sesuai addendum nomor 01.';

        $response = $this->actingAs($this->superuser)->post(route('master.licenses.correct', $license), [
            'start_date' => $newStart,
            'correction_reason' => $reason,
        ]);

        $response->assertRedirect(route('master.licenses.show', $license));
        $response->assertSessionHas('status');

        $license->refresh();
        $this->assertEquals($newStart, $license->start_date->toDateString());
        $this->assertEquals('2027-02-01', $license->expiration_date->toDateString());

        $this->assertDatabaseHas('school_license_logs', [
            'license_id' => $license->id,
            'action' => SchoolLicenseLog::ACTION_DATES_CORRECTED,
            'reason' => $reason,
        ]);

        // Rejection on short reason (<5 chars)
        $responseFail = $this->actingAs($this->superuser)->post(route('master.licenses.correct', $license), [
            'start_date' => '2026-03-01',
            'correction_reason' => 'bad',
        ]);
        $responseFail->assertSessionHasErrors(['correction_reason']);
    }

    public function test_superuser_can_renew_license_annually_without_overwriting_past_history(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $renewAction = app(RenewSchoolLicenseAction::class);

        $license = $initAction->execute($this->schoolA);
        $license = $actAction->execute($license, ['start_date' => '2026-01-01']); // exp: 2027-01-01
        $license->refresh();

        $this->assertEquals(1, $license->terms()->count());
        $this->assertEquals('2027-01-01', $license->expiration_date->toDateString());

        // Renew annually
        $renewed = $renewAction->execute($license, [
            'notes' => 'Perpanjangan resmi tahun ke-2 kontrak 2027',
            'recorded_by' => $this->superuser->id,
        ]);

        $this->assertEquals(2, $renewed->terms()->count());
        // New expiration date is extended by 1 full calendar year without overlapping
        $this->assertEquals('2028-01-01', $renewed->expiration_date->toDateString());

        // Prior Term 1 is fully preserved
        $term1 = $renewed->terms()->where('term_number', 1)->first();
        $this->assertNotNull($term1);
        $this->assertEquals('2026-01-01', $term1->start_date->toDateString());
        $this->assertEquals('2027-01-01', $term1->expiration_date->toDateString());

        // New Term 2 seamlessly chains from prior expiration
        $term2 = $renewed->terms()->where('term_number', 2)->first();
        $this->assertNotNull($term2);
        $this->assertEquals('2027-01-01', $term2->start_date->toDateString());
        $this->assertEquals('2028-01-01', $term2->expiration_date->toDateString());
        $this->assertEquals(SchoolLicenseTerm::TYPE_ANNUAL_RENEWAL, $term2->term_type);

        $this->assertDatabaseHas('school_license_logs', [
            'license_id' => $license->id,
            'action' => SchoolLicenseLog::ACTION_RENEWED,
        ]);
    }

    public function test_admin_sekolah_can_view_own_school_license_read_only(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $license = $initAction->execute($this->schoolA);
        $actAction->execute($license, ['start_date' => Carbon::today()->toDateString()]);

        // 1. Dedicated license page
        $response = $this->actingAs($this->adminSekolahA)->get(route('school.license.show'));
        $response->assertOk();
        $response->assertSee($license->license_number);
        $response->assertSee('Aktif');
        $response->assertSee($this->schoolA->name);

        // 2. Settings tab
        $responseSettings = $this->actingAs($this->adminSekolahA)->get(route('settings.index'));
        $responseSettings->assertOk();
        $responseSettings->assertSee($license->license_number);
        $responseSettings->assertSee('Lisensi LMS');
    }

    public function test_cross_school_license_idor_denied(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);
        $licenseB = $initAction->execute($this->schoolB);
        $actAction->execute($licenseB, ['start_date' => Carbon::today()->toDateString()]);

        // Admin Sekolah A attempts to view /master/licenses -> 403
        $this->actingAs($this->adminSekolahA)->get(route('master.licenses.index'))
            ->assertForbidden();

        // Admin Sekolah A attempts to view /master/licenses/{licenseB} -> 403
        $this->actingAs($this->adminSekolahA)->get(route('master.licenses.show', $licenseB))
            ->assertForbidden();

        // Admin Sekolah A attempts to mutate license B -> 403
        $this->actingAs($this->adminSekolahA)->post(route('master.licenses.renew', $licenseB))
            ->assertForbidden();
        $this->actingAs($this->adminSekolahA)->post(route('master.licenses.correct', $licenseB), [
            'start_date' => '2026-05-01',
            'correction_reason' => 'Unauthorized IDOR mutation attempt.',
        ])->assertForbidden();
    }

    public function test_guru_siswa_and_guest_strictly_denied_from_license_management(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $license = $initAction->execute($this->schoolA);

        // Guest redirected
        $this->get(route('master.licenses.index'))->assertRedirect(route('login'));
        $this->get(route('school.license.show'))->assertRedirect(route('login'));

        // Guru denied
        $this->actingAs($this->guruA)->get(route('master.licenses.index'))->assertForbidden();
        $this->actingAs($this->guruA)->get(route('school.license.show'))->assertForbidden();
        $this->actingAs($this->guruA)->post(route('master.licenses.activate', $license))->assertForbidden();

        // Siswa denied
        $this->actingAs($this->siswaA)->get(route('master.licenses.index'))->assertForbidden();
        $this->actingAs($this->siswaA)->get(route('school.license.show'))->assertForbidden();
        $this->actingAs($this->siswaA)->post(route('master.licenses.activate', $license))->assertForbidden();
    }

    public function test_filter_and_search_in_master_license_index(): void
    {
        $initAction = app(InitializeSchoolLicenseAction::class);
        $actAction = app(ActivateFirstYearLicenseAction::class);

        $licA = $initAction->execute($this->schoolA);
        $actAction->execute($licA, ['start_date' => Carbon::today()->subMonths(1)->toDateString()]); // Active

        $licB = $initAction->execute($this->schoolB); // Pending

        // 1. Search by school name
        $resName = $this->actingAs($this->superuser)->get(route('master.licenses.index', ['search' => 'Sukaraja']));
        $resName->assertOk();
        $resName->assertSee($licA->license_number);
        $resName->assertDontSee($licB->license_number);

        // 2. Filter by status: active
        $resActive = $this->actingAs($this->superuser)->get(route('master.licenses.index', ['status' => 'active']));
        $resActive->assertOk();
        $resActive->assertSee($licA->license_number);
        $resActive->assertDontSee($licB->license_number);

        // 3. Filter by status: pending
        $resPending = $this->actingAs($this->superuser)->get(route('master.licenses.index', ['status' => 'pending']));
        $resPending->assertOk();
        $resPending->assertSee($licB->license_number);
        $resPending->assertDontSee($licA->license_number);
    }
}
