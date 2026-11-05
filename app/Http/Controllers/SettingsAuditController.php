<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsAuditController extends Controller
{
    /**
     * Display the laboratory settings and audit trail view.
     */
    public function index(): View
    {
        $user = Auth::user();
        $schoolId = $user?->school_id
            ?? School::where('slug', 'smk-ppn-1-sukaraja')->value('id')
            ?? School::value('id');

        $setting = null;
        if ($schoolId) {
            $setting = SchoolSetting::where('school_id', $schoolId)->first();
        }

        if (! $setting) {
            $school = $schoolId ? School::find($schoolId) : null;
            $setting = (object) [
                'school_id' => $schoolId,
                'school_name' => $school?->name ?? 'SMK Pertanian Pembangunan Negeri 1 Sukaraja',
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'course_name' => 'Agribisnis Tanaman Pangan & Hortikultura (ATPH)',
                'lead_teacher' => 'Ir. Ahmad Suryanto, S.P., M.Pd.',
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

        $auditQuery = AuditLog::with('user');
        if ($user && $user->school_id) {
            $auditQuery->where('school_id', $user->school_id);
        }

        $auditLogs = $auditQuery->latest('id')->paginate(15);

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
        $user = Auth::user();
        $schoolId = $user?->school_id
            ?? School::where('slug', 'smk-ppn-1-sukaraja')->value('id')
            ?? School::value('id');

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

        $redactSensitive = function (array $data) use (&$redactSensitive) {
            $sensitiveKeys = ['password', 'secret', 'token', 'key', 'device_key', 'api_key', 'credential', 'auth'];
            foreach ($data as $k => $v) {
                if (is_array($v)) {
                    $data[$k] = $redactSensitive($v);
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
        };

        $diff = [
            'before' => $before ? $redactSensitive($before) : null,
            'after' => $redactSensitive($after),
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
    }
}
