<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\PurchaseController;

Route::view('/', 'admin.dashboard')->name('dashboard');

Route::resource('categories', CategoryController::class)->except(['show']);

Route::resource('products', ProductController::class)->except(['show']);
Route::post('products/{product}/despiece', [ProductController::class, 'despiece'])
    ->name('products.despiece');

Route::resource('clients', ClientController::class)->names('clients');
Route::resource('providers', ProviderController::class)->names('providers');
Route::resource('warehouses', WarehouseController::class)->names('warehouses');

/* Órdenes de compra */
Route::resource('purchase-orders', PurchaseOrderController::class)
    ->except('show')
    ->names('purchase-orders');

// Acciones de estado
Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])
    ->name('purchase-orders.approve');

Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])
    ->name('purchase-orders.cancel');

Route::resource('purchases', PurchaseController::class)
    ->except('show')
    ->names('purchases');

# Acciones de estado
Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])
    ->name('purchases.receive');

Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])
    ->name('purchases.cancel');    