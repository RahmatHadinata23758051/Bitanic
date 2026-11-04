<?php

namespace App\Actions\Practicum;

use App\Models\ModulePracticum;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use InvalidArgumentException;

class SubmitStudentReportAction
{
    /**
     * Submit or save student practicum report with telemetry snapshot.
     *
     * @param User $user
     * @param ModulePracticum $module
     * @param array<string, mixed> $data
     * @return StudentReport
     */
    public function execute(User $user, ModulePracticum $module, array $data): StudentReport
    {
        if (empty($data['lks_answers']) || !is_array($data['lks_answers'])) {
            throw new InvalidArgumentException('Jawaban Lembar Kerja Siswa (LKS) wajib diisi.');
        }

        // Ambil snapshot telemetri sensor saat pengerjaan (null-safe)
        $latestReading = TelemetryReading::latest('recorded_at')->first();
        $snapshot = $latestReading ? [
            'ph' => (float) $latestReading->ph,
            'humidity' => (float) $latestReading->humidity,
            'ec' => (float) $latestReading->ec,
            'nitrogen' => (float) $latestReading->nitrogen,
            'phosphorus' => (float) $latestReading->phosphorus,
            'potassium' => (float) $latestReading->potassium,
            'temperature' => (float) $latestReading->temperature,
            'recorded_at' => $latestReading->recorded_at->toIso8601String(),
        ] : [
            'ph' => 6.5,
            'humidity' => 50.0,
            'ec' => 1400.0,
            'nitrogen' => 60.0,
            'phosphorus' => 30.0,
            'potassium' => 120.0,
            'temperature' => 26.0,
            'recorded_at' => now()->toIso8601String(),
        ];

        return StudentReport::updateOrCreate(
            [
                'user_id' => $user->id,
                'module_id' => $module->id,
            ],
            [
                'activity_schedule_id' => $data['activity_schedule_id'] ?? null,
                'lks_answers' => $data['lks_answers'],
                'observation_notes' => $data['observation_notes'] ?? null,
                'telemetry_snapshot' => $snapshot,
                'status' => 'submitted',
            ]
        );
    }
}
