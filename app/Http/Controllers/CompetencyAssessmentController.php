<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetencyAssessmentController extends Controller
{
    /**
     * Display the student competency gradebook and practicum assessment overview.
     */
    public function index(Request $request): View
    {
        $schoolId = $request->user()?->school_id;
        $isSuperuser = $request->user()?->role === 'superuser';
        $classFilter = $request->query('class');

        if ($request->user() && ! $isSuperuser && ! $schoolId) {
            abort(403, 'Akses asesmen kompetensi memerlukan asosiasi sekolah yang valid.');
        }

        $query = User::where('role', 'siswa')
            ->with(['profile', 'studentReports.module'])
            ->when(! $isSuperuser, fn ($q) => $q->where('school_id', $schoolId));

        if ($classFilter) {
            $query->whereHas('profile', function ($q) use ($classFilter) {
                $q->where('class_grade', $classFilter);
            });
        }

        $students = $query->paginate(15)->withQueryString();

        $allStudents = User::where('role', 'siswa')
            ->when(! $isSuperuser, fn ($q) => $q->where('school_id', $schoolId))
            ->with('profile')
            ->get();

        $stats = [
            'total_students' => $allStudents->count(),
            'certified' => $allStudents->filter(fn ($s) => $s->profile?->trainer_certified)->count(),
            'pred_a' => $allStudents->filter(fn ($s) => $s->profile?->competency_predicative === 'A')->count(),
            'pred_b' => $allStudents->filter(fn ($s) => $s->profile?->competency_predicative === 'B')->count(),
            'pred_c' => $allStudents->filter(fn ($s) => $s->profile?->competency_predicative === 'C')->count(),
            'avg_score' => $allStudents->avg(fn ($s) => $s->profile?->average_grade) ?? 0,
        ];

        return view('pages.assessment.index', [
            'students' => $students,
            'stats' => $stats,
            'currentClass' => $classFilter,
        ]);
    }
}
