<?php

namespace App\Livewire\Admin\Datatables;
use App\Models\CashRegister;
use Livewire\Component; use Livewire\WithPagination;

class CashRegistersTable extends Component {
  use WithPagination; public $search='';
  public function updatingSearch(){ $this->resetPage(); }
  public function render(){
    $rows = CashRegister::with(['warehouse:id,nombre','user:id,name'])
      ->when($this->search, fn($q)=>$q->where('fecha','like',"%{$this->search}%"))
      ->orderByDesc('fecha')->paginate(10);
    return view('livewire.admin.datatables.cash-registers-table', compact('rows'));
  }
}