<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\DriverCashRegister;
use Livewire\Component;
use Livewire\WithPagination;

class DriverCashRegistersTable extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch(){ $this->resetPage(); }

    public function render()
    {
        $rows = DriverCashRegister::query()
            // Cargar chofer con la columna correcta
            ->with(['driver:id,nombre'])   // ← antes pedía name
            ->when($this->search, function ($q) {
                $s = $this->search;
                $q->where(function ($qq) use ($s) {
                    $qq->where('fecha', 'like', "%{$s}%")
                       ->orWhereHas('driver', function ($d) use ($s) {
                           // Buscar por nombre/telefono/licencia
                           $d->where('nombre', 'like', "%{$s}%")
                             ->orWhere('telefono', 'like', "%{$s}%")
                             ->orWhere('licencia', 'like', "%{$s}%");
                       });
                });
            })
            ->orderByDesc('fecha')
            ->paginate(10);

        return view('livewire.admin.datatables.driver-cash-registers-table', compact('rows'));
    }
}
