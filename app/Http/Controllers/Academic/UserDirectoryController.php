<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\School;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Tenancy\SchoolContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserDirectoryController extends Controller
{
    /**
     * Display the school student and teacher directory.
     */
    public function index(Request $request, SchoolContextResolver $contextResolver): View
    {
        $user = $request->user();
        $context = $contextResolver->resolve($request, $user);

        $targetSchoolId = $context->targetSchoolId;
        $selectedSchoolId = $context->selectedSchoolId;
        $schools = $context->schools;
        $isSuperuser = $context->isSuperuser;
        $isAdmin = $user && ($user->isSuperuser() || $user->isSchoolAdmin());

        $role = $request->query('role');
        $search = trim((string) $request->query('q', ''));

        $query = User::with(['profile.classroom', 'profile.studentGroup', 'school'])
            ->whereIn('role', ['siswa', 'guru', 'superuser', 'admin', 'admin_sekolah'])
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('role')
            ->orderBy('name');

        if ($role && in_array($role, ['siswa', 'guru', 'superuser', 'admin', 'admin_sekolah'], true)) {
            if (in_array($role, ['superuser', 'admin', 'admin_sekolah'], true)) {
                $query->whereIn('role', ['superuser', 'admin', 'admin_sekolah']);
            } else {
                $query->where('role', $role);
            }
        }

        if ($search !== '') {
            $lowerSearch = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($lowerSearch) {
                $q->whereRaw('LOWER(name) LIKE ?', [$lowerSearch])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$lowerSearch])
                    ->orWhereHas('profile', function ($profileQuery) use ($lowerSearch) {
                        $profileQuery->whereRaw('LOWER(identifier_number) LIKE ?', [$lowerSearch])
                            ->orWhereRaw('LOWER(class_grade) LIKE ?', [$lowerSearch])
                            ->orWhereRaw('LOWER(specialization) LIKE ?', [$lowerSearch]);
                    });
            });
        }

        $users = $query->paginate(20)->withQueryString();

        $stats = [
            'students' => User::where('role', 'siswa')
                ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
                ->count(),
            'teachers' => User::where('role', 'guru')
                ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
                ->count(),
            'admins' => User::whereIn('role', ['superuser', 'admin', 'admin_sekolah'])
                ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
                ->count(),
        ];

        $schoolName = $context->schoolName;

        $classrooms = $targetSchoolId
            ? Classroom::where('school_id', $targetSchoolId)->orderBy('name')->get()
            : collect();

        $groups = $targetSchoolId
            ? StudentGroup::where('school_id', $targetSchoolId)->orderBy('name')->get()
            : collect();

        $devices = $targetSchoolId
            ? Device::where('school_id', $targetSchoolId)->where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('pages.users.user-directory', [
            'users' => $users,
            'stats' => $stats,
            'currentRole' => $role,
            'search' => $search,
            'schoolName' => $schoolName,
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'isSuperuser' => $isSuperuser,
            'isAdmin' => $isAdmin,
            'classrooms' => $classrooms,
            'groups' => $groups,
            'devices' => $devices,
        ]);
    }

    /**
     * Store a newly created teacher or student account.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        if (! $currentUser || (! $currentUser->isSuperuser() && ! $currentUser->isSchoolAdmin())) {
            abort(403, 'Akses ditolak: Hanya administrator yang dapat menambahkan akun pengguna.');
        }

        if ($currentUser->isSuperuser()) {
            $targetSchoolId = $request->input('school_id') ?? $request->query('school_id');
            if (! $targetSchoolId || ! School::where('id', $targetSchoolId)->where('status', 'active')->exists()) {
                return back()->withErrors(['school_id' => 'Target sekolah aktif wajib dipilih oleh superuser.'])->withInput();
            }
            $targetSchoolId = (int) $targetSchoolId;
        } else {
            $targetSchoolId = $currentUser->school_id;
            if (! $targetSchoolId) {
                abort(403, 'Akun admin belum diasosiasikan dengan sekolah.');
            }
        }

        $validated = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:siswa,guru'],
            'identifier_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $targetSchoolId)],
            'student_group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')->where('school_id', $targetSchoolId)],
            'assigned_kit_id' => ['nullable', 'string', 'max:100'],
        ]);

        $initialPassword = Str::random(16);

        DB::transaction(function () use ($validated, $targetSchoolId, $currentUser, $initialPassword) {
            $user = User::create([
                'school_id' => $targetSchoolId,
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'role' => $validated['role'],
                'password' => Hash::make($initialPassword),
                'must_change_password' => true,
                'is_active' => true,
            ]);

            $classroom = ! empty($validated['classroom_id']) ? Classroom::find($validated['classroom_id']) : null;
            $group = ! empty($validated['student_group_id']) ? StudentGroup::find($validated['student_group_id']) : null;

            UserProfile::create([
                'user_id' => $user->id,
                'type' => $validated['role'],
                'identifier_number' => ! empty($validated['identifier_number']) ? trim($validated['identifier_number']) : null,
                'department' => ! empty($validated['department']) ? trim($validated['department']) : 'Agribisnis Tanaman Pangan & Hortikultura',
                'specialization' => ! empty($validated['specialization']) ? trim($validated['specialization']) : null,
                'class_grade' => $classroom?->name,
                'classroom_id' => $classroom?->id,
                'group_name' => $group?->name,
                'student_group_id' => $group?->id,
                'assigned_kit_id' => ! empty($validated['assigned_kit_id']) ? trim($validated['assigned_kit_id']) : null,
                'phone' => ! empty($validated['phone']) ? trim($validated['phone']) : null,
            ]);

            try {
                $token = app('auth.password.broker')->createToken($user);
                $user->sendPasswordResetNotification($token);
            } catch (\Throwable $e) {
                // Ignore mail transport issues in demo/offline environments
            }

            AuditLog::create([
                'school_id' => $targetSchoolId,
                'user_id' => $currentUser->id,
                'actor_name' => $currentUser->name,
                'actor_role' => $currentUser->role,
                'action' => 'Pendaftaran Akun Pengguna',
                'details' => "Menambahkan akun baru: {$user->name} ({$user->email}, peran: {$user->role}). Tautan aktivasi/setelan kata sandi dikirimkan secara aman.",
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        $redirectParams = $currentUser->isSuperuser() ? ['school_id' => $targetSchoolId] : [];

        return redirect()->route('users.index', $redirectParams)
            ->with('success', "Akun {$validated['name']} berhasil didaftarkan dan tautan aktivasi telah dikirimkan.");
    }

    /**
     * Update an existing teacher or student account.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        if (! $currentUser || (! $currentUser->isSuperuser() && ! $currentUser->isSchoolAdmin())) {
            abort(403, 'Akses ditolak: Hanya administrator yang dapat memperbarui akun pengguna.');
        }

        if (! $currentUser->isSuperuser() && (int) $user->school_id !== (int) $currentUser->school_id) {
            abort(403, 'Akses ditolak: Tidak dapat mengubah data pengguna sekolah lain.');
        }

        // Security Hardening (P0): Tenant administrators manage guru/siswa only.
        // Cannot grant, edit, deactivate or delete privileged administrator accounts.
        if (! $currentUser->isSuperuser() && ($user->isSuperuser() || $user->isSchoolAdmin())) {
            abort(403, 'Akses ditolak: Administrator sekolah tidak diizinkan mengubah akun administrator.');
        }

        // Users cannot change their own role
        if ($currentUser->id === $user->id && $request->has('role') && $request->input('role') !== $user->role) {
            abort(403, 'Akses ditolak: Anda tidak dapat mengubah peran akun Anda sendiri.');
        }

        $targetSchoolId = $user->school_id ?? $currentUser->school_id;

        // Role assignment: Tenant admins can only assign guru or siswa. Privileged role assignment requires superuser.
        $allowedRoles = $currentUser->isSuperuser()
            ? ['siswa', 'guru', 'admin_sekolah', 'admin']
            : ['siswa', 'guru'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'identifier_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $targetSchoolId)],
            'student_group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')->where('school_id', $targetSchoolId)],
            'assigned_kit_id' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($validated, $user, $targetSchoolId, $currentUser) {
            $user->update([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'role' => $validated['role'],
            ]);

            $classroom = ! empty($validated['classroom_id']) ? Classroom::find($validated['classroom_id']) : null;
            $group = ! empty($validated['student_group_id']) ? StudentGroup::find($validated['student_group_id']) : null;

            $profile = $user->profile()->firstOrCreate([], ['type' => $validated['role']]);
            $profile->update([
                'type' => $validated['role'],
                'identifier_number' => ! empty($validated['identifier_number']) ? trim($validated['identifier_number']) : null,
                'department' => ! empty($validated['department']) ? trim($validated['department']) : $profile->department,
                'specialization' => ! empty($validated['specialization']) ? trim($validated['specialization']) : null,
                'class_grade' => $classroom?->name ?? $profile->class_grade,
                'classroom_id' => $classroom?->id,
                'group_name' => $group?->name ?? $profile->group_name,
                'student_group_id' => $group?->id,
                'assigned_kit_id' => ! empty($validated['assigned_kit_id']) ? trim($validated['assigned_kit_id']) : null,
                'phone' => ! empty($validated['phone']) ? trim($validated['phone']) : null,
            ]);

            AuditLog::create([
                'school_id' => $targetSchoolId,
                'user_id' => $currentUser->id,
                'actor_name' => $currentUser->name,
                'actor_role' => $currentUser->role,
                'action' => 'Pembaruan Data Pengguna',
                'details' => "Memperbarui profil akun: {$user->name} ({$user->email}, peran: {$user->role}).",
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        $redirectParams = $currentUser->isSuperuser() ? ['school_id' => $targetSchoolId] : [];

        return redirect()->route('users.index', $redirectParams)
            ->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    /**
     * Toggle the active status of an account (safe deactivation).
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        if (! $currentUser || (! $currentUser->isSuperuser() && ! $currentUser->isSchoolAdmin())) {
            abort(403, 'Akses ditolak: Hanya administrator yang dapat mengubah status akun.');
        }

        if (! $currentUser->isSuperuser() && (int) $user->school_id !== (int) $currentUser->school_id) {
            abort(403, 'Akses ditolak: Tidak dapat mengubah status pengguna sekolah lain.');
        }

        if ($currentUser->id === $user->id) {
            return back()->withErrors(['user' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.']);
        }

        // Security Hardening (P0): Tenant administrators cannot deactivate administrator accounts
        if (! $currentUser->isSuperuser() && ($user->isSuperuser() || $user->isSchoolAdmin())) {
            abort(403, 'Akses ditolak: Administrator sekolah tidak diizinkan menonaktifkan akun administrator.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        $statusLabel = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        AuditLog::create([
            'school_id' => $user->school_id,
            'user_id' => $currentUser->id,
            'actor_name' => $currentUser->name,
            'actor_role' => $currentUser->role,
            'action' => 'Perubahan Status Akun Pengguna',
            'details' => "Akun {$user->name} ({$user->email}) berhasil {$statusLabel}.",
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return back()->with('success', "Akun {$user->name} berhasil {$statusLabel}.");
    }

    /**
     * Safely destroy a user account if no historic records exist.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        if (! $currentUser || (! $currentUser->isSuperuser() && ! $currentUser->isSchoolAdmin())) {
            abort(403, 'Akses ditolak: Hanya administrator yang dapat menghapus akun.');
        }

        if (! $currentUser->isSuperuser() && (int) $user->school_id !== (int) $currentUser->school_id) {
            abort(403, 'Akses ditolak: Tidak dapat menghapus pengguna sekolah lain.');
        }

        if ($currentUser->id === $user->id) {
            return back()->withErrors(['user' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        // Security Hardening (P0): Tenant administrators cannot delete administrator accounts
        if (! $currentUser->isSuperuser() && ($user->isSuperuser() || $user->isSchoolAdmin())) {
            abort(403, 'Akses ditolak: Administrator sekolah tidak diizinkan menghapus akun administrator.');
        }

        $hasReports = $user->studentReports()->exists();
        $hasAuditLogs = AuditLog::where('user_id', $user->id)->exists();
        $hasHomeroom = Classroom::where('homeroom_teacher_id', $user->id)->exists();
        $hasGroupLeader = StudentGroup::where('leader_id', $user->id)->exists();

        if ($hasReports || $hasAuditLogs || $hasHomeroom || $hasGroupLeader) {
            return back()->withErrors([
                'user' => 'Pengguna memiliki riwayat laporan, audit, atau penugasan akademik aktif. Gunakan fitur Nonaktifkan Akun untuk mengarsipkan pengguna tanpa menghapus riwayat.',
            ]);
        }

        $userName = $user->name;
        $userEmail = $user->email;
        $schoolId = $user->school_id;

        DB::transaction(function () use ($user, $currentUser, $userName, $userEmail, $schoolId) {
            $user->profile()?->delete();
            $user->delete();

            AuditLog::create([
                'school_id' => $schoolId,
                'user_id' => $currentUser->id,
                'actor_name' => $currentUser->name,
                'actor_role' => $currentUser->role,
                'action' => 'Penghapusan Akun Pengguna',
                'details' => "Akun tanpa riwayat {$userName} ({$userEmail}) berhasil dihapus.",
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        return back()->with('success', "Akun {$userName} berhasil dihapus.");
    }
}
