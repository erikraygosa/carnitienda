<?php

// app/Livewire/Admin/Datatables/ArClientLedgerTable.php
namespace App\Livewire\Admin\Datatables;

use App\Models\AccountReceivable;
use Livewire\Component;
use Livewire\WithPagination;

class ArClientLedgerTable extends Component
{
    use WithPagination;
    public int $clientId;

    public function render()
    {
        $rows = AccountReceivable::where('client_id',$this->clientId)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15);

        return view('livewire.admin.datatables.ar-client-ledger-table', compact('rows'));
    }
}
