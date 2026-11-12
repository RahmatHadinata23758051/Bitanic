<?php

namespace App\Http\Controllers\Admin;

use App\Actions\License\ActivateFirstYearLicenseAction;
use App\Actions\License\CorrectSchoolLicenseDatesAction;
use App\Actions\License\InitializeSchoolLicenseAction;
use App\Actions\License\RenewSchoolLicenseAction;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolLicense;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MasterLicenseController extends Controller
{
    /**
     * Display the global LMS license registry with search, status filtering, and metrics.
     * Rooted in School to ensure 100% of schools appear in the registry.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SchoolLicense::class);

        $today = Carbon::today()->toDateString();
        $query = School::with(['license.terms', 'license.latestTerm', 'provinceRelation', 'regencyRelation'])->orderBy('name');

        // Search by License Number, School Name, or NPSN
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $lowerSearch = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($lowerSearch) {
                $q->whereRaw('LOWER(name) LIKE ?', [$lowerSearch])
                    ->orWhereRaw('LOWER(npsn) LIKE ?', [$lowerSearch])
                    ->orWhereHas('license', function ($lq) use ($lowerSearch) {
                        $lq->whereRaw('LOWER(license_number) LIKE ?', [$lowerSearch]);
                    });
            });
        }

        // Canonical status conditions aligned strictly with SchoolLicenseResolver semantics
        $activeLicenseCondition = function ($lq) use ($today) {
            $lq->where(function ($sub) use ($today) {
                $sub->whereHas('terms', function ($tq) use ($today) {
                    $tq->where('start_date', '<=', $today)
                        ->where('expiration_date', '>', $today);
                })->orWhere(function ($fb) use ($today) {
                    $fb->doesntHave('terms')
                        ->whereNotNull('start_date')
                        ->whereNotNull('expiration_date')
                        ->where('start_date', '<=', $today)
                        ->where('expiration_date', '>', $today);
                });
            });
        };

        $expiredLicenseCondition = function ($lq) use ($today) {
            $lq->where(function ($sub) use ($today) {
                // Case A: terms exist, has at least one expired term, but no active term today
                $sub->where(function ($termsBranch) use ($today) {
                    $termsBranch->whereHas('terms', function ($tq) use ($today) {
                        $tq->where('expiration_date', '<=', $today);
                    })->whereDoesntHave('terms', function ($tq) use ($today) {
                        $tq->where('start_date', '<=', $today)
                            ->where('expiration_date', '>', $today);
                    });
                })
                // Case B: no terms exist, license fallback dates are expired
                    ->orWhere(function ($fallbackBranch) use ($today) {
                        $fallbackBranch->doesntHave('terms')
                            ->whereNotNull('expiration_date')
                            ->where('expiration_date', '<=', $today);
                    });
            });
        };

        $pendingLicenseCondition = function ($lq) use ($today) {
            $lq->where(function ($sub) use ($today) {
                // Case A: terms exist, all terms are strictly in the future (no active term, no expired term)
                $sub->where(function ($futureBranch) use ($today) {
                    $futureBranch->whereHas('terms')
                        ->whereDoesntHave('terms', function ($tq) use ($today) {
                            $tq->where('expiration_date', '<=', $today);
                        })->whereDoesntHave('terms', function ($tq) use ($today) {
                            $tq->where('start_date', '<=', $today)
                                ->where('expiration_date', '>', $today);
                        });
                })
                // Case B: no terms exist, initialized/unactivated or future fallback
                    ->orWhere(function ($fallbackBranch) use ($today) {
                        $fallbackBranch->doesntHave('terms')
                            ->where(function ($fb) use ($today) {
                                $fb->whereNull('start_date')
                                    ->orWhereNull('expiration_date')
                                    ->orWhere('start_date', '>', $today);
                            });
                    });
            });
        };

        // Status Filter: active | pending | expired | uninitialized
        $status = $request->input('status');
        if ($status === 'active') {
            $query->whereHas('license', $activeLicenseCondition);
        } elseif ($status === 'pending' || $status === 'unactivated') {
            $query->whereHas('license', $pendingLicenseCondition);
        } elseif ($status === 'expired') {
            $query->whereHas('license', $expiredLicenseCondition);
        } elseif ($status === 'uninitialized') {
            $query->doesntHave('license');
        }

        // Filter: Schools without active license (Tanpa Hak Aktif)
        if ($request->boolean('unentitled_only')) {
            $query->whereDoesntHave('license', $activeLicenseCondition);
        }

        $schools = $query->paginate(15)->withQueryString();

        // Global Metrics across schools (Canonical SchoolLicenseResolver alignment)
        $totalSchools = School::count();
        $totalLicenses = SchoolLicense::count();
        $activeLicenses = School::whereHas('license', $activeLicenseCondition)->count();
        $pendingLicenses = School::whereHas('license', $pendingLicenseCondition)->count();
        $expiredLicenses = School::whereHas('license', $expiredLicenseCondition)->count();
        $uninitializedSchoolsCount = School::doesntHave('license')->count();
        $unentitledSchoolsCount = School::whereDoesntHave('license', $activeLicenseCondition)->count();

        // Schools available for new license initialization
        $availableSchools = School::where('status', 'active')
            ->whereDoesntHave('license')
            ->orderBy('name')
            ->get();

        return view('pages.master.licenses.index', [
            'schools' => $schools,
            'licenses' => $schools, // Backward compatibility for views and tests
            'totalSchools' => $totalSchools,
            'totalLicenses' => $totalLicenses,
            'activeLicenses' => $activeLicenses,
            'pendingLicenses' => $pendingLicenses,
            'expiredLicenses' => $expiredLicenses,
            'uninitializedSchoolsCount' => $uninitializedSchoolsCount,
            'unentitledSchoolsCount' => $unentitledSchoolsCount,
            'availableSchools' => $availableSchools,
            'search' => $request->input('search', ''),
            'statusFilter' => $status,
            'unentitledOnly' => $request->boolean('unentitled_only'),
        ]);
    }

    /**
     * Initialize canonical LMS license record for a school in pending status.
     */
    public function store(Request $request, InitializeSchoolLicenseAction $action): RedirectResponse
    {
        Gate::authorize('create', SchoolLicense::class);

        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $school = School::findOrFail($validated['school_id']);
        $license = $action->execute($school, [
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->route('master.licenses.show', $license)
            ->with('status', "Lisensi LMS untuk sekolah {$school->name} berhasil diinisialisasi dalam status pending.");
    }

    /**
     * Display detailed license management workstation for Superuser.
     */
    public function show(SchoolLicense $license): View
    {
        Gate::authorize('view', $license);

        $license->load([
            'school',
            'terms.authorizer',
            'logs.user',
            'lastAuthorizedBy',
            'notifications.term',
        ]);

        return view('pages.master.licenses.show', [
            'license' => $license,
        ]);
    }

    /**
     * Activate the initial 1-year entitlement.
     */
    public function activate(Request $request, SchoolLicense $license, ActivateFirstYearLicenseAction $action): RedirectResponse
    {
        Gate::authorize('activate', $license);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $action->execute($license, [
            'start_date' => $validated['start_date'],
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->route('master.licenses.show', $license)
            ->with('status', 'Hak akses lisensi LMS tahun pertama berhasil diaktifkan.');
    }

    /**
     * Manually renew LMS license for another year.
     */
    public function renew(Request $request, SchoolLicense $license, RenewSchoolLicenseAction $action): RedirectResponse
    {
        Gate::authorize('renew', $license);

        $validated = $request->validate([
            'renewal_start_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $action->execute($license, [
            'renewal_start_date' => $validated['renewal_start_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->route('master.licenses.show', $license)
            ->with('status', 'Perpanjangan tahunan lisensi LMS berhasil diotorisasi dan dicatat.');
    }

    /**
     * Correct license dates with mandatory audit reason.
     */
    public function correct(Request $request, SchoolLicense $license, CorrectSchoolLicenseDatesAction $action): RedirectResponse
    {
        Gate::authorize('correct', $license);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'correction_reason' => 'required|string|min:5|max:500',
        ]);

        $action->execute($license, [
            'start_date' => $validated['start_date'],
            'correction_reason' => $validated['correction_reason'],
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->route('master.licenses.show', $license)
            ->with('status', 'Tanggal lisensi LMS berhasil dikoreksi dan dicatat dalam audit trail.');
    }
}
