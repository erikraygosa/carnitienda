<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Warehouse;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class WarehouseTable extends DataTableComponent
{
    protected $model = Warehouse::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setPerPageAccepted([10,25,50,100])
            ->setPerPage(10);
    }

    public function columns(): array
    {
        return [
            Column::make('ID','id')->sortable()->collapseOnMobile(),
            Column::make('Código','codigo')->searchable()->sortable(),
            Column::make('Nombre','nombre')->searchable()->sortable(),
            Column::make('Dirección','direccion')->searchable()->collapseOnMobile(),
            Column::make('Activo','activo')
                ->format(fn($v) => $v ? 'Sí' : 'No')
                ->sortable()
                ->collapseOnMobile(),
            Column::make('Acciones')
                ->label(fn($row) => view('admin.warehouses.partials.actions', ['warehouse' => $row]))
                ->html(),
        ];
    }
}
