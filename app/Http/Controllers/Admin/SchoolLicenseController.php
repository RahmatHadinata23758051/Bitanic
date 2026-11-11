<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SchoolLicenseController extends Controller
{
    /**
     * Display the school's own LMS license status in a read-only capacity.
     * Enforces fail-closed tenancy and zero cross-school leakage.
     */
    public function show(Request $request): View
    {
        $user = Auth::user();
        $schoolId = null;

        if ($user->isSuperuser()) {
            if ($request->filled('school_id')) {
                $schoolId = (int) $request->input('school_id');
            } else {
                $schoolId = $user->school_id ?? School::where('status', 'active')->value('id');
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
}
