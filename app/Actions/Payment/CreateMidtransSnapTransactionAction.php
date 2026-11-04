<?php

namespace App\Actions\Payment;

use App\Models\PaymentHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateMidtransSnapTransactionAction
{
    /**
     * Create a payment record and obtain Midtrans Snap token.
     *
     * @param User $user
     * @param SubscriptionPlan $plan
     * @return array{payment: PaymentHistory, snap_token: string|null, redirect_url: string|null}
     */
    public function execute(User $user, SubscriptionPlan $plan): array
    {
        $orderId = 'BITANIC-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
        $amount = (float) $plan->price;

        $payment = PaymentHistory::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => 'pending',
        ]);

        // Jika paket gratis (Rp 0), otomatis selesaikan langsung tanpa gateway
        if ($amount <= 0) {
            $payment->update([
                'status' => 'settlement',
                'payment_type' => 'free_plan',
                'transaction_time' => now(),
            ]);

            UserSubscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->duration_days ?: 365),
                'status' => 'active',
            ]);

            return [
                'payment' => $payment,
                'snap_token' => null,
                'redirect_url' => route('subscription.index'),
            ];
        }

        $serverKey = config('midtrans.server_key', env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-simulated-key'));
        $isProduction = config('midtrans.is_production', false);
        $snapEndpoint = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => $plan->code,
                    'price' => (int) $amount,
                    'quantity' => 1,
                    'name' => substr($plan->name, 0, 50),
                ]
            ],
        ];

        $snapToken = null;
        $redirectUrl = null;

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->timeout(10)
                ->post($snapEndpoint, $params);

            if ($response->successful()) {
                $body = $response->json();
                $snapToken = $body['token'] ?? null;
                $redirectUrl = $body['redirect_url'] ?? null;

                $payment->update([
                    'snap_token' => $snapToken,
                    'raw_response' => $body,
                ]);
            } else {
                Log::warning("Midtrans Snap API returned non-200 for Order ID {$orderId}: " . $response->body());
                // Fallback deterministic sandbox token untuk development/testing offline
                $snapToken = 'SANDBOX-TOKEN-' . md5($orderId);
                $payment->update(['snap_token' => $snapToken]);
            }
        } catch (\Throwable $e) {
            Log::warning("Midtrans Snap connection failed, using sandbox fallback token: " . $e->getMessage());
            $snapToken = 'SANDBOX-TOKEN-' . md5($orderId);
            $payment->update(['snap_token' => $snapToken]);
        }

        return [
            'payment' => $payment,
            'snap_token' => $snapToken,
            'redirect_url' => $redirectUrl,
        ];
    }
}
