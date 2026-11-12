<?php

namespace App\Services\Geo;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\School;
use App\Models\SchoolLicense;
use App\Services\License\SchoolLicenseResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SchoolDistributionService
{
    public function __construct(
        protected SchoolLicenseResolver $licenseResolver
    ) {}

    /**
     * Get the complete distribution data for partner schools according to entitlement status and geographic region.
     *
     * @param  string  $statusFilter  'all'|'active'|'pending'|'expired'
     */
    public function getDistributionData(
        string $statusFilter = 'all',
        ?string $search = null,
        ?string $selectedProvinceCode = null,
        ?string $selectedRegencyCode = null,
        ?string $selectedDistrictCode = null
    ): array {
        $today = Carbon::today('Asia/Jakarta');

        // 1. Query real schools that have at least one authorized entitlement term
        // A pending empty license initialization without terms is NOT a subscribed partner.
        $schoolsQuery = School::with([
            'license.terms',
            'provinceRelation',
            'regencyRelation',
            'districtRelation',
            'trainerKitInventories',
        ])->whereHas('license.terms');

        $allPartnerSchools = $schoolsQuery->orderBy('name')->get();

        // 2. Evaluate each partner school using SchoolLicenseResolver
        $resolvedSchools = $allPartnerSchools->map(function (School $school) use ($today) {
            $license = $school->license;
            $resolution = $this->licenseResolver->resolve($license, $today);

            $status = $resolution['status']; // 'active', 'pending', 'expired'
            $statusLabel = match ($status) {
                SchoolLicense::STATUS_ACTIVE => 'Aktif',
                SchoolLicense::STATUS_PENDING => 'Menunggu Efektif',
                SchoolLicense::STATUS_EXPIRED => 'Kedaluwarsa',
                default => 'Belum Diaktivasi',
            };

            $activeTerm = $resolution['active_term'];
            $termType = $activeTerm?->term_type ?? ($license->terms->last()?->term_type ?? '—');
            $termTypeLabel = match ($termType) {
                'initial_included' => 'Tahun Pertama (Bundling)',
                'annual_renewal' => 'Perpanjangan Tahunan',
                default => '—',
            };

            $isVerified = $school->hasVerifiedCoordinates();
            $coordStatus = $isVerified
                ? 'verified'
                : (($school->latitude !== null && $school->longitude !== null) ? 'unverified' : 'missing');

            return [
                'model' => $school,
                'id' => $school->id,
                'name' => $school->name,
                'npsn' => $school->npsn,
                'slug' => $school->slug,
                'province_code' => $school->province_code,
                'province_name' => $school->provinceRelation?->name ?? ($school->province ?: 'Tidak Terdefinisi'),
                'regency_code' => $school->regency_code,
                'regency_name' => $school->regencyRelation?->name ?? ($school->city ?: 'Tidak Terdefinisi'),
                'regency_type' => $school->regencyRelation?->type ?? 'KABUPATEN',
                'district_code' => $school->district_code,
                'district_name' => $school->districtRelation?->name ?? 'Belum Ditentukan',
                'address' => $school->address,
                'latitude' => $school->latitude,
                'longitude' => $school->longitude,
                'has_coordinates' => $isVerified,
                'coordinate_status' => $coordStatus,
                'coordinate_source' => $school->coordinate_source,
                'coordinate_verified_at' => $school->coordinate_verified_at,
                'license_number' => $license->license_number,
                'license_status' => $status,
                'license_status_label' => $statusLabel,
                'license_term_type' => $termTypeLabel,
                'days_remaining' => $resolution['days_remaining'],
                'start_date' => $license->start_date,
                'expiration_date' => $resolution['continuous_expiration_date']?->toDateString() ?? $license->expiration_date,
                'trainer_kits_count' => $school->trainerKitInventories->count(),
            ];
        });

        // 3. Compute Global KPI Metrics across all partner subscribers
        $totalPartnerSchools = $resolvedSchools->count();
        $activeSchoolsCount = $resolvedSchools->where('license_status', SchoolLicense::STATUS_ACTIVE)->count();
        $pendingSchoolsCount = $resolvedSchools->where('license_status', SchoolLicense::STATUS_PENDING)->count();
        $expiredSchoolsCount = $resolvedSchools->where('license_status', SchoolLicense::STATUS_EXPIRED)->count();

        // 4. Apply status filter
        $statusFilteredSchools = match ($statusFilter) {
            'active' => $resolvedSchools->where('license_status', SchoolLicense::STATUS_ACTIVE),
            'pending' => $resolvedSchools->where('license_status', SchoolLicense::STATUS_PENDING),
            'expired' => $resolvedSchools->where('license_status', SchoolLicense::STATUS_EXPIRED),
            default => $resolvedSchools,
        };

        // 5. Build Hierarchical Region Tree from status-filtered schools
        // This gives user full view of all partner schools in that status across Indonesia
        $provinceTree = $statusFilteredSchools->groupBy('province_code')->map(function (Collection $provinceSchools, $provCode) {
            $first = $provinceSchools->first();
            $provName = $first['province_name'] ?? 'Provinsi Tidak Dikenal';

            // Group by Regency within Province
            $regencies = $provinceSchools->groupBy('regency_code')->map(function (Collection $regencySchools, $regCode) {
                $firstReg = $regencySchools->first();
                $regName = $firstReg['regency_name'] ?? 'Kabupaten/Kota Tidak Dikenal';
                $regType = $firstReg['regency_type'] ?? 'KABUPATEN';

                // Group by District within Regency
                $districts = $regencySchools->groupBy('district_code')->map(function (Collection $districtSchools, $distCode) {
                    $firstDist = $districtSchools->first();
                    $distName = $firstDist['district_name'] ?? 'Kecamatan Tidak Terdefinisi';

                    return [
                        'code' => $distCode ?: 'unknown',
                        'name' => $distName,
                        'schools_count' => $districtSchools->count(),
                        'schools' => $districtSchools->values()->all(),
                    ];
                })->sortBy('name')->values()->all();

                return [
                    'code' => $regCode ?: 'unknown',
                    'name' => $regName,
                    'type' => $regType,
                    'schools_count' => $regencySchools->count(),
                    'districts_count' => count($districts),
                    'districts' => $districts,
                ];
            })->sortBy('name')->values()->all();

            return [
                'code' => $provCode ?: 'unknown',
                'name' => $provName,
                'schools_count' => $provinceSchools->count(),
                'regencies_count' => count($regencies),
                'regencies' => $regencies,
            ];
        })->sortBy('name')->values()->all();

        // 6. Apply Linked Geographic Filtering
        $filteredSchools = $statusFilteredSchools;

        if (! empty($selectedProvinceCode)) {
            $filteredSchools = $filteredSchools->where('province_code', $selectedProvinceCode);
        }

        if (! empty($selectedRegencyCode)) {
            $filteredSchools = $filteredSchools->where('regency_code', $selectedRegencyCode);
        }

        if (! empty($selectedDistrictCode)) {
            $filteredSchools = $filteredSchools->where('district_code', $selectedDistrictCode);
        }

        // 7. Apply search filter if specified
        if ($search !== null && trim($search) !== '') {
            $lowerSearch = strtolower(trim($search));
            $filteredSchools = $filteredSchools->filter(function ($item) use ($lowerSearch) {
                return str_contains(strtolower($item['name']), $lowerSearch)
                    || str_contains(strtolower($item['npsn'] ?? ''), $lowerSearch)
                    || str_contains(strtolower($item['province_name']), $lowerSearch)
                    || str_contains(strtolower($item['regency_name']), $lowerSearch)
                    || str_contains(strtolower($item['district_name']), $lowerSearch)
                    || str_contains(strtolower($item['license_number']), $lowerSearch);
            });
        }

        // Summary counts for filtered set
        $filteredPartnerCount = $filteredSchools->count();
        $coveredProvincesCount = $filteredSchools->pluck('province_code')->filter()->unique()->count();
        $coveredRegenciesCount = $filteredSchools->pluck('regency_code')->filter()->unique()->count();
        $mappedCount = $filteredSchools->filter(fn ($s) => $s['has_coordinates'])->count();
        $unmappedCount = $filteredSchools->filter(fn ($s) => ! $s['has_coordinates'])->count();

        // 8. Build Interactive Map Markers payload (STRICTLY VERIFIED coordinates only)
        $mapMarkers = $filteredSchools->filter(fn ($s) => $s['has_coordinates'])->map(function ($s) {
            $badgeColor = match ($s['license_status']) {
                'active' => 'emerald',
                'pending' => 'amber',
                default => 'rose',
            };

            return [
                'id' => (int) $s['id'],
                'name' => $s['name'],
                'npsn' => $s['npsn'] ?? '—',
                'lat' => (float) $s['latitude'],
                'lng' => (float) $s['longitude'],
                'province' => $s['province_name'],
                'regency' => $s['regency_name'],
                'district' => $s['district_name'],
                'status' => $s['license_status'],
                'status_label' => $s['license_status_label'],
                'status_color' => $badgeColor,
                'license_number' => $s['license_number'],
                'days_remaining' => $s['days_remaining'],
                'expiration_date' => $s['expiration_date'],
                'kits_count' => $s['trainer_kits_count'],
                'detail_url' => route('master.schools.show', (int) $s['id']),
            ];
        })->values();

        // 9. Build Breadcrumbs & Region Context
        $selectedProvince = ! empty($selectedProvinceCode) ? Province::where('code', $selectedProvinceCode)->first() : null;
        $selectedRegency = ! empty($selectedRegencyCode) ? Regency::where('code', $selectedRegencyCode)->first() : null;
        $selectedDistrict = ! empty($selectedDistrictCode) ? District::where('code', $selectedDistrictCode)->first() : null;

        $breadcrumbs = [
            [
                'label' => 'Semua Wilayah',
                'province_code' => null,
                'regency_code' => null,
                'district_code' => null,
                'active' => empty($selectedProvinceCode),
            ],
        ];

        if ($selectedProvince) {
            $breadcrumbs[] = [
                'label' => $selectedProvince->name,
                'province_code' => $selectedProvince->code,
                'regency_code' => null,
                'district_code' => null,
                'active' => empty($selectedRegencyCode),
            ];
        }

        if ($selectedRegency) {
            $breadcrumbs[] = [
                'label' => $selectedRegency->name,
                'province_code' => $selectedProvinceCode,
                'regency_code' => $selectedRegency->code,
                'district_code' => null,
                'active' => empty($selectedDistrictCode),
            ];
        }

        if ($selectedDistrict) {
            $breadcrumbs[] = [
                'label' => $selectedDistrict->name,
                'province_code' => $selectedProvinceCode,
                'regency_code' => $selectedRegencyCode,
                'district_code' => $selectedDistrict->code,
                'active' => true,
            ];
        }

        return [
            'summary' => [
                'total_partner_schools' => $totalPartnerSchools,
                'filtered_partner_schools' => $filteredPartnerCount,
                'active_license_count' => $activeSchoolsCount,
                'pending_license_count' => $pendingSchoolsCount,
                'expired_license_count' => $expiredSchoolsCount,
                'covered_provinces_count' => $coveredProvincesCount,
                'covered_regencies_count' => $coveredRegenciesCount,
                'unmapped_schools_count' => $unmappedCount,
                'mapped_schools_count' => $mappedCount,
            ],
            'filters' => [
                'status' => $statusFilter,
                'search' => $search ?? '',
                'province_code' => $selectedProvinceCode,
                'regency_code' => $selectedRegencyCode,
                'district_code' => $selectedDistrictCode,
                'selected_province' => $selectedProvinceCode,
                'selected_regency' => $selectedRegencyCode,
                'selected_district' => $selectedDistrictCode,
            ],
            'breadcrumbs' => $breadcrumbs,
            'selected_region' => [
                'province' => $selectedProvince,
                'regency' => $selectedRegency,
                'district' => $selectedDistrict,
                'has_selection' => ! empty($selectedProvinceCode),
            ],
            'hierarchy' => $provinceTree,
            'map_markers' => $mapMarkers,
            'schools_list' => $filteredSchools->values()->all(),
        ];
    }
}
