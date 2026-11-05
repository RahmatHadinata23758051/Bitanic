<?php

use App\Http\Controllers\Academic\ActivityScheduleController;
use App\Http\Controllers\Academic\ClassroomController;
use App\Http\Controllers\Academic\CompetencyAssessmentController;
use App\Http\Controllers\Academic\ModulePracticumController;
use App\Http\Controllers\Academic\PracticumReportController;
use App\Http\Controllers\Academic\UserDirectoryController;
use App\Http\Controllers\Admin\SettingsAuditController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Agronomy\AnalyticsController;
use App\Http\Controllers\Agronomy\PlantingMediaController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\FirstPasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IoT\IotMonitoringController;
use App\Http\Controllers\IoT\RawLogsDebuggerController;
use App\Http\Controllers\IoT\TelemetryHistoryController;
use App\Http\Controllers\IoT\TrainerKitManagerController;
// LEGACY-01: SubscriptionCheckoutController import disabled — routes retired (BR-504)
// use App\Http\Controllers\SubscriptionCheckoutController;
use Illuminate\Support\Facades\Route;

// Authentication portal
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// First Login Initial Password Change Lifecycle
Route::middleware('auth')->group(function () {
    Route::get('/password/change-initial', [FirstPasswordChangeController::class, 'edit'])->name('password.first_change');
    Route::post('/password/change-initial', [FirstPasswordChangeController::class, 'update'])->name('password.first_change.update');
});

// 1. Pusat Kendali Laboratorium Vokasi IoT (Dashboard Utama)
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// 2. Monitoring Stasiun Meja Praktik 7-in-1 Modbus & Kontrol Relai
Route::get('/iot-monitoring', [IotMonitoringController::class, 'index'])->name('iot.index');
Route::get('/iot', [IotMonitoringController::class, 'index'])->name('iot-monitoring.index');

// 3. Analitik Agronomi & Dinamika Lahan (Korelasi pH-EC & Suhu-Lengas)
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
Route::redirect('/telemetry/charts', '/analytics')->name('telemetry.charts');

// 4. Riwayat Log Telemetri & Anomaly Alert Hub
Route::get('/telemetry/history', [TelemetryHistoryController::class, 'index'])->name('telemetry.history');
Route::get('/telemetry/history/export', [TelemetryHistoryController::class, 'exportCsv'])->name('telemetry.history.export');

// 5. Kurikulum 12 Modul Praktikum Kurikulum Merdeka & Penilaian LKS
Route::get('/modules', [ModulePracticumController::class, 'index'])->name('modules.index');
Route::get('/modules/{id}', [ModulePracticumController::class, 'show'])->name('modules.show');
Route::post('/modules/{id}/submit-lks', [ModulePracticumController::class, 'submitLks'])
    ->middleware('auth')
    ->name('modules.submit-lks');
Route::post('/modules/reports/{id}/grade', [ModulePracticumController::class, 'grade'])
    ->middleware(['auth', 'role:guru,superuser'])
    ->name('modules.grade-report');
Route::post('/modules/reports/{id}/request-revision', [ModulePracticumController::class, 'requestRevision'])
    ->middleware(['auth', 'role:guru,superuser'])
    ->name('modules.request-revision');

// 6. Manajemen Jadwal Praktikum & Alokasi Meja Trainer Kit
Route::get('/schedules', [ActivityScheduleController::class, 'index'])->name('schedules.index');
Route::post('/schedules', [ActivityScheduleController::class, 'store'])
    ->middleware(['auth', 'role:guru'])
    ->name('schedules.store');
Route::delete('/schedules/{id}', [ActivityScheduleController::class, 'destroy'])
    ->middleware(['auth', 'role:guru'])
    ->name('schedules.destroy');

// 7. Hub Laporan Lembar Kerja Siswa (LKS)
Route::get('/reports', [PracticumReportController::class, 'index'])
    ->middleware('auth')
    ->name('reports.index');

// 8. Rapor Capaian Kompetensi Siswa SMK ATPH
Route::get('/assessment', [CompetencyAssessmentController::class, 'index'])
    ->middleware('auth')
    ->name('assessment.index');
Route::get('/assessment/export', [CompetencyAssessmentController::class, 'exportCsv'])
    ->middleware('auth')
    ->name('assessment.export');

// 9. Direktori Akademik Siswa & Guru Pembimbing
Route::get('/users-data', [UserDirectoryController::class, 'index'])
    ->middleware(['auth', 'role:guru,superuser'])
    ->name('users.index');

Route::middleware(['auth', 'role:guru'])->group(function () {
    Route::get('/classes', [ClassroomController::class, 'index'])->name('classes.index');
    Route::post('/classes', [ClassroomController::class, 'store'])->name('classes.store');
    Route::put('/classes/{classroom}', [ClassroomController::class, 'update'])->name('classes.update');
    Route::delete('/classes/{classroom}', [ClassroomController::class, 'destroy'])->name('classes.destroy');
    Route::post('/classes/{classroom}/groups', [ClassroomController::class, 'storeGroup'])->name('classes.groups.store');
    Route::put('/groups/{group}', [ClassroomController::class, 'updateGroup'])->name('groups.update');
    Route::delete('/groups/{group}', [ClassroomController::class, 'destroyGroup'])->name('groups.destroy');
    Route::post('/students/{student}/allocation', [ClassroomController::class, 'allocateStudent'])->name('students.allocate');
    Route::post('/users/import/preview', [UserImportController::class, 'preview'])->name('users.import.preview');
    Route::post('/users/import/execute', [UserImportController::class, 'execute'])->name('users.import.execute');
});

// 10. Manajemen Armada Trainer Kit Hardware & Kalibrasi Sensor
Route::get('/trainer-kits', [TrainerKitManagerController::class, 'index'])->name('kits.index');
Route::post('/trainer-kits/{device}/reboot', [TrainerKitManagerController::class, 'reboot'])
    ->middleware(['auth', 'role:guru'])
    ->name('kits.reboot');
Route::post('/trainer-kits/{device}/calibration', [TrainerKitManagerController::class, 'updateCalibration'])
    ->middleware(['auth', 'role:guru'])
    ->name('kits.calibration');
Route::post('/trainer-kits/{device}/api-key/rotate', [TrainerKitManagerController::class, 'rotateApiKey'])
    ->middleware(['auth', 'role:superuser'])
    ->name('kits.api-key.rotate');

// 11. Raw Modbus RS485 Hex Stream Debugger
Route::get('/raw-logs', [RawLogsDebuggerController::class, 'index'])->name('rawlogs.index');
Route::get('/rawlogs', [RawLogsDebuggerController::class, 'index'])->name('raw-logs.index');
Route::post('/raw-logs/inject', [RawLogsDebuggerController::class, 'inject'])
    ->middleware(['auth', 'role:superuser'])
    ->name('rawlogs.inject');

// 12. Pengaturan Laboratorium & Log Aktivitas (Audit Trail)
Route::get('/settings', [SettingsAuditController::class, 'index'])
    ->middleware(['auth', 'role:superuser'])
    ->name('settings.index');
Route::post('/settings/school', [SettingsAuditController::class, 'updateSchool'])
    ->middleware(['auth', 'role:superuser'])
    ->name('settings.update');

// Manajemen Media Tanam (Null-Safe)
Route::resource('media', PlantingMediaController::class)->except(['show']);

// LEGACY-01: Retired SaaS module — routes disabled, historic data preserved.
// Route::get('/subscription', [SubscriptionCheckoutController::class, 'index'])->name('subscription.index');
// Route::post('/subscription/checkout', [SubscriptionCheckoutController::class, 'checkout'])->name('subscription.checkout');
// Midtrans webhook callbacks in routes/api.php remain available to reconcile in-flight payments.
