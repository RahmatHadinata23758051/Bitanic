<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegionReverseGeocodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverse_geocode_requires_authentication(): void
    {
        $response = $this->getJson('/api/regions/reverse-geocode?lat=-6.9213&lng=106.9634');

        $response->assertUnauthorized();
    }

    public function test_reverse_geocode_validates_coordinates(): void
    {
        $user = User::factory()->create(['role' => 'superuser']);

        $response = $this->actingAs($user)->getJson('/api/regions/reverse-geocode');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_reverse_geocode_resolves_and_matches_administrative_regions(): void
    {
        $user = User::factory()->create(['role' => 'superuser']);

        $province = Province::create([
            'code' => '32',
            'name' => 'JAWA BARAT',
        ]);

        $regency = Regency::create([
            'code' => '3202',
            'province_code' => $province->code,
            'name' => 'KABUPATEN SUKABUMI',
        ]);

        $district = District::create([
            'code' => '3202170',
            'regency_code' => $regency->code,
            'name' => 'SUKARAJA',
        ]);

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => [
                    'state' => 'Jawa Barat',
                    'county' => 'Kabupaten Sukabumi',
                    'town' => 'Sukaraja',
                    'road' => 'Jl. Raya Sukabumi',
                    'house_number' => '45',
                ],
                'display_name' => 'Jl. Raya Sukabumi No. 45, Sukaraja, Kabupaten Sukabumi, Jawa Barat',
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/regions/reverse-geocode?lat=-6.9213&lng=106.9634');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.province_code', '32')
            ->assertJsonPath('data.province_name', 'JAWA BARAT')
            ->assertJsonPath('data.regency_code', '3202')
            ->assertJsonPath('data.regency_name', 'KABUPATEN SUKABUMI')
            ->assertJsonPath('data.district_code', '3202170')
            ->assertJsonPath('data.district_name', 'SUKARAJA')
            ->assertJsonPath('data.regencies.0.code', '3202')
            ->assertJsonPath('data.districts.0.code', '3202170');
    }
}
