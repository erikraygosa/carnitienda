<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
        public function index()
    {
        $warehouses      = Warehouse::orderBy('nombre')->get();
        $products        = Product::where('maneja_inventario', 1)
            ->where('activo', 1)
            ->orderBy('nombre')
            ->get(['id','nombre','sku']);

        $mainWarehouseId = $this->getMainWarehouseId();
        $warehouseId     = request('warehouse_id', $mainWarehouseId);
        $productId       = request('product_id');
        $vista           = request('vista', 'productos'); // 'productos' o 'almacenes'

        $stock = collect();

        if ($vista === 'productos' && $warehouseId) {
            $sumExpr = "COALESCE(SUM(CASE
                WHEN sm.tipo = 'IN'  THEN sm.cantidad
                WHEN sm.tipo = 'OUT' THEN -sm.cantidad
                ELSE 0 END), 0)";

            $stock = Product::query()
                ->where('products.maneja_inventario', 1)
                ->where('products.activo', 1)
                ->select([
                    'products.id as product_id',
                    'products.sku',
                    'products.nombre',
                    'products.unidad',
                    'products.stock_min',
                    'products.costo_promedio',
                    DB::raw("({$sumExpr}) as existencia"),
                ])
                ->leftJoin('stock_movements as sm', function($join) use ($warehouseId) {
                    $join->on('sm.product_id', '=', 'products.id')
                        ->where('sm.warehouse_id', '=', $warehouseId);
                })
                ->when($productId, fn($q) => $q->where('products.id', $productId))
                ->groupBy('products.id','products.sku','products.nombre','products.unidad','products.stock_min','products.costo_promedio')
                ->orderBy('products.nombre')
                ->get();
        }

        // Vista resumen por almacén
        $resumenAlmacenes = collect();
        if ($vista === 'almacenes') {
            $sumExpr = "COALESCE(SUM(CASE
                WHEN sm.tipo = 'IN'  THEN sm.cantidad
                WHEN sm.tipo = 'OUT' THEN -sm.cantidad
                ELSE 0 END), 0)";

            $resumenAlmacenes = Warehouse::orderBy('nombre')
                ->get()
                ->map(function($w) use ($sumExpr) {
                    $rows = Product::query()
                        ->where('products.maneja_inventario', 1)
                        ->where('products.activo', 1)
                        ->select([
                            'products.id as product_id',
                            'products.sku',
                            'products.nombre',
                            'products.unidad',
                            'products.stock_min',
                            'products.costo_promedio',
                            DB::raw("({$sumExpr}) as existencia"),
                        ])
                        ->leftJoin('stock_movements as sm', function($join) use ($w) {
                            $join->on('sm.product_id', '=', 'products.id')
                                ->where('sm.warehouse_id', '=', $w->id);
                        })
                        ->groupBy('products.id','products.sku','products.nombre','products.unidad','products.stock_min','products.costo_promedio')
                        ->orderBy('products.nombre')
                        ->get();

                    $w->productos   = $rows;
                    $w->total_valor = $rows->sum(fn($r) => (float)$r->costo_promedio * (float)$r->existencia);
                    $w->con_stock   = $rows->where('existencia', '>', 0)->count();
                    $w->sin_stock   = $rows->where('existencia', '<=', 0)->count();
                    return $w;
                });
        }

        return view('admin.stock.index', compact(
            'warehouses','products','mainWarehouseId',
            'warehouseId','productId','stock','vista','resumenAlmacenes'
        ));
    }

    public function costs()
    {
        $warehouses = Warehouse::orderBy('nombre')->get();
        $products   = Product::where('maneja_inventario', 1)
            ->where('activo', 1)
            ->orderBy('nombre')
            ->get(['id','nombre','sku','costo_promedio']);

        $mainWarehouseId = $this->getMainWarehouseId();

        return view('admin.stock.costs', compact('warehouses', 'products', 'mainWarehouseId'));
    }

    public function kardex(Request $request)
    {
        $product   = Product::findOrFail($request->product_id);
        $warehouse = Warehouse::findOrFail($request->warehouse_id);

        // Movimientos con saldo acumulado (window function MySQL 8+)
        $subSql = "
            SELECT
                sm.*,
                u.name as user_name,
                SUM(CASE WHEN sm.tipo = 'IN' THEN sm.cantidad ELSE -sm.cantidad END)
                    OVER (ORDER BY sm.created_at ASC, sm.id ASC) AS saldo_acumulado
            FROM stock_movements sm
            LEFT JOIN users u ON u.id = sm.user_id
            WHERE sm.product_id = ?
              AND sm.warehouse_id = ?
        ";

        $bindings = [$product->id, $warehouse->id];

        if ($request->desde) {
            $subSql   .= " AND DATE(sm.created_at) >= ?";
            $bindings[] = $request->desde;
        }
        if ($request->hasta) {
            $subSql   .= " AND DATE(sm.created_at) <= ?";
            $bindings[] = $request->hasta;
        }
        if ($request->tipo) {
            $subSql   .= " AND sm.tipo = ?";
            $bindings[] = $request->tipo;
        }

        $subSql .= " ORDER BY sm.created_at DESC, sm.id DESC";

        $all = DB::select($subSql, $bindings);

        // Paginar manualmente
        $page    = (int) $request->get('page', 1);
        $perPage = 30;
        $total   = count($all);
        $items   = array_slice($all, ($page - 1) * $perPage, $perPage);

        $movimientos = new \Illuminate\Pagination\LengthAwarePaginator(
            collect($items),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Existencia actual (sin filtros de fecha)
        $existencia = (float) DB::table('stock_movements')
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo='IN' THEN cantidad ELSE -cantidad END), 0) as total")
            ->value('total');

        return view('admin.stock.kardex', compact('product', 'warehouse', 'existencia', 'movimientos'));
    }

    private function getMainWarehouseId(): ?int
    {
        $id = DB::table('warehouses')->where('is_primary', 1)->value('id')
           ?? DB::table('warehouses')->where('principal', 1)->value('id')
           ?? DB::table('warehouses')->where('es_principal', 1)->value('id')
           ?? DB::table('warehouses')->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    public function printWarehouse(Warehouse $warehouse)
{
    $sumExpr = "COALESCE(SUM(CASE
        WHEN sm.tipo = 'IN'  THEN sm.cantidad
        WHEN sm.tipo = 'OUT' THEN -sm.cantidad
        ELSE 0 END), 0)";

    $stock = Product::query()
        ->where('products.maneja_inventario', 1)
        ->where('products.activo', 1)
        ->select([
            'products.id as product_id',
            'products.sku',
            'products.nombre',
            'products.unidad',
            'products.stock_min',
            'products.costo_promedio',
            DB::raw("({$sumExpr}) as existencia"),
        ])
        ->leftJoin('stock_movements as sm', function($join) use ($warehouse) {
            $join->on('sm.product_id', '=', 'products.id')
                 ->where('sm.warehouse_id', '=', $warehouse->id);
        })
        ->groupBy(
            'products.id','products.sku','products.nombre',
            'products.unidad','products.stock_min','products.costo_promedio'
        )
        ->orderBy('products.nombre')
        ->get();

    return view('admin.stock.print_warehouse', compact('warehouse', 'stock'));
}
}