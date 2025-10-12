<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBom;
use App\Models\ProductSubproductRule;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /** Obtiene el ID del almacén principal (fallback: primero). */
    public function getMainWarehouseId(): int
    {
        // Detectar la columna usada en tu tabla warehouses
        $id = DB::table('warehouses')->where('is_primary', 1)->value('id');
        if (!$id) $id = DB::table('warehouses')->where('principal', 1)->value('id');
        if (!$id) $id = DB::table('warehouses')->where('es_principal', 1)->value('id');
        if (!$id) $id = (int) DB::table('warehouses')->orderBy('id')->value('id');

        if (!$id) {
            throw new \RuntimeException('No hay almacenes definidos. Crea al menos un almacén.');
        }

        return (int) $id;
    }

    /** Entrada a inventario (IN). */
    public function stockIn(int $productId, ?int $warehouseId, float $qty, string $motivo, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();

        StockMovement::create([
            'warehouse_id'    => $warehouseId,
            'product_id'      => $productId,
            'tipo'            => 'IN',
            'cantidad'        => $qty, // cantidad positiva
            'motivo'          => $motivo,
            'referencia_type' => $referencia ? get_class($referencia) : null,
            'referencia_id'   => $referencia->id ?? null,
            'user_id'         => $userId,
        ]);

        // Si llevas stock actual en otra tabla/campo, actualízalo aquí
        // DB::table('products')->where('id',$productId)->increment('stock_actual', $qty);
    }

    /** Salida de inventario (OUT). */
    public function stockOut(int $productId, ?int $warehouseId, float $qty, string $motivo, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();

        StockMovement::create([
            'warehouse_id'    => $warehouseId,
            'product_id'      => $productId,
            'tipo'            => 'OUT',
            'cantidad'        => $qty, // cantidad positiva (se interpreta como salida)
            'motivo'          => $motivo,
            'referencia_type' => $referencia ? get_class($referencia) : null,
            'referencia_id'   => $referencia->id ?? null,
            'user_id'         => $userId,
        ]);

        // Si llevas stock actual, aquí restarías:
        // DB::table('products')->where('id',$productId)->decrement('stock_actual', $qty);
    }

    /** Traspaso entre almacenes (OUT en origen, IN en destino). */
    public function transfer(int $productId, int $fromWarehouseId, int $toWarehouseId, float $qty, ?string $nota = null, ?int $userId = null): void
    {
        DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $qty, $nota, $userId) {
            $this->stockOut($productId, $fromWarehouseId, $qty, 'TRASPASO_OUT', null, $userId);
            $this->stockIn($productId,  $toWarehouseId,   $qty, 'TRASPASO_IN',  null, $userId);
        });
    }

    /** Consumir componentes de una BOM por una venta/producción. */
    public function consumeBom(Product $parent, float $qty, ?int $warehouseId, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();

        // Tu relación se llama bomComponents, pero en el modelo que mostraste
        // usaste 'bomComponents' con FK 'parent_product_id'.
        // Si tu tabla es product_boms(product_id, component_product_id, cantidad, activo),
        // ajusta a ->bomItems() o cambia aquí según corresponda.
        $components = $parent->bomItems()->where('activo', 1)->get();

        foreach ($components as $c) {
            $amount = (float) $c->cantidad * $qty;
            $this->stockOut($c->component_product_id, $warehouseId, $amount, 'CONSUMO_BOM', $referencia, $userId);
        }
    }

    /**
     * Consumir del producto padre segun regla de subproducto (venta de subproducto).
     * Nota: tus nombres en el modelo eran main_product_id/sub_product_id; en otras partes usamos parent_product_id/subproduct_id.
     * Lo hacemos compatible aquí.
     */
    public function consumeFromSubproduct(ProductSubproductRule $rule, float $qty, ?int $warehouseId, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();

        $parentId = $rule->parent_product_id ?? $rule->main_product_id;
        $ratio    = $rule->ratio ?? null;                // si usas ratio directo
        $rendPct  = $rule->rendimiento_pct ?? null;      // o rendimiento %

        // base necesaria del padre para obtener $qty del subproducto
        if ($ratio !== null) {
            $base = $qty * (float) $ratio;
        } elseif ($rendPct !== null) {
            $base = $qty / max(0.000001, ((float)$rendPct / 100.0));
        } else {
            throw new \RuntimeException('La regla de subproducto no define ratio ni rendimiento_pct.');
        }

        // considerar merma si la tienes
        $mermaPct = (float) ($rule->merma_porcent ?? 0);
        $merma    = $base * ($mermaPct / 100.0);
        $amount   = $base + $merma;

        $this->stockOut($parentId, $warehouseId, $amount, 'CONSUMO_SUBPRODUCTO', $referencia, $userId);
    }

    /**
     * Despiece: descuenta del padre y da entrada a subproductos según porcentaje de rendimiento.
     * Si no pasas $warehouseId, usará el ALMACÉN PRINCIPAL.
     */
    public function despiece(Product $parent, float $qty, ?int $warehouseId = null, ?string $nota = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();

        $rules = $parent->subproductRulesAsParent()->get();
        if ($rules->isEmpty()) {
            throw new \RuntimeException('El producto no tiene reglas de subproducto.');
        }

        DB::transaction(function () use ($parent, $qty, $rules, $warehouseId, $nota, $userId) {
            // 1) Salida del padre (despiece)
            $this->stockOut($parent->id, $warehouseId, $qty, 'DESPIECE', null, $userId);

            // 2) Entradas de subproductos
          foreach ($rules as $r) {
    $rend = (float) ($r->rendimiento_pct ?? 0);
    if ($rend <= 0) continue;

    $qtyOut = round($qty * ($rend / 100.0), 3);
    if ($qtyOut <= 0) continue;

    $this->stockIn($r->sub_product_id, $warehouseId, $qtyOut, 'DESPIECE_OUT', null, $userId);
}

        });
    }
}
