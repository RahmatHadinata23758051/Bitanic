<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\RawDataLog;
use App\Models\School;
use App\Services\Tenancy\SchoolContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RawLogsDebuggerController extends Controller
{
    /**
     * Display the Raw Modbus RS485 Hex Stream Debugger.
     */
    public function index(Request $request, SchoolContextResolver $contextResolver): View
    {
        $user = $request->user();
        $context = $contextResolver->resolve($request, $user);

        $targetSchoolId = $context->targetSchoolId;
        $selectedSchoolId = $context->selectedSchoolId;
        $schools = $context->schools;
        $isSuperuser = $context->isSuperuser;

        $devices = Device::query()
            ->where('is_active', true)
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('code')
            ->get();

        $allowedKitCodes = $devices->pluck('code')->all();

        $levelFilter = $request->query('level');
        $kitFilter = $request->query('kit_code');

        $query = RawDataLog::with('device')
            ->when($targetSchoolId, function ($q) use ($targetSchoolId) {
                $q->whereHas('device', fn ($dq) => $dq->where('school_id', $targetSchoolId));
            })
            ->latest('recorded_at');

        if ($levelFilter && in_array($levelFilter, ['INFO', 'WARN', 'ERROR'], true)) {
            $query->where('level', $levelFilter);
        }

        if ($kitFilter) {
            if (in_array($kitFilter, $allowedKitCodes, true) || ($isSuperuser && ! $targetSchoolId)) {
                $query->where('kit_code', $kitFilter);
            } else {
                // Cross-school IDOR attempt: kit code does not belong to authorized school
                $query->whereRaw('1 = 0');
            }
        }

        $logs = $query->paginate(30)->withQueryString();

        $statsBaseQuery = RawDataLog::query()
            ->when($targetSchoolId, function ($q) use ($targetSchoolId) {
                $q->whereHas('device', fn ($dq) => $dq->where('school_id', $targetSchoolId));
            });

        $stats = [
            'total' => (clone $statsBaseQuery)->count(),
            'errors' => (clone $statsBaseQuery)->where('level', 'ERROR')->count(),
            'warnings' => (clone $statsBaseQuery)->where('level', 'WARN')->count(),
            'info' => (clone $statsBaseQuery)->where('level', 'INFO')->count(),
        ];

        return view('pages.rawlogs.raw-logs', [
            'logs' => $logs,
            'devices' => $devices,
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'isSuperuser' => $isSuperuser,
            'stats' => $stats,
            'currentLevel' => $levelFilter,
            'currentKit' => $kitFilter,
        ]);
    }

    /**
     * Inject a simulated Modbus RS485 Hex frame.
     */
    public function inject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kit_code' => 'required|string',
            'packet_hex' => 'required|string',
            'level' => 'required|in:INFO,WARN,ERROR',
        ]);

        $device = Device::where('code', $validated['kit_code'])->first();
        if (! $device) {
            return redirect()->route('rawlogs.index')
                ->with('error', 'Perangkat kit dengan kode tersebut tidak ditemukan.');
        }

        RawDataLog::create([
            'device_id' => $device->id,
            'kit_code' => $device->code,
            'packet_hex' => strtoupper(str_replace(' ', '', $validated['packet_hex'])),
            'protocol' => 'Modbus RS485 RTU',
            'level' => $validated['level'],
            'parsed_payload' => [
                'source' => 'manual_injection',
                'injected_manually' => true,
                'is_simulated' => true,
                'hardware_io_disclaimer' => 'Simulasi frame diagnostik laboratorium tanpa transmisi aktuasi fisik.',
                'timestamp' => now()->toIso8601String(),
            ],
            'recorded_at' => now(),
        ]);

        return redirect()->route('rawlogs.index')
            ->with('success', 'Frame Modbus RS485 simulasi berhasil disuntikkan ke stream debugger.');
    }
}
