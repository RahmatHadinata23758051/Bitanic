<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\Tenancy\SchoolContextResolver;
use Illuminate\Http\Request;
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

        $role = $request->query('role');
        $search = trim((string) $request->query('q', ''));

        $query = User::with('profile')
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

        $schools = $context->schools;
        $schoolName = $context->schoolName;

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
