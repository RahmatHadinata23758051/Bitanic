<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TelemetryReading;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Display the agronomic analytics page with data-backed correlation summaries.
     */
    public function index(Request $request): View
    {
        $deviceId = $request->query('device_id');
        $devices = Device::where('is_active', true)->orderBy('code')->get();

        $selectedDevice = $deviceId
            ? $devices->firstWhere('id', (int) $deviceId)
            : $devices->first();

        $readings = $selectedDevice
            ? TelemetryReading::where('device_id', $selectedDevice->id)
                ->latest('recorded_at')
                ->take(100)
                ->get()
                ->sortBy('recorded_at')
                ->values()
            : collect();

        $parameters = [
            'ph' => ['label' => 'pH Tanah', 'unit' => 'pH'],
            'ec' => ['label' => 'Konduktivitas Listrik (EC)', 'unit' => 'µS/cm'],
            'humidity' => ['label' => 'Kadar Lengas Tanah', 'unit' => '%'],
            'temperature' => ['label' => 'Suhu Tanah', 'unit' => '°C'],
            'nitrogen' => ['label' => 'Nitrogen (N)', 'unit' => 'mg/kg'],
            'phosphorus' => ['label' => 'Fosfor (P)', 'unit' => 'mg/kg'],
            'potassium' => ['label' => 'Kalium (K)', 'unit' => 'mg/kg'],
        ];

        $chartData = [];
        foreach ($parameters as $key => $meta) {
            $chartData[$key] = [
                'label' => $meta['label'],
                'unit' => $meta['unit'],
                'values' => $readings->map(fn ($reading) => [
                    'x' => $reading->recorded_at?->format('Y-m-d H:i:s'),
                    'y' => $reading->{$key} === null ? null : (float) $reading->{$key},
                ])->values(),
            ];
        }

        $correlation = static function (string $xKey, string $yKey) use ($readings): ?float {
            $pairs = $readings->filter(fn ($r) => $r->{$xKey} !== null && $r->{$yKey} !== null);
            $count = $pairs->count();
            if ($count < 2) return null;

            $xMean = $pairs->avg($xKey);
            $yMean = $pairs->avg($yKey);
            $numerator = 0.0;
            $xVariance = 0.0;
            $yVariance = 0.0;

            foreach ($pairs as $row) {
                $xDiff = (float) $row->{$xKey} - $xMean;
                $yDiff = (float) $row->{$yKey} - $yMean;
                $numerator += $xDiff * $yDiff;
                $xVariance += $xDiff ** 2;
                $yVariance += $yDiff ** 2;
            }

            $denominator = sqrt($xVariance * $yVariance);
            return $denominator > 0 ? round($numerator / $denominator, 3) : null;
        };

        return view('pages.analytics.index', [
            'devices' => $devices,
            'selectedDevice' => $selectedDevice,
            'chartData' => $chartData,
            'readingCount' => $readings->count(),
            'correlations' => [
                ['label' => 'pH Tanah ↔ EC Nutrisi', 'value' => $correlation('ph', 'ec')],
                ['label' => 'Suhu Tanah ↔ Kadar Lengas', 'value' => $correlation('temperature', 'humidity')],
            ],
        ]);
    }
}
