<?php

namespace App\Http\Middleware;

use App\Models\SchoolLicense;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolLicenseIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Safety Rollout Gate: If enforcement feature flag is disabled, bypass check
        if (! config('license.enforcement_enabled', false)) {
            return $next($request);
        }

        // 2. Unauthenticated requests bypass (guest routes, login, etc.)
        if (! auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // 3. Platform Superuser is never locked out
        if ($user->isSuperuser()) {
            return $next($request);
        }

        // 4. Allowlist: Routes and paths that must always remain accessible for all users
        if (
            $request->routeIs(
                'logout',
                'school.license.show',
                'school.license.expired',
                'password.*',
                'api.regions.*'
            ) || $request->is('school/license*', 'logout')
        ) {
            return $next($request);
        }

        // 5. Fail-closed: Authenticated non-Superuser accounts must have valid school association
        if (! $user->school_id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Identitas tenant institusi tidak valid atau belum terasosiasi dengan sekolah resmi.',
                    'license_status' => 'invalid_tenant',
                ], 403);
            }

            auth()->logout();

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun pengguna Anda tidak terasosiasi dengan institusi sekolah yang sah.']);
        }

        // 6. Check school existence and active status
        $school = $user->school;
        if (! $school || $school->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Institusi sekolah akun Anda tidak aktif atau tidak ditemukan pada sistem.',
                    'license_status' => 'inactive_school',
                ], 403);
            }

            auth()->logout();

            return redirect()->route('login')
                ->withErrors(['email' => 'Institusi sekolah akun Anda saat ini tidak berstatus aktif.']);
        }

        // 7. Check school license entitlement
        $license = $school->license;

        // If no license exists or status is not expired, allow (safe rollout)
        if (! $license) {
            return $next($request);
        }

        $status = $license->computed_status;
        if ($status !== SchoolLicense::STATUS_EXPIRED) {
            return $next($request);
        }

        // 7. License is EXPIRED: Handle enforcement
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Masa berlaku lisensi LMS Bitanic sekolah Anda telah berakhir.',
                'license_status' => 'expired',
                'redirect' => route('school.license.expired'),
            ], 403);
        }

        // Tenant Admins (admin_sekolah, legacy admin):
        // Allow GET on settings.index so they can view settings/license info
        if ($user->isAdmin() && $request->isMethod('GET') && $request->routeIs('settings.index')) {
            return $next($request);
        }

        // Tenant Admins get redirected to the license overview page with a warning
        if ($user->isAdmin()) {
            $formattedExp = $license->expiration_date?->translatedFormat('d F Y') ?? 'sebelumnya';

            return redirect()->route('school.license.show')
                ->with('error', "Masa berlaku lisensi LMS Bitanic sekolah Anda telah berakhir pada {$formattedExp}. Operasi sistem dibatasi hingga lisensi diperpanjang.");
        }

        // Guru and Siswa: Redirect to polite suspension notice page
        return redirect()->route('school.license.expired');
    }
}
