<?php

namespace App\Http\Controllers;

use App\Actions\Practicum\GradeStudentReportAction;
use App\Actions\Practicum\SubmitStudentReportAction;
use App\Models\ModulePracticum;
use App\Models\StudentReport;
use App\Models\User;
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

        // Jika Guru, ambil seluruh laporan siswa di modul ini untuk dinilai
        $allReports = ($user && in_array($user->role, ['guru', 'superuser']))
            ? StudentReport::with(['user.profile'])
                ->where('module_id', $module->id)
                ->when($user->role !== 'superuser', fn ($query) => $query->whereHas('user', fn ($studentQuery) => $studentQuery->where('school_id', $user->school_id)))
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
        $user = Auth::user() ?? User::where('role', 'siswa')->first() ?? User::first();

        if (! $user) {
            return redirect()->back()->with('error', 'Silakan login terlebih dahulu untuk mengumpulkan LKS.');
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
            'teacher_feedback' => 'nullable|string',
        ]);

        $report = StudentReport::with('module')->findOrFail($reportId);
        $teacher = Auth::user() ?? User::where('role', 'guru')->first() ?? User::first();

        if (! $teacher) {
            return redirect()->back()->with('error', 'Otorisasi guru diperlukan untuk memberikan nilai.');
        }

        $action->execute(
            $report,
            $teacher,
            $request->input('rubric_scores'),
            $request->input('teacher_feedback')
        );

        return redirect()->route('modules.show', $report->module_id)
            ->with('success', 'Nilai rubrik kompetensi untuk siswa berhasil disimpan.');
    }
}
