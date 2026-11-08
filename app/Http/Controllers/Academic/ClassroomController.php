<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\StudentGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $request->user()->school_id;
        if (! $schoolId) {
            abort(403, 'Akses kelas memerlukan asosiasi sekolah yang valid.');
        }

        $classrooms = Classroom::with(['groups.device', 'homeroomTeacher'])
            ->where('school_id', $schoolId)
            ->orderBy('academic_year')
            ->orderBy('name')
            ->paginate(20);

        return view('pages.classrooms.classroom-management', [
            'classrooms' => $classrooms,
            'teachers' => User::where('school_id', $schoolId)->where('role', 'guru')->orderBy('name')->get(),
            'devices' => Device::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $schoolId = $request->user()->school_id;
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

        Classroom::create($data + ['school_id' => $schoolId]);

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil dibuat.');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $schoolId = $request->user()->school_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('classrooms')->where('school_id', $schoolId)->ignore($classroom->id)],
            'department' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:20'],
            'homeroom_teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $schoolId)->where('role', 'guru')],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $classroom->update($data);

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        if ($classroom->profiles()->exists()) {
            return back()->withErrors(['classroom' => 'Kelas masih memiliki siswa. Pindahkan alokasi siswa sebelum menghapus kelas.']);
        }

        $classroom->delete();

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil dihapus.');
    }

    public function storeGroup(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $schoolId = $request->user()->school_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('student_groups')->where('classroom_id', $classroom->id)],
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')->where('school_id', $schoolId)->where('is_active', true)],
            'leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $schoolId)->where('role', 'siswa')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        StudentGroup::create($data + ['school_id' => $schoolId, 'classroom_id' => $classroom->id]);

        return redirect()->route('classes.index')->with('success', 'Kelompok berhasil dibuat.');
    }

    public function updateGroup(Request $request, StudentGroup $group): RedirectResponse
    {
        $this->authorizeGroup($request, $group);
        $schoolId = $request->user()->school_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('student_groups')->where('classroom_id', $group->classroom_id)->ignore($group->id)],
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')->where('school_id', $schoolId)->where('is_active', true)],
            'leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $schoolId)->where('role', 'siswa')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $group->update($data);

        return redirect()->route('classes.index')->with('success', 'Kelompok berhasil diperbarui.');
    }

    public function destroyGroup(Request $request, StudentGroup $group): RedirectResponse
    {
        $this->authorizeGroup($request, $group);
        if ($group->profiles()->exists()) {
            return back()->withErrors(['group' => 'Kelompok masih memiliki siswa. Pindahkan alokasi siswa sebelum menghapus kelompok.']);
        }

        $group->delete();

        return redirect()->route('classes.index')->with('success', 'Kelompok berhasil dihapus.');
    }

    public function allocateStudent(Request $request, User $student): RedirectResponse
    {
        $schoolId = $request->user()->school_id;
        abort_unless($schoolId && $student->role === 'siswa' && (int) $student->school_id === (int) $schoolId, 404);

        $data = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
            'student_group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')->where('school_id', $schoolId)->where('classroom_id', $request->input('classroom_id'))],
            'assigned_kit_id' => ['nullable', 'string', 'max:100'],
        ]);

        $kitCode = trim((string) ($data['assigned_kit_id'] ?? ''));
        $kit = $kitCode === '' ? null : Device::where('school_id', $schoolId)->where('code', $kitCode)->where('is_active', true)->firstOrFail();
        $group = empty($data['student_group_id']) ? null : StudentGroup::findOrFail($data['student_group_id']);
        if ($group && $kit && $group->device_id && (int) $group->device_id !== (int) $kit->id) {
            return back()->withErrors(['assigned_kit_id' => 'Trainer Kit tidak sesuai dengan meja default kelompok.'])->withInput();
        }
        if (! $kit && $group?->device_id) {
            $kit = Device::where('school_id', $schoolId)->whereKey($group->device_id)->where('is_active', true)->first();
        }

        DB::transaction(function () use ($student, $data, $kit, $group) {
            $classroom = Classroom::findOrFail($data['classroom_id']);
            $profile = $student->profile()->firstOrCreate([], ['type' => 'siswa']);
            $profile->update([
                'classroom_id' => $classroom->id,
                'student_group_id' => $group?->id,
                'class_grade' => $classroom->name,
                'group_name' => $group?->name,
                'assigned_kit_id' => $kit?->code,
            ]);
        });

        return back()->with('success', 'Alokasi siswa berhasil diperbarui.');
    }

    private function authorizeClassroom(Request $request, Classroom $classroom): void
    {
        $schoolId = $request->user()->school_id;
        abort_unless($schoolId && (int) $classroom->school_id === (int) $schoolId, 404);
    }

    private function authorizeGroup(Request $request, StudentGroup $group): void
    {
        $schoolId = $request->user()->school_id;
        abort_unless($schoolId && (int) $group->school_id === (int) $schoolId, 404);
    }
}
