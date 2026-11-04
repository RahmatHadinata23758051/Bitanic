<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TelemetryReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_page_renders_successfully_with_anomaly_hub_and_table(): void
    {
        $device = Device::create([
            'code' => 'KIT-01',
            'name' => 'Meja Praktik 1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        // Reading with critical low pH (anomaly)
        TelemetryReading::create([
            'device_id' => $device->id,
            'ph' => 4.8,
            'ec' => 1400.0,
            'humidity' => 60.0,
            'temperature' => 26.0,
            'nitrogen' => 50.0,
            'phosphorus' => 30.0,
            'potassium' => 120.0,
            'raw_payload' => ['sample' => 'test'],
            'threshold_flags' => ['PH_LOW'],
            'recorded_at' => now(),
        ]);

        $response = $this->get(route('telemetry.history'));

        $response->assertStatus(200);
        $response->assertSee('Riwayat Data Sensor &amp; Pusat Peringatan Anomali', false);
        $response->assertSee('Pusat Peringatan Deviasi Agronomi (Anomaly Alert Hub)');
        $response->assertSee('Tingkat Keasaman Masam Kritis');
        $response->assertSee('Aplikasikan 50-80 gram kapur dolomit');
        $response->assertSee('KIT-01');
    }

    public function test_history_filters_by_device_and_anomaly_status(): void
    {
        $deviceA = Device::create([
            'code' => 'KIT-01',
            'name' => 'Meja Praktik 1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        $deviceB = Device::create([
            'code' => 'KIT-02',
            'name' => 'Meja Praktik 2',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        // Device A: Normal reading
        TelemetryReading::create([
            'device_id' => $deviceA->id,
            'ph' => 6.5,
            'ec' => 1500.0,
            'humidity' => 65.0,
            'temperature' => 25.0,
            'nitrogen' => 60.0,
            'phosphorus' => 35.0,
            'potassium' => 130.0,
            'raw_payload' => ['sample' => 'normal'],
            'threshold_flags' => [],
            'recorded_at' => now()->subHours(2),
        ]);

        // Device B: Anomaly reading
        TelemetryReading::create([
            'device_id' => $deviceB->id,
            'ph' => 4.2,
            'ec' => 2800.0,
            'humidity' => 35.0,
            'temperature' => 36.0,
            'nitrogen' => 10.0,
            'phosphorus' => 5.0,
            'potassium' => 20.0,
            'raw_payload' => ['sample' => 'anomaly'],
            'threshold_flags' => ['PH_LOW', 'EC_HIGH'],
            'recorded_at' => now()->subHour(),
        ]);

        // Filter only anomaly
        $responseAnomaly = $this->get(route('telemetry.history', ['status' => 'anomaly']));
        $responseAnomaly->assertStatus(200);
        $responseAnomaly->assertSee('KIT-02');

        // Filter only device A
        $responseDevA = $this->get(route('telemetry.history', ['device_id' => $deviceA->id]));
        $responseDevA->assertStatus(200);
        $responseDevA->assertSee('KIT-01');
    }

    public function test_export_csv_streams_valid_csv_file(): void
    {
        $device = Device::create([
            'code' => 'KIT-01',
            'name' => 'Meja Praktik 1',
            'connection_type' => 'wifi',
            'is_active' => true,
        ]);

        TelemetryReading::create([
            'device_id' => $device->id,
            'ph' => 6.2,
            'ec' => 1300.0,
            'humidity' => 68.0,
            'temperature' => 24.0,
            'nitrogen' => 55.0,
            'phosphorus' => 28.0,
            'potassium' => 115.0,
            'raw_payload' => ['sample' => 'test'],
            'recorded_at' => now(),
        ]);

        $response = $this->get(route('telemetry.history.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="bitanic-telemetry-', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Waktu Pengukuran (WIB)', $content);
        $this->assertStringContainsString('pH Tanah', $content);
        $this->assertStringContainsString('KIT-01', $content);
        $this->assertStringContainsString('6.20', $content);
    }
}
