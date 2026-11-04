<?php

use App\Http\Controllers\Api\TelemetryIngestController;
use Illuminate\Support\Facades\Route;

Route::middleware('device.key')->group(function () {
    Route::post('/v1/telemetry/ingest', [TelemetryIngestController::class, 'store']);
    // Legacy route compatibility for existing firmware
    Route::post('/rsc-data', [TelemetryIngestController::class, 'store']);
});
