<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\SalesOrder;
use Livewire\Component;
use Livewire\WithPagination;

class SalesOrderTable extends Component
{
    use WithPagination;

    public string $search    = '';
    public string $status    = '';
    public string $fechaDesde= '';
    public string $fechaHasta= '';
    public string $sortBy    = 'id';
    public string $sortDir   = 'desc';
    public int    $perPage   = 15;

    public function updatingSearch():    void { $this->resetPage(); }
    public function updatingStatus():    void { $this->resetPage(); }
    public function updatingFechaDesde():void { $this->resetPage(); }
    public function updatingFechaHasta():void { $this->resetPage(); }
    public function updatingPerPage():   void { $this->resetPage(); }

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
        $q = SalesOrder::with(['client','warehouse'])
            ->when($this->search, function($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn($q) =>
                    $q->where('folio','like',$t)
                      ->orWhereHas('client', fn($q) => $q->where('nombre','like',$t))
                );
            })
            ->when($this->status,     fn($q) => $q->where('status', $this->status))
            ->when($this->fechaDesde, fn($q) => $q->whereDate('fecha', '>=', $this->fechaDesde))
            ->when($this->fechaHasta, fn($q) => $q->whereDate('fecha', '<=', $this->fechaHasta))
            ->orderBy($this->sortBy, $this->sortDir);

        return view('livewire.admin.datatables.sales-order-table', [
            'orders' => $q->paginate($this->perPage),
        ]);
    }
}