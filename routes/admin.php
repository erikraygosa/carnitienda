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
use App\Http\Controllers\Admin\SalesOrderController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\DriverCashRegisterController;


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
 Route::resource('sales-orders', SalesOrderController::class);

    // PDF
    Route::get ('sales-orders/{order}/pdf',           [SalesOrderController::class,'pdf'])->name('sales-orders.pdf');
    Route::get ('sales-orders/{order}/pdf/download',  [SalesOrderController::class,'pdfDownload'])->name('sales-orders.pdf.download');

    // Envío (form + acción)
    Route::get ('sales-orders/{order}/send',          [SalesOrderController::class,'sendForm'])->name('sales-orders.send.form');
    Route::post('sales-orders/{order}/send',          [SalesOrderController::class,'send'])->name('sales-orders.send');

    // Acciones de estado (flujo logístico)
    Route::post('sales-orders/{order}/approve',       [SalesOrderController::class,'approve'])->name('sales-orders.approve');
    Route::post('sales-orders/{order}/preparar',      [SalesOrderController::class,'startPreparing'])->name('sales-orders.prepare');
    Route::post('sales-orders/{order}/procesar',      [SalesOrderController::class,'process'])->name('sales-orders.process');
    Route::post('sales-orders/{order}/en-ruta',       [SalesOrderController::class,'dispatchToRoute'])->name('sales-orders.en-ruta');
    Route::post('sales-orders/{order}/entregar',      [SalesOrderController::class,'deliver'])->name('sales-orders.deliver');
    Route::post('sales-orders/{order}/no-entregado',  [SalesOrderController::class,'notDelivered'])->name('sales-orders.not-delivered');
    Route::post('sales-orders/{order}/cobrar',        [SalesOrderController::class,'recordCash'])->name('sales-orders.cobrar');
    Route::post('sales-orders/{order}/liquidar',      [SalesOrderController::class,'settleDriver'])->name('sales-orders.liquidar');
    Route::post('sales-orders/{order}/cancelar',      [SalesOrderController::class,'cancel'])->name('sales-orders.cancel');


    // =========================
    // Sales (Notas de venta)
    // =========================
    Route::resource('sales', SaleController::class)->except(['show']);

    // PDF
    Route::get ('sales/{sale}/pdf',                   [SaleController::class,'pdf'])->name('sales.pdf');
    Route::get ('sales/{sale}/pdf/download',          [SaleController::class,'pdfDownload'])->name('sales.pdf.download');

    // Envío (form + acción)
    Route::get ('sales/{sale}/send',                  [SaleController::class,'sendForm'])->name('sales.send.form');
    Route::post('sales/{sale}/send',                  [SaleController::class,'send'])->name('sales.send');

    // Acciones de estado (flujo logístico)
    Route::post('sales/{sale}/approve',               [SaleController::class,'approve'])->name('sales.approve');
    Route::post('sales/{sale}/preparar',              [SaleController::class,'startPreparing'])->name('sales.prepare');
    Route::post('sales/{sale}/procesar',              [SaleController::class,'process'])->name('sales.process');
    Route::post('sales/{sale}/en-ruta',               [SaleController::class,'dispatchToRoute'])->name('sales.en-ruta');
    Route::post('sales/{sale}/entregar',              [SaleController::class,'deliver'])->name('sales.deliver');
    Route::post('sales/{sale}/no-entregado',          [SaleController::class,'notDelivered'])->name('sales.not-delivered');
    Route::post('sales/{sale}/cobrar',                [SaleController::class,'recordCash'])->name('sales.cobrar');
    Route::post('sales/{sale}/liquidar',              [SaleController::class,'settleDriver'])->name('sales.liquidar');
    Route::post('sales/{sale}/cancelar',              [SaleController::class,'cancel'])->name('sales.cancel');

    Route::post('sales/{sale}/close', [SaleController::class, 'close'])->name('sales.close');
Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');

Route::resource('invoices', InvoiceController::class)->except(['destroy'])->names('invoices');

// Acciones CFDI
Route::post('invoices/{invoice}/stamp',   [InvoiceController::class, 'stamp'])->name('invoices.stamp');
Route::post('invoices/{invoice}/cancel',  [InvoiceController::class, 'cancel'])->name('invoices.cancel');
Route::get ('invoices/{invoice}/pdf',     [InvoiceController::class, 'pdf'])->name('invoices.pdf');
Route::get ('invoices/{invoice}/download',[InvoiceController::class, 'download'])->name('invoices.download');

// Envío
Route::get ('invoices/{invoice}/send',    [InvoiceController::class, 'sendForm'])->name('invoices.send.form');
Route::post('invoices/{invoice}/send',    [InvoiceController::class, 'send'])->name('invoices.send');

// Generación desde Pedido / Venta / Directa
Route::post('sales-orders/{order}/invoice', [InvoiceController::class, 'fromSalesOrder'])->name('invoices.from-order');
Route::post('sales/{sale}/invoice',         [InvoiceController::class, 'fromSale'])->name('invoices.from-sale');

Route::resource('dispatches', DispatchController::class)->except(['show']);

    // Acciones de flujo
    Route::post('dispatches/{dispatch}/preparar', [DispatchController::class,'preparar'])->name('dispatches.preparar');
    Route::post('dispatches/{dispatch}/cargar',   [DispatchController::class,'cargar'])->name('dispatches.cargar');
    Route::post('dispatches/{dispatch}/en-ruta',  [DispatchController::class,'enRuta'])->name('dispatches.enruta');
    Route::post('dispatches/{dispatch}/entregar', [DispatchController::class,'entregar'])->name('dispatches.entregar');
    Route::post('dispatches/{dispatch}/cerrar',   [DispatchController::class,'cerrar'])->name('dispatches.cerrar');
    Route::post('dispatches/{dispatch}/cancelar', [DispatchController::class,'cancelar'])->name('dispatches.cancelar');

 Route::prefix('driver-cash')->name('driver-cash.')->group(function () {
    Route::get('/',                 [DriverCashRegisterController::class,'index'])->name('index');
    Route::get('/create',           [DriverCashRegisterController::class,'create'])->name('create');
    Route::post('/',                [DriverCashRegisterController::class,'store'])->name('store');
    Route::get('/{register}',       [DriverCashRegisterController::class,'show'])->name('show');
    Route::post('/{register}/abono',[DriverCashRegisterController::class,'abono'])->name('abono');
    Route::post('/{register}/close',[DriverCashRegisterController::class,'close'])->name('close');
});   