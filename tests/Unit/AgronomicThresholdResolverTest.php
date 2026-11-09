<?php

namespace Tests\Unit;

use App\Models\School;
use App\Models\SchoolSetting;
use App\Services\Agronomy\AgronomicThresholdResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgronomicThresholdResolverTest extends TestCase
{
    use RefreshDatabase;

    private AgronomicThresholdResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AgronomicThresholdResolver;
    }

    public function test_resolve_returns_all_seven_parameters_with_defaults(): void
    {
        $thresholds = $this->resolver->resolveForSchool(null);

        $expectedKeys = ['ph', 'moisture', 'ec', 'temperature', 'nitrogen', 'phosphorus', 'potassium'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $thresholds);
            $this->assertArrayHasKey('ideal_min', $thresholds[$key]);
            $this->assertArrayHasKey('ideal_max', $thresholds[$key]);
            $this->assertArrayHasKey('unit', $thresholds[$key]);
            $this->assertArrayHasKey('parameter', $thresholds[$key]);
        }

        $this->assertEquals(6.0, $thresholds['ph']['ideal_min']);
        $this->assertEquals(7.2, $thresholds['ph']['ideal_max']);
        $this->assertEquals(22.0, $thresholds['temperature']['ideal_min']);
        $this->assertEquals(29.0, $thresholds['temperature']['ideal_max']);
        $this->assertEquals(25.0, $thresholds['phosphorus']['ideal_min']);
        $this->assertEquals(60.0, $thresholds['phosphorus']['ideal_max']);
        $this->assertEquals(100.0, $thresholds['potassium']['ideal_min']);
        $this->assertEquals(200.0, $thresholds['potassium']['ideal_max']);
    }

    public function test_resolve_overrides_configured_school_thresholds(): void
    {
        $school = School::create([
            'name' => 'SMK Pertanian Test',
            'slug' => 'smk-pertanian-test',
            'npsn' => '12345678',
            'status' => 'active',
        ]);

        SchoolSetting::create([
            'school_id' => $school->id,
            'school_name' => 'SMK Pertanian Test',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru Test',
            'sensor_thresholds' => [
                'minPH' => 6.2,
                'maxPH' => 6.8,
                'minTemperature' => 20.0,
                'maxTemperature' => 28.0,
                'minPhosphorus' => 30.0,
                'maxPhosphorus' => 50.0,
            ],
        ]);

        $thresholds = $this->resolver->resolveForSchool($school->id);

        // Custom configured values
        $this->assertEquals(6.2, $thresholds['ph']['ideal_min']);
        $this->assertEquals(6.8, $thresholds['ph']['ideal_max']);
        $this->assertEquals(20.0, $thresholds['temperature']['ideal_min']);
        $this->assertEquals(28.0, $thresholds['temperature']['ideal_max']);
        $this->assertEquals(30.0, $thresholds['phosphorus']['ideal_min']);
        $this->assertEquals(50.0, $thresholds['phosphorus']['ideal_max']);

        // Unconfigured parameters remain defaults
        $this->assertEquals(45.0, $thresholds['moisture']['ideal_min']);
        $this->assertEquals(100.0, $thresholds['potassium']['ideal_min']);
    }

    public function test_evaluate_value_returns_accurate_status_and_labels(): void
    {
        $phDef = AgronomicThresholdResolver::DEFAULTS['ph'];

        // Null value
        $evalNull = $this->resolver->evaluateValue('ph', null, $phDef);
        $this->assertEquals('optimal', $evalNull['status']);
        $this->assertEquals('Belum ada data', $evalNull['label']);
        $this->assertFalse($evalNull['is_optimal']);

        // In range
        $evalOpt = $this->resolver->evaluateValue('ph', 6.5, $phDef);
        $this->assertEquals('optimal', $evalOpt['status']);
        $this->assertEquals('Dalam rentang acuan', $evalOpt['label']);
        $this->assertTrue($evalOpt['is_optimal']);

        // Warning low (between 5.5 and 6.0)
        $evalWarn = $this->resolver->evaluateValue('ph', 5.8, $phDef);
        $this->assertEquals('warning', $evalWarn['status']);
        $this->assertFalse($evalWarn['is_optimal']);

        // Critical low (<= 5.5)
        $evalCrit = $this->resolver->evaluateValue('ph', 5.0, $phDef);
        $this->assertEquals('critical', $evalCrit['status']);
        $this->assertEquals('Masam Ekstrem', $evalCrit['label']);
        $this->assertFalse($evalCrit['is_optimal']);
    }

    public function test_calculate_health_score_with_live_telemetry(): void
    {
        $thresholds = AgronomicThresholdResolver::DEFAULTS;

        // Offline / not live -> null
        $scoreOffline = $this->resolver->calculateHealthScore(['ph' => 6.5], $thresholds, false);
        $this->assertNull($scoreOffline);

        // Optimal readings -> 100
        $perfectTelemetry = [
            'ph' => 6.5,
            'moisture' => 55.0,
            'ec' => 1500.0,
            'temperature' => 25.0,
            'nitrogen' => 80.0,
            'phosphorus' => 40.0,
            'potassium' => 150.0,
        ];
        $scorePerfect = $this->resolver->calculateHealthScore($perfectTelemetry, $thresholds, true);
        $this->assertEquals(100, $scorePerfect);

        // Stressed readings -> reduced score
        $stressedTelemetry = array_merge($perfectTelemetry, [
            'ph' => 4.5, // critical low (-15)
            'moisture' => 20.0, // critical low (-15)
        ]);
        $scoreStressed = $this->resolver->calculateHealthScore($stressedTelemetry, $thresholds, true);
        $this->assertEquals(70, $scoreStressed);
    }
}

