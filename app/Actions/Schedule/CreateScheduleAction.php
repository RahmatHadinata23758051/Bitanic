<?php

namespace App\Actions\Schedule;

use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\StudentGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateScheduleAction
{
    /**
     * Create a new practicum schedule ensuring zero bench, teacher, class, and group allocation collisions.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data, ?User $creator = null): ActivitySchedule
    {
        $deviceId = ! empty($data['device_id']) ? (int) $data['device_id'] : null;
        $classroomId = ! empty($data['classroom_id']) ? (int) $data['classroom_id'] : null;
        $studentGroupId = ! empty($data['student_group_id']) ? (int) $data['student_group_id'] : null;
        $modulePracticumId = ! empty($data['module_practicum_id']) ? (int) $data['module_practicum_id'] : null;
        $date = $data['activity_date'];
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];

        if ($startTime >= $endTime) {
            throw ValidationException::withMessages([
                'end_time' => 'Jam selesai praktikum harus lebih besar daripada jam mulai.',
            ]);
        }

        return DB::transaction(function () use (
            $data,
            $creator,
            $deviceId,
            $classroomId,
            $studentGroupId,
            $modulePracticumId,
            $date,
            $startTime,
            $endTime
        ) {
            // Lock all shared resources before checking overlaps to serialize concurrent bookings.
            $device = null;
            if ($deviceId) {
                $device = Device::whereKey($deviceId)->lockForUpdate()->firstOrFail();
                if ($creator && $creator->role !== 'superuser'
                    && (! $creator->school_id || (int) $device->school_id !== (int) $creator->school_id)) {
                    throw ValidationException::withMessages([
                        'device_id' => 'Meja Trainer Kit tersebut bukan milik sekolah Anda.',
                    ]);
                }
            }

            $classroom = null;
            if ($classroomId) {
                $classroom = Classroom::whereKey($classroomId)->lockForUpdate()->firstOrFail();
                if ($creator && $creator->role !== 'superuser'
                    && (! $creator->school_id || (int) $classroom->school_id !== (int) $creator->school_id)) {
                    throw ValidationException::withMessages([
                        'classroom_id' => 'Kelas tersebut bukan milik sekolah Anda.',
                    ]);
                }
            }

            $studentGroup = null;
            if ($studentGroupId) {
                $studentGroup = StudentGroup::whereKey($studentGroupId)->lockForUpdate()->firstOrFail();
                if ($creator && $creator->role !== 'superuser'
                    && (! $creator->school_id || (int) $studentGroup->school_id !== (int) $creator->school_id)) {
                    throw ValidationException::withMessages([
                        'student_group_id' => 'Kelompok siswa tersebut bukan milik sekolah Anda.',
                    ]);
                }

                if ($classroomId && $studentGroup->classroom_id && (int) $studentGroup->classroom_id !== (int) $classroomId) {
                    throw ValidationException::withMessages([
                        'student_group_id' => 'Kelompok praktikum tidak terdaftar dalam kelas yang dipilih.',
                    ]);
                }
            }

            if ($modulePracticumId) {
                ModulePracticum::whereKey($modulePracticumId)->firstOrFail();
            }

            if ($creator && in_array($creator->role, ['guru', 'superuser'])) {
                User::whereKey($creator->id)->lockForUpdate()->firstOrFail();
            }

            $timeOverlap = function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            };

            // 1. Cek tabrakan alokasi meja trainer kit pada tanggal dan jam yang sama.
            if ($deviceId) {
                $hasKitConflict = ActivitySchedule::where('device_id', $deviceId)
                    ->whereDate('activity_date', $date)
                    ->where($timeOverlap)
                    ->exists();

                if ($hasKitConflict) {
                    throw ValidationException::withMessages([
                        'device_id' => 'Meja Trainer Kit tersebut telah dialokasikan untuk sesi praktikum lain pada rentang jam tersebut. Silakan pilih meja atau jam lain.',
                    ]);
                }
            }

            // 2. Cek tabrakan jadwal guru pembimbing jika ada
            if ($creator && in_array($creator->role, ['guru', 'superuser'])) {
                $hasTeacherConflict = ActivitySchedule::whereDate('activity_date', $date)
                    ->whereHas('participants', function ($query) use ($creator) {
                        $query->where('user_id', $creator->id)
                            ->where('role', 'guru');
                    })
                    ->where($timeOverlap)
                    ->exists();

                if ($hasTeacherConflict) {
                    throw ValidationException::withMessages([
                        'start_time' => 'Guru pembimbing telah memiliki jadwal sesi praktikum lain pada rentang jam tersebut.',
                    ]);
                }
            }

            // 3. Cek tabrakan jadwal kelas (Classroom Collision)
            if ($classroomId) {
                $hasClassConflict = ActivitySchedule::where('classroom_id', $classroomId)
                    ->whereDate('activity_date', $date)
                    ->where($timeOverlap)
                    ->exists();

                if ($hasClassConflict) {
                    throw ValidationException::withMessages([
                        'classroom_id' => 'Kelas tersebut telah memiliki jadwal praktikum lain pada rentang jam tersebut.',
                    ]);
                }
            }

            // 4. Cek tabrakan jadwal kelompok siswa (Student Group Collision)
            if ($studentGroupId) {
                $hasGroupConflict = ActivitySchedule::where('student_group_id', $studentGroupId)
                    ->whereDate('activity_date', $date)
                    ->where($timeOverlap)
                    ->exists();

                if ($hasGroupConflict) {
                    throw ValidationException::withMessages([
                        'student_group_id' => 'Kelompok siswa tersebut telah memiliki jadwal praktikum lain pada rentang jam tersebut.',
                    ]);
                }
            }

            $resolvedSchoolId = $data['school_id']
                ?? $creator?->school_id
                ?? ($deviceId && $device ? $device->school_id : null)
                ?? ($classroomId && $classroom ? $classroom->school_id : null)
                ?? ($studentGroupId && $studentGroup ? $studentGroup->school_id : null);

            $schedule = ActivitySchedule::create([
                'school_id' => $resolvedSchoolId,
                'classroom_id' => $classroomId,
                'student_group_id' => $studentGroupId,
                'device_id' => $deviceId,
                'module_practicum_id' => $modulePracticumId,
                'title' => $data['title'],
                'activity_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'agenda' => $data['agenda'] ?? null,
            ]);

            if ($creator) {
                $schedule->participants()->create([
                    'user_id' => $creator->id,
                    'role' => in_array($creator->role, ['guru', 'superuser']) ? 'guru' : 'siswa',
                ]);
            }

            return $schedule;
        });
    }
}
