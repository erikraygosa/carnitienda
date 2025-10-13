<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class PurchaseOrderTable extends DataTableComponent
{
    protected $model = PurchaseOrder::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setPerPage(10)
            ->setPerPageAccepted([10, 25, 50, 100]);
    }

    public function builder(): Builder
    {
        return PurchaseOrder::query()
            ->select('purchase_orders.*')   // evita ambigüedad al hacer with()
            ->with(['provider', 'warehouse']);
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')->sortable()->collapseOnMobile(),
            Column::make('Folio', 'folio')->searchable()->sortable(),
            Column::make('Proveedor', 'provider.nombre')
                ->format(fn ($v, $row) => $row->provider?->nombre)
                ->searchable(),
            Column::make('Almacén', 'warehouse.nombre')
                ->format(fn ($v, $row) => $row->warehouse?->nombre)
                ->searchable(),
            Column::make('Fecha', 'fecha')->sortable(),
               Column::make('Estatus', 'status')
                ->label(fn ($row) => '<span class="px-2 py-1 text-xs rounded-full '.$row->status_badge_class.'">'.$row->status_label.'</span>')
                ->html()
                ->sortable(),

            Column::make('Total', 'total')
                ->format(fn ($v) => number_format((float)$v, 2))
                ->sortable(),
            Column::make('Acciones')
                ->label(fn ($row) => view('admin.purchase_orders.partials.actions', ['order' => $row])->render())
                ->html(),
        ];
    }
}
