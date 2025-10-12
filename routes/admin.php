<?php

use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'admin.dashboard')->name('dashboard');

Route::resource('categories', CategoryController::class)->except(['show']);

Route::resource('products', \App\Http\Controllers\Admin\ProductController::class)->except(['show']);

Route::post('products/{product}/despiece', [\App\Http\Controllers\Admin\ProductController::class, 'despiece'])
    ->name('products.despiece');