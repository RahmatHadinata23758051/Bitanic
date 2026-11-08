<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Services\Hardware\DeviceAvailabilityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IotMonitoringController extends Controller
{
    /**
     * Display the real-time vocational IoT workstation (7-in-1 Modbus & Relay Controls).
     */
    public function index(Request $request, DeviceAvailabilityResolver $resolver): View
    {
        $deviceId = $request->query('device_id');
        $user = $request->user();
        $schoolId = $user?->school_id;
        $isSuperuser = (bool) $user?->isSuperuser();

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Akses monitoring memerlukan asosiasi sekolah yang valid.');
            }
            $school = $user->school;
            if (! $school || $school->status !== 'active') {
                abort(403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');
            }
        }

        $selectedSchoolId = $request->query('school_id');
        if ($isSuperuser && $selectedSchoolId) {
            $targetSchoolId = (int) $selectedSchoolId;
        } else {
            $targetSchoolId = $isSuperuser
                ? null
                : ($schoolId ?? School::query()->where('status', 'active')->orderBy('id')->value('id') ?? -1);
        }

        $query = Device::query()
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId));

        $allRawDevices = (clone $query)->orderBy('code')->get();
        $allDevices = $resolver->applyToCollection($allRawDevices);

        if ($deviceId) {
            $activeDevice = (clone $query)->where(function ($q) use ($deviceId) {
                $q->where('id', $deviceId)->orWhere('code', $deviceId);
            })->first();
        } else {
            $activeDevice = (clone $query)->where('is_active', true)->first();
        }

        // Fallback: first active device, or first device, or null if no devices exist
        if (! $activeDevice) {
            $activeDevice = (clone $query)->where('is_active', true)->first() ?? (clone $query)->first();
        }

        if ($activeDevice) {
            $activeDevice = $resolver->applyToDevice($activeDevice);
            $isDeviceOnline = (bool) $activeDevice->is_online;
            $latest = TelemetryReading::where('device_id', $activeDevice->id)->latest('recorded_at')->first();
            $recentReadings = TelemetryReading::where('device_id', $activeDevice->id)
                ->latest('recorded_at')
                ->take(8)
                ->get();
        } else {
            $isDeviceOnline = false;
            $latest = null;
            $recentReadings = collect();
        }

        $freshTimeout = $activeDevice ? $resolver->getTimeoutSeconds($activeDevice) : 900;
        $isTelemetryFresh = $latest && $latest->recorded_at
            && $latest->recorded_at->greaterThanOrEqualTo(now()->subSeconds($freshTimeout));

        $isLive = (bool) ($isDeviceOnline && $isTelemetryFresh && $latest?->isHardware());
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
            'provenance' => $latest?->provenance,
            'provenance_label' => $latest?->provenance ? match ($latest->provenance) {
                'hardware' => 'Hardware',
                'simulation' => 'Simulasi',
                'fixture' => 'Fixture / Data Uji',
                default => 'Tidak Diketahui',
            } : null,
        ];

        // Dynamic anomaly alerts are evaluated only when actual telemetry exists.
        $alerts = [];
        if ($isLive) {
            if ($t['ph'] !== null && $t['ph'] < 5.5) {
                $alerts[] = [
                    'id' => 'alert-ph',
                    'severity' => 'critical',
                    'title' => 'Kemasaman Tanah Masam Ekstrem',
                    'value' => number_format($t['ph'], 2).' pH',
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
                    'value' => number_format($t['humidity'], 1).'%',
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
                    'value' => number_format($t['ec'], 0).' µS/cm',
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
            if ($t['ph'] !== null && ($t['ph'] < 6.0 || $t['ph'] > 7.2)) {
                $score -= 15;
            }
            if ($t['humidity'] !== null && ($t['humidity'] < 40 || $t['humidity'] > 70)) {
                $score -= 15;
            }
            if ($t['ec'] !== null && ($t['ec'] < 1000 || $t['ec'] > 2200)) {
                $score -= 10;
            }
            if ($t['nitrogen'] !== null && $t['nitrogen'] < 40) {
                $score -= 10;
            }
            $healthScore = max(30, min(100, $score));
        }

        // Summary counts for strip inspection
        $normalCount = 0;
        $warningCount = 0;
        if ($hasTelemetry) {
            if ($t['ph'] !== null) {
                ($t['ph'] >= 6.0 && $t['ph'] <= 7.2) ? $normalCount++ : $warningCount++;
            }
            if ($t['humidity'] !== null) {
                ($t['humidity'] >= 45.0 && $t['humidity'] <= 65.0) ? $normalCount++ : $warningCount++;
            }
            if ($t['ec'] !== null) {
                ($t['ec'] >= 1000 && $t['ec'] <= 2200) ? $normalCount++ : $warningCount++;
            }
            if ($t['nitrogen'] !== null) {
                ($t['nitrogen'] >= 40 && $t['nitrogen'] <= 120) ? $normalCount++ : $warningCount++;
            }
            if ($t['phosphorus'] !== null) {
                ($t['phosphorus'] >= 25 && $t['phosphorus'] <= 60) ? $normalCount++ : $warningCount++;
            }
            if ($t['potassium'] !== null) {
                ($t['potassium'] >= 100 && $t['potassium'] <= 200) ? $normalCount++ : $warningCount++;
            }
            if ($t['temperature'] !== null) {
                ($t['temperature'] >= 22.0 && $t['temperature'] <= 29.0) ? $normalCount++ : $warningCount++;
            }
        }

        $relaysState = (array) ($activeDevice?->relays_state ?? []);
        $activeActuatorsCount = count(array_filter($relaysState, fn ($v) => (bool) $v));

        $chartReadings = $activeDevice
            ? TelemetryReading::where('device_id', $activeDevice->id)
                ->latest('recorded_at')
                ->take(30)
                ->get()
                ->reverse()
                ->values()
            : collect();

        $chartData = [
            'ph' => [
                'values' => $chartReadings->map(fn ($r) => ['x' => $r->recorded_at?->toIso8601String(), 'y' => $r->ph !== null ? (float) $r->ph : null])->all(),
            ],
            'moisture' => [
                'values' => $chartReadings->map(fn ($r) => ['x' => $r->recorded_at?->toIso8601String(), 'y' => $r->humidity !== null ? (float) $r->humidity : null])->all(),
            ],
            'humidity' => [
                'values' => $chartReadings->map(fn ($r) => ['x' => $r->recorded_at?->toIso8601String(), 'y' => $r->humidity !== null ? (float) $r->humidity : null])->all(),
            ],
            'ec' => [
                'values' => $chartReadings->map(fn ($r) => ['x' => $r->recorded_at?->toIso8601String(), 'y' => $r->ec !== null ? (float) $r->ec : null])->all(),
            ],
            'nitrogen' => [
                'values' => $chartReadings->map(fn ($r) => ['x' => $r->recorded_at?->toIso8601String(), 'y' => $r->nitrogen !== null ? (float) $r->nitrogen : null])->all(),
            ],
            'phosphorus' => [
                'values' => $chartReadings->map(fn ($r) => ['x' => $r->recorded_at?->toIso8601String(), 'y' => $r->phosphorus !== null ? (float) $r->phosphorus : null])->all(),
            ],
            'potassium' => [
                'values' => $chartReadings->map(fn ($r) => ['x' => $r->recorded_at?->toIso8601String(), 'y' => $r->potassium !== null ? (float) $r->potassium : null])->all(),
            ],
            'temperature' => [
                'values' => $chartReadings->map(fn ($r) => ['x' => $r->recorded_at?->toIso8601String(), 'y' => $r->temperature !== null ? (float) $r->temperature : null])->all(),
            ],
        ];

        return view('pages.iot.monitoring', [
            'activeDevice' => $activeDevice,
            'devices' => $allDevices,
            'telemetry' => $t,
            'alerts' => $alerts,
            'criticalAlertsCount' => count(array_filter($alerts, fn ($a) => $a['severity'] === 'critical')),
            'healthScore' => $healthScore,
            'recentReadings' => $recentReadings,
            'normalParamsCount' => $normalCount,
            'warningParamsCount' => $warningCount,
            'activeActuatorsCount' => $activeActuatorsCount,
            'chartData' => $chartData,
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

        $device = ! empty($validated['device_id']) ? Device::find($validated['device_id']) : null;
        $currentState = $device ? ($device->relays_state[$validated['relay_key']] ?? false) : false;

        // In the current phase, hardware MQTT/Modbus transport is simulated and ESP32 physical ACK is not wired.
        // To maintain data truthfulness, do not mutate relays_state in the database without physical hardware acknowledgement.
        return response()->json([
            'success' => false,
            'is_simulation' => true,
            'hardware_ack' => false,
            'relay_key' => $validated['relay_key'],
            'state' => $currentState,
            'message' => 'Gagal mengubah aktuator '.$validated['relay_key'].': Belum ada konfirmasi ACK fisik dari Trainer Kit ESP32.',
        ], 503);
    }
}
