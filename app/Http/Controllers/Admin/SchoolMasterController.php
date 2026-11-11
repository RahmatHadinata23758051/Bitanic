<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\School;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchoolMasterController extends Controller
{
    /**
     * Display a listing of all tenant schools across the platform.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', School::class);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $provinceCode = $request->query('province_code');

        $query = School::with(['provinceRelation', 'regencyRelation', 'districtRelation'])
            ->withCount(['users', 'devices', 'schedules', 'auditLogs']);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('npsn', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (! empty($status) && in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        if (! empty($provinceCode)) {
            $query->where('province_code', $provinceCode);
        }

        $schools = $query->orderBy('name')->paginate(15)->withQueryString();
        $provinces = Province::orderBy('name')->get();

        return view('pages.master.schools.index', compact('schools', 'search', 'status', 'provinceCode', 'provinces'));
    }

    /**
     * Show the form for creating a new tenant school.
     */
    public function create(): View
    {
        Gate::authorize('create', School::class);

        $provinces = Province::orderBy('name')->get();

        return view('pages.master.schools.create', compact('provinces'));
    }

    /**
     * Store a newly created school in the global tenant registry.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', School::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:20', 'unique:schools,npsn'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:schools,slug'],
            'province_code' => ['required', 'string', 'exists:provinces,code'],
            'regency_code' => ['required', 'string', 'exists:regencies,code'],
            'district_code' => ['nullable', 'string', 'exists:districts,code'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'name.required' => 'Nama sekolah wajib diisi.',
            'npsn.unique' => 'NPSN sudah terdaftar pada sekolah lain.',
            'slug.unique' => 'Slug sekolah sudah digunakan.',
            'province_code.required' => 'Provinsi wajib dipilih.',
            'regency_code.required' => 'Kabupaten/Kota wajib dipilih.',
        ]);

        // Hierarchical validation: Regency must belong to Province
        $regency = Regency::where('code', $validated['regency_code'])
            ->where('province_code', $validated['province_code'])
            ->first();

        if (! $regency) {
            throw ValidationException::withMessages([
                'regency_code' => 'Kabupaten/Kota yang dipilih tidak berada di bawah Provinsi terkait.',
            ]);
        }

        // Hierarchical validation: District must belong to Regency
        if (! empty($validated['district_code'])) {
            $district = District::where('code', $validated['district_code'])
                ->where('regency_code', $validated['regency_code'])
                ->first();

            if (! $district) {
                throw ValidationException::withMessages([
                    'district_code' => 'Kecamatan yang dipilih tidak berada di bawah Kabupaten/Kota terkait.',
                ]);
            }
        }

        // Generate slug if empty
        if (empty($validated['slug'])) {
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (School::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        // Auto-resolve legacy text columns for non-breaking compatibility
        $province = Province::find($validated['province_code']);
        $validated['province'] = $province?->name ?? '';
        $validated['city'] = $regency->name;

        $school = DB::transaction(function () use ($validated) {
            $school = School::create($validated);

            // Provision initial empty school settings
            SchoolSetting::firstOrCreate([
                'school_id' => $school->id,
            ], [
                'school_name' => $school->name,
                'lab_name' => 'Laboratorium Smart Greenhouse & ATPH',
                'auto_refresh_interval' => 10,
                'chart_history_points' => 30,
            ]);

            // Append-only audit record
            $user = Auth::user();
            AuditLog::create([
                'school_id' => $school->id,
                'user_id' => $user?->id,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'school.created',
                'target_type' => 'school',
                'target_id' => (string) $school->id,
                'diff' => $validated,
                'correlation_id' => (string) Str::uuid(),
                'details' => "Tenant school created: {$school->name} (Slug: {$school->slug})",
                'ip_address' => request()->ip(),
            ]);

            return $school;
        });

        return redirect()->route('master.schools.index')
            ->with('success', "Sekolah '{$school->name}' berhasil ditambahkan ke direktori master.");
    }

    /**
     * Display detailed information of a specific school.
     */
    public function show(School $school): View
    {
        Gate::authorize('view', $school);

        $school->load(['provinceRelation', 'regencyRelation', 'districtRelation', 'schoolSetting']);
        $school->loadCount(['users', 'devices', 'schedules']);

        $recentAudits = AuditLog::where('school_id', $school->id)
            ->latest()
            ->take(10)
            ->get();

        return view('pages.master.schools.show', compact('school', 'recentAudits'));
    }

    /**
     * Show the form for editing the specified school.
     */
    public function edit(School $school): View
    {
        Gate::authorize('update', $school);

        $provinces = Province::orderBy('name')->get();
        $regencies = $school->province_code
            ? Regency::where('province_code', $school->province_code)->orderBy('name')->get()
            : collect();
        $districts = $school->regency_code
            ? District::where('regency_code', $school->regency_code)->orderBy('name')->get()
            : collect();

        return view('pages.master.schools.edit', compact('school', 'provinces', 'regencies', 'districts'));
    }

    /**
     * Update the specified school in the global tenant registry.
     */
    public function update(Request $request, School $school): RedirectResponse
    {
        Gate::authorize('update', $school);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:20', Rule::unique('schools', 'npsn')->ignore($school->id)],
            'slug' => ['required', 'string', 'max:100', Rule::unique('schools', 'slug')->ignore($school->id)],
            'province_code' => ['required', 'string', 'exists:provinces,code'],
            'regency_code' => ['required', 'string', 'exists:regencies,code'],
            'district_code' => ['nullable', 'string', 'exists:districts,code'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        // Hierarchical validation: Regency must belong to Province
        $regency = Regency::where('code', $validated['regency_code'])
            ->where('province_code', $validated['province_code'])
            ->first();

        if (! $regency) {
            throw ValidationException::withMessages([
                'regency_code' => 'Kabupaten/Kota yang dipilih tidak berada di bawah Provinsi terkait.',
            ]);
        }

        // Hierarchical validation: District must belong to Regency
        if (! empty($validated['district_code'])) {
            $district = District::where('code', $validated['district_code'])
                ->where('regency_code', $validated['regency_code'])
                ->first();

            if (! $district) {
                throw ValidationException::withMessages([
                    'district_code' => 'Kecamatan yang dipilih tidak berada di bawah Kabupaten/Kota terkait.',
                ]);
            }
        }

        // Auto-resolve legacy text columns
        $province = Province::find($validated['province_code']);
        $validated['province'] = $province?->name ?? '';
        $validated['city'] = $regency->name;

        $oldAttributes = $school->only(['name', 'npsn', 'slug', 'province_code', 'regency_code', 'district_code', 'status', 'address', 'phone']);

        DB::transaction(function () use ($school, $validated, $oldAttributes) {
            $school->update($validated);

            $user = Auth::user();
            AuditLog::create([
                'school_id' => $school->id,
                'user_id' => $user?->id,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'school.updated',
                'target_type' => 'school',
                'target_id' => (string) $school->id,
                'diff' => [
                    'before' => $oldAttributes,
                    'after' => $school->only(array_keys($oldAttributes)),
                ],
                'correlation_id' => (string) Str::uuid(),
                'details' => "Tenant school updated: {$school->name}",
                'ip_address' => request()->ip(),
            ]);
        });

        return redirect()->route('master.schools.index')
            ->with('success', "Data sekolah '{$school->name}' berhasil diperbarui.");
    }

    /**
     * Toggle active/inactive status of a tenant school.
     */
    public function toggleStatus(School $school): RedirectResponse
    {
        Gate::authorize('toggleStatus', $school);

        $newStatus = $school->status === 'active' ? 'inactive' : 'active';
        $oldStatus = $school->status;

        DB::transaction(function () use ($school, $newStatus, $oldStatus) {
            $school->status = $newStatus;
            $school->save();

            $user = Auth::user();
            AuditLog::create([
                'school_id' => $school->id,
                'user_id' => $user?->id,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'school.status_changed',
                'target_type' => 'school',
                'target_id' => (string) $school->id,
                'diff' => [
                    'status' => [
                        'before' => $oldStatus,
                        'after' => $newStatus,
                    ],
                ],
                'correlation_id' => (string) Str::uuid(),
                'details' => "Status sekolah diubah dari {$oldStatus} menjadi {$newStatus}",
                'ip_address' => request()->ip(),
            ]);
        });

        $label = $newStatus === 'active' ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()
            ->with('success', "Sekolah '{$school->name}' berhasil {$label}.");
    }

    /**
     * Delete the school if it contains zero linked records.
     */
    public function destroy(School $school): RedirectResponse
    {
        Gate::authorize('delete', $school);

        $userCount = $school->users()->count();
        $deviceCount = $school->devices()->count();
        $scheduleCount = $school->schedules()->count();
        $auditCount = $school->auditLogs()->count();

        if ($userCount > 0 || $deviceCount > 0 || $scheduleCount > 0 || $auditCount > 0) {
            $reasons = [];
            if ($userCount > 0) {
                $reasons[] = "{$userCount} pengguna";
            }
            if ($deviceCount > 0) {
                $reasons[] = "{$deviceCount} perangkat";
            }
            if ($scheduleCount > 0) {
                $reasons[] = "{$scheduleCount} jadwal";
            }
            if ($auditCount > 0) {
                $reasons[] = "{$auditCount} riwayat audit";
            }

            return redirect()->back()
                ->with('error', "Sekolah '{$school->name}' tidak dapat dihapus karena masih memiliki ".implode(', ', $reasons).'. Silakan nonaktifkan status sekolah (inactive) untuk mengarsipkan.');
        }

        $schoolName = $school->name;
        $schoolId = $school->id;

        DB::transaction(function () use ($school, $schoolId, $schoolName) {
            $user = Auth::user();
            AuditLog::create([
                'school_id' => null,
                'user_id' => $user?->id,
                'actor_name' => $user?->name ?? 'Superuser',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'school.deleted',
                'target_type' => 'school',
                'target_id' => (string) $schoolId,
                'diff' => [
                    'deleted_school_name' => $schoolName,
                ],
                'correlation_id' => (string) Str::uuid(),
                'details' => "Tenant school deleted: {$schoolName} (ID: {$schoolId})",
                'ip_address' => request()->ip(),
            ]);

            $school->schoolSetting()?->delete();
            $school->delete();
        });

        return redirect()->route('master.schools.index')
            ->with('success', "Sekolah '{$schoolName}' berhasil dihapus dari direktori.");
    }
}
