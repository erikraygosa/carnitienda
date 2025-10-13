<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class SalesOrderTable extends DataTableComponent
{
    protected $model = SalesOrder::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setPerPage(10)
            ->setPerPageAccepted([10,25,50,100]);
    }

    public function builder(): Builder
    {
        return SalesOrder::query()
            ->select('sales_orders.*')
            ->with(['client','warehouse']);
    }

    public function columns(): array
    {
        return [
            Column::make('ID','id')->sortable()->collapseOnMobile(),
            Column::make('Folio','folio')->searchable()->sortable(),
            Column::make('Cliente','client.nombre')
                ->format(fn($v,$row)=>$row->client?->nombre)->searchable(),
            Column::make('Almacén','warehouse.nombre')
                ->format(fn($v,$row)=>$row->warehouse?->nombre)->searchable(),
            Column::make('Fecha','fecha')->sortable(),
            Column::make('Estatus','status')
                ->format(fn($v,$row)=>$row->status_label)->sortable(),
            Column::make('Total','total')
                ->format(fn($v)=>number_format((float)$v,2))->sortable(),
            Column::make('Acciones')
                ->label(fn ($row) => view('admin.sales_orders.partials.actions',['order'=>$row])->render())
                ->html(),
        ];
    }
}
