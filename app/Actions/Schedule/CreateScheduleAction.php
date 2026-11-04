<?php

namespace App\Actions\Schedule;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateScheduleAction
{
    /**
     * Create a new practicum schedule ensuring zero bench allocation collisions.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data, ?User $creator = null): ActivitySchedule
    {
        $deviceId = $data['device_id'] ?? null;
        $date = $data['activity_date'];
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];

        if ($startTime >= $endTime) {
            throw ValidationException::withMessages([
                'end_time' => 'Jam selesai praktikum harus lebih besar daripada jam mulai.',
            ]);
        }

        return DB::transaction(function () use ($data, $creator, $deviceId, $date, $startTime, $endTime) {
            // Lock all shared resources before checking overlaps to serialize concurrent bookings.
            if ($deviceId) {
                $device = Device::whereKey($deviceId)->lockForUpdate()->firstOrFail();
                if ($creator && $creator->role !== 'superuser'
                    && (! $creator->school_id || (int) $device->school_id !== (int) $creator->school_id)) {
                    throw ValidationException::withMessages([
                        'device_id' => 'Meja Trainer Kit tersebut bukan milik sekolah Anda.',
                    ]);
                }
            }

            if ($creator && in_array($creator->role, ['guru', 'superuser'])) {
                User::whereKey($creator->id)->lockForUpdate()->firstOrFail();
            }

            // Cek tabrakan alokasi meja trainer kit pada tanggal dan jam yang sama.
            if ($deviceId) {
                $hasKitConflict = ActivitySchedule::where('device_id', $deviceId)
                    ->whereDate('activity_date', $date)
                    ->where(function ($query) use ($startTime, $endTime) {
                        $query->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                    })
                    ->exists();

                if ($hasKitConflict) {
                    throw ValidationException::withMessages([
                        'device_id' => 'Meja Trainer Kit tersebut telah dialokasikan untuk sesi praktikum lain pada rentang jam tersebut. Silakan pilih meja atau jam lain.',
                    ]);
                }
            }

            // Cek tabrakan jadwal guru pembimbing jika ada
            if ($creator && in_array($creator->role, ['guru', 'superuser'])) {
                $hasTeacherConflict = ActivitySchedule::whereDate('activity_date', $date)
                    ->whereHas('participants', function ($query) use ($creator) {
                        $query->where('user_id', $creator->id)
                            ->where('role', 'guru');
                    })
                    ->where(function ($query) use ($startTime, $endTime) {
                        $query->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                    })
                    ->exists();

                if ($hasTeacherConflict) {
                    throw ValidationException::withMessages([
                        'start_time' => 'Guru pembimbing telah memiliki jadwal sesi praktikum lain pada rentang jam tersebut.',
                    ]);
                }
            }

            $schedule = ActivitySchedule::create([
                'school_id' => $data['school_id'] ?? $creator?->school_id ?? ($deviceId ? $device->school_id : null),
                'device_id' => $deviceId,
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
