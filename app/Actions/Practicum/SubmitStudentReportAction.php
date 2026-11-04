<?php

namespace App\Actions\Practicum;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\StudentReport;
use App\Models\TelemetryReading;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SubmitStudentReportAction
{
    /**
     * Submit or save student practicum report with telemetry snapshot and academic integrity enforcement.
     *
     * @param User $user
     * @param ModulePracticum $module
     * @param array<string, mixed> $data
     * @return StudentReport
     * @throws ValidationException
     */
    public function execute(User $user, ModulePracticum $module, array $data): StudentReport
    {
        if (empty($data['lks_answers']) || !is_array($data['lks_answers'])) {
            throw new InvalidArgumentException('Jawaban Lembar Kerja Siswa (LKS) wajib diisi.');
        }

        // Cek laporan yang telah ada untuk modul ini
        $existingReport = StudentReport::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->first();

        // 1. Integritas Akademik: Jangan izinkan menimpa laporan yang sudah dinilai guru
        if ($existingReport && $existingReport->status === 'graded') {
            throw ValidationException::withMessages([
                'lks_answers' => 'Laporan Lembar Kerja Siswa (LKS) ini telah dinilai oleh guru dan terkunci demi integritas asesmen akademik.',
            ]);
        }

        // 2. Isolasi Perangkat: Resolusi Trainer Kit yang dialokasikan
        $deviceId = $data['device_id'] ?? null;
        $scheduleId = $data['activity_schedule_id'] ?? null;

        if (!$deviceId && $scheduleId) {
            $schedule = ActivitySchedule::find($scheduleId);
            $deviceId = $schedule?->device_id;
        }

        if (!$deviceId) {
            // Periksa jadwal aktif hari ini untuk siswa ini
            $activeSchedule = ActivitySchedule::whereDate('activity_date', today())
                ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
                ->whereNotNull('device_id')
                ->latest()
                ->first();

            if ($activeSchedule) {
                $deviceId = $activeSchedule->device_id;
                $scheduleId = $scheduleId ?? $activeSchedule->id;
            }
        }

        if (!$deviceId) {
            // Fallback ke perangkat lab aktif pertama jika ada
            $deviceId = Device::where('is_active', true)->value('id');
        }

        // 3. Bukti Telemetri (Telemetry Evidence & Provenance)
        $latestReading = null;
        if ($deviceId) {
            $latestReading = TelemetryReading::with('device')
                ->where('device_id', $deviceId)
                ->latest('recorded_at')
                ->first();
        }

        if ($latestReading) {
            $snapshot = [
                'has_telemetry' => true,
                'reading_id' => $latestReading->id,
                'device_id' => $latestReading->device_id,
                'device_code' => $latestReading->device?->code ?? 'TK-' . $latestReading->device_id,
                'ph' => (float) $latestReading->ph,
                'humidity' => (float) $latestReading->humidity,
                'ec' => (float) $latestReading->ec,
                'nitrogen' => (float) $latestReading->nitrogen,
                'phosphorus' => (float) $latestReading->phosphorus,
                'potassium' => (float) $latestReading->potassium,
                'temperature' => (float) $latestReading->temperature,
                'provenance' => $latestReading->provenance ?? 'hardware',
                'recorded_at' => $latestReading->recorded_at?->toIso8601String(),
            ];
            $evidenceReadingId = $latestReading->id;
        } else {
            // Jujur: Jangan rekayasa angka tiruan jika data belum ditransmisikan
            $snapshot = [
                'has_telemetry' => false,
                'message' => 'Belum ada transmisi data telemetri dari Trainer Kit yang terhubung.',
                'recorded_at' => now()->toIso8601String(),
            ];
            $evidenceReadingId = null;
        }

        // 4. Hitung Percobaan (Attempt Count) & Catatan Revisi
        $attemptCount = $existingReport ? ($existingReport->attempt_count + 1) : 1;
        $revisionNotes = $data['revision_notes'] ?? ($existingReport?->revision_notes);

        return StudentReport::updateOrCreate(
            [
                'user_id' => $user->id,
                'module_id' => $module->id,
            ],
            [
                'activity_schedule_id' => $scheduleId,
                'device_id' => $deviceId,
                'evidence_reading_id' => $evidenceReadingId,
                'lks_answers' => $data['lks_answers'],
                'observation_notes' => $data['observation_notes'] ?? null,
                'telemetry_snapshot' => $snapshot,
                'status' => 'submitted',
                'attempt_count' => $attemptCount,
                'revision_notes' => $revisionNotes,
            ]
        );
    }
}
