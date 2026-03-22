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
        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setPerPage(10)
            ->setPerPageAccepted([10, 25, 50, 100]);
    }

    public function builder(): Builder
    {
        return Invoice::query()
            ->select('invoices.*')
            ->with(['client']);
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')->sortable()->collapseOnMobile(),

            Column::make('Folio', 'folio')
                ->searchable()
                ->sortable()
                ->format(fn($v, $row) => ($row->serie ?? '') . ($row->folio ?? '—')),

            Column::make('Cliente', 'client.nombre')
                ->format(fn($v, $row) => $row->client?->nombre ?? '—')
                ->searchable(),

            Column::make('Fecha', 'fecha')
                ->sortable()
                ->format(fn($v) => optional($v)->format('d/m/Y') ?? '—'),

            Column::make('Estatus', 'estatus')
                ->sortable()
                ->format(fn($v, $row) => $row->estatus ?? '—'),

            Column::make('Total', 'total')
                ->sortable()
                ->format(fn($v, $row) => ($row->moneda ?? 'MXN') . ' ' . number_format((float)$v, 2)),

            Column::make('Acciones')
                ->label(fn($row) => view('admin.invoices.partials.actions', ['invoice' => $row])->render())
                ->html(),
        ];
    }
}
