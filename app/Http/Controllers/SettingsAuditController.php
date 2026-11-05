<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsAuditController extends Controller
{
    /**
     * Resolve the authenticated user's school_id or abort 403.
     * Fail-closed: no fallback to a default school.
     */
    private function resolveSchoolIdOrFail(): int
    {
        $user = Auth::user();

        if (! $user || ! $user->school_id) {
            abort(403, 'Akses ditolak: akun Anda belum terdaftar pada sekolah manapun.');
        }

        $school = $user->school;
        if (! $school || $school->status !== 'active') {
            abort(403, 'Akses ditolak: sekolah Anda tidak aktif atau belum terdaftar.');
        }

        return (int) $school->id;
    }

    /**
     * Display the laboratory settings and audit trail view.
     */
    public function index(): View
    {
        $schoolId = $this->resolveSchoolIdOrFail();

        $setting = SchoolSetting::where('school_id', $schoolId)->first();

        if (! $setting) {
            $setting = (object) [
                'school_id' => $schoolId,
                'school_name' => Auth::user()->school?->name ?? 'Belum Dikonfigurasi',
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'course_name' => 'Agribisnis Tanaman Pangan & Hortikultura (ATPH)',
                'lead_teacher' => '-',
                'sensor_thresholds' => [
                    'minMoisture' => 30.0,
                    'maxMoisture' => 75.0,
                    'minPH' => 5.5,
                    'maxPH' => 7.2,
                    'minEC' => 800,
                    'maxEC' => 2200,
                    'minNitrogen' => 40.0,
                    'maxNitrogen' => 120.0,
                ],
                'audio_alarm_enabled' => true,
                'auto_sync_interval_sec' => 3,
            ];
        }

        $auditLogs = AuditLog::with('user')
            ->where('school_id', $schoolId)
            ->latest('id')
            ->paginate(15);

        return view('pages.settings.index', [
            'setting' => $setting,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Update school profile and agronomy thresholds.
     */
    public function updateSchool(Request $request): RedirectResponse
    {
        $schoolId = $this->resolveSchoolIdOrFail();

        $validated = $request->validate([
            'school_name' => 'required|string|max:255',
            'academic_year' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'course_name' => 'required|string|max:255',
            'lead_teacher' => 'required|string|max:255',
            'thresholds.minMoisture' => 'required|numeric|min:0|max:100',
            'thresholds.maxMoisture' => 'required|numeric|min:0|max:100|gte:thresholds.minMoisture',
            'thresholds.minPH' => 'required|numeric|min:0|max:14',
            'thresholds.maxPH' => 'required|numeric|min:0|max:14|gte:thresholds.minPH',
            'thresholds.minEC' => 'required|numeric|min:0|max:20000',
            'thresholds.maxEC' => 'required|numeric|min:0|max:20000|gte:thresholds.minEC',
            'thresholds.minNitrogen' => 'required|numeric|min:0|max:1000',
            'thresholds.maxNitrogen' => 'required|numeric|min:0|max:1000|gte:thresholds.minNitrogen',
        ], [
            'thresholds.maxMoisture.gte' => 'Lengas tanah maksimum harus lebih besar atau sama dengan minimum.',
            'thresholds.maxPH.gte' => 'Derajat keasaman (pH) maksimum harus lebih besar atau sama dengan minimum.',
            'thresholds.maxEC.gte' => 'Konduktivitas listrik (EC) maksimum harus lebih besar atau sama dengan minimum.',
            'thresholds.maxNitrogen.gte' => 'Kadar Nitrogen maksimum harus lebih besar atau sama dengan minimum.',
        ]);

        $user = Auth::user();

        return DB::transaction(function () use ($schoolId, $validated, $user, $request) {
            $setting = SchoolSetting::firstOrNew(['school_id' => $schoolId]);
            $before = $setting->exists ? [
                'school_name' => $setting->school_name,
                'academic_year' => $setting->academic_year,
                'semester' => $setting->semester,
                'course_name' => $setting->course_name,
                'lead_teacher' => $setting->lead_teacher,
                'sensor_thresholds' => $setting->sensor_thresholds,
            ] : null;

            $setting->school_name = $validated['school_name'];
            $setting->academic_year = $validated['academic_year'];
            $setting->semester = $validated['semester'];
            $setting->course_name = $validated['course_name'];
            $setting->lead_teacher = $validated['lead_teacher'];
            $setting->sensor_thresholds = $validated['thresholds'];
            $setting->save();

            $after = [
                'school_name' => $setting->school_name,
                'academic_year' => $setting->academic_year,
                'semester' => $setting->semester,
                'course_name' => $setting->course_name,
                'lead_teacher' => $setting->lead_teacher,
                'sensor_thresholds' => $setting->sensor_thresholds,
            ];

            $diff = [
                'before' => $before ? self::redactSensitive($before) : null,
                'after' => self::redactSensitive($after),
            ];

            $correlationId = (string) Str::uuid();

            AuditLog::create([
                'school_id' => $schoolId,
                'user_id' => $user?->id,
                'actor_name' => $user?->name ?? 'Admin Laboratorium',
                'actor_role' => $user?->role ?? 'superuser',
                'action' => 'UPDATE_SETTINGS',
                'target_type' => 'SchoolSetting',
                'target_id' => (string) $setting->id,
                'diff' => $diff,
                'correlation_id' => $correlationId,
                'details' => 'Memperbarui profil laboratorium sekolah dan ambang batas sensor tanah.',
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('settings.index')
                ->with('success', 'Konfigurasi profil laboratorium dan ambang batas agronomi berhasil diperbarui.');
        });
    }

    /**
     * Recursively redact sensitive fields from audit diff data.
     */
    private static function redactSensitive(array $data): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'key', 'device_key', 'api_key', 'credential', 'auth'];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::redactSensitive($v);
            } else {
                foreach ($sensitiveKeys as $sensitive) {
                    if (stripos((string) $k, $sensitive) !== false) {
                        $data[$k] = '[REDACTED]';
                        break;
                    }
                }
            }
        }

        return $data;
    }
}
