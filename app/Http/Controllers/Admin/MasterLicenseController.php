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
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SchoolLicense::class);

        $today = Carbon::today()->toDateString();
        $query = SchoolLicense::with(['school', 'terms', 'latestTerm'])->latest('updated_at');

        // Search by License Number, School Name, or NPSN
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $lowerSearch = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($lowerSearch) {
                $q->whereRaw('LOWER(license_number) LIKE ?', [$lowerSearch])
                    ->orWhereHas('school', function ($sq) use ($lowerSearch) {
                        $sq->whereRaw('LOWER(name) LIKE ?', [$lowerSearch])
                            ->orWhereRaw('LOWER(npsn) LIKE ?', [$lowerSearch]);
                    });
            });
        }

        // Status Filter
        $status = $request->input('status');
        if ($status === 'active') {
            $query->whereNotNull('start_date')
                ->whereNotNull('expiration_date')
                ->where('start_date', '<=', $today)
                ->where('expiration_date', '>', $today);
        } elseif ($status === 'pending') {
            $query->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                    ->orWhereNull('expiration_date')
                    ->orWhere('start_date', '>', $today);
            });
        } elseif ($status === 'expired') {
            $query->whereNotNull('expiration_date')
                ->where('expiration_date', '<=', $today);
        }

        // Filter: Schools without active license
        if ($request->boolean('unentitled_only')) {
            $query->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                    ->orWhereNull('expiration_date')
                    ->orWhere('start_date', '>', $today)
                    ->orWhere('expiration_date', '<=', $today);
            });
        }

        $licenses = $query->paginate(15)->withQueryString();

        // Global Metrics
        $totalLicenses = SchoolLicense::count();
        $activeLicenses = SchoolLicense::whereNotNull('start_date')
            ->whereNotNull('expiration_date')
            ->where('start_date', '<=', $today)
            ->where('expiration_date', '>', $today)
            ->count();
        $pendingLicenses = SchoolLicense::where(function ($q) use ($today) {
            $q->whereNull('start_date')
                ->orWhereNull('expiration_date')
                ->orWhere('start_date', '>', $today);
        })->count();
        $expiredLicenses = SchoolLicense::whereNotNull('expiration_date')
            ->where('expiration_date', '<=', $today)
            ->count();

        // Active schools without an active LMS license
        $unentitledSchoolsCount = School::where('status', 'active')
            ->where(function ($q) use ($today) {
                $q->whereDoesntHave('license')
                    ->orWhereHas('license', function ($lq) use ($today) {
                        $lq->whereNull('start_date')
                            ->orWhereNull('expiration_date')
                            ->orWhere('start_date', '>', $today)
                            ->orWhere('expiration_date', '<=', $today);
                    });
            })->count();

        // Schools available for new license initialization
        $availableSchools = School::where('status', 'active')
            ->whereDoesntHave('license')
            ->orderBy('name')
            ->get();

        return view('pages.master.licenses.index', [
            'licenses' => $licenses,
            'totalLicenses' => $totalLicenses,
            'activeLicenses' => $activeLicenses,
            'pendingLicenses' => $pendingLicenses,
            'expiredLicenses' => $expiredLicenses,
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
