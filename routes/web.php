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

// El scaffolding de Jetstream registraba /dashboard mostrando su vista de
// bienvenida por defecto; la app real vive en /admin, así que se redirige.
Route::redirect('/dashboard', '/admin');
