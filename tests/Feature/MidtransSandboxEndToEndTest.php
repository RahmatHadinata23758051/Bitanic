<?php

namespace Tests\Feature;

use App\Models\PaymentHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MidtransSandboxEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected string $serverKey = 'SB-Mid-server-sandbox-test-12345';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('subscription.index') || ! Route::has('subscription.checkout')) {
            $this->markTestSkipped('Legacy SaaS subscription routes are disabled under LEGACY-01.');
        }

        config(['midtrans.server_key' => $this->serverKey]);
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_subscription_index_displays_plans_and_pricing(): void
    {
        $response = $this->get(route('subscription.index'));

        $response->assertStatus(200);
        $response->assertSee('Paket Langganan &amp; Kuota AI Gemini', false);
        $response->assertSee('Siswa Praktikan (Starter)');
        $response->assertSee('Guru Pembimbing (Pro Educator)');
        $response->assertSee('Laboratorium SMK 4.0 (Lab Enterprise)');
    }

    public function test_free_starter_plan_checkout_activates_subscription_immediately(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $freePlan = SubscriptionPlan::where('price', 0)->firstOrFail();

        $response = $this->actingAs($student)->post(route('subscription.checkout'), [
            'plan_id' => $freePlan->id,
        ]);

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $student->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('payment_histories', [
            'user_id' => $student->id,
            'plan_id' => $freePlan->id,
            'status' => 'settlement',
        ]);
    }

    public function test_paid_plan_checkout_creates_payment_record_and_returns_snap_token(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $paidPlan = SubscriptionPlan::where('code', 'plan-guru-pro')->firstOrFail();

        $response = $this->actingAs($teacher)->postJson(route('subscription.checkout'), [
            'plan_id' => $paidPlan->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'snap_token',
            'order_id',
        ]);

        $this->assertDatabaseHas('payment_histories', [
            'user_id' => $teacher->id,
            'plan_id' => $paidPlan->id,
            'status' => 'pending',
        ]);
    }

    public function test_end_to_end_simulated_webhook_settlement_extends_subscription(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $plan = SubscriptionPlan::where('code', 'plan-guru-pro')->firstOrFail();

        // 1. Checkout
        $checkoutResponse = $this->actingAs($teacher)->postJson(route('subscription.checkout'), [
            'plan_id' => $plan->id,
        ]);
        $orderId = $checkoutResponse->json('order_id');

        $payment = PaymentHistory::where('order_id', $orderId)->firstOrFail();
        $this->assertEquals('pending', $payment->status);

        // 2. Midtrans Webhook Callback dengan SHA512 Signature sah
        $grossAmount = number_format((float) $payment->amount, 2, '.', '');
        $signature = hash('sha512', $orderId.'200'.$grossAmount.$this->serverKey);

        $webhookResponse = $this->postJson('/api/v1/payments/webhook', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_time' => now()->toDateTimeString(),
        ]);

        $webhookResponse->assertStatus(200);
        $webhookResponse->assertJson(['success' => true]);

        // 3. Verifikasi Database
        $payment->refresh();
        $this->assertEquals('settlement', $payment->status);
        $this->assertEquals('qris', $payment->payment_type);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $teacher->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }
}
