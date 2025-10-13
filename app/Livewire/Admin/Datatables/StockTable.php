<?php

namespace App\Livewire\Admin\Datatables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class StockTable extends DataTableComponent
{
    /** Filtros que recibes desde la vista */
    public ?int $warehouseId = null;
    public ?int $productId   = null;

    public function configure(): void
    {
        $this->setPrimaryKey('product_id')
            ->setPerPage(10)
            ->setPerPageAccepted([10, 25, 50, 100])
            ->setDefaultSort('products.nombre', 'asc');
    }

    public function builder(): Builder
    {
        // Solo calcula existencia y devuelve el ID; deja que Rappasoft agregue sku/nombre/unidad
        $sumExpr = "SUM(CASE
                        WHEN stock_movements.tipo = 'IN' THEN stock_movements.cantidad
                        WHEN stock_movements.tipo = 'OUT' THEN -stock_movements.cantidad
                        ELSE stock_movements.cantidad
                    END)";

        return \App\Models\Product::query()
            ->select([
                'products.id as product_id',
                DB::raw("COALESCE({$sumExpr}, 0) as existencia"),
            ])
            ->leftJoin('stock_movements', 'stock_movements.product_id', '=', 'products.id')
            ->when($this->warehouseId, fn ($q) => $q->where('stock_movements.warehouse_id', $this->warehouseId))
            ->when($this->productId, fn ($q) => $q->where('products.id', $this->productId))
            ->groupBy('products.id');
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
                ->sortable()
                ->collapseOnMobile(),

            Column::make('Existente', 'existencia')
                ->label(fn ($row) => '<span class="font-mono">'.number_format((float)$row->existencia, 3).'</span>')
                ->html()
                ->sortable(),

            Column::make('Acciones')
                ->label(function ($row) {
                    $w = $this->warehouseId;
                    $costUrl = $w
                        ? route('admin.stock.costs', ['warehouse_id' => $w, 'product_id' => $row->product_id])
                        : null;

                    return view('admin.stock.partials.actions', [
                        'product_id'   => $row->product_id,
                        'warehouse_id' => $w,
                        'costUrl'      => $costUrl,
                    ])->render();
                })
                ->html(),
        ];
    }
}
