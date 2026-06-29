<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Sale;
use App\Models\Quote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\InventoryMovement;

use App\Observers\SaleObserver;
use App\Observers\QuoteObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ProductObserver;
use App\Observers\SalesOrderObserver;
use App\Observers\StockMovementObserver;
use App\Observers\StockTransferObserver;
use App\Observers\InventoryMovementObserver;

class ObserverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sale::observe(SaleObserver::class);
        Quote::observe(QuoteObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Product::observe(ProductObserver::class);
        SalesOrder::observe(SalesOrderObserver::class);
        StockMovement::observe(StockMovementObserver::class);
        StockTransfer::observe(StockTransferObserver::class);
        InventoryMovement::observe(InventoryMovementObserver::class);
    }
}
