<?php

namespace App\Actions\Payment;

use App\Models\PaymentHistory;
use App\Models\UserSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated LEGACY-01 / BR-504 — Legacy Midtrans webhook reconciliation Action.
 *
 * Customer-facing checkout routes are disabled, but API callbacks are retained
 * to reconcile in-flight payments. Preserve historic payment/subscription records.
 * Do NOT delete or disable callback processing without a separate decision.
 */
class ProcessMidtransWebhookAction
{
    /**
     * Process incoming Midtrans webhook notification with signature verification and idempotency protection.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, code: int, status: string, message: string}
     */
    public function execute(array $payload): array
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $receivedSignature = (string) ($payload['signature_key'] ?? '');
        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $paymentType = (string) ($payload['payment_type'] ?? null);
        $transactionTime = isset($payload['transaction_time']) ? Carbon::parse($payload['transaction_time']) : now();

        $serverKey = config('midtrans.server_key', env('MIDTRANS_SERVER_KEY', ''));

        // 1. Verifikasi Signature Key SHA-512
        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            Log::warning("Midtrans Webhook: Invalid signature for Order ID {$orderId}. Expected {$expectedSignature}, got {$receivedSignature}");
            return [
                'success' => false,
                'code' => 403,
                'status' => 'invalid_signature',
                'message' => 'Invalid Midtrans signature key.',
            ];
        }

        // 2. Ambil catatan pembayaran
        $payment = PaymentHistory::with(['user', 'plan'])->where('order_id', $orderId)->first();

        if (!$payment) {
            Log::warning("Midtrans Webhook: Payment record not found for Order ID {$orderId}");
            return [
                'success' => false,
                'code' => 404,
                'status' => 'not_found',
                'message' => 'Payment history not found.',
            ];
        }

        // 3. Proteksi Idempotensi (Anti-Double-Credit)
        $isSuccessStatus = in_array($transactionStatus, ['settlement', 'capture']);

        if ($payment->isSettled() && $isSuccessStatus) {
            Log::info("Midtrans Webhook: Order ID {$orderId} already settled. Idempotent response returned.");
            return [
                'success' => true,
                'code' => 200,
                'status' => 'already_settled',
                'message' => 'Transaction was already processed (idempotent).',
            ];
        }

        // 4. Proses Transaksi Atomik dalam DB Transaction
        DB::transaction(function () use ($payment, $transactionStatus, $paymentType, $transactionTime, $payload, $isSuccessStatus) {
            $payment->update([
                'status' => $transactionStatus,
                'payment_type' => $paymentType,
                'transaction_time' => $transactionTime,
                'raw_response' => $payload,
            ]);

            // Jika status transaksi berhasil, aktifkan/perpanjang langganan pengguna
            if ($isSuccessStatus && $payment->user && $payment->plan) {
                $durationDays = $payment->plan->duration_days ?: 30;

                // Cek apakah user memiliki subscription aktif yang belum kedaluwarsa
                $activeSub = UserSubscription::where('user_id', $payment->user_id)
                    ->where('status', 'active')
                    ->where('ends_at', '>', now())
                    ->latest('id')
                    ->first();

                $startDate = now();
                $endsDate = $activeSub ? $activeSub->ends_at->addDays($durationDays) : now()->addDays($durationDays);

                UserSubscription::create([
                    'user_id' => $payment->user_id,
                    'plan_id' => $payment->plan_id,
                    'starts_at' => $startDate,
                    'ends_at' => $endsDate,
                    'status' => 'active',
                ]);
            }
        });

        return [
            'success' => true,
            'code' => 200,
            'status' => 'processed',
            'message' => 'Payment webhook processed successfully.',
        ];
    }
}
