<?php

namespace App\Http\Controllers;

use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\StudentReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PracticumReportController extends Controller
{
    /**
     * Display the student practicum worksheet (LKS) hub.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $moduleIdFilter = $request->query('module_id');
        $classFilter = $request->query('class') ?? $request->query('classroom_id');
        $selectedSchoolId = $request->query('school_id');

        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Akses laporan praktikum memerlukan asosiasi sekolah yang valid.');
            }
            $school = $user->school;
            if (! $school || $school->status !== 'active') {
                abort(403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');
            }
        }

        $targetSchoolId = ($isSuperuser && $selectedSchoolId)
            ? (int) $selectedSchoolId
            : (! $isSuperuser ? $schoolId : null);

        $baseQuery = StudentReport::query();
        if ($user?->isSiswa()) {
            $baseQuery->where('user_id', $user->id);
        } elseif ($targetSchoolId) {
            $baseQuery->whereHas('user', fn ($u) => $u->where('school_id', $targetSchoolId));
        }

        if ($classFilter) {
            $baseQuery->whereHas('user.profile', function ($p) use ($classFilter) {
                $p->where('class_grade', $classFilter)
                    ->orWhere('classroom_id', $classFilter);
            });
        }

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'submitted' => (clone $baseQuery)->where('status', 'submitted')->count(),
            'graded' => (clone $baseQuery)->where('status', 'graded')->count(),
            'revision' => (clone $baseQuery)->whereIn('status', ['revision', 'revision_needed'])->count(),
            'avg_grade' => (clone $baseQuery)->whereNotNull('final_grade')->avg('final_grade') ?? 0,
        ];

        $query = (clone $baseQuery)->with(['user.profile', 'module', 'teacher', 'device', 'evidenceReading'])
            ->latest();

        if ($statusFilter && in_array($statusFilter, ['submitted', 'graded', 'revision', 'revision_needed'])) {
            $dbStatus = $statusFilter === 'revision_needed' ? 'revision' : $statusFilter;
            $query->where('status', $dbStatus);
        }

        if ($moduleIdFilter) {
            $query->where('module_id', (int) $moduleIdFilter);
        }

        $reports = $query->paginate(15)->withQueryString();
        $modules = ModulePracticum::orderBy('meeting_number')->get();
        $schools = $isSuperuser ? School::where('status', 'active')->orderBy('name')->get() : collect();

        return view('pages.reports.index', [
            'user' => $user,
            'reports' => $reports,
            'modules' => $modules,
            'stats' => $stats,
            'currentStatus' => $statusFilter,
            'currentModuleId' => $moduleIdFilter,
            'currentClass' => $classFilter,
            'selectedSchoolId' => $selectedSchoolId,
            'schools' => $schools,
            'isSuperuser' => $isSuperuser,
        ]);
    }
}
