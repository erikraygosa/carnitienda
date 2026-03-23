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
use App\Http\Controllers\Admin\AccountsReceivableController;
use App\Http\Controllers\Admin\ArPaymentsController;
use App\Http\Controllers\Admin\CashRegisterController;
use App\Http\Controllers\Admin\CashMovementController;
use App\Http\Controllers\Admin\POSController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CompanyController;



Route::get('/', [DashboardController::class, 'index'])
    ->name('dashboard');

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
Route::get('stock/kardex', [StockController::class, 'kardex'])->name('stock.kardex');

Route::get('stock/adjustments/create', [StockAdjustmentController::class, 'create'])->name('stock.adjustments.create');
Route::post('stock/adjustments', [StockAdjustmentController::class, 'store'])->name('stock.adjustments.store');

Route::get('stock/transfers/create', [StockTransferController::class, 'create'])->name('stock.transfers.create');
Route::post('stock/transfers', [StockTransferController::class, 'store'])->name('stock.transfers.store');

Route::resource('quotes', QuoteController::class)
    ->except('show')
    ->names('quotes');

Route::get ('quotes/{quote}/send',          [QuoteController::class, 'sendForm'])->name('quotes.send.form');
Route::post('quotes/{quote}/send',          [QuoteController::class, 'send'])->name('quotes.send');
Route::post('quotes/{quote}/approve',       [QuoteController::class, 'approve'])->name('quotes.approve');
Route::post('quotes/{quote}/reject',        [QuoteController::class, 'reject'])->name('quotes.reject');
Route::post('quotes/{quote}/cancel',        [QuoteController::class, 'cancel'])->name('quotes.cancel');
Route::get ('quotes/{quote}/pdf',           [QuoteController::class, 'pdf'])->name('quotes.pdf');
Route::get ('quotes/{quote}/pdf/download',  [QuoteController::class, 'pdfDownload'])->name('quotes.pdf.download');
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


    Route::post('/{dispatch}/traspasos/{assignment}/completar',
    [DispatchController::class, 'completarTraspaso'])->name('admin.dispatches.traspasos.completar');
Route::post('/{dispatch}/traspasos/{assignment}/no-completar',
    [DispatchController::class, 'noCompletarTraspaso'])->name('admin.dispatches.traspasos.no-completar');
    // Traspasos en despacho
Route::post('dispatches/{dispatch}/traspasos/{assignment}/completar',
    [DispatchController::class, 'completarTraspaso'])->name('dispatches.traspasos.completar');
Route::post('dispatches/{dispatch}/traspasos/{assignment}/no-completar',
    [DispatchController::class, 'noCompletarTraspaso'])->name('dispatches.traspasos.no-completar');
    Route::post('dispatches/{dispatch}/traspasos/bulk', [DispatchController::class, 'bulkTraspasos'])->name('dispatches.traspasos.bulk');
Route::post('dispatches/{dispatch}/pedidos/bulk',   [DispatchController::class, 'bulkPedidos'])->name('dispatches.pedidos.bulk');
Route::post('dispatches/{dispatch}/cxc/bulk',       [DispatchController::class, 'bulkCxc'])->name('dispatches.cxc.bulk');


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

Route::prefix('driver-cash')->name('driver-cash.')->group(function () {
    Route::get('/',                  [DriverCashRegisterController::class,'index'])->name('index');
    Route::get('/create',            [DriverCashRegisterController::class,'create'])->name('create');
    Route::post('/',                 [DriverCashRegisterController::class,'store'])->name('store');
    Route::get('/{register}',        [DriverCashRegisterController::class,'show'])->name('show');
    Route::post('/{register}/abono', [DriverCashRegisterController::class,'abono'])->name('abono');
    Route::post('/{register}/close', [DriverCashRegisterController::class,'close'])->name('close');
});

Route::resource('dispatches', DispatchController::class)->except(['show']);
 
// Acciones de flujo del despacho
Route::post('dispatches/{dispatch}/preparar',  [DispatchController::class,'preparar'])->name('dispatches.preparar');
Route::post('dispatches/{dispatch}/cargar',    [DispatchController::class,'cargar'])->name('dispatches.cargar');
Route::post('dispatches/{dispatch}/en-ruta',   [DispatchController::class,'enRuta'])->name('dispatches.enruta');
Route::post('dispatches/{dispatch}/entregar',  [DispatchController::class,'entregar'])->name('dispatches.entregar');
Route::post('dispatches/{dispatch}/cancelar',  [DispatchController::class,'cancelar'])->name('dispatches.cancelar');
Route::post('dispatches/{dispatch}/cerrar',    [DispatchController::class,'cerrar'])->name('dispatches.cerrar');
Route::post('dispatches/{dispatch}/cxc/{assignment}/no-cobrar', [DispatchController::class,'noCobrarCxc'])->name('dispatches.cxc.no-cobrar'); 

// Acciones sobre pedidos individuales dentro del despacho
Route::post('dispatches/{dispatch}/pedido/{item}/entregar',    [DispatchController::class,'entregarPedido'])->name('dispatches.pedido.entregar');
Route::post('dispatches/{dispatch}/pedido/{item}/no-entregar', [DispatchController::class,'noEntregarPedido'])->name('dispatches.pedido.no-entregar');

Route::get('dispatches/{dispatch}/print/ruta',        [DispatchController::class,'printRuta'])->name('dispatches.print.ruta');
Route::get('dispatches/{dispatch}/print/liquidacion', [DispatchController::class,'printLiquidacion'])->name('dispatches.print.liquidacion');
 
 
// Cobro de CxC desde el despacho
Route::post('dispatches/{dispatch}/cxc/{assignment}/cobrar', [DispatchController::class,'cobrarCxc'])->name('dispatches.cxc.cobrar');

Route::prefix('ar')->name('ar.')->group(function () {
    Route::get('/',                [AccountsReceivableController::class,'index'])->name('index');
    Route::get('/cliente/{client}',[AccountsReceivableController::class,'show'])->name('show');
    Route::post('/cliente/{client}/cargo',[AccountsReceivableController::class,'charge'])->name('charge'); // opcional
});

Route::prefix('ar-payments')->name('ar-payments.')->group(function () {
    Route::get('/create', [ArPaymentsController::class,'create'])->name('create');
    Route::post('/',       [ArPaymentsController::class,'store'])->name('store');
});

Route::prefix('cash')->name('cash.')->group(function () {
  Route::get('/', [CashRegisterController::class,'index'])->name('index');
  Route::get('/create', [CashRegisterController::class,'create'])->name('create');
  Route::post('/', [CashRegisterController::class,'store'])->name('store');
  Route::get('/{cash}', [CashRegisterController::class,'show'])->name('show');
  Route::post('/{cash}/close', [CashRegisterController::class,'close'])->name('close');
  Route::get('/{cash}/ticket', [CashRegisterController::class,'ticket'])->name('ticket');

  Route::post('/{cashRegister}/movement', [CashMovementController::class,'store'])->name('movement.store');
});

Route::prefix('pos')->name('pos.')->group(function () {
  Route::get('/create', [POSController::class,'create'])->name('create');
  Route::post('/', [POSController::class,'store'])->name('store');
  Route::get('/ticket/{sale}', [POSController::class,'ticket'])->name('ticket');
   Route::get('/ticket/{sale}/pdf', [POSController::class,'ticketPdf'])->name('ticket.pdf');





});

// Dentro del grupo admin, junto a las otras rutas de stock:
Route::prefix('stock/transfers')->name('stock.transfers.')->group(function () {
    Route::get('/',                    [StockTransferController::class, 'index'])->name('index');
    Route::get('/create',              [StockTransferController::class, 'create'])->name('create');
    Route::post('/',                   [StockTransferController::class, 'store'])->name('store');
    Route::get('/{transfer}',          [StockTransferController::class, 'show'])->name('show');
    Route::get('/{transfer}/print',    [StockTransferController::class, 'print'])->name('print');
    Route::post('/{transfer}/complete',[StockTransferController::class, 'complete'])->name('complete');
    Route::post('/{transfer}/cancel',  [StockTransferController::class, 'cancel'])->name('cancel');
});

Route::prefix('parametros')->name('parametros.')->middleware(['auth'])->group(function () {
 
    // Gestión de empresas
    Route::prefix('empresas')->name('companies.')->group(function () {
 
        Route::get('/',                             [CompanyController::class, 'index'])
            ->name('index');
 
        Route::get('/nueva',                        [CompanyController::class, 'create'])
            ->name('create');
 
        Route::post('/',                            [CompanyController::class, 'store'])
            ->name('store');
 
        Route::get('/{company}/editar',             [CompanyController::class, 'edit'])
            ->name('edit');
 
        Route::put('/{company}',                    [CompanyController::class, 'update'])
            ->name('update');
 
        // Datos fiscales
        Route::get('/{company}/fiscal',             [CompanyController::class, 'fiscalEdit'])
            ->name('fiscal');
 
        Route::put('/{company}/fiscal',             [CompanyController::class, 'fiscalUpdate'])
            ->name('fiscal.update');
 
        // Certificados CSD / FIEL
        Route::get('/{company}/certificados',       [CompanyController::class, 'certificatesIndex'])
            ->name('certificates');
 
        Route::post('/{company}/certificados',      [CompanyController::class, 'certificateStore'])
            ->name('certificates.store');
 
        Route::delete('/{company}/certificados/{certificate}', [CompanyController::class, 'certificateDestroy'])
            ->name('certificates.destroy');
    });
});

//Envio de correos
Route::get('/{invoice}/send',        [InvoiceController::class, 'sendForm'])->name('invoices.send.form');
Route::post('/{invoice}/send',       [InvoiceController::class, 'send'])->name('invoices.send');