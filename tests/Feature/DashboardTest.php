<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_successfully(): void
    {
        $device = Device::create([
            'code' => 'BITANIC-TK-01',
            'name' => 'BITANIC Precision Agritech Kit #01',
            'connection_type' => 'modbus_rs485',
            'is_active' => true,
        ]);

        TelemetryReading::create([
            'device_id' => $device->id,
            'nitrogen' => 65.5,
            'phosphorus' => 32.0,
            'potassium' => 140.0,
            'ec' => 1420.0,
            'ph' => 6.5,
            'temperature' => 26.5,
            'humidity' => 45.0,
            'raw_payload' => ['sample' => 'test'],
            'recorded_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('BITANIC Precision Agritech Kit #01');
        $response->assertSee('Pusat Kendali Laboratorium Vokasi IoT');
        $response->assertSee('Armada Trainer Kit');

        // New shared layout: mobile header replaces old topbar
        // On desktop the header is hidden (lg:hidden), so we check for sidebar presence instead
        $content = (string) $response->getContent();
        $this->assertStringContainsString('BITANIC-TK-01', $content);
        $this->assertStringNotContainsString('Live', $content);
        $this->assertStringNotContainsString('-54 dBm', $content);
        $this->assertStringNotContainsString('94%', $content);
    }

    public function test_iot_monitoring_page_renders_real_time_workstation(): void
    {
        $device = Device::create([
            'code' => 'BITANIC-TK-01',
            'name' => 'BITANIC Precision Agritech Kit #01',
            'bench_location' => 'Meja Praktik 01 - Lahan Uji',
            'connection_type' => 'modbus_rs485',
            'protocol' => 'Modbus RS485',
            'firmware_version' => 'v2.4.1',
            'ip_address' => '192.168.10.101',
            'status' => 'online',
            'is_active' => true,
            'relays_state' => ['irrigationPump' => false],
        ]);

        TelemetryReading::create([
            'device_id' => $device->id,
            'nitrogen' => 65.5,
            'phosphorus' => 32.0,
            'potassium' => 140.0,
            'ec' => 1420.0,
            'ph' => 6.5,
            'temperature' => 26.5,
            'humidity' => 45.0,
        ]);

        $response = $this->get('/iot-monitoring');

        $response->assertStatus(200);
        $response->assertSee('Monitoring Telemetri Lahan & Trainer Kit Vokasi', false);
        $response->assertSee('v2.4.1');
        $response->assertSee('Derajat Keasaman (pH)');
        $response->assertSee('Konduktivitas Listrik (EC)');
        $response->assertSee('Glosarium');
    }

    public function test_iot_monitoring_empty_state_shows_honest_copy(): void
    {
        $response = $this->get('/iot-monitoring');

        $response->assertStatus(200);
        $response->assertSee('Belum Ada Transmisi Data');
        $response->assertSee('Menunggu Telemetri');
    }

    public function test_iot_monitoring_marks_offline_after_heartbeat_timeout(): void
    {
        $device = Device::create([
            'code' => 'TK-OFFLINE',
            'name' => 'Offline Kit',
            'connection_type' => 'modbus_rs485',
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(20),
        ]);

        $response = $this->get('/iot-monitoring');

        $response->assertStatus(200);
        $response->assertSee('Offline');
    }

    public function test_analytics_route_renders_the_analytics_page(): void
    {
        $response = $this->get('/analytics');

        $response->assertStatus(200);
        $response->assertSee('Analitik Agronomi');
    }

    public function test_dashboard_displays_lab_overview_separate_from_sensor_dashboard(): void
    {
        Device::create([
            'code' => 'TK-DASH',
            'name' => 'Dashboard Trainer Kit',
            'connection_type' => 'modbus_rs485',
            'status' => 'online',
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Pusat Kendali Laboratorium Vokasi IoT');
        $response->assertSee('Armada Trainer Kit');
        $response->assertDontSee('Parameter Sensor Tanah 7-in-1');
    }

    public function test_practicum_reports_route_renders_report_hub(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'guru']))->get('/reports');

        $response->assertStatus(200);
        $response->assertSee('Hub Laporan Lembar Kerja Siswa (LKS)');
    }

    public function test_trainer_kits_route_renders_hardware_fleet(): void
    {
        $response = $this->get('/trainer-kits');

        $response->assertStatus(200);
        $response->assertSee('Manajemen Armada Trainer Kit IoT');
    }

    public function test_analytics_selects_device_from_available_active_devices(): void
    {
        $device = Device::create([
            'code' => 'TK-ANALYTICS',
            'name' => 'Analytics Trainer Kit',
            'connection_type' => 'modbus_rs485',
            'status' => 'online',
            'is_active' => true,
        ]);

        $response = $this->get('/analytics?device_id='.$device->id);

        $response->assertOk();
        $response->assertSee('TK-ANALYTICS');
    }

    public function test_iot_monitoring_unknown_device_id_falls_back_to_first_active_kit(): void
    {
        Device::create([
            'code' => 'TK-FIRST',
            'name' => 'First Active Trainer Kit',
            'connection_type' => 'modbus_rs485',
            'status' => 'online',
            'is_active' => true,
        ]);

        $response = $this->get('/iot-monitoring?device_id=999999');

        $response->assertOk();
        $response->assertSee('TK-FIRST');
    }

    public function test_empty_database_renders_all_separated_primary_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('Pusat Kendali Laboratorium Vokasi IoT');
        $this->get('/iot-monitoring')->assertOk()->assertSee('Monitoring Telemetri Lahan & Trainer Kit Vokasi', false);
        $this->get('/analytics')->assertOk()->assertSee('Analitik Agronomi');
    }

    public function test_relay_toggle_endpoint_rejects_unauthenticated_requests(): void
    {
        $response = $this->postJson('/api/v1/devices/relay/toggle', [
            'relay_key' => 'irrigationPump',
            'state' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_relay_toggle_without_hardware_ack_does_not_persist_requested_state(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create([
            'code' => 'TK-RELAY-NO-ACK',
            'name' => 'Relay tanpa ACK',
            'is_active' => true,
            'relays_state' => ['irrigationPump' => false],
        ]);

        $response = $this->actingAs($teacher)->postJson('/api/v1/devices/relay/toggle', [
            'device_id' => $device->id,
            'relay_key' => 'irrigationPump',
            'state' => true,
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'is_simulation' => true,
            'hardware_ack' => false,
            'relay_key' => 'irrigationPump',
            'state' => false,
        ]);
        $this->assertFalse($device->fresh()->relays_state['irrigationPump']);
    }

    public function test_user_without_school_is_rejected_on_dashboard_and_monitoring(): void
    {
        $user = User::factory()->create(['role' => 'guru', 'school_id' => 1]);
        DB::table('users')->where('id', $user->id)->update(['school_id' => null]);

        $this->actingAs($user->fresh())->get('/')->assertStatus(403);
        $this->actingAs($user->fresh())->get('/iot-monitoring')->assertStatus(403);
    }

    public function test_user_with_inactive_school_is_rejected_on_dashboard_and_monitoring(): void
    {
        $inactiveSchool = School::create([
            'npsn' => '99990099',
            'name' => 'SMK Nonaktif Tenancy',
            'slug' => 'smk-nonaktif-tenancy',
            'status' => 'inactive',
        ]);
        $user = User::factory()->create([
            'role' => 'guru',
            'school_id' => $inactiveSchool->id,
        ]);

        $this->actingAs($user)->get('/')->assertStatus(403);
        $this->actingAs($user)->get('/iot-monitoring')->assertStatus(403);
    }

    public function test_dashboard_card_1_renders_honest_empty_fleet_state_when_zero_devices(): void
    {
        $school = School::create([
            'npsn' => '20200111',
            'name' => 'SMK Tanpa Device',
            'slug' => 'smk-tanpa-device',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->get('/');

        $response->assertOk();
        $response->assertSee('Belum Ada Meja Terdaftar');
        $response->assertDontSee('Semua Siap Pakai');
        $response->assertSee('Belum Ada Meja Praktikum IoT Terdaftar');

        // No fabricated values anywhere on the page
        $content = (string) $response->getContent();
        foreach (['BITANIC-TK-01', 'Meja Praktik 01', 'Live', 'animate-ping', '-54 dBm', '94%'] as $fabricatedValue) {
            $this->assertStringNotContainsString($fabricatedValue, $content);
        }
    }

    public function test_dashboard_card_2_does_not_claim_upcoming_schedule_is_today(): void
    {
        $school = School::create([
            'npsn' => '20200112',
            'name' => 'SMK Schedule Check',
            'slug' => 'smk-schedule-check',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id, 'name' => 'Pak Budi Handoko']);
        $device = Device::create([
            'school_id' => $school->id,
            'code' => 'TK-SCHED-01',
            'name' => 'Meja Praktikum Jadwal',
            'is_active' => true,
        ]);

        $nextWeek = now()->addDays(7);
        $schedule = ActivitySchedule::create([
            'school_id' => $school->id,
            'device_id' => $device->id,
            'title' => 'Praktikum Minggu Depan',
            'activity_date' => $nextWeek->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);
        $schedule->participants()->create([
            'user_id' => $teacher->id,
            'role' => 'guru',
        ]);

        $response = $this->actingAs($teacher)->get('/');

        $response->assertOk();
        $response->assertSee('Praktikum Minggu Depan');
        $response->assertSee($nextWeek->format('d/m/Y'));
        $response->assertDontSee('Hari Ini');
        $response->assertSee('Pak Budi Handoko');
    }

    public function test_dashboard_card_3_renders_student_scoped_view_for_siswa(): void
    {
        $school = School::create([
            'npsn' => '20200113',
            'name' => 'SMK Siswa View',
            'slug' => 'smk-siswa-view',
            'status' => 'active',
        ]);
        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);
        $otherStudent = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);

        $module = ModulePracticum::create([
            'meeting_number' => 1,
            'title' => 'Modul Student View',
            'duration_minutes' => 90,
            'badge' => 'Badge',
        ]);

        // Student has 1 submitted report
        StudentReport::create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'status' => 'submitted',
            'lks_answers' => [],
        ]);

        // Other student has 3 submitted reports waiting for teacher
        for ($i = 0; $i < 3; $i++) {
            StudentReport::create([
                'user_id' => $otherStudent->id,
                'module_id' => $module->id,
                'status' => 'submitted',
                'lks_answers' => [],
            ]);
        }

        $response = $this->actingAs($student)->get('/');

        $response->assertOk();
        $response->assertSee('LKS Praktikum Saya');
        $response->assertDontSee('LKS Menunggu Review');
        $response->assertSee('1</span>', false); // 1 Laporan Terkumpul for this student
        $response->assertDontSee('4 Laporan Praktikan');
    }

    public function test_dashboard_card_4_does_not_raise_critical_anomalies_for_offline_or_stale_telemetry(): void
    {
        $school = School::create([
            'npsn' => '20200114',
            'name' => 'SMK Stale Anomaly',
            'slug' => 'smk-stale-anomaly',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);

        // Offline device with stale reading
        $offlineDevice = Device::create([
            'school_id' => $school->id,
            'code' => 'TK-OFFLINE-STALE',
            'name' => 'Meja Offline',
            'is_active' => true,
            'last_seen_at' => now()->subHours(2), // offline
        ]);
        TelemetryReading::create([
            'device_id' => $offlineDevice->id,
            'provenance' => 'hardware',
            'ph' => 4.2, // extreme acid
            'humidity' => 15.0, // extreme dry
            'ec' => 3000,
            'recorded_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($teacher)->get('/');

        $response->assertOk();
        // Should NOT trigger active anomaly alerts since device is offline / stale
        // New honest empty-state copy replaces "Kondisi Optimal"
        $response->assertSee('Semua Parameter Dalam Batas Ideal');
        $response->assertDontSee('Perlu Intervensi');
        $response->assertDontSee('Kemasaman masam ekstrem');

        // Now create an online device with fresh hardware telemetry and low pH
        $onlineDevice = Device::create([
            'school_id' => $school->id,
            'code' => 'TK-ONLINE-FRESH',
            'name' => 'Meja Online Fresh',
            'is_active' => true,
            'last_seen_at' => now(), // online
        ]);
        TelemetryReading::create([
            'device_id' => $onlineDevice->id,
            'provenance' => 'hardware',
            'ph' => 4.5,
            'humidity' => 50.0,
            'ec' => 1200,
            'recorded_at' => now(),
        ]);

        $responseFresh = $this->actingAs($teacher)->get('/');
        $responseFresh->assertOk();
        $responseFresh->assertSee('Perlu Intervensi');
        $responseFresh->assertSee('TK-ONLINE-FRESH');
        $responseFresh->assertSee('Kemasaman masam ekstrem');
    }

    public function test_dashboard_does_not_flag_fresh_simulation_readings_as_hardware_anomalies(): void
    {
        $school = School::create([
            'npsn' => '20200121',
            'name' => 'SMK Simulation Provenance',
            'slug' => 'smk-simulation-provenance',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $device = Device::create([
            'school_id' => $school->id,
            'code' => 'TK-SIM-FRESH',
            'name' => 'Meja Simulasi Fresh',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'simulation',
            'ph' => 4.2,
            'humidity' => 15.0,
            'ec' => 3000,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get('/');

        $response->assertOk();
        // Simulation provenance never raises active anomalies
        $response->assertSee('Semua Parameter Dalam Batas Ideal');
        $response->assertDontSee('Kemasaman masam ekstrem');
    }

    public function test_dashboard_counts_affected_stations_accurately_with_multiple_breaches_on_single_station(): void
    {
        $school = School::create([
            'npsn' => '20200115',
            'name' => 'SMK Multi Breach',
            'slug' => 'smk-multi-breach',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);

        $device = Device::create([
            'school_id' => $school->id,
            'code' => 'TK-MULTI-01',
            'name' => 'Meja Multi Breach',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'hardware',
            'ph' => 4.8, // breach 1
            'humidity' => 20.0, // breach 2
            'ec' => 1400,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get('/');

        $response->assertOk();
        $response->assertSee('2</span> parameter melewati ambang pada data terbaru', false);
        $this->assertMatchesRegularExpression(
            '/font-tabular[^>]*>\s*1\s*<\/span>\s*<span[^>]*>Meja Terdampak/s',
            $response->getContent(),
        );
        $response->assertDontSee('2 Stasiun Meja');
    }

    public function test_dashboard_calculates_and_displays_average_student_attendance(): void
    {
        $school = School::create([
            'npsn' => '20200116',
            'name' => 'SMK Attendance Calc',
            'slug' => 'smk-attendance-calc',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $student1 = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);
        $student2 = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);

        UserProfile::create([
            'user_id' => $student1->id,
            'type' => 'siswa',
            'attendance_percent' => 92.0,
        ]);
        UserProfile::create([
            'user_id' => $student2->id,
            'type' => 'siswa',
            'attendance_percent' => 98.0,
        ]);

        $response = $this->actingAs($teacher)->get('/');

        $response->assertOk();
        $response->assertSee('Kehadiran: 95.0%');
    }

    public function test_superuser_sees_school_names_on_devices_in_fleet(): void
    {
        $schoolA = School::create([
            'npsn' => '20200117',
            'name' => 'SMK Agribisnis A',
            'slug' => 'smk-agribisnis-a',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200118',
            'name' => 'SMK Agribisnis B',
            'slug' => 'smk-agribisnis-b',
            'status' => 'active',
        ]);

        $superuser = User::factory()->create(['role' => 'superuser']);

        Device::create([
            'school_id' => $schoolA->id,
            'code' => 'TK-ROOT-A',
            'name' => 'Kit Alpha',
            'is_active' => true,
        ]);
        Device::create([
            'school_id' => $schoolB->id,
            'code' => 'TK-ROOT-B',
            'name' => 'Kit Beta',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superuser)->get('/');

        $response->assertOk();
        $response->assertSee('SMK Agribisnis A');
        $response->assertSee('SMK Agribisnis B');
    }

    public function test_superuser_school_filter_scopes_dashboard_metrics_and_monitoring(): void
    {
        $schoolA = School::create([
            'npsn' => '20200119',
            'name' => 'SMK Filter A',
            'slug' => 'smk-filter-a',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'npsn' => '20200120',
            'name' => 'SMK Filter B',
            'slug' => 'smk-filter-b',
            'status' => 'active',
        ]);
        $superuser = User::factory()->create(['role' => 'superuser']);
        $studentA = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolA->id]);
        $studentB = User::factory()->create(['role' => 'siswa', 'school_id' => $schoolB->id]);

        foreach ([[$studentA, 90.0], [$studentB, 40.0]] as [$student, $attendance]) {
            UserProfile::create([
                'user_id' => $student->id,
                'type' => 'siswa',
                'attendance_percent' => $attendance,
            ]);
        }

        Device::create([
            'school_id' => $schoolA->id,
            'code' => 'TK-FILTER-A',
            'name' => 'Kit Filter A',
            'is_active' => true,
        ]);
        Device::create([
            'school_id' => $schoolB->id,
            'code' => 'TK-FILTER-B',
            'name' => 'Kit Filter B',
            'is_active' => true,
        ]);

        $dashboard = $this->actingAs($superuser)->get('/?school_id='.$schoolA->id);
        $dashboard->assertOk();
        $dashboard->assertSee('TK-FILTER-A');
        $dashboard->assertDontSee('TK-FILTER-B');
        $dashboard->assertSee('Kehadiran: 90.0%');

        $monitoring = $this->get('/iot-monitoring?school_id='.$schoolA->id);
        $monitoring->assertOk();
        $monitoring->assertSee('TK-FILTER-A');
        $monitoring->assertDontSee('TK-FILTER-B');
    }

    public function test_guest_does_not_see_private_network_address_or_school_attendance(): void
    {
        $school = School::create([
            'npsn' => '20200122',
            'name' => 'SMK Guest Privacy',
            'slug' => 'smk-guest-privacy',
            'status' => 'active',
        ]);
        $device = Device::create([
            'school_id' => $school->id,
            'code' => 'TK-GUEST-01',
            'name' => 'Meja Guest',
            'ip_address' => '192.168.10.50',
            'is_active' => true,
        ]);
        TelemetryReading::create([
            'device_id' => $device->id,
            'ph' => 6.5,
            'humidity' => 45.0,
            'ec' => 1200,
            'recorded_at' => now(),
        ]);

        $student = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);
        UserProfile::create([
            'user_id' => $student->id,
            'type' => 'siswa',
            'attendance_percent' => 88.0,
        ]);

        // Guest access
        $response = $this->get('/');
        $response->assertOk();

        // IP address must NOT be exposed to guests
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('192.168.10.50', $content);

        // School-wide attendance aggregate must NOT be exposed to guests
        $this->assertStringNotContainsString('Kehadiran:', $content);
    }

    public function test_siswa_does_not_see_school_attendance_aggregate(): void
    {
        $school = School::create([
            'npsn' => '20200123',
            'name' => 'SMK Siswa Privacy',
            'slug' => 'smk-siswa-privacy',
            'status' => 'active',
        ]);
        $siswa = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);
        UserProfile::create([
            'user_id' => $siswa->id,
            'type' => 'siswa',
            'attendance_percent' => 91.0,
        ]);

        $response = $this->actingAs($siswa)->get('/');
        $response->assertOk();

        $content = (string) $response->getContent();
        // Student should not see the school-wide "Kehadiran:" badge in header
        $this->assertStringNotContainsString('Kehadiran:', $content);
    }

    public function test_guru_sees_school_attendance_aggregate(): void
    {
        $school = School::create([
            'npsn' => '20200124',
            'name' => 'SMK Guru Attendance',
            'slug' => 'smk-guru-attendance',
            'status' => 'active',
        ]);
        $guru = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);

        // Create actual student users with profiles
        $student1 = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);
        $student2 = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);
        UserProfile::create([
            'user_id' => $student1->id,
            'type' => 'siswa',
            'attendance_percent' => 93.0,
        ]);
        UserProfile::create([
            'user_id' => $student2->id,
            'type' => 'siswa',
            'attendance_percent' => 93.0,
        ]);

        $response = $this->actingAs($guru)->get('/');
        $response->assertOk();
        $response->assertSee('Kehadiran: 93.0%');
    }

    public function test_dashboard_no_longer_uses_kondisi_optimal_copy(): void
    {
        $school = School::create([
            'npsn' => '20200125',
            'name' => 'SMK Copy Check',
            'slug' => 'smk-copy-check',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);
        $device = Device::create([
            'school_id' => $school->id,
            'code' => 'TK-COPY',
            'name' => 'Meja Copy',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        TelemetryReading::create([
            'device_id' => $device->id,
            'provenance' => 'hardware',
            'ph' => 6.5,
            'humidity' => 60.0,
            'ec' => 1200,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get('/');
        $response->assertOk();

        $content = (string) $response->getContent();
        // The prohibited phrase "Kondisi Optimal" must not appear
        $this->assertStringNotContainsString('Kondisi Optimal', $content);
        // New honest empty state
        $this->assertStringContainsString('Pantauan Normal', $content);
    }

    public function test_dashboard_uses_resolved_is_online_not_raw_status(): void
    {
        $school = School::create([
            'npsn' => '20200126',
            'name' => 'SMK Status Check',
            'slug' => 'smk-status-check',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create(['role' => 'guru', 'school_id' => $school->id]);

        // Device with stale last_seen_at should show offline via resolved is_online
        Device::create([
            'school_id' => $school->id,
            'code' => 'TK-STALE-STATUS',
            'name' => 'Meja Stale',
            'is_active' => true,
            'status' => 'online', // raw DB value says online but heartbeat stale
            'last_seen_at' => now()->subMinutes(20),
        ]);

        $response = $this->actingAs($teacher)->get('/');
        $response->assertOk();

        $content = (string) $response->getContent();
        // Should show as offline in the fleet table (derived from is_online)
        $this->assertStringContainsString('Offline', $content);
        $this->assertStringNotContainsString('>Online<', $content);
    }
}
