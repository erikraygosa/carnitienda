<?php

namespace App\Observers;

use App\Models\InventoryMovement;
use App\Services\DocumentLogService;

class InventoryMovementObserver
{
    public function created(InventoryMovement $im): void
    {
        app(DocumentLogService::class)->log(
            $im, 'CREATED', null, $im->tipo, $im->created_by,
            "Producto:{$im->product_id} | Cant:{$im->cantidad}"
        );
    }
}
