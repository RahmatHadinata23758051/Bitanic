<?php

namespace App\Actions\Schedule;

use App\Models\ActivitySchedule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateScheduleAction
{
    /**
     * Create a new practicum schedule ensuring zero bench allocation collisions.
     *
     * @param array<string, mixed> $data
     * @param User|null $creator
     * @return ActivitySchedule
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

        // Cek tabrakan alokasi meja trainer kit pada tanggal dan jam yang sama
        if ($deviceId) {
            $hasConflict = ActivitySchedule::where('device_id', $deviceId)
                ->where('activity_date', $date)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'device_id' => 'Meja Trainer Kit tersebut telah dialokasikan untuk sesi praktikum lain pada rentang jam tersebut. Silakan pilih meja atau jam lain.',
                ]);
            }
        }

        $schedule = ActivitySchedule::create([
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
    }
}
