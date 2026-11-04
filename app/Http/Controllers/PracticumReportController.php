<?php

namespace App\Http\Controllers;

use App\Models\ModulePracticum;
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

        $baseQuery = StudentReport::query();
        if ($request->user()->isSiswa()) {
            $baseQuery->where('user_id', $request->user()->id);
        } elseif ($request->user()->role !== 'superuser' && $request->user()->school_id) {
            $baseQuery->whereHas('user', fn ($u) => $u->where('school_id', $request->user()->school_id));
        }

        $query = (clone $baseQuery)->with(['user.profile', 'module', 'teacher'])
            ->latest();

        if ($statusFilter && in_array($statusFilter, ['submitted', 'graded', 'revision_needed'])) {
            $query->where('status', $statusFilter);
        }

        if ($moduleIdFilter) {
            $query->where('module_id', (int) $moduleIdFilter);
        }

        $reports = $query->paginate(15)->withQueryString();
        $modules = ModulePracticum::orderBy('meeting_number')->get();

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'submitted' => (clone $baseQuery)->where('status', 'submitted')->count(),
            'graded' => (clone $baseQuery)->where('status', 'graded')->count(),
            'avg_grade' => (clone $baseQuery)->whereNotNull('final_grade')->avg('final_grade') ?? 0,
        ];

        return view('pages.reports.index', [
            'reports' => $reports,
            'modules' => $modules,
            'stats' => $stats,
            'currentStatus' => $statusFilter,
            'currentModuleId' => $moduleIdFilter,
        ]);
    }
}
