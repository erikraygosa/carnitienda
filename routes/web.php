<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperDashboard;
use App\Http\Controllers\SuperAdmin\PacController as SuperPac;
use App\Http\Controllers\SuperAdmin\CompanyController as SuperCompany;
use App\Http\Controllers\SuperAdmin\SeriesController as SuperSeries;
use App\Http\Controllers\SuperAdmin\SettingsController as SuperSettings;

Route::redirect('/', '/admin/');

// Route::get('/', function () {
//     return view('welcome');
// });

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
