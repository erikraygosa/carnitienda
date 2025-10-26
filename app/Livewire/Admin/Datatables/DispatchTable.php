<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Dispatch;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class DispatchTable extends DataTableComponent
{
    protected $model = Dispatch::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');

        $this->setThAttributes(fn(Column $column) => ['class'=>'px-2 py-2']);
        $this->setTdAttributes(fn(Column $column, $row, int $ci, int $ri) => ['class'=>'px-2 py-2']);

        $this->setPerPageAccepted([10,25,50,100]);
    }

    public function builder(): Builder
    {
        return Dispatch::query()->with(['route','driver','warehouse'])->latest('fecha');
    }

    public function columns(): array
    {
        return [
            Column::make('ID','id')->sortable(),
            Column::make('Fecha','fecha')
                ->sortable()
                ->format(fn($v)=>optional($v)->format('Y-m-d H:i')),
            Column::make('Ruta','route.nombre')
                ->format(fn($v,$row)=>$row->route?->nombre)->sortable()->searchable(),
            Column::make('Chofer','driver.nombre')
                ->format(fn($v,$row)=>$row->driver?->nombre)->sortable()->searchable(),
            Column::make('Almacén','warehouse.nombre')
                ->format(fn($v,$row)=>$row->warehouse?->nombre)->sortable()->searchable(),
            Column::make('Estatus','status')
                ->format(function($v){
                    $map = [
                        'PLANEADO'=>'bg-gray-100 text-gray-700','PREPARANDO'=>'bg-sky-100 text-sky-700',
                        'CARGADO'=>'bg-amber-100 text-amber-700','EN_RUTA'=>'bg-violet-100 text-violet-700',
                        'ENTREGADO'=>'bg-emerald-100 text-emerald-700','CERRADO'=>'bg-blue-100 text-blue-700',
                        'CANCELADO'=>'bg-rose-100 text-rose-700',
                    ];
                    return view('admin.dispatches.partials.badge', ['label'=>$v,'cls'=>$map[$v] ?? 'bg-slate-100 text-slate-700']);
                })->html()->sortable(),
            Column::make('Acciones')
                ->label(fn($row)=>view('admin.dispatches.partials.actions',['dispatch'=>$row]))->html(),
        ];
    }
}
