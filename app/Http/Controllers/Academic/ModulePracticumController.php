<?php

namespace App\Http\Controllers\Academic;

use App\Actions\Practicum\AuthorizeReportRevisionAction;
use App\Actions\Practicum\GradeStudentReportAction;
use App\Actions\Practicum\SubmitStudentReportAction;
use App\Http\Controllers\Controller;
use App\Models\ModulePracticum;
use App\Models\StudentReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ModulePracticumController extends Controller
{
    /**
     * Display the 12-Module Vocational Curriculum Catalog.
     */
    public function index(): View
    {
        $modules = ModulePracticum::orderBy('meeting_number')->get();
        $user = Auth::user();

        // Ambil status penyelesaian modul untuk pengguna saat ini jika login
        $userReports = $user ? StudentReport::where('user_id', $user->id)->get()->keyBy('module_id') : collect();

        return view('pages.modules.index', [
            'modules' => $modules,
            'userReports' => $userReports,
        ]);
    }

    /**
     * Display comprehensive details of a practicum module.
     */
    public function show(int $id): View
    {
        $module = ModulePracticum::with('studentReports.user')->findOrFail($id);
        $user = Auth::user();

        if ($user && $user->role !== 'superuser' && ! $user->school_id) {
            abort(403, 'Akses modul dan laporan memerlukan asosiasi sekolah yang valid.');
        }

        $currentReport = $user ? StudentReport::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->first() : null;

        // Jika Guru atau Admin Sekolah/Superuser, ambil seluruh laporan siswa di modul ini untuk dinilai
        $canGrade = $user && ($user->isGuru() || $user->isSuperuser() || $user->isAdmin());
        $allReports = $canGrade
            ? StudentReport::with(['user.profile'])
                ->where('module_id', $module->id)
                ->when(! $user->isSuperuser(), fn ($query) => $query->whereHas('user', fn ($studentQuery) => $studentQuery->where('school_id', $user->school_id)))
                ->latest()
                ->get()
            : collect();

        return view('pages.modules.show', [
            'module' => $module,
            'currentReport' => $currentReport,
            'allReports' => $allReports,
        ]);
    }

    /**
     * Handle student LKS submission.
     */
    public function submitLks(Request $request, int $id, SubmitStudentReportAction $action): RedirectResponse
    {
        $request->validate([
            'lks_answers' => 'required|array',
            'observation_notes' => 'nullable|string',
            'device_id' => 'nullable|integer|exists:devices,id',
            'activity_schedule_id' => 'nullable|integer|exists:activity_schedules,id',
            'evidence_reading_id' => 'nullable|integer|exists:telemetry_readings,id',
            'revision_notes' => 'nullable|string|max:2000',
        ]);

        $module = ModulePracticum::findOrFail($id);
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $action->execute($user, $module, [
            'lks_answers' => $request->input('lks_answers'),
            'observation_notes' => $request->input('observation_notes'),
            'device_id' => $request->input('device_id'),
            'activity_schedule_id' => $request->input('activity_schedule_id'),
            'evidence_reading_id' => $request->input('evidence_reading_id'),
            'revision_notes' => $request->input('revision_notes'),
        ]);

        return redirect()->route('modules.show', $module->id)
            ->with('success', 'Laporan & LKS Modul #'.$module->meeting_number.' berhasil dikumpulkan.');
    }

    /**
     * Handle teacher rubric grading.
     */
    public function grade(Request $request, int $reportId, GradeStudentReportAction $action): RedirectResponse
    {
        $request->validate([
            'rubric_scores' => 'required|array',
            'rubric_scores.*' => 'required|integer|between:1,4',
            'teacher_feedback' => 'nullable|string|max:5000',
            'adjustment_reason' => 'nullable|string|max:2000',
        ]);

        $report = StudentReport::with(['module', 'user'])->findOrFail($reportId);
        $teacher = $request->user();

        $this->authorize('grade', $report);

        $action->execute(
            $report,
            $teacher,
            $request->input('rubric_scores'),
            $request->input('teacher_feedback'),
            $request->input('adjustment_reason')
        );

        return redirect()->route('modules.show', $report->module_id)
            ->with('success', 'Nilai rubrik kompetensi untuk siswa berhasil disimpan.');
    }

    /**
     * Authorize a revision request for a student report.
     */
    public function requestRevision(
        Request $request,
        int $reportId,
        AuthorizeReportRevisionAction $action
    ): RedirectResponse {
        $request->validate([
            'instructions' => 'required|string|max:2000',
        ]);

        $report = StudentReport::with(['module', 'user'])->findOrFail($reportId);
        $teacher = $request->user();

        $this->authorize('requestRevision', $report);

        $action->execute($report, $teacher, $request->input('instructions'));

        return redirect()->route('modules.show', $report->module_id)
            ->with('success', 'Permintaan revisi berhasil dikirim ke siswa.');
    }
}
