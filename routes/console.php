<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bitanic:check-license-reminders')
    ->dailyAt('07:00')
    ->description('Periksa dan kirim email pengingat H-14 lisensi LMS sekolah');
