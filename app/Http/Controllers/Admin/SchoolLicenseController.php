<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolLicense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SchoolLicenseController extends Controller
{
    /**
     * Display the school's own LMS license status in a read-only capacity.
     * Enforces fail-closed tenancy, explicit context for Superuser, and zero cross-school leakage.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $schoolId = null;

        if ($user->isSuperuser()) {
            if ($request->filled('school_id')) {
                $schoolId = (int) $request->input('school_id');
            } elseif ($user->school_id) {
                $schoolId = $user->school_id;
            } else {
                // Direct Superuser to global registry rather than using implicit school fallback
                return redirect()->route('master.licenses.index');
            }
        } else {
            $schoolId = $user->school_id;
        }

        if (! $schoolId) {
            abort(403, 'Akses ditolak: Pengguna tidak terikat pada sekolah aktif.');
        }

        $school = School::findOrFail($schoolId);
        $license = SchoolLicense::with(['terms.authorizer', 'latestTerm', 'logs.user'])
            ->where('school_id', $schoolId)
            ->first();

        return view('pages.licenses.school-license', [
            'school' => $school,
            'license' => $license,
            'isSuperuser' => $user->isSuperuser(),
        ]);
    }

    /**
     * Display the courteous vocational notice screen when a school's LMS license has expired.
     */
    public function expired(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isSuperuser()) {
            return redirect()->route('dashboard');
        }

        $schoolId = $user->school_id;
        if (! $schoolId) {
            return redirect()->route('dashboard');
        }

        $school = School::find($schoolId);
        $license = SchoolLicense::with(['terms', 'latestTerm'])
            ->where('school_id', $schoolId)
            ->first();

        // If license is active or missing or not expired, redirect to dashboard
        if (! $license || $license->computed_status !== SchoolLicense::STATUS_EXPIRED) {
            return redirect()->route('dashboard');
        }

        return view('pages.licenses.expired', [
            'school' => $school,
            'license' => $license,
            'user' => $user,
        ]);
    }
}
