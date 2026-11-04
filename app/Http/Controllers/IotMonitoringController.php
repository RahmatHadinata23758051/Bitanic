<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TelemetryReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IotMonitoringController extends Controller
{
    /**
     * Display the real-time vocational IoT workstation (7-in-1 Modbus & Relay Controls).
     */
    public function index(Request $request): View
    {
        $deviceId = $request->query('device_id');

        // Fetch active kit or selected kit
        if ($deviceId) {
            $activeDevice = Device::find($deviceId);
        } else {
            $activeDevice = Device::where('is_active', true)->first();
        }

        if (!$activeDevice) {
            $activeDevice = Device::where('is_active', true)->first() ?? Device::first() ?? (object) [
                'id' => 1,
                'code' => 'TK-01',
                'name' => 'BITANIC Precision Agritech Kit #01',
                'bench_location' => 'Meja Praktik 01 - Bedengan Tomat Ceri',
                'connection_type' => 'modbus_rs485',
                'is_active' => true,
                'status' => 'online',
                'battery_percent' => 95,
                'rssi_signal' => -55,
                'relays_state' => [
                    'irrigationPump' => false,
                    'fertigationValve' => false,
                    'mistingFan' => true,
                    'growLight' => true,
                ],
                'last_seen_at' => now(),
            ];
        }

        $allDevices = Device::where('is_active', true)->get();

        // Fetch latest actual telemetry only; missing sensors remain unavailable rather than simulated numbers.
        $latest = $activeDevice->id
            ? TelemetryReading::where('device_id', $activeDevice->id)->latest('recorded_at')->first()
            : null;

        // Device heartbeat health is independent from persisted metadata status.
        $isDeviceOnline = $activeDevice->last_seen_at
            && $activeDevice->last_seen_at->greaterThan(now()->subMinutes(15));
        $activeDevice->status = $isDeviceOnline ? 'online' : 'offline';

        $isTelemetryFresh = $latest && $latest->recorded_at
            && $latest->recorded_at->greaterThan(now()->subMinutes(15));

        $isLive = (bool) ($isDeviceOnline && $isTelemetryFresh);

        $hasTelemetry = $latest !== null;
        $t = [
            'nitrogen' => $latest ? (float) $latest->nitrogen : null,
            'phosphorus' => $latest ? (float) $latest->phosphorus : null,
            'potassium' => $latest ? (float) $latest->potassium : null,
            'ph' => $latest ? (float) $latest->ph : null,
            'ec' => $latest ? (float) $latest->ec : null,
            'temperature' => $latest ? (float) $latest->temperature : null,
            'humidity' => $latest ? (float) $latest->humidity : null,
            'recorded_at' => $latest?->recorded_at?->diffForHumans() ?? 'Belum Ada Transmisi Data',
            'has_telemetry' => $hasTelemetry,
            'is_live' => $isLive,
            'is_fresh' => $isTelemetryFresh,
            'is_online' => $isDeviceOnline,
        ];

        // Dynamic anomaly alerts are evaluated only when actual telemetry exists.
        $alerts = [];
        if ($isLive) {
            if ($t['ph'] !== null && $t['ph'] < 5.5) {
                $alerts[] = [
                    'id' => 'alert-ph',
                    'severity' => 'critical',
                    'title' => 'Kemasaman Tanah Masam Ekstrem',
                    'value' => number_format($t['ph'], 2) . ' pH',
                    'threshold' => 'Ideal: 6.0 – 7.2 pH',
                    'message' => 'Tingkat kemasaman tanah berada di bawah batas toleransi hortikultura, menghambat penyerapan unsur fosfor.',
                    'agronomic_action' => 'Aplikasi kapur pertanian / dolomit dengan dosis 50 gr/m² dan hentikan pupuk berbasis amonium.',
                ];
            }
            if ($t['humidity'] !== null && $t['humidity'] < 25.0) {
                $alerts[] = [
                    'id' => 'alert-moisture',
                    'severity' => 'critical',
                    'title' => 'Kadar Lengas Tanah Rendah (Titik Layu)',
                    'value' => number_format($t['humidity'], 1) . '%',
                    'threshold' => 'Ideal: 45 – 65%',
                    'message' => 'Tanah mendekati titik layu permanen. Tanaman berisiko dehidrasi seluler dalam beberapa jam.',
                    'agronomic_action' => 'Aktifkan pompa irigasi tetes segera selama 15 menit pada bedengan uji.',
                ];
            }
            if ($t['ec'] !== null && $t['ec'] > 2200) {
                $alerts[] = [
                    'id' => 'alert-ec',
                    'severity' => 'warning',
                    'title' => 'Salinitas Nutrisi Tanah Tinggi',
                    'value' => number_format($t['ec'], 0) . ' µS/cm',
                    'threshold' => 'Ideal: 1200 – 2000 µS/cm',
                    'message' => 'Konsentrasi garam larut pupuk berlebih, berisiko menyebabkan plasmolisis perakaran.',
                    'agronomic_action' => 'Lakukan pembilasan air murni (leaching) tanpa penambahan pekatan pupuk.',
                ];
            }
        }

        // Soil Health Index (null if no telemetry received)
        $healthScore = null;
        if ($isLive) {
            $score = 100;
            if ($t['ph'] !== null && ($t['ph'] < 6.0 || $t['ph'] > 7.2)) $score -= 15;
            if ($t['humidity'] !== null && ($t['humidity'] < 40 || $t['humidity'] > 70)) $score -= 15;
            if ($t['ec'] !== null && ($t['ec'] < 1000 || $t['ec'] > 2200)) $score -= 10;
            if ($t['nitrogen'] !== null && $t['nitrogen'] < 40) $score -= 10;
            $healthScore = max(30, min(100, $score));
        }

        // Recent readings
        $recentReadings = TelemetryReading::where('device_id', $activeDevice->id ?? 1)
            ->latest('recorded_at')
            ->take(8)
            ->get();

        return view('pages.iot.index', [
            'activeDevice' => $activeDevice,
            'devices' => $allDevices,
            'telemetry' => $t,
            'alerts' => $alerts,
            'criticalAlertsCount' => count(array_filter($alerts, fn($a) => $a['severity'] === 'critical')),
            'healthScore' => $healthScore,
            'recentReadings' => $recentReadings,
        ]);
    }

    /**
     * API to toggle hardware trainer kit actuator relays.
     */
    public function toggleRelay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'nullable',
            'relay_key' => 'required|string|in:irrigationPump,fertigationValve,mistingFan,growLight',
            'state' => 'required|boolean',
        ]);

        if (!empty($validated['device_id'])) {
            $device = Device::find($validated['device_id']);
            if ($device) {
                $relays = $device->relays_state ?? [];
                $relays[$validated['relay_key']] = (bool) $validated['state'];
                $device->relays_state = $relays;
                $device->save();
            }
        }

        return response()->json([
            'success' => true,
            'is_simulation' => true,
            'hardware_ack' => false,
            'relay_key' => $validated['relay_key'],
            'state' => $validated['state'],
            'message' => 'Status aktuator ' . $validated['relay_key'] . ' diperbarui di basis data (Simulasi — Belum terhubung ACK fisik ESP32).',
        ]);
    }
}
