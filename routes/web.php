<?php

use App\Http\Controllers\ActivityScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModulePracticumController;
use App\Http\Controllers\PlantingMediaController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\TelemetryHistoryController;
use Illuminate\Support\Facades\Route;

// Live IoT Monitoring Dashboard (Bento Grid & 3D Flip Card Glosarium)
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Manajemen Media Tanam (Null-Safe)
Route::resource('media', PlantingMediaController::class);

// Rute Placeholder untuk Menu Navigasi SMK Pertanian
Route::get('/telemetry/charts', [DashboardController::class, 'index'])->name('telemetry.charts');
// Riwayat Log Telemetri & Anomaly Alert Hub (Tanpa jQuery)
Route::get('/telemetry/history', [TelemetryHistoryController::class, 'index'])->name('telemetry.history');
Route::get('/telemetry/history/export', [TelemetryHistoryController::class, 'exportCsv'])->name('telemetry.history.export');
// Modul Kurikulum Praktikum 12 Pertemuan SMK Pertanian & Rubrik Penilaian
Route::get('/modules', [ModulePracticumController::class, 'index'])->name('modules.index');
Route::get('/modules/{id}', [ModulePracticumController::class, 'show'])->name('modules.show');
Route::post('/modules/{id}/submit-lks', [ModulePracticumController::class, 'submitLks'])->name('modules.submit-lks');
Route::post('/modules/reports/{id}/grade', [ModulePracticumController::class, 'grade'])->name('modules.grade-report');
// Manajemen Jadwal Praktikum & Alokasi Meja Trainer Kit
Route::get('/schedules', [ActivityScheduleController::class, 'index'])->name('schedules.index');
Route::post('/schedules', [ActivityScheduleController::class, 'store'])->name('schedules.store');
Route::delete('/schedules/{id}', [ActivityScheduleController::class, 'destroy'])->name('schedules.destroy');
Route::get('/reports', function () {
    return view('pages.reports.index');
})->name('reports.index');
Route::get('/trainer-kits', function () {
    return view('pages.kits.index');
})->name('kits.index');
// Paket Langganan & Midtrans Snap Checkout
Route::get('/subscription', [SubscriptionCheckoutController::class, 'index'])->name('subscription.index');
Route::post('/subscription/checkout', [SubscriptionCheckoutController::class, 'checkout'])->name('subscription.checkout');
