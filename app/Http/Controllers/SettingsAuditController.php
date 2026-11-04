<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsAuditController extends Controller
{
    /**
     * Display the laboratory settings and audit trail view.
     */
    public function index(): View
    {
        $setting = SchoolSetting::first() ?? (object) [
            'school_name' => 'SMK Pertanian Pembangunan Negeri 1 Sukaraja',
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

        $auditLogs = AuditLog::with('user')->latest()->paginate(15);

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
        $validated = $request->validate([
            'school_name' => 'required|string|max:255',
            'academic_year' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'course_name' => 'required|string|max:255',
            'lead_teacher' => 'required|string|max:255',
            'thresholds.minMoisture' => 'required|numeric',
            'thresholds.maxMoisture' => 'required|numeric',
            'thresholds.minPH' => 'required|numeric',
            'thresholds.maxPH' => 'required|numeric',
            'thresholds.minEC' => 'required|numeric',
            'thresholds.maxEC' => 'required|numeric',
            'thresholds.minNitrogen' => 'required|numeric',
            'thresholds.maxNitrogen' => 'required|numeric',
        ]);

        $setting = SchoolSetting::first();
        if (!$setting) {
            $setting = new SchoolSetting();
        }

        $setting->school_name = $validated['school_name'];
        $setting->academic_year = $validated['academic_year'];
        $setting->semester = $validated['semester'];
        $setting->course_name = $validated['course_name'];
        $setting->lead_teacher = $validated['lead_teacher'];
        $setting->sensor_thresholds = $validated['thresholds'];
        $setting->save();

        // Record audit trail
        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Admin Laboratorium',
            'actor_role' => $user?->role ?? 'superuser',
            'action' => 'UPDATE_SETTINGS',
            'details' => 'Memperbarui profil laboratorium sekolah dan ambang batas sensor tanah.',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'Konfigurasi profil laboratorium dan ambang batas agronomi berhasil diperbarui.');
    }
}
