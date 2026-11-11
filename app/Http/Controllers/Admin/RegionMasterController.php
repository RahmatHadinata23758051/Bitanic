<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\RegionDatasetMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class RegionMasterController extends Controller
{
    /**
     * Display a searchable, paginated list of Indonesian administrative regions.
     */
    public function index(Request $request): View
    {
        $level = $request->query('level', 'provinces');
        $search = trim((string) $request->query('search', ''));
        $selectedProvince = $request->query('province_code');
        $selectedRegency = $request->query('regency_code');

        $metadata = RegionDatasetMetadata::where('status', 'active')->latest()->first();
        $provinces = Province::orderBy('name')->get();

        if ($level === 'regencies') {
            $query = Regency::with('province');

            if (! empty($selectedProvince)) {
                $query->where('province_code', $selectedProvince);
            }

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            }

            $items = $query->orderBy('name')->paginate(20)->withQueryString();
        } elseif ($level === 'districts') {
            $query = District::with('regency.province');

            if (! empty($selectedRegency)) {
                $query->where('regency_code', $selectedRegency);
            } elseif (! empty($selectedProvince)) {
                $query->whereHas('regency', function ($q) use ($selectedProvince) {
                    $q->where('province_code', $selectedProvince);
                });
            }

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            }

            $items = $query->orderBy('name')->paginate(20)->withQueryString();
        } else {
            $level = 'provinces';
            $query = Province::withCount(['regencies', 'schools']);

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            }

            $items = $query->orderBy('name')->paginate(20)->withQueryString();
        }

        return view('pages.master.regions.index', compact(
            'items',
            'level',
            'search',
            'selectedProvince',
            'selectedRegency',
            'provinces',
            'metadata'
        ));
    }

    /**
     * Trigger synchronization of Indonesian administrative region reference data.
     */
    public function sync(Request $request): RedirectResponse
    {
        $exitCode = Artisan::call('bitanic:sync-indonesian-regions', [
            '--force' => true,
        ]);

        if ($exitCode === 0) {
            return redirect()->route('master.regions.index')
                ->with('success', 'Data referensi Wilayah Indonesia berhasil disinkronisasi.');
        }

        return redirect()->route('master.regions.index')
            ->with('error', 'Gagal menyinkronisasi data referensi Wilayah Indonesia.');
    }

    /**
     * Cascading API: Get all provinces.
     */
    public function provinces(Request $request): JsonResponse
    {
        $provinces = Province::select('code', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $provinces,
        ]);
    }

    /**
     * Cascading API: Get regencies by province code.
     */
    public function regencies(Request $request): JsonResponse
    {
        $provinceCode = $request->query('province_code');

        if (empty($provinceCode)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter province_code is required.',
                'data' => [],
            ], 422);
        }

        $regencies = Regency::where('province_code', $provinceCode)
            ->select('code', 'name', 'province_code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regencies,
        ]);
    }

    /**
     * Cascading API: Get districts by regency code.
     */
    public function districts(Request $request): JsonResponse
    {
        $regencyCode = $request->query('regency_code');

        if (empty($regencyCode)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter regency_code is required.',
                'data' => [],
            ], 422);
        }

        $districts = District::where('regency_code', $regencyCode)
            ->select('code', 'name', 'regency_code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $districts,
        ]);
    }
}
