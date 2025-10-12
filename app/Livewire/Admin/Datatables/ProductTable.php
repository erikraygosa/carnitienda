<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class ProductTable extends DataTableComponent
{
    protected $model = Product::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setSearchPlaceholder('Buscar SKU / Nombre...')
            ->setColumnSelectStatus(false);
    }

    public function builder(): Builder
    {
        return Product::query()
            ->with('category') // relación: belongsTo Category
            ->select('products.*');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable()
                ->collapseOnTablet(),

            Column::make('SKU', 'sku')
                ->sortable()
                ->searchable()
                ->format(fn($value) => $value ?: '—'),

            Column::make('Nombre', 'nombre')
                ->sortable()
                ->searchable(),

            Column::make('Unidad', 'unidad')
                ->sortable()
                ->collapseOnTablet(),

            Column::make('Categoría', 'category_id')
                ->label(fn($row) => $row->category?->nombre ?? '—')
                ->sortable(fn(Builder $q, string $dir) =>
                    $q->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                      ->orderBy('categories.nombre', $dir)
                      ->select('products.*')
                ),

            Column::make('Compuesto', 'es_compuesto')
                ->sortable()
                ->label(fn($row) => $this->boolBadge($row->es_compuesto))
                ->html(),

            Column::make('Subproducto', 'es_subproducto')
                ->sortable()
                ->label(fn($row) => $this->boolBadge($row->es_subproducto))
                ->html(),

            Column::make('Maneja inv.', 'maneja_inventario')
                ->sortable()
                ->label(fn($row) => $this->boolBadge($row->maneja_inventario))
                ->html(),

            Column::make('Precio base', 'precio_base')
                ->sortable()
                ->label(fn($row) => $this->money($row->precio_base))
                ->html(),

            Column::make('Costo prom.', 'costo_promedio')
                ->sortable()
                ->label(fn($row) => $this->money($row->costo_promedio))
                ->html(),

            Column::make('IVA %', 'tasa_iva')
                ->sortable()
                ->label(fn($row) => number_format((float)$row->tasa_iva, 2) . '%'),

            Column::make('Stock min', 'stock_min')
                ->sortable()
                ->label(fn($row) => number_format((float)$row->stock_min, 3)),

            Column::make('Barcode', 'barcode')
                ->sortable()
                ->label(fn($row) => $row->barcode ?: '—')
                ->collapseOnTablet(),

            Column::make('Notas', 'notas')
                ->label(fn($row) => Str::limit((string)($row->notas ?? ''), 40) ?: '—')
                ->collapseOnTablet(),

            Column::make('Activo', 'activo')
                ->sortable()
                ->label(fn($row) => $this->statusBadge($row->activo))
                ->html(),

            Column::make('Creado', 'created_at')
                ->sortable()
                ->label(fn($row) => optional($row->created_at)->format('d/m/Y H:i'))
                ->collapseOnTablet(),

            Column::make('Actualizado', 'updated_at')
                ->sortable()
                ->label(fn($row) => optional($row->updated_at)->format('d/m/Y H:i'))
                ->collapseOnTablet(),

            Column::make('Acciones')
            ->label(fn ($row) => view('admin.products.actions', ['product' => $row]))
            ->html(),

        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Categoría')
                ->options(['' => 'Todas'] + Category::orderBy('nombre')->pluck('nombre', 'id')->toArray())
                ->filter(fn (Builder $b, $v) => $v !== '' && $v !== null ? $b->where('category_id', $v) : null),

            SelectFilter::make('Activo')
                ->options(['' => 'Todos', '1' => 'Activos', '0' => 'Inactivos'])
                ->filter(fn (Builder $b, $v) => in_array($v, ['0','1'], true) ? $b->where('activo', $v) : null),

            SelectFilter::make('Compuesto')
                ->options(['' => 'Todos', '1' => 'Sí', '0' => 'No'])
                ->filter(fn (Builder $b, $v) => in_array($v, ['0','1'], true) ? $b->where('es_compuesto', $v) : null),
        ];
    }

    // Helpers de formato (badges/moneda)
    private function boolBadge($val): string
    {
        return $val
            ? '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Sí</span>'
            : '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">No</span>';
    }

    private function statusBadge($val): string
    {
        return $val
            ? '<span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>'
            : '<span class="px-2 py-1 text-xs rounded-full bg-rose-100 text-rose-700">Inactivo</span>';
    }

    private function money($value): string
    {
        return '<span class="font-mono">\$' . number_format((float)$value, 4) . '</span>';
    }
}
