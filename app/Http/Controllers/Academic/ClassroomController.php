<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\StudentGroup;
use App\Models\User;
use App\Services\Tenancy\SchoolContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Classroom::class);
        $user = $request->user();

        if ($user && $user->isSuperuser()) {
            /** @var SchoolContextResolver $resolver */
            $resolver = app(SchoolContextResolver::class);
            $context = $resolver->resolve($request, $user);
            $schoolId = $context->targetSchoolId ?? School::query()->where('status', 'active')->orderBy('id')->value('id');
            $schools = $context->schools;
            $selectedSchoolId = $context->selectedSchoolId;
            $schoolName = $context->schoolName;
            $isSuperuser = true;
        } else {
            $schoolId = $user?->school_id;
            if (! $schoolId) {
                abort(403, 'Akses kelas memerlukan asosiasi sekolah yang valid.');
            }
            $schools = collect();
            $selectedSchoolId = $schoolId;
            $schoolName = $user->school?->name ?? 'Sekolah Saya';
            $isSuperuser = false;
        }

        if (! $schoolId) {
            abort(404, 'Tidak ada sekolah aktif yang tersedia.');
        }

        $academicYearFilter = $request->query('academic_year');

        $query = Classroom::with(['groups.device', 'groups.leader', 'homeroomTeacher'])
            ->withCount('profiles')
            ->where('school_id', $schoolId)
            ->when($academicYearFilter, fn ($q) => $q->where('academic_year', $academicYearFilter))
            ->orderBy('academic_year', 'desc')
            ->orderBy('name');

        $classrooms = $query->paginate(20)->withQueryString();

        $setting = SchoolSetting::where('school_id', $schoolId)->first();

        $availableYears = Classroom::where('school_id', $schoolId)
            ->distinct()
            ->pluck('academic_year')
            ->filter()
            ->values();

        $teachers = User::where('school_id', $schoolId)
            ->where('role', 'guru')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $devices = Device::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $students = User::where('school_id', $schoolId)
            ->where('role', 'siswa')
            ->where('is_active', true)
            ->with('profile')
            ->orderBy('name')
            ->get();

        return view('pages.classrooms.classroom-management', [
            'classrooms' => $classrooms,
            'teachers' => $teachers,
            'devices' => $devices,
            'students' => $students,
            'isSuperuser' => $isSuperuser,
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'schoolName' => $schoolName,
            'setting' => $setting,
            'availableYears' => $availableYears,
            'academicYearFilter' => $academicYearFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Classroom::class);
        $user = $request->user();

        if ($user && $user->isSuperuser()) {
            $schoolId = $request->input('school_id') ?? $request->query('school_id');
            if (! $schoolId || ! School::where('id', $schoolId)->where('status', 'active')->exists()) {
                return back()->withErrors(['school_id' => 'Target sekolah aktif wajib dipilih secara eksplisit oleh superuser.'])->withInput();
            }
            $schoolId = (int) $schoolId;
        } else {
            $schoolId = $user?->school_id;
        }

        if (! $schoolId) {
            abort(403, 'Pembuatan kelas memerlukan asosiasi sekolah yang valid.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('classrooms')->where('school_id', $schoolId)],
            'department' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:20'],
            'homeroom_teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $schoolId)->where('role', 'guru')],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $classroom = Classroom::create($data + ['school_id' => $schoolId]);

        AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Sistem',
            'actor_role' => $user?->role ?? 'guru',
            'action' => 'Pembuatan Kelas',
            'details' => "Membuat kelas baru {$classroom->name} untuk tahun ajaran {$classroom->academic_year}.",
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $redirectParams = ($user && $user->isSuperuser()) ? ['school_id' => $schoolId] : [];

        return redirect()->route('classes.index', $redirectParams)->with('success', "Kelas {$classroom->name} berhasil dibuat.");
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $this->authorize('update', $classroom);

        $schoolId = $classroom->school_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('classrooms')->where('school_id', $schoolId)->ignore($classroom->id)],
            'department' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:20'],
            'homeroom_teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $schoolId)->where('role', 'guru')],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $classroom->update($data);

        $user = $request->user();
        AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Sistem',
            'actor_role' => $user?->role ?? 'guru',
            'action' => 'Pembaruan Kelas',
            'details' => "Memperbarui detail kelas {$classroom->name}.",
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $redirectParams = ($user && $user->isSuperuser()) ? ['school_id' => $schoolId] : [];

        return redirect()->route('classes.index', $redirectParams)->with('success', "Kelas {$classroom->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $this->authorize('delete', $classroom);

        if ($classroom->profiles()->exists()) {
            return back()->withErrors(['classroom' => 'Kelas masih memiliki siswa terdaftar. Pindahkan alokasi siswa sebelum menghapus kelas.']);
        }

        if ($classroom->groups()->exists()) {
            return back()->withErrors(['classroom' => 'Kelas masih memiliki kelompok praktikum terdaftar. Hapus kelompok terlebih dahulu.']);
        }

        $className = $classroom->name;
        $schoolId = $classroom->school_id;
        $user = $request->user();

        $classroom->delete();

        AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Sistem',
            'actor_role' => $user?->role ?? 'guru',
            'action' => 'Penghapusan Kelas',
            'details' => "Menghapus kelas {$className}.",
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $redirectParams = ($user && $user->isSuperuser()) ? ['school_id' => $schoolId] : [];

        return redirect()->route('classes.index', $redirectParams)->with('success', "Kelas {$className} berhasil dihapus.");
    }

    public function storeGroup(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);

        // Derive school_id strictly from the parent classroom
        $schoolId = $classroom->school_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('student_groups')->where('classroom_id', $classroom->id)],
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')->where('school_id', $schoolId)->where('is_active', true)],
            'leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $schoolId)->where('role', 'siswa')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $group = StudentGroup::create($data + [
            'school_id' => $schoolId,
            'classroom_id' => $classroom->id,
        ]);

        $user = $request->user();
        AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Sistem',
            'actor_role' => $user?->role ?? 'guru',
            'action' => 'Pembuatan Kelompok Praktikum',
            'details' => "Menambahkan kelompok {$group->name} pada kelas {$classroom->name}.",
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $redirectParams = ($user && $user->isSuperuser()) ? ['school_id' => $schoolId] : [];

        return redirect()->route('classes.index', $redirectParams)->with('success', "Kelompok {$group->name} berhasil dibuat.");
    }

    public function updateGroup(Request $request, StudentGroup $group): RedirectResponse
    {
        $this->authorizeGroup($request, $group);

        // Derive school_id strictly from the parent classroom or group
        $schoolId = $group->classroom?->school_id ?? $group->school_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('student_groups')->where('classroom_id', $group->classroom_id)->ignore($group->id)],
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')->where('school_id', $schoolId)->where('is_active', true)],
            'leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $schoolId)->where('role', 'siswa')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $group->update($data);

        $user = $request->user();
        AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Sistem',
            'actor_role' => $user?->role ?? 'guru',
            'action' => 'Pembaruan Kelompok Praktikum',
            'details' => "Memperbarui kelompok {$group->name}.",
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $redirectParams = ($user && $user->isSuperuser()) ? ['school_id' => $schoolId] : [];

        return redirect()->route('classes.index', $redirectParams)->with('success', "Kelompok {$group->name} berhasil diperbarui.");
    }

    public function destroyGroup(Request $request, StudentGroup $group): RedirectResponse
    {
        $this->authorizeGroup($request, $group);

        if ($group->profiles()->exists()) {
            return back()->withErrors(['group' => 'Kelompok masih memiliki siswa. Pindahkan alokasi siswa sebelum menghapus kelompok.']);
        }

        $groupName = $group->name;
        $schoolId = $group->school_id;
        $user = $request->user();

        $group->delete();

        AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Sistem',
            'actor_role' => $user?->role ?? 'guru',
            'action' => 'Penghapusan Kelompok Praktikum',
            'details' => "Menghapus kelompok {$groupName}.",
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $redirectParams = ($user && $user->isSuperuser()) ? ['school_id' => $schoolId] : [];

        return redirect()->route('classes.index', $redirectParams)->with('success', "Kelompok {$groupName} berhasil dihapus.");
    }

    public function allocateStudent(Request $request, User $student): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->isSuperuser()) {
            $schoolId = $student->school_id;
        } else {
            $schoolId = $user?->school_id;
            abort_unless($schoolId && (int) $student->school_id === (int) $schoolId, 404);
        }

        abort_unless($student->role === 'siswa', 422);

        $data = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
            'student_group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')->where('school_id', $schoolId)->where('classroom_id', $request->input('classroom_id'))],
            'assigned_kit_id' => ['nullable', 'string', 'max:100'],
        ]);

        $classroom = Classroom::findOrFail($data['classroom_id']);
        $group = empty($data['student_group_id']) ? null : StudentGroup::findOrFail($data['student_group_id']);

        $kitCode = trim((string) ($data['assigned_kit_id'] ?? ''));
        $kit = null;

        if ($kitCode !== '') {
            $kit = Device::where('school_id', $schoolId)
                ->where(function ($q) use ($kitCode) {
                    $q->where('code', $kitCode);
                    if (is_numeric($kitCode)) {
                        $q->orWhere('id', (int) $kitCode);
                    }
                })
                ->where('is_active', true)
                ->first();

            if (! $kit) {
                return back()->withErrors(['assigned_kit_id' => 'Trainer Kit tidak ditemukan atau belum aktif pada sekolah ini.'])->withInput();
            }
        }

        if ($group && $kit && $group->device_id && (int) $group->device_id !== (int) $kit->id) {
            return back()->withErrors(['assigned_kit_id' => 'Trainer Kit tidak sesuai dengan meja default kelompok.'])->withInput();
        }

        if (! $kit && $group?->device_id) {
            $kit = Device::where('school_id', $schoolId)->whereKey($group->device_id)->where('is_active', true)->first();
        }

        DB::transaction(function () use ($student, $classroom, $group, $kit, $schoolId, $user) {
            $profile = $student->profile()->firstOrCreate([], ['type' => 'siswa']);
            $profile->update([
                'classroom_id' => $classroom->id,
                'class_grade' => $classroom->name,
                'student_group_id' => $group?->id,
                'group_name' => $group?->name,
                'assigned_kit_id' => $kit?->code,
            ]);

            AuditLog::create([
                'school_id' => $schoolId,
                'user_id' => $user?->id,
                'actor_name' => $user?->name ?? 'Sistem',
                'actor_role' => $user?->role ?? 'guru',
                'action' => 'Alokasi Siswa',
                'details' => "Mengalokasikan siswa {$student->name} ke kelas {$classroom->name}".($group ? ", kelompok {$group->name}" : '').($kit ? ", meja {$kit->code}" : '').'.',
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        return back()->with('success', "Alokasi siswa {$student->name} berhasil diperbarui.");
    }

    public function updateAcademicSettings(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->isSuperuser()) {
            $schoolId = $request->input('school_id');
            if (! $schoolId || ! School::where('id', $schoolId)->where('status', 'active')->exists()) {
                return back()->withErrors(['school_id' => 'Target sekolah aktif wajib dipilih.'])->withInput();
            }
            $schoolId = (int) $schoolId;
        } else {
            $schoolId = $user?->school_id;
        }

        if (! $schoolId) {
            abort(403, 'Akses memerlukan asosiasi sekolah yang valid.');
        }

        $data = $request->validate([
            'academic_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'string', 'in:Ganjil,Genap'],
        ]);

        $setting = SchoolSetting::firstOrNew(['school_id' => $schoolId]);
        $setting->academic_year = $data['academic_year'];
        $setting->semester = $data['semester'];
        $setting->save();

        AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Sistem',
            'actor_role' => $user?->role ?? 'guru',
            'action' => 'Pembaruan Tahun Ajaran Sekolah',
            'details' => "Memperbarui konfigurasi akademik sekolah ke Tahun Ajaran {$data['academic_year']} Semester {$data['semester']}.",
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $redirectParams = ($user && $user->isSuperuser()) ? ['school_id' => $schoolId] : [];

        return redirect()->route('classes.index', $redirectParams)->with('success', "Konfigurasi tahun ajaran {$data['academic_year']} semester {$data['semester']} berhasil disimpan.");
    }

    private function authorizeClassroom(Request $request, Classroom $classroom): void
    {
        $user = $request->user();
        if ($user && $user->isSuperuser()) {
            return;
        }

        $schoolId = $user?->school_id;
        abort_unless($schoolId && (int) $classroom->school_id === (int) $schoolId, 404);
    }

    private function authorizeGroup(Request $request, StudentGroup $group): void
    {
        $user = $request->user();
        if ($user && $user->isSuperuser()) {
            return;
        }

        $schoolId = $user?->school_id;
        abort_unless($schoolId && (int) $group->school_id === (int) $schoolId, 404);
    }
}
