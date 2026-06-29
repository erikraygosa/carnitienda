<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\DocumentLogService;

class ProductObserver
{
    public function creating(Product $p): void
    {
        $p->created_by = auth()->id();
    }

    public function updating(Product $p): void
    {
        $p->updated_by = auth()->id();
    }

    public function created(Product $p): void
    {
        app(DocumentLogService::class)->log($p, 'CREATED', null, null, $p->created_by);
    }

    public function updated(Product $p): void
    {
        $changes = app(DocumentLogService::class)->diff($p);
        if ($changes) {
            app(DocumentLogService::class)->log($p, 'UPDATED', null, null, $p->updated_by, null, $changes);
        }
    }
}
