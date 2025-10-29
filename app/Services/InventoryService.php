<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSubproductRule;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function getMainWarehouseId(): int
    {
        $id = DB::table('warehouses')->where('is_primary', 1)->value('id');
        if (!$id) $id = DB::table('warehouses')->where('principal', 1)->value('id');
        if (!$id) $id = DB::table('warehouses')->where('es_principal', 1)->value('id');
        if (!$id) $id = (int) DB::table('warehouses')->orderBy('id')->value('id');
        if (!$id) throw new \RuntimeException('No hay almacenes definidos. Crea al menos un almacén.');
        return (int) $id;
    }

    protected function normQty(float $qty): float
    {
        $q = max(0, (float) $qty);
        return (float) number_format($q, 3, '.', '');
    }

    public function stockIn(int $productId, ?int $warehouseId, float $qty, string $motivo, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();
        $qty = $this->normQty($qty);
        if ($qty <= 0) return;

        StockMovement::create([
            'warehouse_id'    => $warehouseId,
            'product_id'      => $productId,
            'tipo'            => 'IN',
            'cantidad'        => $qty,
            'motivo'          => $motivo,
            'referencia_type' => $referencia ? get_class($referencia) : null,
            'referencia_id'   => $referencia->id ?? null,
            'user_id'         => $userId,
        ]);
    }

    public function stockOut(int $productId, ?int $warehouseId, float $qty, string $motivo, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();
        $qty = $this->normQty($qty);
        if ($qty <= 0) return;

        StockMovement::create([
            'warehouse_id'    => $warehouseId,
            'product_id'      => $productId,
            'tipo'            => 'OUT',
            'cantidad'        => $qty,
            'motivo'          => $motivo,
            'referencia_type' => $referencia ? get_class($referencia) : null,
            'referencia_id'   => $referencia->id ?? null,
            'user_id'         => $userId,
        ]);
    }

    /** OUT de componentes según BOM del padre (no toca al padre). */
    public function consumeBom(Product $parent, float $qty, ?int $warehouseId, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();
        $qty = $this->normQty($qty);
        if ($qty <= 0) return;

        $components = $parent->bomItems()->where('activo', 1)->with('component')->get();
        foreach ($components as $c) {
            if (!$c->component || !$c->component->maneja_inventario) continue;
            $amount = $this->normQty((float)$c->cantidad * $qty);
            if ($amount <= 0) continue;

            $this->stockOut(
                (int) $c->component_product_id,
                $warehouseId,
                $amount,
                'CONSUMO_BOM',
                $referencia,
                $userId
            );
        }
    }

    /** Venta/consumo del PADRE (descuenta padre; si es compuesto, además descuenta componentes). */
    public function consumeForSale(Product $product, float $qty, ?int $warehouseId = null, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();
        $qty = $this->normQty($qty);
        if ($qty <= 0) return;

        DB::transaction(function () use ($product, $qty, $warehouseId, $referencia, $userId) {
            if ($product->maneja_inventario) {
                $this->stockOut($product->id, $warehouseId, $qty, 'VENTA_PADRE', $referencia, $userId);
            }
            if ($product->es_compuesto) {
                $this->consumeBom($product, $qty, $warehouseId, $referencia, $userId);
            }
        });
    }

    /**
     * Entrada para items del pedido.
     * REGLA: si el PRODUCTO es SUBPRODUCTO y existe regla activa -> consumir el PADRE.
     *        (no se descuenta el propio subproducto en ese caso)
     * Si no hay regla, se descuenta el propio producto normalmente.
     */
    public function consumeForOrderItem(object $item, ?int $warehouseId = null, $referencia = null, ?int $userId = null): void
    {
        $product = Product::with(['bomItems' => fn($q) => $q->where('activo', 1), 'bomItems.component'])
            ->findOrFail((int) $item->product_id);

        $qty = $this->normQty((float) $item->cantidad);
        if ($qty <= 0) return;

        // 1) ¿Es subproducto? intenta consumir su PADRE según la regla
        if ($product->es_subproducto) {
            $rule = ProductSubproductRule::where('sub_product_id', $product->id)
                    ->where('activo', 1)
                    ->orderByDesc('id')
                    ->first();

            if ($rule) {
                $this->consumeFromSubproduct($rule, $qty, $warehouseId, $referencia, $userId);
                return; // ✅ NO descontamos el subproducto
            }
            // Si no hay regla, cae al flujo normal (descuenta el propio subproducto)
        }

        // 2) No es subproducto (o no hay regla) -> flujo normal del producto
        $this->consumeForSale($product, $qty, $warehouseId, $referencia, $userId);
    }

    /** Despiece: salida del padre y entrada a subproductos por ratio y merma. */
    public function despiece(Product $parent, float $qty, ?int $warehouseId = null, ?string $nota = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();
        $qty = $this->normQty($qty);
        if ($qty <= 0) return;

        $rules = $parent->subproductRulesAsParent()->where('activo', 1)->get();
        if ($rules->isEmpty()) {
            throw new \RuntimeException('El producto no tiene reglas de subproducto activas.');
        }

        DB::transaction(function () use ($parent, $qty, $rules, $warehouseId, $nota, $userId) {
            $this->stockOut($parent->id, $warehouseId, $qty, 'DESPIECE_OUT'.($nota ? " ({$nota})" : ''), null, $userId);

            foreach ($rules as $r) {
                $ratio   = (float) ($r->ratio ?? 0);
                if ($ratio <= 0) continue;

                $merma   = (float) ($r->merma_porcent ?? 0);
                $qtyBase = $qty * $ratio;
                $qtyIn   = $this->normQty(max(0, $qtyBase - ($qtyBase * ($merma / 100.0))));
                if ($qtyIn <= 0) continue;

                $this->stockIn((int) $r->sub_product_id, $warehouseId, $qtyIn, 'DESPIECE_IN'.($nota ? " ({$nota})" : ''), null, $userId);
            }
        });
    }

    /**
     * Vendo X del SUBPRODUCTO => consumo del PADRE según ratio (+ merma si quieres considerarla).
     * ratio = subunits_per_parent  =>  parent_needed = qty / ratio
     */
    public function consumeFromSubproduct(ProductSubproductRule $rule, float $qty, ?int $warehouseId = null, $referencia = null, ?int $userId = null): void
    {
        $warehouseId = $warehouseId ?: $this->getMainWarehouseId();
        $qty = $this->normQty($qty);
        if ($qty <= 0) return;

        $ratio = (float) ($rule->ratio ?? 0);
        if ($ratio <= 0) {
            throw new \RuntimeException('La regla de subproducto no define ratio (> 0).');
        }

        $base     = $qty / $ratio; // cuántas unidades del padre necesito
        $mermaPct = (float) ($rule->merma_porcent ?? 0);
        $amount   = $this->normQty($base + ($base * ($mermaPct / 100.0)));

        $this->stockOut((int) $rule->main_product_id, $warehouseId, $amount, 'CONSUMO_SUBPRODUCTO', $referencia, $userId);
    }
}
