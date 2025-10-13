<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Provider;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class ProviderTable extends DataTableComponent
{
    protected $model = Provider::class;

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
            Column::make('Nombre','nombre')->searchable()->sortable(),
            Column::make('RFC','rfc')->searchable()->sortable()->collapseOnMobile(),
            Column::make('Teléfono','telefono')->searchable()->sortable()->collapseOnMobile(),
            Column::make('Email','email')->searchable()->sortable()->collapseOnMobile(),
            Column::make('Activo','activo')
                ->format(fn($v) => $v ? 'Sí' : 'No')
                ->sortable()
                ->collapseOnMobile(),
            Column::make('Acciones')
                ->label(fn($row) => view('admin.providers.partials.actions', ['provider'=>$row]))
                ->html(),
        ];
    }
}
