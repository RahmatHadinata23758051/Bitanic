<?php

namespace App\Http\Controllers\Api;

use App\Actions\Telemetry\IngestTelemetryAction;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryIngestController extends Controller
{
    public function store(Request $request, IngestTelemetryAction $action): JsonResponse
    {
        $request->validate([
            'soilrs485' => 'required|array',
            'soilrs485.Nitrogen' => 'nullable|numeric',
            'soilrs485.Phosporus' => 'nullable|numeric',
            'soilrs485.Kalium' => 'nullable|numeric',
            'soilrs485.Ec' => 'nullable|numeric',
            'soilrs485.Ph' => 'nullable|numeric',
            'soilrs485.Temperature' => 'nullable|numeric',
            'soilrs485.Humidity' => 'nullable|numeric',
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');

        // Fallback jika device_id dikirim di body dan cocok
        if (!$device && $request->filled('device_id')) {
            $device = Device::where('code', $request->device_id)->orWhere('id', $request->device_id)->first();
        }

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Target device could not be identified.',
            ], 422);
        }

        $reading = $action->execute($device, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Telemetry reading successfully ingested and evaluated.',
            'data' => [
                'id' => $reading->id,
                'device_code' => $device->code,
                'recorded_at' => $reading->recorded_at->toIso8601String(),
                'readings' => [
                    'nitrogen' => (float) $reading->nitrogen,
                    'phosphorus' => (float) $reading->phosphorus,
                    'potassium' => (float) $reading->potassium,
                    'ec' => (float) $reading->ec,
                    'ph' => (float) $reading->ph,
                    'temperature' => (float) $reading->temperature,
                    'humidity' => (float) $reading->humidity,
                ],
                'threshold_flags' => $reading->threshold_flags,
            ],
        ], 201);
    }
}
