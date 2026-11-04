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

        if ($user->role !== 'superuser' && ! $user->school_id) {
            throw ValidationException::withMessages([
                'lks_answers' => 'Pengumpulan laporan memerlukan asosiasi sekolah yang valid.',
            ]);
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
        $latestReading = null;
        $requestedReadingId = $data['evidence_reading_id'] ?? null;

        if ($requestedReadingId) {
            $evidenceReading = TelemetryReading::with('device')->find($requestedReadingId);
            if (! $evidenceReading || ! $deviceId || (int) $evidenceReading->device_id !== (int) $deviceId) {
                throw ValidationException::withMessages([
                    'evidence_reading_id' => 'Bukti telemetri tidak valid atau tidak berasal dari meja Trainer Kit yang dialokasikan.',
                ]);
            }
            $latestReading = $evidenceReading;
        } elseif ($deviceId) {
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
                'device_code' => $latestReading->device?->code ?? 'TK-'.$latestReading->device_id,
                'ph' => (float) $latestReading->ph,
                'humidity' => (float) $latestReading->humidity,
                'ec' => (float) $latestReading->ec,
                'nitrogen' => (float) $latestReading->nitrogen,
                'phosphorus' => (float) $latestReading->phosphorus,
                'potassium' => (float) $latestReading->potassium,
                'temperature' => (float) $latestReading->temperature,
                'provenance' => $latestReading->provenance ?? 'hardware',
                'recorded_at' => $latestReading->recorded_at?->toIso8601String(),
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
