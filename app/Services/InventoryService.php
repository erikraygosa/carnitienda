<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBom;
use App\Models\ProductSubproductRule;
use App\Models\StockMovement;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function consumeBom(Product $parent, float $qty, int $warehouseId, $referencia = null, ?int $userId = null): void
    {
        $components = $parent->bomComponents()->where('activo', 1)->get();
        foreach ($components as $c) {
            $amount = (float)$c->cantidad * $qty;
            $this->stockOut($c->component_product_id, $warehouseId, $amount, 'VENTA_BOM', $referencia, $userId);
        }
    }

    public function consumeFromSubproduct(ProductSubproductRule $rule, float $qty, int $warehouseId, $referencia = null, ?int $userId = null): void
    {
        $base = $qty * (float)$rule->ratio;
        $merma = $base * ((float)$rule->merma_porcent / 100.0);
        $amount = $base + $merma;
        $this->stockOut($rule->main_product_id, $warehouseId, $amount, 'VENTA_SUBPRODUCTO', $referencia, $userId);
    }

    public function stockOut(int $productId, int $warehouseId, float $qty, string $motivo, $referencia = null, ?int $userId = null): void
    {
        StockMovement::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'tipo' => 'OUT',
            'cantidad' => $qty,
            'motivo' => $motivo,
            'referencia_type' => $referencia ? get_class($referencia) : null,
            'referencia_id'   => $referencia ? $referencia->id : null,
            'user_id' => $userId,
        ]);
    }
}
