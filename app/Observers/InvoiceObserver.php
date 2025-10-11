<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\DocumentLogService;

class InvoiceObserver
{
    public function created(Invoice $i): void
    {
        app(DocumentLogService::class)->log($i, 'CREATED', null, $i->estatus, $i->created_by);
    }

    public function updated(Invoice $i): void
    {
        if ($i->wasChanged('estatus')) {
            $old = $i->getOriginal('estatus');
            $new = $i->estatus;
            app(DocumentLogService::class)->log($i, 'STATUS_CHANGED', $old, $new, $i->owner_id);
        }
    }
}
