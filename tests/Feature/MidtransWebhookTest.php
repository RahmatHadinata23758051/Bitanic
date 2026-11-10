<?php

namespace Tests\Feature;

use App\Models\PaymentHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected SubscriptionPlan $plan;

    protected PaymentHistory $payment;

    protected string $serverKey = 'SB-Mid-server-test-key-12345';

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => $this->serverKey]);

        $this->user = User::create([
            'name' => 'Guru Langganan',
            'email' => 'guru.pay@bitanic.test',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'school_id' => 1,
        ]);

        $this->plan = SubscriptionPlan::create([
            'name' => 'Pro Sebulan',
            'code' => 'pro-sebulan',
            'price' => 50000.00,
            'duration_days' => 30,
            'features' => ['ai_quota' => 50],
            'is_active' => true,
        ]);

        $this->payment = PaymentHistory::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'order_id' => 'ORDER-TEST-001',
            'amount' => 50000.00,
            'status' => 'pending',
        ]);
    }

    public function test_it_rejects_webhook_with_invalid_sha512_signature(): void
    {
        $payload = [
            'order_id' => $this->payment->order_id,
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'signature_key' => 'fake_invalid_sha512_signature',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ];

        $response = $this->postJson('/api/v1/payments/webhook', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid Midtrans signature key.',
            ]);

        // Status pembayaran tetap pending
        $this->assertEquals('pending', $this->payment->fresh()->status);
    }

    public function test_it_handles_valid_settlement_and_activates_subscription(): void
    {
        $orderId = $this->payment->order_id;
        $statusCode = '200';
        $grossAmount = '50000.00';
        $validSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $validSignature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_time' => now()->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/payments/webhook', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment webhook processed successfully.',
            ]);

        // Verifikasi payment status berubah ke settlement
        $this->assertEquals('settlement', $this->payment->fresh()->status);
        $this->assertEquals('qris', $this->payment->fresh()->payment_type);

        // Verifikasi UserSubscription aktif
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    public function test_it_guarantees_idempotency_on_repeated_webhook_calls(): void
    {
        $orderId = $this->payment->order_id;
        $statusCode = '200';
        $grossAmount = '50000.00';
        $validSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $validSignature,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ];

        // Call pertama: aktivasi subscription
        $this->postJson('/api/v1/payments/webhook', $payload)->assertStatus(200);

        $subscription = UserSubscription::where('user_id', $this->user->id)->first();
        $initialEndsAt = $subscription->ends_at;

        // Simulasi waktu maju 5 menit kemudian ada webhook retry dari Midtrans
        Carbon::setTestNow(now()->addMinutes(5));

        // Call kedua (retry): tidak boleh merubah tanggal ends_at (anti-double-credit)
        $retryResponse = $this->postJson('/api/v1/payments/webhook', $payload);

        $retryResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Transaction was already processed (idempotent).',
            ]);

        // Langganan tidak bertambah ganda
        $this->assertEquals(1, UserSubscription::where('user_id', $this->user->id)->count());
        $this->assertEquals($initialEndsAt->toDateTimeString(), $subscription->fresh()->ends_at->toDateTimeString());

        Carbon::setTestNow();
    }
}
