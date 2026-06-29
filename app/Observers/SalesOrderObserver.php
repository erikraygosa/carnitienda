<?php

namespace App\Observers;

use App\Models\SalesOrder;
use App\Services\DocumentLogService;

class SalesOrderObserver
{
    public function created(SalesOrder $so): void
    {
        app(DocumentLogService::class)->log($so, 'CREATED', null, $so->status, $so->created_by);
    }

    public function updated(SalesOrder $so): void
    {
        $svc    = app(DocumentLogService::class);
        $userId = auth()->id() ?? $so->created_by;

        if ($so->wasChanged('status')) {
            $svc->log($so, 'STATUS_CHANGED', $so->getOriginal('status'), $so->status, $userId);
        }

        $changes = $svc->diff($so, ['status']);
        if ($changes) {
            $svc->log($so, 'UPDATED', null, null, $userId, null, $changes);
        }
    }
}
