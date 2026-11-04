<?php

namespace Tests\Feature;

use App\Jobs\GenerateCropRecommendationJob;
use App\Models\Device;
use App\Models\SubscriptionPlan;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CropRecommendationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Device $device;
    protected TelemetryReading $reading;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Guru Test',
            'email' => 'guru.test@bitanic.test',
            'password' => bcrypt('password'),
            'role' => 'guru',
        ]);

        $this->device = Device::create([
            'code' => 'BTLP-DEV-01',
            'name' => 'Farm 1',
            'is_active' => true,
        ]);

        $this->reading = TelemetryReading::create([
            'device_id' => $this->device->id,
            'nitrogen' => 45,
            'phosphorus' => 30,
            'potassium' => 120,
            'ec' => 500,
            'ph' => 6.5,
            'temperature' => 26.5,
            'humidity' => 75.0,
            'raw_payload' => [],
            'recorded_at' => now(),
        ]);
    }

    public function test_it_rejects_user_without_active_subscription(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/recommendations/request', [
                'reading_id' => $this->reading->id,
            ]);

        $response->assertStatus(402)
            ->assertJson([
                'success' => false,
                'message' => 'Kuota AI habis atau masa langganan berakhir. Silakan upgrade paket langganan Anda.',
            ]);
    }

    public function test_it_dispatches_async_job_and_returns_ticket_id(): void
    {
        Queue::fake();

        // Berikan langganan aktif
        $plan = SubscriptionPlan::create([
            'name' => 'Pro Sebulan',
            'code' => 'pro-sebulan',
            'price' => 50000,
            'duration_days' => 30,
            'features' => ['ai_quota' => 50],
            'is_active' => true,
        ]);

        UserSubscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/recommendations/request', [
                'reading_id' => $this->reading->id,
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'success',
                'ticket_id',
                'status',
                'message',
            ]);

        $ticketId = $response->json('ticket_id');
        $this->assertNotEmpty($ticketId);

        Queue::assertPushed(GenerateCropRecommendationJob::class, function ($job) use ($ticketId) {
            return $job->ticketId === $ticketId;
        });
    }

    public function test_job_execution_stores_result_in_cache_and_updates_status(): void
    {
        $ticketId = 'ticket-uuid-test-1234';

        $job = new GenerateCropRecommendationJob($this->user, $this->reading, $ticketId);
        $job->handle();

        $cachedData = Cache::get('crop_recommendation_' . $ticketId);
        $this->assertNotNull($cachedData);
        $this->assertEquals('completed', $cachedData['status']);
        $this->assertArrayHasKey('recommendations', $cachedData);
        $this->assertArrayHasKey('soil_classification', $cachedData);
    }
}
