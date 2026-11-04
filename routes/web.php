<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModulePracticumController;
use App\Http\Controllers\PlantingMediaController;
use Illuminate\Support\Facades\Route;

// Live IoT Monitoring Dashboard (Bento Grid & 3D Flip Card Glosarium)
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Manajemen Media Tanam (Null-Safe)
Route::resource('media', PlantingMediaController::class);

// Rute Placeholder untuk Menu Navigasi SMK Pertanian
Route::get('/telemetry/charts', [DashboardController::class, 'index'])->name('telemetry.charts');
Route::get('/telemetry/history', [DashboardController::class, 'index'])->name('telemetry.history');
// Modul Kurikulum Praktikum 12 Pertemuan SMK Pertanian & Rubrik Penilaian
Route::get('/modules', [ModulePracticumController::class, 'index'])->name('modules.index');
Route::get('/modules/{id}', [ModulePracticumController::class, 'show'])->name('modules.show');
Route::post('/modules/{id}/submit-lks', [ModulePracticumController::class, 'submitLks'])->name('modules.submit-lks');
Route::post('/modules/reports/{id}/grade', [ModulePracticumController::class, 'grade'])->name('modules.grade-report');
Route::get('/schedules', function () {
    return view('pages.schedules.index');
})->name('schedules.index');
Route::get('/reports', function () {
    return view('pages.reports.index');
})->name('reports.index');
Route::get('/trainer-kits', function () {
    return view('pages.kits.index');
})->name('kits.index');
Route::get('/subscription', function () {
    return view('pages.subscription.index');
})->name('subscription.index');
