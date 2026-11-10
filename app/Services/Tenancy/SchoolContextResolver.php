<?php

namespace App\Services\Tenancy;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;

class SchoolContextResolver
{
    /**
     * Authoritatively resolve the tenant school context for the current request and user.
     *
     * Invariants:
     * - Superuser: Can select any active school via ?school_id=. If omitted, views global/all-school context (targetSchoolId = null).
     *   Hostile, malformed, nonexistent, or inactive school_id aborts 404 fail-closed.
     * - Authenticated Non-Superuser (Admin, Guru, Siswa): Bound strictly to assigned school_id.
     *   If a foreign or malformed school_id is passed in request, strictly aborts 403 fail-closed.
     *   Does not expose other schools in dropdown ($schools is empty collection).
     * - Guest: Bound to public default active school. Does not expose other schools.
     */
    public function resolve(Request $request, ?User $user = null): ResolvedSchoolContext
    {
        $user = $user ?? $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();

        // 1. GUEST USER (Unauthenticated)
        if (! $user) {
            $defaultSchool = School::query()->where('status', 'active')->orderBy('id')->first();
            $defaultSchoolId = $defaultSchool ? (int) $defaultSchool->id : -1;

            return new ResolvedSchoolContext(
                targetSchoolId: $defaultSchoolId,
                selectedSchoolId: $defaultSchoolId,
                schools: collect(),
                isSuperuser: false,
                school: $defaultSchool,
                schoolName: $defaultSchool?->name ?? 'Belum Dikonfigurasi'
            );
        }

        // 2. TRUE SUPERUSER (Platform-Level Operator)
        if ($isSuperuser) {
            $activeSchools = School::query()->where('status', 'active')->orderBy('name')->get();
            $rawSchoolId = $request->input('school_id');

            // If no school filter requested, superuser sees global platform data
            if ($rawSchoolId === null || $rawSchoolId === '') {
                return new ResolvedSchoolContext(
                    targetSchoolId: null,
                    selectedSchoolId: null,
                    schools: $activeSchools,
                    isSuperuser: true,
                    school: null,
                    schoolName: 'Semua Sekolah'
                );
            }

            // Hostile parameter check: array or non-numeric
            if (is_array($rawSchoolId) || ! is_numeric($rawSchoolId)) {
                abort(404, 'Parameter sekolah tidak valid.');
            }

            $targetId = (int) $rawSchoolId;
            $selectedSchool = $activeSchools->firstWhere('id', $targetId);

            if (! $selectedSchool) {
                // Must be active and existent
                abort(404, 'Sekolah tidak ditemukan atau tidak aktif.');
            }

            return new ResolvedSchoolContext(
                targetSchoolId: $targetId,
                selectedSchoolId: $targetId,
                schools: $activeSchools,
                isSuperuser: true,
                school: $selectedSchool,
                schoolName: $selectedSchool->name
            );
        }

        // 3. AUTHENTICATED NON-SUPERUSER (Legacy Admin, Guru, Siswa)
        $schoolId = $user->school_id;
        if (! $schoolId) {
            abort(403, 'Akses memerlukan asosiasi sekolah yang valid.');
        }

        $userSchool = $user->school ?? School::find($schoolId);
        if (! $userSchool || $userSchool->status !== 'active') {
            abort(403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');
        }

        // Check if client passed a school_id parameter in query or body
        if ($request->has('school_id')) {
            $rawSchoolId = $request->input('school_id');

            // Hostile parameter check (e.g. array, non-numeric, or foreign ID)
            if (is_array($rawSchoolId) || ! is_numeric($rawSchoolId)) {
                abort(403, 'Akses ditolak: Parameter sekolah tidak valid.');
            }

            if ((int) $rawSchoolId !== (int) $schoolId) {
                abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses data sekolah lain.');
            }
        }

        return new ResolvedSchoolContext(
            targetSchoolId: (int) $schoolId,
            selectedSchoolId: (int) $schoolId,
            schools: collect(),
            isSuperuser: false,
            school: $userSchool,
            schoolName: $userSchool->name
        );
    }
}

