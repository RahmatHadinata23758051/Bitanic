<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\RawDataLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RawLogsDebuggerController extends Controller
{
    /**
     * Display the Raw Modbus RS485 Hex Stream Debugger.
     */
    public function index(Request $request): View
    {
        $levelFilter = $request->query('level');
        $kitFilter = $request->query('kit_code');

        $query = RawDataLog::with('device')->latest();

        if ($levelFilter && in_array($levelFilter, ['INFO', 'WARN', 'ERROR'])) {
            $query->where('level', $levelFilter);
        }

        if ($kitFilter) {
            $query->where('kit_code', $kitFilter);
        }

        $logs = $query->paginate(30)->withQueryString();
        $devices = Device::orderBy('code')->get();

        $stats = [
            'total' => RawDataLog::count(),
            'errors' => RawDataLog::where('level', 'ERROR')->count(),
            'warnings' => RawDataLog::where('level', 'WARN')->count(),
            'info' => RawDataLog::where('level', 'INFO')->count(),
        ];

        return view('pages.rawlogs.index', [
            'logs' => $logs,
            'devices' => $devices,
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

        RawDataLog::create([
            'device_id' => $device?->id,
            'kit_code' => $validated['kit_code'],
            'packet_hex' => strtoupper(str_replace(' ', '', $validated['packet_hex'])),
            'protocol' => 'Modbus RS485 RTU',
            'level' => $validated['level'],
            'parsed_payload' => [
                'injected_manually' => true,
                'timestamp' => now()->toIso8601String(),
            ],
            'recorded_at' => now(),
        ]);

        return redirect()->route('rawlogs.index')
            ->with('success', 'Frame Modbus RS485 berhasil disuntikkan ke stream debugger.');
    }
}
