<?php

namespace App\Observers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\ProductSubproductRule;
use App\Services\InventoryService;
use App\Services\DocumentLogService;

class SaleObserver
{
    public function created(Sale $sale): void
    {
        app(DocumentLogService::class)->log($sale, 'CREATED', null, $sale->status, $sale->created_by);
    }

    public function updated(Sale $sale): void
    {
        if ($sale->wasChanged('status')) {
            $old = $sale->getOriginal('status');
            $new = $sale->status;
            app(DocumentLogService::class)->log($sale, 'STATUS_CHANGED', $old, $new, $sale->owner_id);
        }

        // En transición a CERRADA o ENTREGADA, descontar inventario según BOM/subproducto
        if (in_array($sale->status, ['CERRADA','ENTREGADA'])) {
            $inv = app(InventoryService::class);
            foreach ($sale->items as $item) {
                if (!$item->product) continue;
                $p = $item->product;

                // Si el producto es compuesto (BOM): consume componentes
                if ($p->es_compuesto && $p->bomComponents()->exists()) {
                    $inv->consumeBom($p, (float)$item->cantidad, $sale->warehouse_id, $sale, $sale->user_id);
                }

                // Si el producto es subproducto: consumir del principal según regla
                if ($p->es_subproducto) {
                    $rule = $p->subproductRules()->where('activo', 1)->first();
                    if ($rule) {
                        $inv->consumeFromSubproduct($rule, (float)$item->cantidad, $sale->warehouse_id, $sale, $sale->user_id);
                    }
                }
            }
        }
    }
}
