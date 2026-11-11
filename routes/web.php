<?php

use App\Http\Controllers\Academic\ActivityScheduleController;
use App\Http\Controllers\Academic\ClassroomController;
use App\Http\Controllers\Academic\CompetencyAssessmentController;
use App\Http\Controllers\Academic\ModulePracticumController;
use App\Http\Controllers\Academic\PracticumReportController;
use App\Http\Controllers\Academic\UserDirectoryController;
use App\Http\Controllers\Admin\MasterLicenseController;
use App\Http\Controllers\Admin\MasterTrainerKitController;
use App\Http\Controllers\Admin\RegionMasterController;
use App\Http\Controllers\Admin\SchoolLicenseController;
use App\Http\Controllers\Admin\SchoolMasterController;
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

Route::middleware(['auth', 'role:admin,admin_sekolah,superuser'])->group(function () {
    Route::post('/users', [UserDirectoryController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserDirectoryController::class, 'update'])->name('users.update');
    Route::match(['PATCH', 'POST'], '/users/{user}/toggle-status', [UserDirectoryController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::delete('/users/{user}', [UserDirectoryController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/import/preview', [UserImportController::class, 'preview'])->name('users.import.preview');
    Route::post('/users/import/execute', [UserImportController::class, 'execute'])->name('users.import.execute');
});

Route::middleware(['auth', 'role:guru'])->group(function () {
    Route::get('/classes', [ClassroomController::class, 'index'])->name('classes.index');
    Route::post('/classes', [ClassroomController::class, 'store'])->name('classes.store');
    Route::put('/classes/{classroom}', [ClassroomController::class, 'update'])->name('classes.update');
    Route::delete('/classes/{classroom}', [ClassroomController::class, 'destroy'])->name('classes.destroy');
    Route::post('/classes/{classroom}/groups', [ClassroomController::class, 'storeGroup'])->name('classes.groups.store');
    Route::put('/groups/{group}', [ClassroomController::class, 'updateGroup'])->name('groups.update');
    Route::delete('/groups/{group}', [ClassroomController::class, 'destroyGroup'])->name('groups.destroy');
    Route::post('/students/{student}/allocation', [ClassroomController::class, 'allocateStudent'])->name('students.allocate');
    Route::post('/classes/academic-year', [ClassroomController::class, 'updateAcademicSettings'])->name('classes.academic-year.update');
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
    ->middleware(['auth', 'role:superuser,admin'])
    ->name('settings.index');
Route::post('/settings/school', [SettingsAuditController::class, 'updateSchool'])
    ->middleware(['auth', 'role:superuser,admin'])
    ->name('settings.update');

// Manajemen Media Tanam (Null-Safe & Tenant-Isolated)
Route::middleware(['auth', 'role:guru,superuser'])->group(function () {
    Route::resource('media', PlantingMediaController::class)->except(['show']);
});

// 13. Superuser Platform Data Master (MASTER-03-01 & MASTER-03-02)
Route::middleware(['auth', 'role:superuser'])->prefix('master')->name('master.')->group(function () {
    // Master Wilayah (MASTER-03-01)
    Route::get('/regions', [RegionMasterController::class, 'index'])->name('regions.index');
    Route::post('/regions/sync', [RegionMasterController::class, 'sync'])->name('regions.sync');

    // Master Sekolah (MASTER-03-02)
    Route::get('/schools', [SchoolMasterController::class, 'index'])->name('schools.index');
    Route::get('/schools/create', [SchoolMasterController::class, 'create'])->name('schools.create');
    Route::post('/schools', [SchoolMasterController::class, 'store'])->name('schools.store');
    Route::get('/schools/{school}', [SchoolMasterController::class, 'show'])->name('schools.show');
    Route::get('/schools/{school}/edit', [SchoolMasterController::class, 'edit'])->name('schools.edit');
    Route::put('/schools/{school}', [SchoolMasterController::class, 'update'])->name('schools.update');
    Route::match(['PATCH', 'POST'], '/schools/{school}/toggle-status', [SchoolMasterController::class, 'toggleStatus'])->name('schools.toggle-status');
    Route::delete('/schools/{school}', [SchoolMasterController::class, 'destroy'])->name('schools.destroy');

    // Master Trainer Kit Inventory & Activation (DEVICE-04-01 & DEVICE-04-02)
    Route::get('/trainer-kits', [MasterTrainerKitController::class, 'index'])->name('trainer-kits.index');
    Route::post('/trainer-kits', [MasterTrainerKitController::class, 'store'])->name('trainer-kits.store');
    Route::get('/trainer-kits/{inventory}', [MasterTrainerKitController::class, 'show'])->name('trainer-kits.show');
    Route::put('/trainer-kits/{inventory}', [MasterTrainerKitController::class, 'update'])->name('trainer-kits.update');
    Route::post('/trainer-kits/{inventory}/sale', [MasterTrainerKitController::class, 'recordSale'])->name('trainer-kits.sale');
    Route::post('/trainer-kits/{inventory}/assign', [MasterTrainerKitController::class, 'assignDevice'])->name('trainer-kits.assign');
    Route::post('/trainer-kits/{inventory}/activate', [MasterTrainerKitController::class, 'activate'])->name('trainer-kits.activate');
    Route::post('/trainer-kits/{inventory}/handover', [MasterTrainerKitController::class, 'recordHandover'])->name('trainer-kits.handover');
    Route::post('/trainer-kits/{inventory}/warranty/correct', [MasterTrainerKitController::class, 'correctWarranty'])->name('trainer-kits.warranty.correct');
    Route::delete('/trainer-kits/{inventory}', [MasterTrainerKitController::class, 'destroy'])->name('trainer-kits.destroy');

    // Master Lisensi LMS Sekolah (LICENSE-05-01 & LICENSE-05-02)
    Route::get('/licenses', [MasterLicenseController::class, 'index'])->name('licenses.index');
    Route::post('/licenses', [MasterLicenseController::class, 'store'])->name('licenses.store');
    Route::get('/licenses/{license}', [MasterLicenseController::class, 'show'])->name('licenses.show');
    Route::post('/licenses/{license}/activate', [MasterLicenseController::class, 'activate'])->name('licenses.activate');
    Route::post('/licenses/{license}/renew', [MasterLicenseController::class, 'renew'])->name('licenses.renew');
    Route::post('/licenses/{license}/correct', [MasterLicenseController::class, 'correct'])->name('licenses.correct');
});

// 14. Lisensi LMS Sekolah (Read-Only Tenant Visibility & Expiration Notice)
Route::get('/school/license', [SchoolLicenseController::class, 'show'])
    ->middleware(['auth', 'role:superuser,admin'])
    ->name('school.license.show');
Route::get('/school/license-expired', [SchoolLicenseController::class, 'expired'])
    ->middleware('auth')
    ->name('school.license.expired');

// Cascading region options accessible to authenticated users (e.g. for dynamic form selects)
Route::middleware(['auth'])->prefix('api/regions')->name('api.regions.')->group(function () {
    Route::get('/provinces', [RegionMasterController::class, 'provinces'])->name('provinces');
    Route::get('/regencies', [RegionMasterController::class, 'regencies'])->name('regencies');
    Route::get('/districts', [RegionMasterController::class, 'districts'])->name('districts');
});

// LEGACY-01: Retired SaaS module — routes disabled, historic data preserved.
// Route::get('/subscription', [SubscriptionCheckoutController::class, 'index'])->name('subscription.index');
// Route::post('/subscription/checkout', [SubscriptionCheckoutController::class, 'checkout'])->name('subscription.checkout');
// Midtrans webhook callbacks in routes/api.php remain available to reconcile in-flight payments.
