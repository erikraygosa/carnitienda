<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Services\DocumentLogService;

class StockMovementObserver
{
    public function created(StockMovement $sm): void
    {
        app(DocumentLogService::class)->log(
            $sm, 'CREATED', null, $sm->tipo, $sm->user_id,
            "Producto ID:{$sm->product_id} | Cant:{$sm->cantidad} | {$sm->motivo}"
        );
    }
}
