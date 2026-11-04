<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainerKitManagerController extends Controller
{
    /**
     * Display the Trainer Kit fleet and hardware status.
     */
    public function index(): View
    {
        $kits = Device::with('latestReading')->orderBy('code')->paginate(15);

        return view('pages.kits.index', [
            'kits' => $kits,
            'stats' => [
                'total' => Device::count(),
                'online' => Device::where('status', 'online')->count(),
                'attention' => Device::whereIn('status', ['anomaly', 'offline', 'maintenance'])->count(),
            ],
        ]);
    }

    /**
     * Handle simulated soft-reboot of a Trainer Kit.
     */
    public function reboot(Device $device): RedirectResponse
    {
        // Simulasi pengiriman frame perintah restart ke mikrokontroler ESP32
        $device->update([
            'last_seen_at' => now(),
            'status' => 'online',
        ]);

        return redirect()->route('kits.index')
            ->with('success', 'Perintah soft-reboot berhasil dikirimkan ke modul ' . $device->code . '. Sistem online kembali.');
    }

    /**
     * Update calibration offsets for sensors.
     */
    public function updateCalibration(Request $request, Device $device): RedirectResponse
    {
        $validated = $request->validate([
            'phOffset' => 'required|numeric|between:-5,5',
            'moistureOffset' => 'required|numeric|between:-20,20',
            'ecOffset' => 'required|numeric|between:-500,500',
        ]);

        $device->update([
            'calibration_offsets' => [
                'ph' => (float) $validated['phOffset'],
                'humidity' => (float) $validated['moistureOffset'],
                'ec' => (float) $validated['ecOffset'],
            ],
        ]);

        return redirect()->route('kits.index')
            ->with('success', 'Offset kalibrasi sensor untuk ' . $device->code . ' berhasil diperbarui.');
    }
}
