<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class InvoiceTable extends DataTableComponent
{
    protected $model = Invoice::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');

        // v3: firmas correctas de callbacks
        $this->setThAttributes(function (Column $column) {
            return ['class' => 'px-2 py-2'];
        });

        $this->setTdAttributes(function (Column $column, $row, int $columnIndex, int $rowIndex) {
            return ['class' => 'px-2 py-2'];
        });

        $this->setPerPageAccepted([10, 25, 50, 100]);
        $this->setFilterLayout('slide-down'); // si usas filtros luego
    }

    public function builder(): Builder
    {
        return Invoice::query()
            ->with('client')
            ->latest('fecha'); // o ->latest()
    }

    public function columns(): array
    {
        return [
            Column::make('Folio', 'folio')
                ->sortable()
                ->searchable(),

            // Si prefieres máxima compatibilidad, formatea desde la relación:
            Column::make('Cliente', 'client.nombre')
                ->sortable()
                ->searchable()
                ->format(fn ($value, $row) => $row->client?->nombre),

            Column::make('Fecha', 'fecha')
                ->sortable()
                ->format(fn ($value) => optional($value)->format('Y-m-d')),

            Column::make('Moneda', 'moneda')
                ->sortable(),

            Column::make('Total', 'total')
                ->sortable()
                ->format(fn ($value) => number_format((float) $value, 2)),

            Column::make('Estatus', 'status')
                ->sortable()
                ->format(function ($value) {
                    $map = [
                        'BORRADOR'  => 'bg-gray-100 text-gray-700',
                        'TIMBRADA'  => 'bg-emerald-100 text-emerald-700',
                        'CANCELADA' => 'bg-rose-100 text-rose-700',
                        'ERROR'     => 'bg-amber-100 text-amber-700',
                    ];
                    $cls = $map[$value] ?? 'bg-slate-100 text-slate-700';
                    return view('admin.invoices.partials.badge', [
                        'label' => $value,
                        'cls'   => $cls,
                    ]);
                })->html(),

            Column::make('Acciones')
                ->label(fn ($row) => view('admin.invoices.partials.actions', ['invoice' => $row]))
                ->html(),
        ];
    }
}
