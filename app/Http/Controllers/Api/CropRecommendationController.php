<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCropRecommendationJob;
use App\Models\TelemetryReading;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CropRecommendationController extends Controller
{
    public function requestRecommendation(Request $request, SubscriptionService $subService): JsonResponse
    {
        $user = $request->user() ?? Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi diperlukan.',
            ], 401);
        }

        $request->validate([
            'reading_id' => 'nullable|exists:telemetry_readings,id',
        ]);

        if (!$subService->canConsumeAi($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota AI habis atau masa langganan berakhir. Silakan upgrade paket langganan Anda.',
            ], 402);
        }

        $readingId = $request->input('reading_id');
        $reading = $readingId ? TelemetryReading::find($readingId) : TelemetryReading::latest('recorded_at')->first();

        if (!$reading) {
            return response()->json([
                'success' => false,
                'message' => 'Data telemetri sensor belum tersedia untuk dianalisis.',
            ], 404);
        }

        $ticketId = (string) Str::uuid();

        Cache::put('crop_recommendation_' . $ticketId, [
            'status' => 'queued',
            'ticket_id' => $ticketId,
            'user_id' => $user->id,
            'queued_at' => now()->toIso8601String(),
        ], now()->addHour());

        GenerateCropRecommendationJob::dispatch($user, $reading, $ticketId);

        return response()->json([
            'success' => true,
            'ticket_id' => $ticketId,
            'status' => 'processing',
            'message' => 'Permintaan rekomendasi AI berhasil dijadwalkan ke antrean sistem.',
        ], 202);
    }

    public function checkStatus(Request $request, string $ticketId): JsonResponse
    {
        $user = $request->user() ?? Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi diperlukan.',
            ], 401);
        }

        $cacheKey = 'crop_recommendation_' . $ticketId;
        $data = Cache::get($cacheKey);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket rekomendasi AI tidak ditemukan atau telah kedaluwarsa.',
            ], 404);
        }

        if (isset($data['user_id']) && (int) $data['user_id'] !== (int) $user->id && !$user->isSuperuser()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Anda tidak memiliki wewenang untuk memeriksa status tiket rekomendasi ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
