<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetencyAssessmentController extends Controller
{
    /**
     * Display the student competency gradebook and practicum assessment overview.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $isStudent = (bool) $user?->isSiswa();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Akses asesmen kompetensi memerlukan asosiasi sekolah yang valid.');
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

        $classFilter = $request->query('class');

        $query = User::where('role', 'siswa')
            ->with(['profile.classroom', 'profile.studentGroup', 'studentReports.module'])
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->when($isStudent, fn ($q) => $q->where('id', $user->id));

        if ($classFilter) {
            $isNumericClass = is_numeric($classFilter);
            $query->whereHas('profile', function ($q) use ($classFilter, $isNumericClass) {
                $q->where('class_grade', $classFilter);
                if ($isNumericClass) {
                    $q->orWhere('classroom_id', (int) $classFilter);
                }
            });
        }

        $students = $query->paginate(15)->withQueryString();

        $allStudents = User::where('role', 'siswa')
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->when($isStudent, fn ($q) => $q->where('id', $user->id))
            ->when($isStudent && $classFilter, function ($q) use ($user, $classFilter) {
                $isNumeric = is_numeric($classFilter);
                $profileQuery = fn ($pq) => $pq->where('user_id', $user->id)
                    ->where(function ($sub) use ($classFilter, $isNumeric) {
                        $sub->where('class_grade', $classFilter);
                        if ($isNumeric) {
                            $sub->orWhere('classroom_id', (int) $classFilter);
                        }
                    });
                $q->whereHas('profile', $profileQuery);
            })
            ->when(! $isStudent && $classFilter, function ($q) use ($classFilter) {
                $isNumeric = is_numeric($classFilter);
                $q->whereHas('profile', function ($pq) use ($classFilter, $isNumeric) {
                    $pq->where('class_grade', $classFilter);
                    if ($isNumeric) {
                        $pq->orWhere('classroom_id', (int) $classFilter);
                    }
                });
            })
            ->with(['profile.classroom', 'profile.studentGroup'])
            ->get();

        $gradedStudents = $allStudents->filter(fn ($s) => $s->profile?->average_grade !== null);
        $avgScore = $gradedStudents->isNotEmpty()
            ? (float) $gradedStudents->avg(fn ($s) => $s->profile->average_grade)
            : null;

        $stats = [
            'total_students' => $allStudents->count(),
            'certified' => $allStudents->filter(fn ($s) => (bool) $s->profile?->trainer_certified)->count(),
            'pred_a' => $allStudents->filter(fn ($s) => $s->profile?->competency_predicative === 'A')->count(),
            'pred_b' => $allStudents->filter(fn ($s) => $s->profile?->competency_predicative === 'B')->count(),
            'pred_c' => $allStudents->filter(fn ($s) => $s->profile?->competency_predicative === 'C')->count(),
            'avg_score' => $avgScore,
        ];

        $schools = $isSuperuser ? School::where('status', 'active')->orderBy('name')->get() : collect();

        $classrooms = Classroom::when($targetSchoolId !== null, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->orderBy('name')
            ->get();

        $availableClasses = $classrooms->isNotEmpty()
            ? $classrooms->pluck('name')
            : $allStudents->pluck('profile.class_grade')->filter()->unique()->sort()->values();

        return view('pages.assessment.competency-reports', [
            'user' => $user,
            'students' => $students,
            'stats' => $stats,
            'currentClass' => $classFilter,
            'availableClasses' => $availableClasses,
            'selectedSchoolId' => $selectedSchoolId,
            'schools' => $schools,
            'isSuperuser' => $isSuperuser,
        ]);
    }

    /**
     * Export the student competency gradebook to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $isSuperuser = (bool) $user?->isSuperuser();
        $isStudent = (bool) $user?->isSiswa();
        $schoolId = $user?->school_id;

        if ($user && ! $isSuperuser) {
            if (! $schoolId) {
                abort(403, 'Ekspor asesmen kompetensi memerlukan asosiasi sekolah yang valid.');
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

        $classFilter = $request->query('class');

        $query = User::where('role', 'siswa')
            ->with(['profile.classroom', 'profile.studentGroup'])
            ->when($targetSchoolId, fn ($q) => $q->where('school_id', $targetSchoolId))
            ->when($isStudent, fn ($q) => $q->where('id', $user->id))
            ->orderBy('name');

        if ($classFilter) {
            $isNumericClass = is_numeric($classFilter);
            $query->whereHas('profile', function ($q) use ($classFilter, $isNumericClass) {
                $q->where('class_grade', $classFilter);
                if ($isNumericClass) {
                    $q->orWhere('classroom_id', (int) $classFilter);
                }
            });
        }

        $filename = 'rekapitulasi-nilai-kompetensi-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Nama Siswa',
                'NISN',
                'Kelas',
                'Kelompok',
                'Meja Trainer Kit',
                'Presensi (%)',
                'Rata-Rata Nilai',
                'Predikat',
                'Sertifikasi IoT',
            ]);

            $query->chunk(100, function ($students) use ($handle) {
                foreach ($students as $student) {
                    $profile = $student->profile;
                    $grade = $profile?->average_grade !== null ? (float) $profile->average_grade : null;
                    $pred = $profile?->competency_predicative ?? ($grade !== null ? ($grade >= 85 ? 'A' : ($grade >= 75 ? 'B' : ($grade >= 65 ? 'C' : 'D'))) : '—');

                    fputcsv($handle, [
                        $this->sanitizeCsvCell($student->name),
                        $this->sanitizeCsvCell($profile?->identifier_number ?? '—'),
                        $this->sanitizeCsvCell($profile?->class_grade ?? ($profile?->classroom?->name ?? '—')),
                        $this->sanitizeCsvCell($profile?->group_name ?? ($profile?->studentGroup?->name ?? '—')),
                        $this->sanitizeCsvCell($profile?->assigned_kit_id ? 'TK-0'.$profile->assigned_kit_id : 'Belum Ditugaskan'),
                        $profile?->attendance_percent !== null ? number_format((float) $profile->attendance_percent, 1).'%' : '—',
                        $grade !== null ? number_format($grade, 1) : '—',
                        $pred,
                        $profile?->trainer_certified ? 'Tersertifikasi' : 'Proses Belajar',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Sanitize cell against CSV Formula Injection (CWE-1236 / DDE attack).
     */
    protected function sanitizeCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        $str = (string) $value;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }
}
