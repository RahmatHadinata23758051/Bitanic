<?php

namespace App\Http\Controllers;

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
        $role = $request->query('role');
        $search = trim((string) $request->query('q', ''));

        $schoolId = $request->user()?->school_id;
        $isSuperuser = $request->user()?->role === 'superuser';

        if (! $isSuperuser && ! $schoolId) {
            abort(403, 'Akses direktori pengguna memerlukan asosiasi sekolah yang valid.');
        }

        $query = User::with('profile')
            ->whereIn('role', ['siswa', 'guru', 'superuser'])
            ->when(! $isSuperuser, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('role')
            ->orderBy('name');

        if ($role && in_array($role, ['siswa', 'guru', 'superuser'])) {
            $query->where('role', $role);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('email', 'ilike', '%'.$search.'%')
                    ->orWhereHas('profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('identifier_number', 'ilike', '%'.$search.'%')
                            ->orWhere('class_grade', 'ilike', '%'.$search.'%')
                            ->orWhere('specialization', 'ilike', '%'.$search.'%');
                    });
            });
        }

        $users = $query->paginate(20)->withQueryString();

        $stats = [
            'students' => User::where('role', 'siswa')
                ->when(! $isSuperuser, fn ($q) => $q->where('school_id', $schoolId))
                ->count(),
            'teachers' => User::where('role', 'guru')
                ->when(! $isSuperuser, fn ($q) => $q->where('school_id', $schoolId))
                ->count(),
            'admins' => User::where('role', 'superuser')->count(),
        ];

        return view('pages.users.index', [
            'users' => $users,
            'stats' => $stats,
            'currentRole' => $role,
            'search' => $search,
            'schoolName' => $request->user()?->school?->name ?? 'SMK Pertanian Pembangunan Negeri 1 Sukaraja',
        ]);
    }
}
