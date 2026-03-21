<?php

namespace App\Livewire\Admin\Datatables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class StockTable extends DataTableComponent
{
    public ?int $warehouseId = null;
    public ?int $productId   = null;

    public function configure(): void
    {
        $this->setPrimaryKey('product_id')
            ->setPerPage(25)
            ->setPerPageAccepted([25, 50, 100])
            ->setDefaultSort('nombre', 'asc');
    }

        public function builder(): Builder
{
    $sumExpr = "COALESCE(SUM(CASE
        WHEN sm.tipo = 'IN'  THEN sm.cantidad
        WHEN sm.tipo = 'OUT' THEN -sm.cantidad
        ELSE 0 END), 0)";

    return \App\Models\Product::query()
        ->where('products.maneja_inventario', 1)
        ->where('products.activo', 1)
        ->select([
            'products.id as product_id',
            'products.stock_min',
            'products.costo_promedio',
            DB::raw("({$sumExpr}) as existencia"),
        ])
        ->leftJoin('stock_movements as sm', function($join) {
            $join->on('sm.product_id', '=', 'products.id');
            if ($this->warehouseId) {
                $join->where('sm.warehouse_id', '=', $this->warehouseId);
            }
        })
        ->when($this->productId, fn($q) => $q->where('products.id', $this->productId))
        ->groupBy(
            'products.id','products.sku','products.nombre',
            'products.unidad','products.stock_min','products.costo_promedio'
        );
}

    public function columns(): array
    {
        return [
            Column::make('SKU', 'sku')
                ->sortable()
                ->searchable()
                ->collapseOnMobile(),

            Column::make('Producto', 'nombre')
                ->sortable()
                ->searchable(),

            Column::make('Unidad', 'unidad')
                ->collapseOnMobile(),

            Column::make('Existencia', 'existencia')
                ->label(function ($row) {
                    $qty      = (float) $row->existencia;
                    $stockMin = (float) ($row->stock_min ?? 0);
                    $color    = $qty <= 0
                        ? 'text-red-700 bg-red-50'
                        : ($stockMin > 0 && $qty <= $stockMin
                            ? 'text-amber-700 bg-amber-50'
                            : 'text-emerald-700 bg-emerald-50');

                    return "<span class=\"px-2 py-0.5 rounded font-mono text-sm {$color}\">"
                         . number_format($qty, 3)
                         . "</span>";
                })
                ->html()
                ->sortable(),

            Column::make('Stock mín.', 'stock_min')
                ->label(fn($row) =>
                    $row->stock_min
                        ? "<span class='text-gray-500 font-mono text-sm'>".number_format((float)$row->stock_min,3)."</span>"
                        : "<span class='text-gray-300'>—</span>"
                )
                ->html()
                ->collapseOnMobile(),

            Column::make('Valor inventario')
                ->label(function ($row) {
                    $costo = (float) ($row->costo_promedio ?? 0);
                    $qty   = (float) $row->existencia;
                    $valor = $costo * $qty;
                    return $valor > 0
                        ? "<span class='text-gray-700 font-mono text-sm'>$".number_format($valor,2)."</span>"
                        : "<span class='text-gray-300'>—</span>";
                })
                ->html()
                ->collapseOnMobile(),

            Column::make('Acciones')
                ->label(function ($row) {
                    $w         = $this->warehouseId;
                    $kardexUrl = route('admin.stock.kardex', [
                        'product_id'   => $row->product_id,
                        'warehouse_id' => $w,
                    ]);
                    $adjUrl = route('admin.stock.adjustments.create', [
                        'product_id'   => $row->product_id,
                        'warehouse_id' => $w,
                    ]);

                    return "
                        <div class='flex items-center gap-1'>
                            <a href='{$kardexUrl}'
                               class='px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50'>
                                Kardex
                            </a>
                            <a href='{$adjUrl}'
                               class='px-2 py-1 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-50'>
                                Ajuste
                            </a>
                        </div>
                    ";
                })
                ->html(),
        ];
    }
}