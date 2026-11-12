<?php

namespace Tests\Feature;

use App\Actions\License\DispatchSchoolLicenseRemindersAction;
use App\Jobs\SendSchoolLicenseReminderJob;
use App\Mail\SchoolLicenseExpirationReminderMail;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseNotification;
use App\Models\SchoolLicenseTerm;
use App\Models\User;
use App\Services\License\SchoolLicenseResolver;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TrainerKitSeeder;
use Database\Seeders\VocationalLmsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchoolLicenseReleaseReadinessAndHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * REQUIREMENT 1: Reminder Scheduler Timezone & Calendar Boundaries
     */
    public function test_scheduler_task_is_configured_for_asia_jakarta_at_0700_wib(): void
    {
        $schedule = app()->make(Schedule::class);
        $events = collect($schedule->events());

        $reminderEvent = $events->first(function ($event) {
            return str_contains($event->command ?? '', 'bitanic:check-license-reminders');
        });

        $this->assertNotNull($reminderEvent, 'Scheduled command bitanic:check-license-reminders must be registered in routes/console.php');
        $this->assertEquals('0 7 * * *', $reminderEvent->expression);
        $this->assertEquals('Asia/Jakarta', $reminderEvent->timezone);

        // Verification of UTC equivalent: 07:00 WIB is 00:00 UTC
        $wibTime = Carbon::create(2026, 6, 1, 7, 0, 0, 'Asia/Jakarta');
        $utcEquivalent = $wibTime->copy()->setTimezone('UTC');
        $this->assertEquals(0, $utcEquivalent->hour);
        $this->assertEquals(0, $utcEquivalent->minute);
    }

    public function test_reminder_calculation_honors_wib_calendar_day_boundary(): void
    {
        // When UTC is 2026-05-31 23:30:00, Asia/Jakarta is 2026-06-01 06:30:00 (next calendar day in Indonesia)
        Carbon::setTestNow(Carbon::parse('2026-05-31 23:30:00', 'UTC'));

        $school = School::create([
            'npsn' => '99880001',
            'name' => 'SMK Pertanian Cibadak',
            'slug' => 'smk-pertanian-cibadak',
            'status' => 'active',
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'email' => 'admin@smkcibadak.sch.id',
            'is_active' => true,
        ]);

        // License expires exactly 14 calendar days from WIB today (2026-06-01 + 14 = 2026-06-15)
        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-CBD-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $action = app(DispatchSchoolLicenseRemindersAction::class);
        $summary = $action->execute(14, true);

        // Target date must be 2026-06-15 (based on WIB today 2026-06-01), not 2026-06-14 (which would occur if evaluated in UTC)
        $this->assertEquals('2026-06-15', $summary['target_date']);
        $this->assertEquals(1, $summary['found_licenses']);
        $this->assertEquals(1, $summary['queued']);
    }

    /**
     * REQUIREMENT 2: Hardened Email Delivery, Concurrency & Stale Policy
     */
    public function test_concurrent_worker_claiming_prevents_duplicate_delivery(): void
    {
        Mail::fake();

        $school = School::create([
            'npsn' => '99880002',
            'name' => 'SMK Pertanian Lembang',
            'slug' => 'smk-pertanian-lembang',
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-01 07:00:00', 'Asia/Jakarta'));

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-LMB-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $notification = SchoolLicenseNotification::create([
            'license_id' => $license->id,
            'term_id' => $term->id,
            'school_id' => $school->id,
            'recipient_email' => 'admin@smklembang.sch.id',
            'recipient_name' => 'Admin Lembang',
            'notification_type' => SchoolLicenseNotification::TYPE_REMINDER_H14,
            'channel' => SchoolLicenseNotification::CHANNEL_MAIL,
            'status' => SchoolLicenseNotification::STATUS_QUEUED,
            'scheduled_for' => '2026-06-01',
            'payload' => [
                'days_remaining' => 14,
                'expiration_date' => '2026-06-15',
                'school_name' => $school->name,
                'license_number' => $license->license_number,
            ],
        ]);

        $resolver = app(SchoolLicenseResolver::class);

        // Worker 1 processes job
        $job1 = new SendSchoolLicenseReminderJob($notification);
        $job1->handle($resolver);

        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_SENT, $notification->status);
        $this->assertEquals(1, $notification->attempts);
        $this->assertNotNull($notification->claimed_at);
        $this->assertNotNull($notification->sent_at);

        // Worker 2 attempts to process concurrently / afterwards
        $job2 = new SendSchoolLicenseReminderJob($notification);
        $job2->handle($resolver);

        // Verify Mail was sent exactly once
        Mail::assertSent(SchoolLicenseExpirationReminderMail::class, 1);
        $notification->refresh();
        $this->assertEquals(1, $notification->attempts, 'Attempts must not increment again for completed notification');
    }

    public function test_stale_delayed_h14_job_is_safely_skipped_without_sending_misleading_mail(): void
    {
        Mail::fake();

        $school = School::create([
            'npsn' => '99880003',
            'name' => 'SMK Pertanian Subang',
            'slug' => 'smk-pertanian-subang',
            'status' => 'active',
        ]);

        // Scheduled on June 1 when days remaining was 14 (expires June 15)
        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SBG-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $notification = SchoolLicenseNotification::create([
            'license_id' => $license->id,
            'term_id' => $term->id,
            'school_id' => $school->id,
            'recipient_email' => 'admin@smksubang.sch.id',
            'recipient_name' => 'Admin Subang',
            'status' => SchoolLicenseNotification::STATUS_QUEUED,
            'scheduled_for' => '2026-06-01',
            'payload' => [
                'days_remaining' => 14,
                'expiration_date' => '2026-06-15',
            ],
        ]);

        // Simulate queue worker being down for 5 days: Current time is June 6 (9 days remaining, not 14)
        Carbon::setTestNow(Carbon::parse('2026-06-06 09:00:00', 'Asia/Jakarta'));

        $resolver = app(SchoolLicenseResolver::class);
        $job = new SendSchoolLicenseReminderJob($notification);
        $job->handle($resolver);

        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_SKIPPED, $notification->status);
        $this->assertStringContainsString('keterlambatan antrean', $notification->error_message);

        // Mail must NOT be sent with stale H-14 notice
        Mail::assertNothingSent();
    }

    public function test_smtp_failure_increments_attempts_and_records_durable_failure_state(): void
    {
        $school = School::create([
            'npsn' => '99880004',
            'name' => 'SMK Pertanian Majalengka',
            'slug' => 'smk-pertanian-majalengka',
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-01 07:00:00', 'Asia/Jakarta'));

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-MJL-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $notification = SchoolLicenseNotification::create([
            'license_id' => $license->id,
            'term_id' => $term->id,
            'school_id' => $school->id,
            'recipient_email' => 'admin@smkmajalengka.sch.id',
            'recipient_name' => 'Admin Majalengka',
            'status' => SchoolLicenseNotification::STATUS_QUEUED,
            'scheduled_for' => '2026-06-01',
            'attempts' => 2, // Already tried 2 times
        ]);

        // Mock Mail failure (deterministic 5xx recipient rejection)
        Mail::shouldReceive('to->send')->andThrow(new \Exception('550 5.1.1 Recipient address rejected: User unknown'));

        $resolver = app(SchoolLicenseResolver::class);
        $job = new SendSchoolLicenseReminderJob($notification);

        try {
            $job->handle($resolver);
        } catch (\Throwable $e) {
            // Expected
        }

        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_FAILED, $notification->status);
        $this->assertEquals(3, $notification->attempts);
        $this->assertStringContainsString('550 5.1.1 Recipient address rejected', $notification->error_message);
    }

    /**
     * REQUIREMENT 3: Safe Production Mail Config & Feature Flag
     */
    public function test_reminders_enabled_feature_flag_prevents_dispatch_when_false(): void
    {
        Queue::fake();

        config(['license.reminders_enabled' => false]);
        Carbon::setTestNow(Carbon::parse('2026-06-01 07:00:00', 'Asia/Jakarta'));

        $school = School::create([
            'npsn' => '99880005',
            'name' => 'SMK Pertanian Garut',
            'slug' => 'smk-pertanian-garut',
            'status' => 'active',
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'email' => 'admin@smkgarut.sch.id',
            'is_active' => true,
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-GRT-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $action = app(DispatchSchoolLicenseRemindersAction::class);
        $summary = $action->execute(14, false);

        $this->assertFalse($summary['reminders_enabled']);
        $this->assertEquals(1, $summary['found_licenses']);
        $this->assertEquals(0, $summary['queued']);
        $this->assertEquals(1, $summary['skipped_disabled']);

        // No jobs pushed and no DB notifications written
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('school_license_notifications', [
            'license_id' => $license->id,
        ]);
    }

    public function test_missing_support_contact_renders_safe_generic_instructions(): void
    {
        config([
            'license.support_contact.email' => null,
            'license.support_contact.phone' => null,
            'license.support_contact.helpdesk_url' => null,
        ]);

        $school = School::create([
            'npsn' => '99880006',
            'name' => 'SMK Pertanian Tasikmalaya',
            'slug' => 'smk-pertanian-tasikmalaya',
            'status' => 'active',
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-TSK-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $mail = new SchoolLicenseExpirationReminderMail($license, $term, 14);
        $html = $mail->render();

        // No hardcoded placeholders
        $this->assertStringNotContainsString('+62 812-3456-7890', $html);
        $this->assertStringContainsString('Silakan hubungi administrator sistem atau tim kemitraan resmi', $html);
    }

    /**
     * REQUIREMENT 4: License Middleware Security & Fail-Closed Boundary
     */
    public function test_authenticated_non_superuser_without_school_id_fails_closed(): void
    {
        config(['license.enforcement_enabled' => true]);

        // Create an invalid/corrupted user with no school_id
        $invalidUser = User::factory()->create([
            'school_id' => 1, // temporary to satisfy booted guard
            'role' => User::ROLE_GURU,
        ]);
        DB::table('users')->where('id', $invalidUser->id)->update(['school_id' => null]);
        $invalidUser->refresh();

        // Web request: fails closed, logs out, redirects to login
        $response = $this->actingAs($invalidUser)->get(route('schedules.index'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();

        // JSON request: returns 403 Forbidden with structured message
        $responseJson = $this->actingAs($invalidUser)->getJson(route('schedules.index'));
        $responseJson->assertStatus(403);
        $responseJson->assertJsonFragment([
            'license_status' => 'invalid_tenant',
        ]);
    }

    public function test_authenticated_user_with_inactive_school_fails_closed(): void
    {
        config(['license.enforcement_enabled' => true]);

        $inactiveSchool = School::create([
            'npsn' => '99880007',
            'name' => 'SMK Pertanian Non-Aktif',
            'slug' => 'smk-pertanian-non-aktif',
            'status' => 'inactive',
        ]);

        $teacher = User::factory()->create([
            'school_id' => $inactiveSchool->id,
            'role' => User::ROLE_GURU,
        ]);

        $response = $this->actingAs($teacher)->get(route('schedules.index'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $responseJson = $this->actingAs($teacher)->getJson(route('schedules.index'));
        $responseJson->assertStatus(403);
        $responseJson->assertJsonFragment([
            'license_status' => 'inactive_school',
        ]);
    }

    public function test_device_telemetry_ingress_operates_independent_of_license_enforcement(): void
    {
        config(['license.enforcement_enabled' => true]);

        $school = School::create([
            'npsn' => '99880008',
            'name' => 'SMK Pertanian Indramayu',
            'slug' => 'smk-pertanian-indramayu',
            'status' => 'active',
        ]);

        $device = Device::create([
            'school_id' => $school->id,
            'code' => 'BITANIC-TK-08',
            'name' => 'Meja Praktik 08',
            'status' => 'online',
        ]);

        $rawKey = 'btk_secret_key_release_test_08';
        DeviceApiKey::create([
            'device_id' => $device->id,
            'key_hash' => hash('sha256', $rawKey),
            'key_prefix' => substr($rawKey, 0, 8),
            'is_active' => true,
        ]);

        // Telemetry payload matching TelemetryIngestController schema
        $payload = [
            'soilrs485' => [
                'Nitrogen' => 60,
                'Phosporus' => 45,
                'Kalium' => 180,
                'Ec' => 1200,
                'Ph' => 6.5,
                'Temperature' => 25.2,
                'Humidity' => 45.5,
            ],
            'recorded_at' => now()->toIso8601String(),
        ];

        $response = $this->postJson('/api/v1/telemetry/ingest', $payload, [
            'X-Device-Key' => $rawKey,
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('telemetry_readings', [
            'device_id' => $device->id,
            'provenance' => 'hardware',
        ]);
    }

    /**
     * REQUIREMENT: Authentication Hardening (SEC-AUTH-SEED-01)
     */
    public function test_authentication_rejects_alternate_dev_passwords_and_never_modifies_hashes(): void
    {
        $school = School::create([
            'npsn' => '99880009',
            'name' => 'SMK Pertanian Cianjur',
            'slug' => 'smk-pertanian-cianjur',
            'status' => 'active',
        ]);

        $originalHash = Hash::make('ActualSecret123!');

        $user = User::create([
            'name' => 'Guru Kejuruan Cianjur',
            'email' => 'guru.cianjur@smk.id',
            'password' => $originalHash,
            'role' => User::ROLE_GURU,
            'school_id' => $school->id,
            'is_active' => true,
        ]);

        // 1. Attempt login with common dev passwords (must be rejected)
        $devPasswords = ['password123', 'Password123!', 'password'];
        foreach ($devPasswords as $badPass) {
            $resp = $this->post('/login', [
                'email' => 'guru.cianjur@smk.id',
                'password' => $badPass,
            ]);

            $resp->assertSessionHasErrors('email');
            $this->assertGuest();
        }

        // 2. Verify stored password hash was NEVER modified
        $user->refresh();
        $this->assertEquals($originalHash, $user->password, 'Password hash must remain unmodified after failed attempts');

        // 3. Attempt login with actual stored secret (must succeed)
        $successResp = $this->post('/login', [
            'email' => 'guru.cianjur@smk.id',
            'password' => 'ActualSecret123!',
        ]);

        $successResp->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    /**
     * REQUIREMENT: Email Failure Recovery & Orphaned Processing Lease
     */
    public function test_abrupt_worker_termination_respects_five_minute_lease(): void
    {
        Mail::fake();

        $school = School::create([
            'npsn' => '99880010',
            'name' => 'SMK Pertanian Tasikmalaya',
            'slug' => 'smk-pertanian-tasikmalaya',
            'status' => 'active',
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-TSK-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        // Notification claimed 2 minutes ago by Worker A (lease active until 5 minutes)
        $notification = SchoolLicenseNotification::create([
            'license_id' => $license->id,
            'term_id' => $term->id,
            'school_id' => $school->id,
            'recipient_email' => 'admin@smktasik.sch.id',
            'recipient_name' => 'Admin Tasik',
            'status' => SchoolLicenseNotification::STATUS_PROCESSING,
            'claimed_at' => now()->subMinutes(2),
            'attempts' => 1,
            'scheduled_for' => '2026-06-01',
            'payload' => ['days_remaining' => 14],
        ]);

        $resolver = app(SchoolLicenseResolver::class);

        // Worker B attempts to process within lease
        $job = new SendSchoolLicenseReminderJob($notification);
        $job->handle($resolver);

        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_PROCESSING, $notification->status);
        $this->assertEquals(1, $notification->attempts, 'Attempts must not increment while lease is active');
        Mail::assertNothingSent();
    }

    public function test_orphaned_processing_status_recovered_after_lease_expires(): void
    {
        Mail::fake();

        Carbon::setTestNow(Carbon::parse('2026-06-01 07:00:00', 'Asia/Jakarta'));

        $school = School::create([
            'npsn' => '99880011',
            'name' => 'SMK Pertanian Ciamis',
            'slug' => 'smk-pertanian-ciamis',
            'status' => 'active',
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-CMS-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        // Worker died abruptly 10 minutes ago (lease expired > 5 min, attempts = 1)
        $notification = SchoolLicenseNotification::create([
            'license_id' => $license->id,
            'term_id' => $term->id,
            'school_id' => $school->id,
            'recipient_email' => 'admin@smkciamis.sch.id',
            'recipient_name' => 'Admin Ciamis',
            'status' => SchoolLicenseNotification::STATUS_PROCESSING,
            'claimed_at' => now()->subMinutes(10),
            'attempts' => 1,
            'scheduled_for' => '2026-06-01',
            'payload' => ['days_remaining' => 14],
        ]);

        $resolver = app(SchoolLicenseResolver::class);

        // Next worker picks up job: recovers orphaned processing lease
        $job = new SendSchoolLicenseReminderJob($notification);
        $job->handle($resolver);

        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_SENT, $notification->status);
        $this->assertEquals(2, $notification->attempts, 'Attempts incremented to 2 upon orphaned recovery');
        $this->assertNotNull($notification->sent_at);
        Mail::assertSent(SchoolLicenseExpirationReminderMail::class, 1);
    }

    public function test_uncertain_smtp_delivery_outcome_recorded_as_unknown_and_not_re_sent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 07:00:00', 'Asia/Jakarta'));

        $school = School::create([
            'npsn' => '99880012',
            'name' => 'SMK Pertanian Banjar',
            'slug' => 'smk-pertanian-banjar',
            'status' => 'active',
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-BJR-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        // Attempt 2 already tried, attempt 3 throws timeout
        $notification = SchoolLicenseNotification::create([
            'license_id' => $license->id,
            'term_id' => $term->id,
            'school_id' => $school->id,
            'recipient_email' => 'admin@smkbanjar.sch.id',
            'recipient_name' => 'Admin Banjar',
            'status' => SchoolLicenseNotification::STATUS_QUEUED,
            'attempts' => 2,
            'scheduled_for' => '2026-06-01',
            'payload' => ['days_remaining' => 14],
        ]);

        Mail::shouldReceive('to->send')->andThrow(new \Exception('Connection closed unexpectedly during transport'));

        $resolver = app(SchoolLicenseResolver::class);
        $job = new SendSchoolLicenseReminderJob($notification);

        try {
            $job->handle($resolver);
        } catch (\Throwable $e) {
            // Handled
        }

        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_UNKNOWN, $notification->status);
        $this->assertEquals(3, $notification->attempts);
        $this->assertTrue($notification->isUnknown());

        // Subsequent worker execution will NOT retry an unknown delivery result
        $job2 = new SendSchoolLicenseReminderJob($notification);
        $job2->handle($resolver);

        $notification->refresh();
        $this->assertEquals(3, $notification->attempts, 'Attempts must not increment on unknown delivery state');
    }

    public function test_dispatcher_recovers_orphaned_processing_notifications_under_max_attempts(): void
    {
        Queue::fake();

        config(['license.reminders_enabled' => true]);
        Carbon::setTestNow(Carbon::parse('2026-06-01 07:00:00', 'Asia/Jakarta'));

        $school = School::create([
            'npsn' => '99880013',
            'name' => 'SMK Pertanian Sukabumi',
            'slug' => 'smk-pertanian-sukabumi',
            'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Sukabumi',
            'email' => 'admin@smksukabumi.sch.id',
            'password' => Hash::make('Secret123!'),
            'role' => User::ROLE_ADMIN_SEKOLAH,
            'school_id' => $school->id,
            'is_active' => true,
        ]);

        $license = SchoolLicense::create([
            'school_id' => $school->id,
            'license_number' => 'LIC-SKB-2026-01',
            'status' => SchoolLicense::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        $term = SchoolLicenseTerm::create([
            'license_id' => $license->id,
            'term_number' => 1,
            'term_type' => SchoolLicenseTerm::TYPE_INITIAL_INCLUDED,
            'status' => SchoolLicenseTerm::STATUS_ACTIVE,
            'start_date' => '2025-06-15',
            'expiration_date' => '2026-06-15',
        ]);

        // Orphaned processing notification (worker died 15 minutes ago, attempt 1)
        $notification = SchoolLicenseNotification::create([
            'license_id' => $license->id,
            'term_id' => $term->id,
            'school_id' => $school->id,
            'recipient_email' => $admin->email,
            'recipient_name' => $admin->name,
            'status' => SchoolLicenseNotification::STATUS_PROCESSING,
            'claimed_at' => now()->subMinutes(15),
            'attempts' => 1,
            'scheduled_for' => '2026-06-01',
            'payload' => ['days_remaining' => 14],
        ]);

        $action = app(DispatchSchoolLicenseRemindersAction::class);
        $summary = $action->execute(14, false);

        $this->assertEquals(1, $summary['queued']);
        $notification->refresh();
        $this->assertEquals(SchoolLicenseNotification::STATUS_QUEUED, $notification->status);
        $this->assertNull($notification->claimed_at);

        Queue::assertPushed(SendSchoolLicenseReminderJob::class, 1);
    }

    public function test_production_safe_seeding_prohibits_demo_fixtures_in_production_and_staging(): void
    {
        // 1. Direct invocation of VocationalLmsSeeder in production throws RuntimeException
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VocationalLmsSeeder');

        $seeder = new VocationalLmsSeeder;
        $seeder->run();
    }

    public function test_trainer_kit_seeder_prohibits_demo_fixtures_in_production_and_staging(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TrainerKitSeeder');

        $seeder = new TrainerKitSeeder;
        $seeder->run();
    }

    public function test_database_seeder_skips_demo_fixtures_in_production(): void
    {
        $this->app['env'] = 'production';

        // Run DatabaseSeeder with --force in production
        $this->artisan('db:seed', ['--force' => true])
            ->expectsOutputToContain('Environment produksi/staging terdeteksi')
            ->assertExitCode(0);

        // Verify NO demo users were seeded
        $this->assertDatabaseMissing('users', ['email' => 'admin@smk.id']);
        $this->assertDatabaseMissing('users', ['email' => 'guru@smk.id']);
        $this->assertDatabaseMissing('users', ['email' => 'siswa@smk.id']);
        $this->assertDatabaseMissing('devices', ['code' => 'TK-01']);

        // Verify reference master data WAS seeded
        $this->assertDatabaseHas('subscription_plans', ['code' => 'plan-siswa-starter']);
        $this->assertDatabaseHas('module_practicums', ['meeting_number' => 1]);
    }
}
