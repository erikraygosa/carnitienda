<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class QuoteTable extends DataTableComponent
{
    protected $model = Quote::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('id','desc')
            ->setPerPage(10)
            ->setPerPageAccepted([10,25,50,100]);
    }

    public function builder(): Builder
    {
        return Quote::query()
            ->select('quotes.*')
            ->with(['client']);
    }

    public function columns(): array
    {
        return [
            Column::make('ID','id')->sortable()->collapseOnMobile(),
            Column::make('Fecha','fecha')->sortable(),
            Column::make('Cliente','client.nombre')
                ->label(fn($row) => $row->client?->nombre ?? '—')
                ->searchable(),
            Column::make('Moneda','moneda')->sortable()->collapseOnMobile(),
            Column::make('Subtotal','subtotal')
                ->label(fn($r) => '$'.number_format((float)$r->subtotal,2))
                ->html()->sortable()->collapseOnMobile(),
            Column::make('Impuestos','impuestos')
                ->label(fn($r) => '$'.number_format((float)$r->impuestos,2))
                ->html()->sortable()->collapseOnMobile(),
            Column::make('Total','total')
                ->label(fn($r) => '<span class="font-mono">$'.number_format((float)$r->total,2).'</span>')
                ->html()->sortable(),
            Column::make('Estatus','status')
                ->label(function($r){
                    $classes = [
                        'BORRADOR'   => 'bg-gray-100 text-gray-700',
                        'ENVIADA'    => 'bg-sky-100 text-sky-700',
                        'APROBADA'   => 'bg-emerald-100 text-emerald-700',
                        'RECHAZADA'  => 'bg-rose-100 text-rose-700',
                        'CONVERTIDA' => 'bg-indigo-100 text-indigo-700',
                        'CANCELADA'  => 'bg-amber-100 text-amber-700',
                    ];
                    $cls = $classes[$r->status] ?? 'bg-slate-100 text-slate-700';
                    return '<span class="px-2 py-1 text-xs rounded-full '.$cls.'">'.$r->status_label.'</span>';
                })
                ->html()
                ->sortable(),
            Column::make('Acciones')
                ->label(fn($row) => view('admin.quotes.partials.actions', ['quote'=>$row])->render())
                ->html(),
        ];
    }
}
