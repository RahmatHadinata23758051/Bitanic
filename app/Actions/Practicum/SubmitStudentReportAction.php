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

        // 2. Integritas alokasi Trainer Kit dan validasi hak akses
        $requestedDeviceId = $data['device_id'] ?? null;
        $scheduleId = $data['activity_schedule_id'] ?? null;
        $profile = $user->profile;

        $assignedKitCode = $profile?->assigned_kit_id;
        $profileDevice = null;
        if ($assignedKitCode) {
            $profileDevice = is_numeric($assignedKitCode)
                ? Device::where('id', (int) $assignedKitCode)->orWhere('code', (string) $assignedKitCode)->first()
                : Device::where('code', (string) $assignedKitCode)->first();
        }

        // Cek apakah siswa memiliki jadwal praktikum aktif hari ini
        $activeScheduleToday = ActivitySchedule::whereDate('activity_date', today())
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('device_id')
            ->latest()
            ->first();

        $schedule = null;
        if ($scheduleId) {
            $schedule = ActivitySchedule::with('participants')->find($scheduleId);

            if (!$schedule) {
                throw ValidationException::withMessages([
                    'activity_schedule_id' => 'Jadwal praktikum tidak ditemukan atau tidak valid.',
                ]);
            }

            if ($schedule->school_id && $user->school_id && $schedule->school_id !== $user->school_id) {
                throw ValidationException::withMessages([
                    'activity_schedule_id' => 'Jadwal praktikum berada di luar domain sekolah Anda.',
                ]);
            }

            if ($user->isSiswa() && !$schedule->participants->contains('user_id', $user->id)) {
                throw ValidationException::withMessages([
                    'activity_schedule_id' => 'Anda tidak terdaftar sebagai peserta pada jadwal praktikum ini.',
                ]);
            }
        }

        $resolvedDevice = null;
        if ($requestedDeviceId) {
            $requestedDevice = is_numeric($requestedDeviceId)
                ? Device::where('id', (int) $requestedDeviceId)->orWhere('code', (string) $requestedDeviceId)->first()
                : Device::where('code', (string) $requestedDeviceId)->first();

            if (!$requestedDevice) {
                throw ValidationException::withMessages([
                    'device_id' => 'Perangkat Trainer Kit tidak ditemukan.',
                ]);
            }

            if ($requestedDevice->school_id && $user->school_id && $requestedDevice->school_id !== $user->school_id) {
                throw ValidationException::withMessages([
                    'device_id' => 'Trainer Kit ini berada di luar domain sekolah Anda.',
                ]);
            }

            // Jika siswa memiliki alokasi kit spesifik di profil, tidak boleh memilih kit lain
            if ($user->isSiswa() && $profileDevice && $profileDevice->id !== $requestedDevice->id) {
                throw ValidationException::withMessages([
                    'device_id' => "Trainer Kit {$requestedDevice->code} bukan alokasi meja praktik Anda ({$profileDevice->code}).",
                ]);
            }

            // Jika siswa memiliki jadwal aktif hari ini, tidak boleh memilih kit di luar jadwalnya
            if ($user->isSiswa() && $activeScheduleToday && (int)$activeScheduleToday->device_id !== (int)$requestedDevice->id) {
                throw ValidationException::withMessages([
                    'device_id' => 'Trainer Kit yang dipilih tidak sesuai dengan alokasi meja pada jadwal praktikum aktif Anda.',
                ]);
            }

            // Jika jadwal spesifik ditentukan, perangkat harus sesuai dengan jadwal
            if ($schedule && (int)$schedule->device_id !== (int)$requestedDevice->id) {
                throw ValidationException::withMessages([
                    'device_id' => 'Trainer Kit yang dipilih tidak sesuai dengan jadwal praktikum.',
                ]);
            }

            $resolvedDevice = $requestedDevice;
        } else {
            // Resolusi server-side otomatis jika device_id tidak dikirim browser
            if ($schedule) {
                $resolvedDevice = $schedule->device;
            } elseif ($activeScheduleToday) {
                $resolvedDevice = $activeScheduleToday->device;
                $scheduleId = $activeScheduleToday->id;
            } elseif ($profileDevice) {
                $resolvedDevice = $profileDevice;
            } else {
                // Fallback aman ke perangkat lab aktif pertama di sekolah siswa
                $resolvedDevice = Device::where('is_active', true)
                    ->when($user->school_id, fn($q) => $q->where('school_id', $user->school_id))
                    ->first();
            }
        }

        $deviceId = $resolvedDevice?->id;

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
