<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class PurchaseTable extends DataTableComponent
{
    protected $model = Purchase::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('id','desc')
            ->setPerPage(10)
            ->setPerPageAccepted([10,25,50,100]);
    }

    public function builder(): Builder
    {
        return Purchase::query()
            ->select('purchases.*')
            ->with(['provider','warehouse']);
    }

    public function columns(): array
    {
        return [
            Column::make('ID','id')->sortable()->collapseOnMobile(),
            Column::make('Folio','folio')->searchable()->sortable(),
            Column::make('Proveedor')->label(fn($r)=>$r->provider?->nombre)->searchable(),
            Column::make('Almacén')->label(fn($r)=>$r->warehouse?->nombre)->searchable(),
            Column::make('Fecha','fecha')->sortable(),
               Column::make('Estatus', 'status')
                ->label(fn ($row) => '<span class="px-2 py-1 text-xs rounded-full '.$row->status_badge_class.'">'.$row->status_label.'</span>')
                ->html()
                ->sortable(),

            Column::make('Total','total')->format(fn($v)=>number_format((float)$v,2))->sortable(),
            Column::make('Acciones')
                ->label(fn($row)=> view('admin.purchases.partials.actions',['purchase'=>$row])->render())
                ->html(),
        ];
    }
}
