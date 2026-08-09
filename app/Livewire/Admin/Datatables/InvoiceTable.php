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
    public function updatingFechaDesde():       void { $this->resetPage(); }
    public function updatingFechaHasta():       void { $this->resetPage(); }
    public function updatingPerPage():          void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'tipoComprobante', 'estatus', 'fechaDesde', 'fechaHasta']);
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

    public function render()
    {
        $q = Invoice::with('client')
            ->when($this->search, function ($q) {
                $t = '%' . $this->search . '%';
                $q->where(fn($q) =>
                    $q->where('folio', 'like', $t)
                      ->orWhere('serie', 'like', $t)
                      ->orWhereHas('client', fn($q) => $q->where('nombre', 'like', $t))
                );
            })
            ->when($this->tipoComprobante, fn($q) => $q->where('tipo_comprobante', $this->tipoComprobante))
            ->when($this->estatus,         fn($q) => $q->where('estatus', $this->estatus))
            ->when($this->fechaDesde,      fn($q) => $q->whereDate('fecha', '>=', $this->fechaDesde))
            ->when($this->fechaHasta,      fn($q) => $q->whereDate('fecha', '<=', $this->fechaHasta))
            ->orderBy($this->sortBy, $this->sortDir);

        return view('livewire.admin.datatables.invoice-table', [
            'invoices' => $q->paginate($this->perPage),
        ]);
    }
}
