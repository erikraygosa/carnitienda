<?php

namespace App\Observers;

use App\Models\StockTransfer;
use App\Services\DocumentLogService;

class StockTransferObserver
{
    public function created(StockTransfer $st): void
    {
        app(DocumentLogService::class)->log($st, 'CREATED', null, $st->status, $st->created_by, "Folio:{$st->folio}");
    }

    public function updated(StockTransfer $st): void
    {
        if ($st->wasChanged('status')) {
            app(DocumentLogService::class)->log(
                $st, 'STATUS_CHANGED',
                $st->getOriginal('status'), $st->status, auth()->id()
            );
        }
    }
}
