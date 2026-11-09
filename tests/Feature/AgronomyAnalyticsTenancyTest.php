<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgronomyAnalyticsTenancyTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $teacherA;

    private User $teacherB;

    private User $superuser;

    private Device $deviceA1;

    private Device $deviceA2;

    private Device $deviceB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::query()->where('status', 'active')->orderBy('id')->first() ?? School::create([
            'npsn' => '30300001',
            'name' => 'SMK Pertanian Sukaraja',
            'slug' => 'smk-pertanian-sukaraja',
            'status' => 'active',
        ]);

        $this->schoolB = School::where('id', '!=', $this->schoolA->id)->first() ?? School::create([
            'npsn' => '30300002',
            'name' => 'SMK Pertanian Subang',
            'slug' => 'smk-pertanian-subang',
            'status' => 'active',
        ]);
        $this->schoolB->update(['status' => 'active']);

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

        $this->superuser = User::factory()->create([
            'role' => 'superuser',
            'school_id' => $this->schoolA->id,
            'name' => 'Superuser Agronomy',
        ]);

        $this->deviceA1 = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-ANA-A1',
            'name' => 'Meja Analitik A1',
            'is_active' => true,
        ]);

        $this->deviceA2 = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-ANA-A2',
            'name' => 'Meja Analitik A2',
            'is_active' => true,
        ]);

        $this->deviceB1 = Device::create([
            'school_id' => $this->schoolB->id,
            'code' => 'TK-ANA-B1',
            'name' => 'Meja Analitik B1',
            'is_active' => true,
        ]);

        // Seed readings on deviceA1
        TelemetryReading::create([
            'device_id' => $this->deviceA1->id,
            'provenance' => 'hardware',
            'ph' => 6.5,
            'ec' => 1500.0,
            'humidity' => 70.0,
            'temperature' => 25.0,
            'nitrogen' => 80.0,
            'phosphorus' => 40.0,
            'potassium' => 120.0,
            'recorded_at' => now()->subMinutes(10),
        ]);

        TelemetryReading::create([
            'device_id' => $this->deviceA1->id,
            'provenance' => 'hardware',
            'ph' => 6.8,
            'ec' => 1600.0,
            'humidity' => 65.0,
            'temperature' => 26.0,
            'nitrogen' => 85.0,
            'phosphorus' => 42.0,
            'potassium' => 125.0,
            'recorded_at' => now()->subMinutes(5),
        ]);

        // Seed distinct readings on deviceB1
        TelemetryReading::create([
            'device_id' => $this->deviceB1->id,
            'provenance' => 'hardware',
            'ph' => 4.2,
            'ec' => 3100.0,
            'humidity' => 35.0,
            'temperature' => 37.0,
            'nitrogen' => 15.0,
            'phosphorus' => 10.0,
            'potassium' => 45.0,
            'recorded_at' => now()->subMinutes(2),
        ]);
    }

    public function test_guest_can_access_analytics_and_sees_default_school_devices(): void
    {
        $response = $this->get('/analytics');
        $response->assertOk();
        $response->assertViewIs('pages.analytics.agronomy-analytics');
        $response->assertSee('TK-ANA-A1');
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
            'npsn' => '30300999',
            'name' => 'SMK Inactive Agri',
            'slug' => 'smk-inactive-agri',
            'status' => 'inactive',
        ]);

        $teacherInactiveSchool = User::factory()->create([
            'role' => 'guru',
            'school_id' => $inactiveSchool->id,
        ]);

        $this->actingAs($teacherNoSchool)->get('/analytics')->assertForbidden();
        $this->actingAs($teacherInactiveSchool)->get('/analytics')->assertForbidden();
    }

    public function test_teacher_a_sees_only_school_a_devices_in_selector(): void
    {
        $response = $this->actingAs($this->teacherA)->get('/analytics');
        $response->assertOk();
        $response->assertSee('TK-ANA-A1');
        $response->assertSee('TK-ANA-A2');
        $response->assertDontSee('TK-ANA-B1');
    }

    public function test_cross_school_idor_device_tampering_is_rejected_and_isolated(): void
    {
        // Teacher A attempts to access School B device analytics via ?device_id=
        $response = $this->actingAs($this->teacherA)->get("/analytics?device_id={$this->deviceB1->id}");
        $response->assertOk();

        // Selected device must fallback to School A's device, not School B
        $response->assertDontSee('TK-ANA-B1');
        $this->assertNotEquals($this->deviceB1->id, $response->viewData('selectedDevice')?->id);
        $this->assertEquals($this->deviceA1->id, $response->viewData('selectedDevice')?->id);

        // Telemetry readings must NOT contain School B's pH 4.2 data
        $readings = $response->viewData('readings') ?? collect();
        foreach ($readings as $reading) {
            $this->assertNotEquals(4.2, (float) $reading->ph);
        }
    }

    public function test_superuser_can_filter_analytics_by_school(): void
    {
        // Superuser with school_id filter for School B
        $response = $this->actingAs($this->superuser)->get("/analytics?school_id={$this->schoolB->id}");
        $response->assertOk();
        $response->assertSee('TK-ANA-B1');
        $this->assertEquals($this->deviceB1->id, $response->viewData('selectedDevice')?->id);

        // Superuser without school_id filter sees all devices
        $responseAll = $this->actingAs($this->superuser)->get('/analytics');
        $responseAll->assertOk();
        $responseAll->assertSee('TK-ANA-A1');
        $responseAll->assertSee('TK-ANA-B1');
    }

    public function test_correlation_calculation_and_chart_data_structure(): void
    {
        $response = $this->actingAs($this->teacherA)->get('/analytics');
        $response->assertOk();

        $correlations = $response->viewData('correlations');
        $this->assertIsArray($correlations);
        $this->assertCount(2, $correlations);

        // Pearson r for pH vs EC should be a valid float
        $this->assertNotNull($correlations[0]['value']);
        $this->assertIsFloat($correlations[0]['value']);

        $chartData = $response->viewData('chartData');
        $this->assertArrayHasKey('ph', $chartData);
        $this->assertArrayHasKey('ec', $chartData);
        $this->assertCount(2, $chartData['ph']['values']);
        $this->assertSame(6.5, $chartData['ph']['values'][0]['y']);
        $this->assertSame(6.8, $chartData['ph']['values'][1]['y']);
        $this->assertSame(
            $this->deviceA1->telemetryReadings()->orderBy('recorded_at')->first()->recorded_at->toIso8601String(),
            $chartData['ph']['values'][0]['x'],
        );
        $response->assertDontSee('mockVals');
        $response->assertSee('initialData:');
        $response->assertSee($chartData['ph']['values'][0]['x']);
    }

    public function test_analytics_time_range_filtering(): void
    {
        // Add an older reading on deviceA1 (2 hours ago)
        TelemetryReading::create([
            'device_id' => $this->deviceA1->id,
            'provenance' => 'hardware',
            'ph' => 7.0,
            'ec' => 1700.0,
            'humidity' => 60.0,
            'temperature' => 24.0,
            'nitrogen' => 90.0,
            'phosphorus' => 45.0,
            'potassium' => 130.0,
            'recorded_at' => now()->subHours(2),
        ]);

        // 1-hour range should exclude the 2-hour-old reading
        $resp1h = $this->actingAs($this->teacherA)->get('/analytics?range=1h');
        $resp1h->assertOk();
        $this->assertEquals('1h', $resp1h->viewData('selectedRange'));
        $this->assertEquals('1 Jam Terakhir', $resp1h->viewData('periodLabel'));
        $this->assertEquals(2, $resp1h->viewData('readingCount'));

        // 24-hour range should include the 2-hour-old reading
        $resp24h = $this->actingAs($this->teacherA)->get('/analytics?range=24h');
        $resp24h->assertOk();
        $this->assertEquals('24h', $resp24h->viewData('selectedRange'));
        $this->assertEquals(3, $resp24h->viewData('readingCount'));
    }

    public function test_analytics_correlations_require_ten_samples_for_sufficiency(): void
    {
        // Initially 2 readings from setUp: not sufficient (n < 10)
        $resp = $this->actingAs($this->teacherA)->get('/analytics');
        $resp->assertOk();
        $correlations = $resp->viewData('correlations');
        $this->assertFalse($correlations[0]['is_sufficient']);
        $this->assertEquals(2, $correlations[0]['n']);
        $this->assertEquals('insufficient_samples', $correlations[0]['status']);
        $this->assertEquals('Data Belum Cukup', $correlations[0]['status_label']);

        // Insert 8 more varying readings to reach 10
        for ($i = 1; $i <= 8; $i++) {
            TelemetryReading::create([
                'device_id' => $this->deviceA1->id,
                'provenance' => 'hardware',
                'ph' => 6.0 + ($i * 0.1),
                'ec' => 1400.0 + ($i * 30),
                'humidity' => 60.0 + $i,
                'temperature' => 24.0 + ($i * 0.5),
                'nitrogen' => 70.0 + $i,
                'phosphorus' => 35.0 + $i,
                'potassium' => 110.0 + $i,
                'recorded_at' => now()->subMinutes(15 + $i),
            ]);
        }

        $respSufficient = $this->actingAs($this->teacherA)->get('/analytics');
        $respSufficient->assertOk();
        $correlationsSufficient = $respSufficient->viewData('correlations');
        $this->assertTrue($correlationsSufficient[0]['is_sufficient']);
        $this->assertEquals(10, $correlationsSufficient[0]['n']);
        $this->assertEquals('sufficient', $correlationsSufficient[0]['status']);
        $this->assertNotNull($correlationsSufficient[0]['value']);
        $this->assertNotEmpty($correlationsSufficient[0]['points']);
    }

    public function test_analytics_zero_variance_handled_gracefully(): void
    {
        // Create a new device with identical readings (zero variance)
        $flatDevice = Device::create([
            'school_id' => $this->schoolA->id,
            'code' => 'TK-FLAT-01',
            'name' => 'Meja Flat Zero Variance',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            TelemetryReading::create([
                'device_id' => $flatDevice->id,
                'provenance' => 'hardware',
                'ph' => 6.5,
                'ec' => 1500.0,
                'humidity' => 50.0,
                'temperature' => 25.0,
                'nitrogen' => 80.0,
                'phosphorus' => 40.0,
                'potassium' => 120.0,
                'recorded_at' => now()->subMinutes($i),
            ]);
        }

        $resp = $this->actingAs($this->teacherA)->get("/analytics?device_id={$flatDevice->id}");
        $resp->assertOk();
        $correlations = $resp->viewData('correlations');
        $this->assertNull($correlations[0]['value']);
        $this->assertFalse($correlations[0]['is_sufficient']);
        $this->assertEquals('zero_variance', $correlations[0]['status']);
        $this->assertEquals('Variansi Nol', $correlations[0]['status_label']);
    }

    public function test_analytics_resolves_school_scoped_thresholds(): void
    {
        $resp = $this->actingAs($this->teacherA)->get('/analytics');
        $resp->assertOk();

        $thresholds = $resp->viewData('thresholds');
        $this->assertIsArray($thresholds);
        $this->assertCount(7, $thresholds);

        foreach (['ph', 'moisture', 'ec', 'temperature', 'nitrogen', 'phosphorus', 'potassium'] as $param) {
            $this->assertArrayHasKey($param, $thresholds);
            $this->assertArrayHasKey('ideal_min', $thresholds[$param]);
            $this->assertArrayHasKey('ideal_max', $thresholds[$param]);
            $this->assertArrayHasKey('low_implication', $thresholds[$param]);
            $this->assertArrayHasKey('high_implication', $thresholds[$param]);
            $this->assertArrayHasKey('source_label', $thresholds[$param]);
        }

        $this->assertIsBool($resp->viewData('isCustomizedThresholds'));
    }
}
