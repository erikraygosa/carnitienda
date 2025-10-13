<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class SalesTable extends DataTableComponent
{
    protected $model = Sale::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setPerPage(10)
            ->setPerPageAccepted([10, 25, 50, 100]);
    }

    public function builder(): Builder
    {
        // Para evitar ambigüedad en columnas al usar with()
        return Sale::query()
            ->select('sales.*')
            ->with(['client','warehouse']);
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')->sortable()->collapseOnMobile(),

            Column::make('Folio', 'folio')
                ->searchable()
                ->sortable(),

            Column::make('Cliente', 'client.nombre')
                ->format(fn ($v, $row) => $row->client?->nombre)
                ->searchable(),

            Column::make('Almacén', 'warehouse.nombre')
                ->format(fn ($v, $row) => $row->warehouse?->nombre)
                ->searchable(),

            Column::make('Fecha', 'fecha')->sortable(),

            Column::make('Estatus', 'status')
                ->format(fn ($v, $row) => view('admin.sales.partials.status-badge', ['sale' => $row]))
                ->html()
                ->sortable(),

            Column::make('Total', 'total')
                ->format(fn ($v) => number_format((float)$v, 2))
                ->sortable(),

            Column::make('Acciones')
                ->label(fn ($row) => view('admin.sales.partials.actions', ['sale' => $row])->render())
                ->html(),
        ];
    }
}
