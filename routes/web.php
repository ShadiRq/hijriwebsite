<?php

use App\Http\Controllers\HijriController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/hijri', [HijriController::class, 'show']);
Route::get('/hijri.json', [HijriController::class, 'json']);
