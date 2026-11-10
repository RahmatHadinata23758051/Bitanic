<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Services\PlantConditionEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantingMediaNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_evaluates_plant_condition_gracefully_when_telemetry_table_is_empty(): void
    {
        // Pastikan tabel telemetri kosong total
        $this->assertEquals(0, TelemetryReading::count());

        $evaluator = new PlantConditionEvaluator;
        $results = $evaluator->evaluate(['Cabai', 'Tomat'], null);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals('Cabai', $results[0]['tanaman']);
        $this->assertNotEmpty($results[0]['kondisi']);
        $this->assertEquals('Tomat', $results[1]['tanaman']);
        $this->assertNotEmpty($results[1]['kondisi']);
    }

    public function test_it_creates_planting_media_successfully_when_telemetry_table_is_empty(): void
    {
        $this->assertEquals(0, TelemetryReading::count());

        $user = User::create([
            'name' => 'Guru Pertanian',
            'email' => 'guru@bitanic.test',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'school_id' => 1,
        ]);

        $response = $this->actingAs($user)->post('/media', [
            'name' => 'Plot A1 Cabai Hidroponik',
            'plants' => ['Cabai Rawit', 'Bawang Merah'],
        ]);

        // Verifikasi tidak crash 500, melainkan redirect sukses
        $response->assertStatus(302);
        $this->assertDatabaseCount('media', 1);

        $media = Media::first();
        $this->assertEquals('Plot A1 Cabai Hidroponik', $media->name);
        $this->assertIsArray($media->plants);
        $this->assertIsArray($media->plants_condition);
        $this->assertCount(2, $media->plants_condition);
    }
}
