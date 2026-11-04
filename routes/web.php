<?php

use App\Http\Controllers\PlantingMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('media', PlantingMediaController::class);
