<?php

namespace App\Http\Controllers\Agronomy;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Media;
use App\Models\TelemetryReading;
use App\Services\PlantConditionEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlantingMediaController extends Controller
{
    public function index(): View
    {
        $medias = Media::with(['device', 'uploader'])->latest()->get();

        return view('pages.media.index', compact('medias'));
    }

    public function create(): View
    {
        $devices = Device::where('is_active', true)->get();

        return view('pages.media.create', compact('devices'));
    }

    public function store(Request $request, PlantConditionEvaluator $evaluator): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'plants' => 'required|array|min:1',
            'device_id' => 'nullable|exists:devices,id',
        ], [
            'name.required' => 'Nama media tanam wajib diisi.',
            'plants.required' => 'Daftar tanaman wajib dipilih.',
            'plants.array' => 'Daftar tanaman harus berupa array.',
        ]);

        // Null-safe fetch of latest telemetry reading
        $deviceId = $request->input('device_id');
        $latestReading = $deviceId
            ? TelemetryReading::where('device_id', $deviceId)->latest('recorded_at')->first()
            : TelemetryReading::latest('recorded_at')->first();

        // Null-safe agronomy evaluation
        $conditions = $evaluator->evaluate($request->plants, $latestReading);

        Media::create([
            'name' => $request->name,
            'device_id' => $deviceId,
            'plants' => $request->plants,
            'plants_condition' => $conditions,
            'uploaded_by' => Auth::id(),
        ]);

        return redirect('/media')->with('success', 'Media tanam baru berhasil ditambahkan.');
    }

    public function edit(int $id): View
    {
        $media = Media::findOrFail($id);
        $devices = Device::where('is_active', true)->get();

        return view('pages.media.edit', compact('media', 'devices'));
    }

    public function update(Request $request, int $id, PlantConditionEvaluator $evaluator): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'plants' => 'required|array|min:1',
            'device_id' => 'nullable|exists:devices,id',
        ]);

        $media = Media::findOrFail($id);
        $deviceId = $request->input('device_id', $media->device_id);

        $latestReading = $deviceId
            ? TelemetryReading::where('device_id', $deviceId)->latest('recorded_at')->first()
            : TelemetryReading::latest('recorded_at')->first();

        $conditions = $evaluator->evaluate($request->plants, $latestReading);

        $media->update([
            'name' => $request->name,
            'device_id' => $deviceId,
            'plants' => $request->plants,
            'plants_condition' => $conditions,
        ]);

        return redirect('/media')->with('success', 'Media tanam berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $media = Media::findOrFail($id);
        $media->delete();

        return redirect('/media')->with('success', 'Media tanam berhasil dihapus.');
    }
}
