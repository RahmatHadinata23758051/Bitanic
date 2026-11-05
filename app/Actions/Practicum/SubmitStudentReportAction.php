<?php

namespace App\Actions\Practicum;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\StudentReport;
use App\Models\StudentReportAttempt;
use App\Models\TelemetryReading;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SubmitStudentReportAction
{
    /**
     * Submit or save student practicum report with telemetry snapshot and academic integrity enforcement.
     * BR-203: Each submission appends an immutable attempt record; prior attempts remain unchanged.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(User $user, ModulePracticum $module, array $data): StudentReport
    {
        if (empty($data['lks_answers']) || ! is_array($data['lks_answers'])) {
            throw new InvalidArgumentException('Jawaban Lembar Kerja Siswa (LKS) wajib diisi.');
        }

        if ($user->role !== 'superuser') {
            if (! $user->school_id) {
                throw ValidationException::withMessages([
                    'lks_answers' => 'Pengumpulan laporan memerlukan asosiasi sekolah yang valid.',
                ]);
            }

            if ($user->school && $user->school->status !== 'active') {
                throw ValidationException::withMessages([
                    'lks_answers' => 'Sekolah Anda sedang tidak aktif.',
                ]);
            }
        }

        // Cek laporan yang telah ada untuk modul ini
        $existingReport = StudentReport::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->first();

        // 1. Integritas Akademik: Jangan izinkan menimpa laporan yang sudah dinilai guru
        //    Hanya status 'revision' (transisi dari guru) yang mengizinkan resubmisi
        if ($existingReport && $existingReport->status === 'graded') {
            throw ValidationException::withMessages([
                'lks_answers' => 'Laporan Lembar Kerja Siswa (LKS) ini telah dinilai oleh guru dan terkunci demi integritas asesmen akademik.',
            ]);
        }

        // 2. Integritas alokasi Trainer Kit dan validasi hak akses
        $requestedDeviceId = $data['device_id'] ?? null;
        $scheduleId = $data['activity_schedule_id'] ?? null;
        $profile = $user->profile;

        // Resolusi Trainer Kit dari profil: alokasi langsung atau default kit kelompok
        $assignedKitCode = $profile?->assigned_kit_id;
        $profileDevice = null;
        if ($assignedKitCode) {
            $profileDevice = is_numeric($assignedKitCode)
                ? Device::where('id', (int) $assignedKitCode)->orWhere('code', (string) $assignedKitCode)->first()
                : Device::where('code', (string) $assignedKitCode)->first();
        }
        if (! $profileDevice && $profile?->studentGroup?->device) {
            $profileDevice = $profile->studentGroup->device;
        }

        // Cek apakah siswa memiliki jadwal praktikum aktif saat ini (pada hari dan jam sesi praktikum)
        $currentTime = now()->format('H:i:s');
        $activeScheduleToday = ActivitySchedule::whereDate('activity_date', today())
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('device_id')
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->when($user->school_id, fn ($q) => $q->where('school_id', $user->school_id))
            ->latest()
            ->first();

        $schedule = null;
        if ($scheduleId) {
            $schedule = ActivitySchedule::with('participants')->find($scheduleId);

            if (! $schedule) {
                throw ValidationException::withMessages([
                    'activity_schedule_id' => 'Jadwal praktikum tidak ditemukan atau tidak valid.',
                ]);
            }

            if ($schedule->school_id && (! $user->school_id || (int) $schedule->school_id !== (int) $user->school_id)) {
                throw ValidationException::withMessages([
                    'activity_schedule_id' => 'Jadwal praktikum berada di luar domain sekolah Anda.',
                ]);
            }

            if ($user->isSiswa() && ! $schedule->participants->contains('user_id', $user->id)) {
                throw ValidationException::withMessages([
                    'activity_schedule_id' => 'Anda tidak terdaftar sebagai peserta pada jadwal praktikum ini.',
                ]);
            }

            if ($user->isSiswa()) {
                $nowTime = now()->format('H:i:s');
                $isSameDay = $schedule->activity_date?->isToday() ?? false;
                $isWithinWindow = $nowTime >= $schedule->start_time && $nowTime <= $schedule->end_time;
                if (! $isSameDay || ! $isWithinWindow) {
                    throw ValidationException::withMessages([
                        'activity_schedule_id' => 'Sesi praktikum pada jadwal ini belum dimulai atau telah berakhir.',
                    ]);
                }
            }
        }

        $resolvedDevice = null;
        if ($requestedDeviceId) {
            $requestedDevice = is_numeric($requestedDeviceId)
                ? Device::where('id', (int) $requestedDeviceId)->orWhere('code', (string) $requestedDeviceId)->first()
                : Device::where('code', (string) $requestedDeviceId)->first();

            if (! $requestedDevice) {
                throw ValidationException::withMessages([
                    'device_id' => 'Perangkat Trainer Kit tidak ditemukan.',
                ]);
            }

            if ($requestedDevice->school_id && (! $user->school_id || (int) $requestedDevice->school_id !== (int) $user->school_id)) {
                throw ValidationException::withMessages([
                    'device_id' => 'Trainer Kit ini berada di luar domain sekolah Anda.',
                ]);
            }

            // Siswa tanpa alokasi meja tidak boleh memilih meja sembarangan
            if ($user->isSiswa() && ! $profileDevice && ! $activeScheduleToday && ! $schedule) {
                throw ValidationException::withMessages([
                    'device_id' => 'Anda belum dialokasikan ke meja Trainer Kit atau jadwal praktikum oleh guru pembimbing.',
                ]);
            }

            // Jika jadwal spesifik ditentukan, perangkat harus sesuai dengan jadwal
            if ($schedule && (int) $schedule->device_id !== (int) $requestedDevice->id) {
                throw ValidationException::withMessages([
                    'device_id' => 'Trainer Kit yang dipilih tidak sesuai dengan jadwal praktikum.',
                ]);
            }

            // Jika siswa memiliki jadwal aktif hari ini, tidak boleh memilih kit di luar jadwalnya
            if ($user->isSiswa() && ! $schedule && $activeScheduleToday && (int) $activeScheduleToday->device_id !== (int) $requestedDevice->id) {
                throw ValidationException::withMessages([
                    'device_id' => 'Trainer Kit yang dipilih tidak sesuai dengan alokasi meja pada jadwal praktikum aktif Anda.',
                ]);
            }

            // Jika tidak ada jadwal dan siswa memiliki alokasi kit spesifik di profil, tidak boleh memilih kit lain
            if ($user->isSiswa() && ! $schedule && ! $activeScheduleToday && $profileDevice && $profileDevice->id !== $requestedDevice->id) {
                throw ValidationException::withMessages([
                    'device_id' => "Trainer Kit {$requestedDevice->code} bukan alokasi meja praktik Anda ({$profileDevice->code}).",
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
            } elseif ($user->isSiswa()) {
                // Siswa tanpa alokasi meja atau jadwal tidak boleh mengklaim perangkat sembarangan
                $resolvedDevice = null;
            } else {
                // Fallback aman untuk demonstrasi guru/admin
                $resolvedDevice = Device::where('is_active', true)
                    ->when($user->school_id, fn ($q) => $q->where('school_id', $user->school_id))
                    ->first();
            }
        }

        $deviceId = $resolvedDevice?->id;

        // 3. Bukti Telemetri (Telemetry Evidence & Provenance)
        // When a schedule is active, evidence must fall within the session window.
        $sessionWindowStart = null;
        $sessionWindowEnd = null;
        $resolvedSchedule = $schedule ?? ($activeScheduleToday ?? null);

        if ($resolvedSchedule && $resolvedSchedule->activity_date && $resolvedSchedule->start_time && $resolvedSchedule->end_time) {
            $sessionWindowStart = $resolvedSchedule->activity_date->copy()->setTimeFromTimeString($resolvedSchedule->start_time);
            $sessionWindowEnd = $resolvedSchedule->activity_date->copy()->setTimeFromTimeString($resolvedSchedule->end_time);
        }

        $latestReading = null;
        $requestedReadingId = $data['evidence_reading_id'] ?? null;

        if ($requestedReadingId) {
            $evidenceReading = TelemetryReading::with('device')->find($requestedReadingId);
            if (! $evidenceReading || ! $deviceId || (int) $evidenceReading->device_id !== (int) $deviceId) {
                throw ValidationException::withMessages([
                    'evidence_reading_id' => 'Bukti telemetri tidak valid atau tidak berasal dari meja Trainer Kit yang dialokasikan.',
                ]);
            }

            if (! $evidenceReading->recorded_at) {
                throw ValidationException::withMessages([
                    'evidence_reading_id' => 'Bukti telemetri tidak memiliki timestamp yang valid.',
                ]);
            }

            // Evidence for a scheduled session must have a timestamp inside that session.
            if ($sessionWindowStart && $sessionWindowEnd) {
                if ($evidenceReading->recorded_at->lt($sessionWindowStart) || $evidenceReading->recorded_at->gt($sessionWindowEnd)) {
                    throw ValidationException::withMessages([
                        'evidence_reading_id' => 'Bukti telemetri tidak berada dalam jendela waktu sesi praktikum yang berlaku.',
                    ]);
                }
            }

            $latestReading = $evidenceReading;
        } elseif ($deviceId) {
            // Auto-resolve latest reading, scoped to session window if available and never attach undated evidence.
            $readingQuery = TelemetryReading::with('device')
                ->where('device_id', $deviceId)
                ->whereNotNull('recorded_at');

            if ($sessionWindowStart && $sessionWindowEnd) {
                $readingQuery->where('recorded_at', '>=', $sessionWindowStart)
                    ->where('recorded_at', '<=', $sessionWindowEnd);
            }

            $latestReading = $readingQuery->latest('recorded_at')->first();
        }

        if ($latestReading) {
            $snapshot = [
                'has_telemetry' => true,
                'reading_id' => $latestReading->id,
                'device_id' => $latestReading->device_id,
                'device_code' => $latestReading->device?->code ?? 'TK-'.$latestReading->device_id,
                'ph' => $latestReading->ph !== null ? (float) $latestReading->ph : null,
                'humidity' => $latestReading->humidity !== null ? (float) $latestReading->humidity : null,
                'ec' => $latestReading->ec !== null ? (float) $latestReading->ec : null,
                'nitrogen' => $latestReading->nitrogen !== null ? (float) $latestReading->nitrogen : null,
                'phosphorus' => $latestReading->phosphorus !== null ? (float) $latestReading->phosphorus : null,
                'potassium' => $latestReading->potassium !== null ? (float) $latestReading->potassium : null,
                'temperature' => $latestReading->temperature !== null ? (float) $latestReading->temperature : null,
                'provenance' => $latestReading->provenance ?? 'unknown',
                'recorded_at' => $latestReading->recorded_at->toIso8601String(),
                'evidence_verified' => true,
            ];
            $evidenceReadingId = $latestReading->id;
        } else {
            // Jujur: Jangan rekayasa angka tiruan jika data belum ditransmisikan
            $snapshot = [
                'has_telemetry' => false,
                'message' => ! $deviceId
                    ? 'Siswa belum memiliki alokasi meja Trainer Kit praktikum.'
                    : 'Belum ada transmisi data telemetri dari Trainer Kit yang terhubung.',
                'recorded_at' => now()->toIso8601String(),
            ];
            $evidenceReadingId = null;
        }

        // 4. Atomic Transaction: Create/update report + append immutable attempt
        $revisionNotes = $data['revision_notes'] ?? ($existingReport?->revision_notes);

        return DB::transaction(function () use (
            $user, $module, $existingReport, $scheduleId, $deviceId,
            $evidenceReadingId, $data, $snapshot, $revisionNotes
        ) {
            if ($existingReport) {
                // Lock the report row to prevent concurrent duplicate attempts
                $report = StudentReport::whereKey($existingReport->id)->lockForUpdate()->firstOrFail();

                // Re-check graded status under lock (race condition guard)
                if ($report->status === 'graded') {
                    throw ValidationException::withMessages([
                        'lks_answers' => 'Laporan Lembar Kerja Siswa (LKS) ini telah dinilai oleh guru dan terkunci demi integritas asesmen akademik.',
                    ]);
                }

                $attemptCount = $report->attempt_count + 1;

                $report->update([
                    'activity_schedule_id' => $scheduleId,
                    'device_id' => $deviceId,
                    'evidence_reading_id' => $evidenceReadingId,
                    'lks_answers' => $data['lks_answers'],
                    'observation_notes' => $data['observation_notes'] ?? null,
                    'telemetry_snapshot' => $snapshot,
                    'status' => 'submitted',
                    'attempt_count' => $attemptCount,
                    'revision_notes' => $revisionNotes,
                ]);
            } else {
                $attemptCount = 1;

                $report = StudentReport::create([
                    'user_id' => $user->id,
                    'module_id' => $module->id,
                    'activity_schedule_id' => $scheduleId,
                    'device_id' => $deviceId,
                    'evidence_reading_id' => $evidenceReadingId,
                    'lks_answers' => $data['lks_answers'],
                    'observation_notes' => $data['observation_notes'] ?? null,
                    'telemetry_snapshot' => $snapshot,
                    'status' => 'submitted',
                    'attempt_count' => $attemptCount,
                    'revision_notes' => $revisionNotes,
                ]);
            }

            // Append immutable attempt record
            StudentReportAttempt::create([
                'student_report_id' => $report->id,
                'attempt_number' => $attemptCount,
                'lks_answers' => $data['lks_answers'],
                'observation_notes' => $data['observation_notes'] ?? null,
                'telemetry_snapshot' => $snapshot,
                'evidence_reading_id' => $evidenceReadingId,
                'device_id' => $deviceId,
                'activity_schedule_id' => $scheduleId,
                'submitted_at' => now(),
            ]);

            // Reload the attempts relationship
            $report->load('attempts');

            return $report;
        });
    }
}
