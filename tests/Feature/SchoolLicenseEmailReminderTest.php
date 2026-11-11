<?php

namespace Tests\Feature;

use App\Actions\License\DispatchSchoolLicenseRemindersAction;
use App\Jobs\SendSchoolLicenseReminderJob;
use App\Mail\SchoolLicenseExpirationReminderMail;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseNotification;
use App\Models\SchoolLicenseTerm;
use App\Models\User;
use App\Services\License\SchoolLicenseResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchoolLicenseEmailReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Freeze time at a known point
        Carbon::setTestNow('2026-06-01 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_h14_reminder_dispatches_notifications_to_active_school_admins(): void
    {
        Mail::fake();

        $school = School::create([
            'npsn' => '77000001',
            'name' => 'SMK Negeri Pertanian Cianjur',
            'slug' => 'smkn-pertanian-cianjur',
            'status' => 'active',
        ]);

        $adminSekolah = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'email' => 'admin@smkn-cianjur.sch.id',
            'name' => 'Budi Santoso',
            'is_active' => true,
        ]);

        // Inactive admin should NOT receive email
        User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'email' => 'inactive@smkn-cianjur.sch.id',
            'is_active' => false,
        ]);

        // Guru and Siswa should NOT receive email
        User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_GURU,
            'email' => 'guru@smkn-cianjur.sch.id',
            'is_active' => true,
        ]);

        // License expires exactly 14 days from today (2026-06-15)
        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-0001',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'entitlement_type' => SchoolLicense::ENTITLEMENT_FIRST_YEAR,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
            'status' => 'active',
        ]);

        Queue::fake([SendSchoolLicenseReminderJob::class]);

        $action = app(DispatchSchoolLicenseRemindersAction::class);
        $summary = $action->execute(14, false);

        $this->assertEquals(1, $summary['found_licenses']);
        $this->assertEquals(1, $summary['queued']);

        Queue::assertPushed(SendSchoolLicenseReminderJob::class, 1);

        $this->assertDatabaseHas('school_license_notifications', [
            'license_id' => $license->id,
            'term_id' => $term->id,
            'school_id' => $school->id,
            'recipient_email' => 'admin@smkn-cianjur.sch.id',
            'notification_type' => SchoolLicenseNotification::TYPE_REMINDER_H14,
            'status' => SchoolLicenseNotification::STATUS_QUEUED,
        ]);

        // Assert inactive admin or guru received no notification
        $this->assertDatabaseMissing('school_license_notifications', [
            'recipient_email' => 'inactive@smkn-cianjur.sch.id',
        ]);
        $this->assertDatabaseMissing('school_license_notifications', [
            'recipient_email' => 'guru@smkn-cianjur.sch.id',
        ]);

        // Now process the queued job
        $notification = SchoolLicenseNotification::first();
        $job = new SendSchoolLicenseReminderJob($notification);
        $job->handle(app(SchoolLicenseResolver::class));

        Mail::assertSent(SchoolLicenseExpirationReminderMail::class, function ($mail) use ($adminSekolah) {
            return $mail->hasTo($adminSekolah->email);
        });

        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_SENT, $notification->status);
        $this->assertNotNull($notification->sent_at);

        $this->assertDatabaseHas('school_license_logs', [
            'license_id' => $license->id,
            'action' => 'reminder_h14_sent',
        ]);
    }

    public function test_h14_reminder_is_idempotent_on_subsequent_runs(): void
    {
        Mail::fake();

        $school = School::create([
            'npsn' => '77000002',
            'name' => 'SMK Pertanian Subang',
            'slug' => 'smk-pertanian-subang',
            'status' => 'active',
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'email' => 'admin@smk-subang.sch.id',
            'is_active' => true,
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-0002',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
            'status' => 'active',
        ]);

        $action = app(DispatchSchoolLicenseRemindersAction::class);

        // Run 1
        $summary1 = $action->execute(14, false);
        $this->assertEquals(1, $summary1['queued']);

        // Run 2
        $summary2 = $action->execute(14, false);
        $this->assertEquals(0, $summary2['queued']);
        $this->assertEquals(1, $summary2['skipped_existing']);

        $this->assertEquals(1, SchoolLicenseNotification::count());
    }

    public function test_dry_run_mode_does_not_mutate_db(): void
    {
        Mail::fake();

        $school = School::create([
            'npsn' => '77000003',
            'name' => 'SMK Negeri 1 Lembang',
            'slug' => 'smkn-1-lembang',
            'status' => 'active',
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'email' => 'admin@smkn1lembang.sch.id',
            'is_active' => true,
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-0003',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
            'status' => 'active',
        ]);

        $this->artisan('bitanic:check-license-reminders', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertEquals(0, SchoolLicenseNotification::count());
        Mail::assertNothingSent();
    }

    public function test_reminder_skips_licenses_not_expiring_in_14_days(): void
    {
        $school = School::create([
            'npsn' => '77000004',
            'name' => 'SMK Sukamaju',
            'slug' => 'smk-sukamaju',
            'status' => 'active',
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'email' => 'admin@smksukamaju.sch.id',
            'is_active' => true,
        ]);

        // License expires in 30 days (2026-07-01)
        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-0004',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-07-01',
            'expiration_date' => '2026-07-01',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-07-01',
            'expiration_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $action = app(DispatchSchoolLicenseRemindersAction::class);
        $summary = $action->execute(14, false);

        $this->assertEquals(0, $summary['queued']);
        $this->assertEquals(0, SchoolLicenseNotification::count());
    }

    public function test_reminder_skips_schools_without_active_admins_without_inventing_fake_emails(): void
    {
        $school = School::create([
            'npsn' => '77000005',
            'name' => 'SMK Tanpa Admin',
            'slug' => 'smk-tanpa-admin',
            'status' => 'active',
        ]);

        // Only siswa exists
        User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_SISWA,
            'email' => 'siswa@smk.sch.id',
            'is_active' => true,
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-0005',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
            'status' => 'active',
        ]);

        $action = app(DispatchSchoolLicenseRemindersAction::class);
        $summary = $action->execute(14, false);

        $this->assertEquals(0, $summary['queued']);
        $this->assertEquals(1, $summary['skipped_no_admins']);
        $this->assertEquals(0, SchoolLicenseNotification::count());
    }

    public function test_job_revalidates_entitlement_and_skips_if_already_renewed(): void
    {
        Mail::fake();

        $school = School::create([
            'npsn' => '77000006',
            'name' => 'SMK Pertanian Majalengka',
            'slug' => 'smk-pertanian-majalengka',
            'status' => 'active',
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SMK-2026-0006',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term1 = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
            'status' => 'active',
        ]);

        $notification = SchoolLicenseNotification::create([
            'license_id' => $license->id,
            'term_id' => $term1->id,
            'school_id' => $school->id,
            'recipient_email' => 'admin@smk-majalengka.sch.id',
            'recipient_name' => 'Admin Majalengka',
            'notification_type' => SchoolLicenseNotification::TYPE_REMINDER_H14,
            'channel' => SchoolLicenseNotification::CHANNEL_MAIL,
            'status' => SchoolLicenseNotification::STATUS_QUEUED,
            'scheduled_for' => Carbon::today(),
        ]);

        // Now school is renewed before job runs!
        $license->update([
            'expiration_date' => '2027-06-15',
        ]);
        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 2,
            'term_type' => SchoolLicenseTerm::TYPE_ANNUAL_RENEWAL,
            'start_date' => '2026-06-15',
            'expiration_date' => '2027-06-15',
            'status' => 'active',
        ]);

        $job = new SendSchoolLicenseReminderJob($notification);
        $job->handle(app(SchoolLicenseResolver::class));

        // Mail should NOT be sent because remaining days is now 379, not 14
        Mail::assertNothingSent();

        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_SKIPPED, $notification->status);
    }
}
