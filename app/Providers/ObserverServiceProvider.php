<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Sale;
use App\Models\Quote;
use App\Models\Invoice;

use App\Observers\SaleObserver;
use App\Observers\QuoteObserver;
use App\Observers\InvoiceObserver;

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
    }
}
