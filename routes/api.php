<?php

use App\Http\Controllers\Api\CropRecommendationController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\TelemetryIngestController;
use Illuminate\Support\Facades\Route;

// Endpoint IoT Edge yang diproteksi X-Device-Key
Route::middleware('device.key')->group(function () {
    Route::post('/v1/telemetry/ingest', [TelemetryIngestController::class, 'store']);
    // Legacy route compatibility for existing firmware
    Route::post('/rsc-data', [TelemetryIngestController::class, 'store']);
});

// Endpoint Rekomendasi AI Gemini Asinkronus (Job Queue)
Route::post('/v1/recommendations/request', [CropRecommendationController::class, 'requestRecommendation']);
Route::get('/v1/recommendations/status/{ticketId}', [CropRecommendationController::class, 'checkStatus']);

// Endpoint Midtrans Snap Webhook & Callback
Route::post('/v1/payments/webhook', [PaymentWebhookController::class, 'handle']);
Route::post('/payment/callback', [PaymentWebhookController::class, 'handle']);
