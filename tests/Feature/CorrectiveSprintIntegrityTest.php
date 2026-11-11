<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\ModulePracticum;
use App\Models\ScheduleParticipant;
use App\Models\StudentReport;
use App\Models\SubscriptionPlan;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSubscription;
use Database\Seeders\ModulePracticumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrectiveSprintIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulePracticumSeeder::class);
    }

    public function test_guest_cannot_access_sensitive_read_endpoints(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->get(route('settings.index'))->assertRedirect(route('login'));
        $this->postJson('/api/v1/recommendations/request', [])->assertStatus(401);
        $this->getJson('/api/v1/recommendations/status/any-ticket-id')->assertStatus(401);
    }

    public function test_student_sees_only_own_reports_and_cannot_access_user_directory_or_settings(): void
    {
        $studentA = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa A']);
        $studentB = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa B']);
        $module = ModulePracticum::firstOrFail();

        StudentReport::create([
            'user_id' => $studentA->id,
            'module_id' => $module->id,
            'lks_answers' => ['q' => 'Jawaban A'],
            'status' => 'submitted',
        ]);
        StudentReport::create([
            'user_id' => $studentB->id,
            'module_id' => $module->id,
            'lks_answers' => ['q' => 'Jawaban B Rahasia'],
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($studentA)->get(route('reports.index'));
        $response->assertStatus(200);
        $response->assertSee('Siswa A');
        $response->assertDontSee('Siswa B');

        $this->actingAs($studentA)->get(route('users.index'))->assertStatus(403);
        $this->actingAs($studentA)->get(route('settings.index'))->assertStatus(403);
    }

    public function test_ai_recommendation_status_enforces_ticket_ownership(): void
    {
        $userA = User::factory()->create(['role' => 'guru', 'name' => 'Guru A']);
        $userB = User::factory()->create(['role' => 'guru', 'name' => 'Guru B']);
        $plan = SubscriptionPlan::create([
            'name' => 'Pro AI', 'code' => 'pro-ai', 'price' => 50000, 'duration_days' => 30,
            'features' => ['ai_quota' => 50], 'is_active' => true,
        ]);
        UserSubscription::create([
            'user_id' => $userA->id, 'plan_id' => $plan->id, 'starts_at' => now(),
            'ends_at' => now()->addMonth(), 'status' => 'active',
        ]);
        $device = Device::create(['code' => 'TK-AI-01', 'name' => 'Meja AI', 'is_active' => true]);
        $reading = TelemetryReading::create([
            'device_id' => $device->id, 'ph' => 6.2, 'temperature' => 25.0, 'humidity' => 65.0,
            'ec' => 1200, 'nitrogen' => 40, 'phosphorus' => 25, 'potassium' => 110, 'recorded_at' => now(),
        ]);

        $response = $this->actingAs($userA)->postJson('/api/v1/recommendations/request', ['reading_id' => $reading->id]);
        $response->assertStatus(202);
        $ticketId = $response->json('ticket_id');

        $this->actingAs($userB)->getJson("/api/v1/recommendations/status/{$ticketId}")->assertStatus(403);
        $this->actingAs($userA)->getJson("/api/v1/recommendations/status/{$ticketId}")
            ->assertStatus(200)
            ->assertJsonPath('data.ticket_id', $ticketId);
    }

    public function test_student_cannot_submit_lks_with_unallocated_kit_or_schedule_of_another_student(): void
    {
        $studentA = User::factory()->create(['role' => 'siswa']);
        $studentB = User::factory()->create(['role' => 'siswa']);
        $kit1 = Device::create(['code' => 'TK-VOKASI-01', 'name' => 'Meja 1', 'is_active' => true]);
        $kit2 = Device::create(['code' => 'TK-VOKASI-02', 'name' => 'Meja 2', 'is_active' => true]);
        UserProfile::create(['user_id' => $studentA->id, 'type' => 'siswa', 'assigned_kit_id' => 'TK-VOKASI-01']);
        $module = ModulePracticum::firstOrFail();

        $this->actingAs($studentA)->post(route('modules.submit-lks', $module->id), [
            'device_id' => $kit2->id, 'lks_answers' => ['q1' => 'Mencoba ambil data meja teman'],
        ])->assertSessionHasErrors('device_id');

        $scheduleB = ActivitySchedule::create([
            'device_id' => $kit2->id, 'title' => 'Praktikum Sesi Kelompok B', 'activity_date' => today(),
            'start_time' => '08:00', 'end_time' => '23:59',
        ]);
        ScheduleParticipant::create(['activity_schedule_id' => $scheduleB->id, 'user_id' => $studentB->id, 'role' => 'siswa']);

        $this->actingAs($studentA)->post(route('modules.submit-lks', $module->id), [
            'activity_schedule_id' => $scheduleB->id, 'lks_answers' => ['q1' => 'Mencoba submit jadwal orang lain'],
        ])->assertSessionHasErrors('activity_schedule_id');
    }

    public function test_calibration_offsets_format_synchronization_between_form_and_ingestion(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-CALIB-01',
            'name' => 'Meja Kalibrasi',
            'is_active' => true,
        ]);

        $rawKey = 'calib-test-secret-key-123';
        DeviceApiKey::create([
            'device_id' => $device->id,
            'key_hash' => hash('sha256', $rawKey),
        ]);

        // Guru mengisi form kalibrasi dengan field phOffset, moistureOffset, ecOffset
        $this->actingAs($teacher)->post(route('kits.calibration', $device->id), [
            'phOffset' => 0.35,
            'moistureOffset' => 5.5,
            'ecOffset' => 120,
        ])->assertRedirect(route('kits.index'));

        $device->refresh();
        $this->assertEquals(0.35, (float) ($device->calibration_offsets['ph'] ?? $device->calibration_offsets['phOffset']));

        // Ingest data hardware melalui API
        $payload = [
            'device_id' => $device->code,
            'soilrs485' => [
                'Ph' => 6.00,
                'Humidity' => 50.0,
                'Ec' => 1000,
                'Temperature' => 25.0,
                'Nitrogen' => 30,
                'Phosporus' => 20,
                'Kalium' => 90,
            ],
        ];

        $res = $this->withHeaders(['X-Device-Key' => $rawKey])
            ->postJson('/api/v1/telemetry/ingest', $payload);
        $res->assertStatus(201);

        $reading = TelemetryReading::where('device_id', $device->id)->latest('id')->firstOrFail();
        // 6.00 + 0.35 = 6.35
        $this->assertEquals(6.35, (float) $reading->ph);
        // 50.0 + 5.5 = 55.5
        $this->assertEquals(55.5, (float) $reading->humidity);
        // 1000 + 120 = 1120
        $this->assertEquals(1120, (float) $reading->ec);
    }

    public function test_simulation_command_sets_provenance_to_simulation_while_api_sets_hardware(): void
    {
        $device = Device::create([
            'code' => 'TK-PROV-01',
            'name' => 'Meja Provenance',
            'is_active' => true,
        ]);

        $rawKey = 'prov-test-key-456';
        DeviceApiKey::create([
            'device_id' => $device->id,
            'key_hash' => hash('sha256', $rawKey),
        ]);

        // 1. Simulator Artisan Command
        $this->artisan('bitanic:simulate-telemetry --count=1 --device=TK-PROV-01')
            ->assertSuccessful();

        $simReading = TelemetryReading::where('device_id', $device->id)->latest('id')->firstOrFail();
        $this->assertEquals('simulation', $simReading->provenance);

        // 2. Hardware API Ingest
        $this->withHeaders(['X-Device-Key' => $rawKey])
            ->postJson('/api/v1/telemetry/ingest', [
                'device_id' => $device->code,
                'soilrs485' => [
                    'Ph' => 6.5,
                    'Humidity' => 60.0,
                    'Ec' => 1200,
                    'Temperature' => 26.0,
                    'Nitrogen' => 40,
                    'Phosporus' => 25,
                    'Kalium' => 100,
                ],
            ])->assertStatus(201);

        $hwReading = TelemetryReading::where('device_id', $device->id)->latest('id')->firstOrFail();
        $this->assertEquals('hardware', $hwReading->provenance);
    }

    public function test_iot_monitoring_marks_stale_telemetry_unavailable_for_live_status(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'school_id' => $teacher->school_id,
            'code' => 'TK-STALE', 'name' => 'Meja Offline', 'is_active' => true,
            'last_seen_at' => now()->subHours(2),
        ]);
        TelemetryReading::create([
            'device_id' => $device->id, 'ph' => 6.2, 'temperature' => 24.0, 'humidity' => 60.0,
            'ec' => 1000, 'nitrogen' => 30, 'phosphorus' => 20, 'potassium' => 80,
            'recorded_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($teacher)->get('/iot-monitoring?device_id='.$device->id);
        $response->assertOk();
        $response->assertDontSee('Streaming Modbus Aktif');
        $response->assertSee('Telemetri Terakhir (Stale / Offline)');
    }

    public function test_database_seeder_does_not_enable_dev_passwords_in_production(): void
    {
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringContainsString("app()->environment('production')", $source);
        $this->assertStringNotContainsString("'--dev-passwords' => true", $source);
    }
}
