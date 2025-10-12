<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class ClientTable extends DataTableComponent
{
    protected $model = Client::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setSearchPlaceholder('Buscar nombre / email / teléfono...');
    }

    public function builder(): Builder
    {
        return Client::query()
            ->select('clients.*') // asegura traer `activo` y demás
            ->with(['shippingRoute', 'paymentType', 'priceList']); // ← sin listas de columnas
    }

    public function columns(): array
    {
        return [
            Column::make('ID','id')->sortable()->collapseOnTablet(),
            Column::make('Nombre','nombre')->sortable()->searchable(),

            Column::make('Email','email')
                ->label(fn ($r) => $r->email ?: '—')
                ->sortable()->searchable(),

            Column::make('Teléfono','telefono')
                ->label(fn ($r) => $r->telefono ?: '—')
                ->sortable()->searchable()->collapseOnTablet(),

            Column::make('Ruta')
                ->label(function ($r) {
                    $m = $r->shippingRoute;
                    if (! $m) return '—';
                    foreach (['nombre','name','descripcion','codigo','titulo'] as $col) {
                        if (!empty($m->$col)) return $m->$col;
                    }
                    return 'ID '.$m->id;
                })
                ->sortable(),

            Column::make('Tipo pago')
                ->label(function ($r) {
                    $m = $r->paymentType;
                    if (! $m) return '—';
                    foreach (['nombre','name','descripcion','tipo','titulo','codigo'] as $col) {
                        if (!empty($m->$col)) return $m->$col;
                    }
                    return 'ID '.$m->id;
                })
                ->sortable(),

            Column::make('Lista precio')
                ->label(function ($r) {
                    $m = $r->priceList;
                    if (! $m) return '—';
                    foreach (['nombre','name','descripcion','titulo','codigo'] as $col) {
                        if (!empty($m->$col)) return $m->$col;
                    }
                    return 'ID '.$m->id;
                })
                ->sortable(),

            Column::make('Crédito límite','credito_limite')
                ->label(fn ($r) => '<span class="font-mono">$'.number_format((float)$r->credito_limite,2).'</span>')
                ->html()->sortable()->collapseOnTablet(),

            Column::make('Crédito días','credito_dias')
                ->label(fn ($r) => (string)($r->credito_dias ?? 0))
                ->sortable()->collapseOnTablet(),

            Column::make('Activo','activo')
                ->sortable()
                ->label(fn ($r) => $this->statusBadge((int) data_get($r,'activo',0)))
                ->html(),

            Column::make('Acciones')
                ->label(fn ($r) => view('admin.clients.actions', ['client' => $r]))
                ->html(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Activo')
                ->options(['' => 'Todos','1' => 'Activos','0' => 'Inactivos'])
                ->filter(fn (Builder $q, $v) => in_array($v, ['0','1'], true) ? $q->where('activo', $v) : null),
        ];
    }

    private function statusBadge(int $value): string
    {
        return $value === 1
            ? '<span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>'
            : '<span class="px-2 py-1 text-xs rounded-full bg-rose-100 text-rose-700">Inactivo</span>';
    }
}
