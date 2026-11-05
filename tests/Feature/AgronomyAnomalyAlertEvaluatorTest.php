<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TelemetryReading;
use App\Services\Agronomy\AnomalyAlertEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgronomyAnomalyAlertEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private AnomalyAlertEvaluator $evaluator;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new AnomalyAlertEvaluator;
        $this->device = Device::create([
            'code' => 'TK-ATPH-01',
            'name' => 'Meja Praktikum ATPH 1',
            'is_active' => true,
        ]);
    }

    public function test_evaluation_returns_empty_collection_for_null_or_ideal_reading(): void
    {
        $this->assertTrue($this->evaluator->evaluate(null)->isEmpty());

        $idealReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 6.5,
            'ec' => 1500.0,
            'humidity' => 60.0,
            'temperature' => 25.0,
            'nitrogen' => 60.0,
            'phosphorus' => 35.0,
            'potassium' => 140.0,
            'recorded_at' => now(),
        ]);

        $alerts = $this->evaluator->evaluate($idealReading);
        $this->assertTrue($alerts->isEmpty());
        $this->assertFalse($this->evaluator->isAnomaly($idealReading));
    }

    public function test_evaluation_detects_acidic_and_alkaline_ph_with_safe_guidance(): void
    {
        // Acidic pH (< 5.5)
        $acidicReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 4.9,
            'ec' => 1400.0,
            'humidity' => 55.0,
            'temperature' => 26.0,
            'recorded_at' => now(),
        ]);

        $acidicAlerts = $this->evaluator->evaluate($acidicReading);
        $this->assertNotEmpty($acidicAlerts);
        $phAlert = $acidicAlerts->firstWhere('parameter_key', 'ph');
        $this->assertNotNull($phAlert);
        $this->assertSame('danger', $phAlert['severity']);
        $this->assertStringContainsString('kapur dolomit', $phAlert['action']);

        // Alkaline pH (> 7.5)
        $alkalineReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 8.1,
            'ec' => 1400.0,
            'humidity' => 55.0,
            'temperature' => 26.0,
            'recorded_at' => now(),
        ]);

        $alkalineAlerts = $this->evaluator->evaluate($alkalineReading);
        $alkalineAlert = $alkalineAlerts->firstWhere('parameter_key', 'ph');
        $this->assertNotNull($alkalineAlert);
        $this->assertSame('warning', $alkalineAlert['severity']);
        $this->assertStringContainsString('belerang', $alkalineAlert['action']);
    }

    public function test_evaluation_detects_low_and_high_ec_without_prescriptive_solenoid_relay_commands(): void
    {
        // Low EC (< 800)
        $lowEcReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 6.5,
            'ec' => 450.0,
            'humidity' => 55.0,
            'temperature' => 25.0,
            'recorded_at' => now(),
        ]);

        $alertsLow = $this->evaluator->evaluate($lowEcReading);
        $ecLowAlert = $alertsLow->firstWhere('parameter_key', 'ec');
        $this->assertNotNull($ecLowAlert);
        $this->assertSame('warning', $ecLowAlert['severity']);
        // Verify absence of prescriptive physical solenoid/relay commands
        $this->assertStringNotContainsString('relai', strtolower($ecLowAlert['action']));
        $this->assertStringNotContainsString('solenoid', strtolower($ecLowAlert['action']));
        $this->assertStringContainsString('AB Mix', $ecLowAlert['action']);

        // High EC (> 2400)
        $highEcReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 6.5,
            'ec' => 2900.0,
            'humidity' => 55.0,
            'temperature' => 25.0,
            'recorded_at' => now(),
        ]);

        $alertsHigh = $this->evaluator->evaluate($highEcReading);
        $ecHighAlert = $alertsHigh->firstWhere('parameter_key', 'ec');
        $this->assertNotNull($ecHighAlert);
        $this->assertSame('danger', $ecHighAlert['severity']);
        $this->assertStringContainsString('flushing', strtolower($ecHighAlert['action']));
    }

    public function test_evaluation_detects_low_and_high_moisture_without_prescriptive_relay_commands(): void
    {
        // Low Moisture (< 40.0)
        $dryReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 6.5,
            'ec' => 1500.0,
            'humidity' => 28.5,
            'temperature' => 27.0,
            'recorded_at' => now(),
        ]);

        $dryAlerts = $this->evaluator->evaluate($dryReading);
        $dryAlert = $dryAlerts->firstWhere('parameter_key', 'humidity');
        $this->assertNotNull($dryAlert);
        $this->assertSame('danger', $dryAlert['severity']);
        // Verify complete removal of prescriptive physical command 'Relay 1 ON'
        $this->assertStringNotContainsString('Relay 1 ON', $dryAlert['action']);
        $this->assertStringNotContainsString('Relay', $dryAlert['action']);
        $this->assertStringContainsString('irigasi', strtolower($dryAlert['action']));

        // High Moisture / Waterlogged (> 85.0)
        $wetReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 6.5,
            'ec' => 1500.0,
            'humidity' => 92.0,
            'temperature' => 25.0,
            'recorded_at' => now(),
        ]);

        $wetAlerts = $this->evaluator->evaluate($wetReading);
        $wetAlert = $wetAlerts->firstWhere('parameter_key', 'humidity');
        $this->assertNotNull($wetAlert);
        $this->assertSame('warning', $wetAlert['severity']);
        $this->assertStringContainsString('drainase', strtolower($wetAlert['action']));
    }

    public function test_evaluation_detects_extreme_temperatures_without_prescriptive_relay_commands(): void
    {
        // High Temperature (> 33.0)
        $hotReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 6.5,
            'ec' => 1500.0,
            'humidity' => 50.0,
            'temperature' => 36.5,
            'recorded_at' => now(),
        ]);

        $hotAlerts = $this->evaluator->evaluate($hotReading);
        $hotAlert = $hotAlerts->firstWhere('parameter_key', 'temperature');
        $this->assertNotNull($hotAlert);
        $this->assertSame('warning', $hotAlert['severity']);
        // Verify complete removal of prescriptive physical command 'Relay 3 ON'
        $this->assertStringNotContainsString('Relay 3 ON', $hotAlert['action']);
        $this->assertStringNotContainsString('Relay', $hotAlert['action']);
        $this->assertStringContainsString('paranet', strtolower($hotAlert['action']));

        // Cold Temperature (< 18.0)
        $coldReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 6.5,
            'ec' => 1500.0,
            'humidity' => 50.0,
            'temperature' => 15.0,
            'recorded_at' => now(),
        ]);

        $coldAlerts = $this->evaluator->evaluate($coldReading);
        $coldAlert = $coldAlerts->firstWhere('parameter_key', 'temperature');
        $this->assertNotNull($coldAlert);
        $this->assertSame('warning', $coldAlert['severity']);
        $this->assertStringContainsString('ventilasi', strtolower($coldAlert['action']));
    }

    public function test_evaluation_detects_macro_nutrient_npk_deficiencies(): void
    {
        // Reading with N, P, K deficiencies
        $deficientReading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 6.5,
            'ec' => 1500.0,
            'humidity' => 55.0,
            'temperature' => 25.0,
            'nitrogen' => 12.0,     // < 20.0
            'phosphorus' => 8.0,    // < 15.0
            'potassium' => 35.0,    // < 50.0
            'recorded_at' => now(),
        ]);

        $alerts = $this->evaluator->evaluate($deficientReading);
        $nAlert = $alerts->firstWhere('parameter_key', 'nitrogen');
        $pAlert = $alerts->firstWhere('parameter_key', 'phosphorus');
        $kAlert = $alerts->firstWhere('parameter_key', 'potassium');

        $this->assertNotNull($nAlert);
        $this->assertNotNull($pAlert);
        $this->assertNotNull($kAlert);

        $this->assertStringContainsString('klorosis', strtolower($nAlert['impact']));
        $this->assertStringContainsString('perakaran', strtolower($pAlert['impact']));
        $this->assertStringContainsString('nekrosis', strtolower($kAlert['impact']));
    }

    public function test_alert_records_contain_standard_fields_and_active_lifecycle(): void
    {
        $reading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'ph' => 4.5,
            'recorded_at' => now(),
        ]);

        $alerts = $this->evaluator->evaluate($reading);
        $this->assertCount(1, $alerts);

        $alert = $alerts->first();
        $this->assertArrayHasKey('parameter', $alert);
        $this->assertArrayHasKey('parameter_key', $alert);
        $this->assertArrayHasKey('value', $alert);
        $this->assertArrayHasKey('unit', $alert);
        $this->assertArrayHasKey('severity', $alert);
        $this->assertArrayHasKey('title', $alert);
        $this->assertArrayHasKey('impact', $alert);
        $this->assertArrayHasKey('action', $alert);
        $this->assertArrayHasKey('status', $alert);
        $this->assertSame('active', $alert['status']);
    }
}
