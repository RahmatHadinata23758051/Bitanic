<?php

namespace App\Http\Middleware;

use App\Models\DeviceApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeviceApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('X-Device-Key');

        if (!$rawKey) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid X-Device-Key header.',
            ], 401);
        }

        $keyHash = hash('sha256', trim($rawKey));
        $apiKey = DeviceApiKey::with('device')->where('key_hash', $keyHash)->first();

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid device API key.',
            ], 401);
        }

        if ($apiKey->revoked_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Device API key has been revoked.',
            ], 401);
        }

        $device = $apiKey->device;
        if (!$device || !$device->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Device is inactive or disabled.',
            ], 401);
        }

        // Attach authenticated device and key to request attributes
        $request->attributes->set('device', $device);
        $request->attributes->set('device_api_key', $apiKey);

        return $next($request);
    }
}
