<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceTable extends Component
{
    use WithPagination;

    public string $search          = '';
    public string $tipoComprobante = '';
    public string $estatus         = '';
    public string $formaPago       = '';
    public string $metodoPago      = '';
    public string $campoFecha      = 'fecha'; // fecha (elaboración) | fecha_timbrado (certificación SAT)
    public string $fechaDesde      = '';
    public string $fechaHasta      = '';
    public string $sortBy          = 'id';
    public string $sortDir         = 'desc';
    public int    $perPage         = 15;

    public function mount(): void
    {
        // Por default solo el mes actual, para no cargar todo el histórico.
        $this->fechaDesde = now()->startOfMonth()->toDateString();
        $this->fechaHasta = now()->endOfMonth()->toDateString();
    }

    public function updatingSearch():          void { $this->resetPage(); }
    public function updatingTipoComprobante():  void { $this->resetPage(); }
    public function updatingEstatus():          void { $this->resetPage(); }
    public function updatingFormaPago():        void { $this->resetPage(); }
    public function updatingMetodoPago():       void { $this->resetPage(); }
    public function updatingCampoFecha():       void { $this->resetPage(); }
    public function updatingFechaDesde():       void { $this->resetPage(); }
    public function updatingFechaHasta():       void { $this->resetPage(); }
    public function updatingPerPage():          void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'tipoComprobante', 'estatus', 'formaPago', 'metodoPago', 'campoFecha', 'fechaDesde', 'fechaHasta']);
        $this->resetPage();
    }

    public function sort(string $col): void
    {
        $this->sortDir = $this->sortBy === $col
            ? ($this->sortDir === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sortBy = $col;
        $this->resetPage();
    }

    /** Filtros actuales, en el formato que entiende Invoice::filtrarListado() y el Excel. */
    public function filtros(): array
    {
        return [
            'search'      => $this->search,
            'tipo'        => $this->tipoComprobante,
            'estatus'     => $this->estatus,
            'forma_pago'  => $this->formaPago,
            'metodo_pago' => $this->metodoPago,
            'campo_fecha' => $this->campoFecha,
            'desde'       => $this->fechaDesde,
            'hasta'       => $this->fechaHasta,
        ];
    }

    public function render()
    {
        $q = Invoice::with('client')->filtrarListado($this->filtros());

        // Totales de TODO lo filtrado (no solo la página): vigentes suman, notas de crédito restan.
        $vigentes = (clone $q)->whereIn('estatus', ['TIMBRADA', 'CANCELACION_PENDIENTE']);
        $totales = [
            'facturas' => (clone $vigentes)->where('tipo_comprobante', 'I')->count(),
            'neto'     => (float) (clone $vigentes)->where('tipo_comprobante', 'I')->sum('total')
                        - (float) (clone $vigentes)->where('tipo_comprobante', 'E')->sum('total'),
            'registros' => (clone $q)->count(),
        ];

        return view('livewire.admin.datatables.invoice-table', [
            'invoices' => $q->orderBy($this->sortBy, $this->sortDir)->paginate($this->perPage),
            'totales'  => $totales,
        ]);
    }
}
