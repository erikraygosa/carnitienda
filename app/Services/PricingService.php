<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductWarehousePrice;
use App\Models\SystemSetting;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class PricingService
{
    /**
     * Precio por cliente/lista de precios (independiente del precio por
     * almacén de abajo — Ventas/Pedidos/Cotizaciones resuelven esto mismo
     * directamente en su JS y controlador; este helper queda disponible
     * para quien lo necesite server-side).
     */
    public static function priceFor(int $productId, int $clientId, ?int $priceListId, ?string &$source = null): float
    {
        // 1) Override por cliente
        $override = DB::table('client_price_overrides')
            ->where('client_id', $clientId)
            ->where('product_id', $productId)
            ->value('precio');
        if ($override !== null) { $source = 'override'; return (float) $override; }

        // 2) Lista del cliente
        if ($priceListId) {
            $listPrice = DB::table('price_list_items')
                ->where('price_list_id', $priceListId)
                ->where('product_id', $productId)
                ->value('precio');
            if ($listPrice !== null) { $source = 'price_list'; return (float) $listPrice; }
        }

        // 3) Precio base del producto
        $base = DB::table('products')->where('id', $productId)->value('precio_base') ?? 0;
        $source = 'base';
        return (float) $base;
    }

    // ── Precio por almacén (Punto de Venta) ─────────────────────────────────

    public function modoAlmacen(): bool
    {
        return SystemSetting::get('precios.modo', 'global') === 'almacen';
    }

    /**
     * Precio efectivo de un solo producto para un almacén (o global si el
     * modo 'almacen' está apagado, o si el producto no tiene override ahí).
     */
    public function precioParaAlmacen(Product $product, ?int $warehouseId): float
    {
        if ($this->modoAlmacen() && $warehouseId) {
            $override = ProductWarehousePrice::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->value('precio');

            if ($override !== null) return (float) $override;
        }

        return (float) $product->precio_base;
    }

    /**
     * Mapa product_id => precio efectivo para TODOS los productos activos,
     * resuelto para el almacén dado. Pensado para armar el JSON de precios
     * que consumen POS/Pedidos de una sola pasada (sin N+1).
     *
     * @return array<int,float>
     */
    public function mapaPrecios(?int $warehouseId = null): array
    {
        $base = Product::where('activo', 1)->pluck('precio_base', 'id')
            ->map(fn ($v) => (float) $v)->all();

        if (! $this->modoAlmacen() || ! $warehouseId) {
            return $base;
        }

        $overrides = ProductWarehousePrice::where('warehouse_id', $warehouseId)->pluck('precio', 'product_id');

        foreach ($overrides as $productId => $precio) {
            if (array_key_exists($productId, $base)) {
                $base[$productId] = (float) $precio;
            }
        }

        return $base;
    }

    /**
     * Mapa warehouse_id => [product_id => precio], para formularios donde el
     * almacén se elige en el propio form (Pedidos) y el precio debe
     * recalcularse en el navegador al cambiarlo, sin ir de vuelta al server.
     *
     * @return array<int,array<int,float>>
     */
    public function mapaPreciosPorAlmacen(): array
    {
        if (! $this->modoAlmacen()) {
            return [];
        }

        $mapa = [];
        foreach (Warehouse::pluck('id') as $warehouseId) {
            $mapa[$warehouseId] = $this->mapaPrecios($warehouseId);
        }

        return $mapa;
    }

    /**
     * Aplica los precios del almacén Matriz (is_primary) a los demás
     * almacenes — SOLO donde no exista ya un override configurado
     * específicamente ahí (no pisa nada que ya esté personalizado).
     *
     * @return int cantidad de precios creados
     */
    public function aplicarPreciosDeMatriz(int $userId): int
    {
        $matriz = Warehouse::where('is_primary', 1)->first() ?? Warehouse::orderBy('id')->first();
        abort_if(! $matriz, 422, 'No hay almacén Matriz configurado.');

        $preciosMatriz = ProductWarehousePrice::where('warehouse_id', $matriz->id)
            ->pluck('precio', 'product_id');

        if ($preciosMatriz->isEmpty()) {
            return 0;
        }

        $otrosAlmacenes = Warehouse::where('id', '!=', $matriz->id)->pluck('id');
        $creados = 0;

        foreach ($otrosAlmacenes as $warehouseId) {
            $yaConfigurados = ProductWarehousePrice::where('warehouse_id', $warehouseId)
                ->pluck('product_id')->flip();

            foreach ($preciosMatriz as $productId => $precio) {
                if ($yaConfigurados->has($productId)) continue; // ya tiene su propio precio, no se toca

                ProductWarehousePrice::create([
                    'product_id'   => $productId,
                    'warehouse_id' => $warehouseId,
                    'precio'       => $precio,
                    'updated_by'   => $userId,
                ]);
                $creados++;
            }
        }

        return $creados;
    }
}
