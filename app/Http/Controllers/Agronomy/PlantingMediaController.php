<?php

namespace App\Http\Controllers\Agronomy;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Media;
use App\Models\School;
use App\Models\TelemetryReading;
use App\Services\PlantConditionEvaluator;
use App\Services\Tenancy\SchoolContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlantingMediaController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $this->resolveSchoolId($request);
        $medias = Media::with(['device', 'uploader'])
            ->where('school_id', $schoolId)
            ->latest()
            ->paginate(20);
        $devices = $this->devicesForSchool($schoolId);

        return view('pages.media.index', compact('medias', 'devices'));
    }

    public function create(Request $request): View
    {
        $schoolId = $this->resolveSchoolId($request);
        $devices = $this->devicesForSchool($schoolId);

        return view('pages.media.create', compact('devices'));
    }

    public function store(Request $request, PlantConditionEvaluator $evaluator): RedirectResponse
    {
        $this->authorize('manage', Media::class);
        $schoolId = $this->resolveSchoolId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plants' => ['required', 'array', 'min:1', 'max:20'],
            'plants.*' => ['required', 'string', 'max:100'],
            'device_id' => [
                'nullable',
                'integer',
                Rule::exists('devices', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
        ], [
            'name.required' => 'Nama media tanam wajib diisi.',
            'plants.required' => 'Daftar tanaman wajib dipilih.',
            'plants.array' => 'Daftar tanaman harus berupa array.',
            'device_id.exists' => 'Perangkat tidak ditemukan atau bukan milik sekolah Anda.',
        ]);

        $deviceId = $validated['device_id'] ?? null;
        $latestReading = $this->latestReadingForSchool($schoolId, $deviceId);
        $conditions = $evaluator->evaluate($validated['plants'], $latestReading);

        Media::create([
            'school_id' => $schoolId,
            'name' => $validated['name'],
            'device_id' => $deviceId,
            'plants' => $validated['plants'],
            'plants_condition' => $conditions,
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('media.index')->with('success', 'Media tanam baru berhasil ditambahkan.');
    }

    public function edit(Request $request, int $id): View
    {
        $schoolId = $this->resolveSchoolId($request);
        $media = Media::where('school_id', $schoolId)->findOrFail($id);
        $this->authorize('manage', $media);
        $devices = $this->devicesForSchool($schoolId);

        return view('pages.media.edit', compact('media', 'devices'));
    }

    public function update(Request $request, int $id, PlantConditionEvaluator $evaluator): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $media = Media::where('school_id', $schoolId)->findOrFail($id);
        $this->authorize('manage', $media);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plants' => ['required', 'array', 'min:1', 'max:20'],
            'plants.*' => ['required', 'string', 'max:100'],
            'device_id' => [
                'nullable',
                'integer',
                Rule::exists('devices', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
        ], [
            'device_id.exists' => 'Perangkat tidak ditemukan atau bukan milik sekolah Anda.',
        ]);

        $deviceId = $validated['device_id'] ?? null;
        $latestReading = $this->latestReadingForSchool($schoolId, $deviceId);
        $conditions = $evaluator->evaluate($validated['plants'], $latestReading);

        $media->update([
            'name' => $validated['name'],
            'device_id' => $deviceId,
            'plants' => $validated['plants'],
            'plants_condition' => $conditions,
        ]);

        return redirect()->route('media.index')->with('success', 'Media tanam berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $media = Media::where('school_id', $schoolId)->findOrFail($id);
        $this->authorize('manage', $media);
        $media->delete();

        return redirect()->route('media.index')->with('success', 'Media tanam berhasil dihapus.');
    }

    private function resolveSchoolId(Request $request): int
    {
        /** @var SchoolContextResolver $resolver */
        $resolver = app(SchoolContextResolver::class);
        $context = $resolver->resolve($request, $request->user());

        if ($context->targetSchoolId !== null) {
            return (int) $context->targetSchoolId;
        }

        return (int) ($request->user()?->school_id ?? School::query()->where('status', 'active')->orderBy('id')->value('id') ?? -1);
    }

    /** @return Collection<int, Device> */
    private function devicesForSchool(int $schoolId)
    {
        return Device::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function latestReadingForSchool(int $schoolId, ?int $deviceId): ?TelemetryReading
    {
        if ($deviceId === null) {
            return null;
        }

        // Defense in depth: validate the device's tenant again before reading its telemetry.
        $device = Device::whereKey($deviceId)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->firstOrFail();

        return $device->telemetryReadings()->latest('recorded_at')->first();
    }
}
