<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Dispatch;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class DispatchTable extends DataTableComponent
{
    protected $model = Dispatch::class;

        public function configure(): void
{
    $this->setPrimaryKey('id')
        ->setPerPageAccepted([10, 25, 50, 100])
        ->setPerPage(10)
        ->setDefaultSort('fecha', 'desc');
}

    public function builder(): Builder
    {
        return Dispatch::query()
            ->with(['route','driver','warehouse'])
            ->withCount('items')
            ->withCount(['items as items_entregados' => fn($q) =>
                $q->whereHas('salesOrder', fn($q) => $q->where('status','ENTREGADO'))
            ])
            ->withCount(['items as items_no_entregados' => fn($q) =>
                $q->whereHas('salesOrder', fn($q) => $q->where('status','NO_ENTREGADO'))
            ])
            ->latest('fecha');
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Estado')
                ->options([
                    ''           => 'Todos',
                    'PLANEADO'   => 'Planeado',
                    'PREPARANDO' => 'Preparando',
                    'CARGADO'    => 'Cargado',
                    'EN_RUTA'    => 'En ruta',
                    'ENTREGADO'  => 'Entregado',
                    'CERRADO'    => 'Cerrado',
                    'CANCELADO'  => 'Cancelado',
                ])
                ->filter(fn(Builder $q, $v) => $v ? $q->where('status', $v) : $q),
        ];
    }

    public function columns(): array
    {
        $statusMap = [
            'PLANEADO'   => 'bg-gray-100 text-gray-700',
            'PREPARANDO' => 'bg-sky-100 text-sky-700',
            'CARGADO'    => 'bg-amber-100 text-amber-700',
            'EN_RUTA'    => 'bg-violet-100 text-violet-700',
            'ENTREGADO'  => 'bg-emerald-100 text-emerald-700',
            'CERRADO'    => 'bg-blue-100 text-blue-700',
            'CANCELADO'  => 'bg-rose-100 text-rose-700',
        ];

        return [
            Column::make('Fecha', 'fecha')
                ->sortable()
                ->format(fn($v) => optional($v)->format('d/m/Y H:i')),

            Column::make('Chofer', 'driver.nombre')
                ->sortable()
                ->searchable()
                ->format(fn($v, $row) => $row->driver?->nombre ?? '<span class="text-gray-400">—</span>')
                ->html(),

            Column::make('Ruta', 'route.nombre')
                ->sortable()
                ->searchable()
                ->format(fn($v, $row) => $row->route?->nombre ?? '<span class="text-gray-400">—</span>')
                ->html(),

            Column::make('Almacén', 'warehouse.nombre')
                ->sortable()
                ->searchable()
                ->format(fn($v, $row) => $row->warehouse?->nombre ?? '<span class="text-gray-400">—</span>')
                ->html(),

        Column::make('Pedidos')
    ->label(function($row) {
        $total        = (int) $row->items_count;
        $entregados   = (int) $row->items_entregados;
        $noEntregados = (int) $row->items_no_entregados;
        $pendientes   = $total - $entregados - $noEntregados;

        $html = "<span class='font-mono text-sm'>{$total}</span>";
        if ($entregados > 0) {
            $html .= " <span class='text-xs text-emerald-600'>✓{$entregados}</span>";
        }
        if ($noEntregados > 0) {
            $html .= " <span class='text-xs text-red-500'>✗{$noEntregados}</span>";
        }
        if ($pendientes > 0 && $row->status === 'EN_RUTA') {
            $html .= " <span class='text-xs text-violet-500'>⏳{$pendientes}</span>";
        }
        return $html;
    })
    ->html(),

            Column::make('Estado', 'status')
                ->sortable()
                ->format(function($v) use ($statusMap) {
                    $cls = $statusMap[$v] ?? 'bg-slate-100 text-slate-700';
                    return "<span class='px-2 py-0.5 rounded-full text-xs font-medium {$cls}'>{$v}</span>";
                })
                ->html(),

            Column::make('Acciones')
                ->label(fn($row) => view('admin.dispatches.partials.actions', ['dispatch' => $row]))
                ->html(),
        ];
    }
}