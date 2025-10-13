<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\QuoteController;

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

Route::get('stock', [StockController::class, 'index'])->name('stock.index');
Route::get('stock/costs', [StockController::class, 'costs'])->name('stock.costs');

Route::get('stock/adjustments/create', [StockAdjustmentController::class, 'create'])->name('stock.adjustments.create');
Route::post('stock/adjustments', [StockAdjustmentController::class, 'store'])->name('stock.adjustments.store');

Route::get('stock/transfers/create', [StockTransferController::class, 'create'])->name('stock.transfers.create');
Route::post('stock/transfers', [StockTransferController::class, 'store'])->name('stock.transfers.store');

Route::resource('quotes', App\Http\Controllers\Admin\QuoteController::class)
    ->except('show')
    ->names('quotes');

 Route::post('quotes/{quote}/send',     [\App\Http\Controllers\Admin\QuoteController::class, 'send'])->name('quotes.send');
Route::post('quotes/{quote}/approve',  [\App\Http\Controllers\Admin\QuoteController::class, 'approve'])->name('quotes.approve');
Route::post('quotes/{quote}/reject',   [\App\Http\Controllers\Admin\QuoteController::class, 'reject'])->name('quotes.reject');
Route::post('quotes/{quote}/cancel',   [\App\Http\Controllers\Admin\QuoteController::class, 'cancel'])->name('quotes.cancel');
Route::get('quotes/{quote}/send', [QuoteController::class, 'sendForm'])->name('quotes.send.form');
    Route::post('quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');   
    Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');            // ver en navegador
    Route::get('quotes/{quote}/pdf/download', [QuoteController::class, 'pdfDownload'])->name('quotes.pdf.download'); // descarga
