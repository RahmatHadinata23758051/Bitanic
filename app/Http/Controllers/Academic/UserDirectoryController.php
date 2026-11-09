<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDirectoryController extends Controller
{
    /**
     * Display the school student and teacher directory.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Akses direktori pengguna memerlukan asosiasi sekolah yang valid.');
            }
            $school = $user->school;
            if (! $school || $school->status !== 'active') {
                abort(403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');
            }
        }

        $selectedSchoolId = $request->query('school_id');
        $targetSchoolId = ($isSuperuser && $selectedSchoolId)
            ? (int) $selectedSchoolId
            : (! $isSuperuser ? $schoolId : null);

        $role = $request->query('role');
        $search = trim((string) $request->query('q', ''));

        $query = User::with('profile')
            ->whereIn('role', ['siswa', 'guru', 'superuser', 'admin'])
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('role')
            ->orderBy('name');

        if ($role && in_array($role, ['siswa', 'guru', 'superuser', 'admin'], true)) {
            if (in_array($role, ['superuser', 'admin'], true)) {
                $query->whereIn('role', ['superuser', 'admin']);
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
            'admins' => User::whereIn('role', ['superuser', 'admin'])
                ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
                ->count(),
        ];

        $schools = $isSuperuser ? School::where('status', 'active')->orderBy('name')->get() : collect();
        $activeSchool = $targetSchoolId ? School::find($targetSchoolId) : $user?->school;
        $schoolName = $activeSchool?->name ?? ($isSuperuser ? 'Semua Sekolah (Agregat Global)' : 'SMK Pertanian Pembangunan Negeri 1 Sukaraja');

        return view('pages.users.user-directory', [
            'users' => $users,
            'stats' => $stats,
            'currentRole' => $role,
            'search' => $search,
            'schoolName' => $schoolName,
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'isSuperuser' => $isSuperuser,
        ]);
    }
}
