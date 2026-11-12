<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\RegionDatasetMetadata;
use App\Services\Geo\SchoolDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class RegionMasterController extends Controller
{
    public function __construct(
        protected SchoolDistributionService $distributionService
    ) {}

    /**
     * Display either the School Distribution Dashboard (default) or the administrative reference dataset.
     */
    public function index(Request $request): View
    {
        $metadata = RegionDatasetMetadata::where('status', 'active')->latest()->first();

        // If explicitly requested administrative reference view or level filter
        if ($request->query('view') === 'reference' || $request->has('level')) {
            $level = $request->query('level', 'provinces');
            $search = trim((string) $request->query('search', ''));
            $selectedProvince = $request->query('province_code');
            $selectedRegency = $request->query('regency_code');
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

            return view('pages.master.regions.reference', compact(
                'items',
                'level',
                'search',
                'selectedProvince',
                'selectedRegency',
                'provinces',
                'metadata'
            ));
        }

        // Primary: Persebaran Sekolah Mitra BITANIC Dashboard
        $statusFilter = (string) $request->query('status', 'all');
        $search = $request->query('search');
        $selectedProvince = $request->query('province_code') ?? $request->query('province');
        $selectedRegency = $request->query('regency_code') ?? $request->query('regency');
        $selectedDistrict = $request->query('district_code') ?? $request->query('district');

        $distribution = $this->distributionService->getDistributionData(
            statusFilter: $statusFilter,
            search: $search,
            selectedProvinceCode: $selectedProvince,
            selectedRegencyCode: $selectedRegency,
            selectedDistrictCode: $selectedDistrict
        );

        return view('pages.master.regions.index', compact('distribution', 'metadata'));
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

    /**
     * GIS Reverse Geocoding API:
     * Resolves latitude and longitude coordinates into official Kemendagri / BPS administrative regions
     * (Province, Regency, District) and human-friendly address.
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $request->query('lat');
        $lng = (float) $request->query('lng');

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'User-Agent' => 'Bitanic-Precision-Agriculture/1.0 (smk@bitanic.id)',
                'Accept-Language' => 'id,en',
            ])->timeout(6)->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat' => $lat,
                'lon' => $lng,
                'addressdetails' => 1,
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Layanan geocoding OSM tidak merespons.',
                    'data' => null,
                ], 502);
            }

            $json = $response->json();
            $addr = $json['address'] ?? [];
            $displayName = $json['display_name'] ?? '';

            $state = $addr['state'] ?? '';
            $regencyCandidates = array_filter([
                $addr['county'] ?? null,
                $addr['city'] ?? null,
                $addr['city_district'] ?? null,
            ]);
            $districtCandidates = array_filter([
                $addr['town'] ?? null,
                $addr['district'] ?? null,
                $addr['municipality'] ?? null,
                $addr['suburb'] ?? null,
                $addr['neighbourhood'] ?? null,
            ]);

            // 1. Match Province
            $cleanState = strtolower(preg_replace('/^(provinsi|daerah khusus ibukota|daerah istimewa)\s+/i', '', trim($state)));
            $province = ! empty($cleanState)
                ? Province::whereRaw('LOWER(name) LIKE ?', ["%{$cleanState}%"])->first()
                : null;

            $matchedRegency = null;
            $matchedDistrict = null;
            $regenciesList = [];
            $districtsList = [];

            if ($province) {
                // Fetch all regencies for this province so the dropdown is ready
                $regenciesList = Regency::where('province_code', $province->code)
                    ->select('code', 'name', 'province_code')
                    ->orderBy('name')
                    ->get();

                // 2. Match Regency
                foreach ($regencyCandidates as $cand) {
                    $isKota = str_contains(strtolower($cand), 'kota');
                    $isKab = str_contains(strtolower($cand), 'kabupaten') || str_contains(strtolower($cand), 'kab.');
                    $cleanCand = strtolower(preg_replace('/^(kabupaten|kota|kab\.|adm\.)\s+/i', '', trim($cand)));

                    $regencyQuery = Regency::where('province_code', $province->code)
                        ->whereRaw('LOWER(name) LIKE ?', ["%{$cleanCand}%"]);

                    if ($isKota) {
                        $matchedRegency = (clone $regencyQuery)->whereRaw("LOWER(name) LIKE 'kota %'")->first() ?? $regencyQuery->first();
                    } elseif ($isKab) {
                        $matchedRegency = (clone $regencyQuery)->whereRaw("LOWER(name) LIKE 'kabupaten %'")->first() ?? $regencyQuery->first();
                    } else {
                        $matchedRegency = $regencyQuery->first();
                    }

                    if ($matchedRegency) {
                        break;
                    }
                }

                if ($matchedRegency) {
                    // Fetch all districts for this regency so the dropdown is ready
                    $districtsList = District::where('regency_code', $matchedRegency->code)
                        ->select('code', 'name', 'regency_code')
                        ->orderBy('name')
                        ->get();

                    // 3. Match District
                    foreach ($districtCandidates as $cand) {
                        $cleanCand = strtolower(preg_replace('/^(kecamatan|kec\.)\s+/i', '', trim($cand)));
                        $matchedDistrict = District::where('regency_code', $matchedRegency->code)
                            ->whereRaw('LOWER(name) LIKE ?', ["%{$cleanCand}%"])
                            ->first();

                        if ($matchedDistrict) {
                            break;
                        }
                    }
                }
            }

            // Build clean formatted address suggestion
            $addressParts = [];
            if (! empty($addr['road'])) {
                $addressParts[] = $addr['road'].(! empty($addr['house_number']) ? ' No. '.$addr['house_number'] : '');
            }
            if (! empty($addr['village'])) {
                $addressParts[] = 'Desa/Kel. '.$addr['village'];
            } elseif (! empty($addr['suburb'])) {
                $addressParts[] = $addr['suburb'];
            }
            if ($matchedDistrict) {
                $addressParts[] = 'Kec. '.ucwords(strtolower($matchedDistrict->name));
            }
            if ($matchedRegency) {
                $addressParts[] = ucwords(strtolower($matchedRegency->name));
            }

            $formattedAddress = ! empty($addressParts) ? implode(', ', $addressParts) : $displayName;

            return response()->json([
                'success' => true,
                'data' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'province_code' => $province?->code,
                    'province_name' => $province?->name,
                    'regency_code' => $matchedRegency?->code,
                    'regency_name' => $matchedRegency?->name,
                    'district_code' => $matchedDistrict?->code,
                    'district_name' => $matchedDistrict?->name,
                    'address' => $formattedAddress,
                    'display_name' => $displayName,
                    'regencies' => $regenciesList,
                    'districts' => $districtsList,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses geocoding: '.$e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
