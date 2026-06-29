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
        $svc = app(DocumentLogService::class);
        $userId = auth()->id() ?? $i->owner_id;

        if ($i->wasChanged('estatus')) {
            $svc->log($i, 'STATUS_CHANGED', $i->getOriginal('estatus'), $i->estatus, $userId);
        }

        $changes = $svc->diff($i, ['estatus']);
        if ($changes) {
            $svc->log($i, 'UPDATED', null, null, $userId, null, $changes);
        }
    }
}
