<?php


namespace App\Livewire\Admin\Datatables;

use App\Models\Client;
use App\Models\AccountReceivable;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class ArAccountsTable extends Component
{
    use WithPagination;
    public $search = '';

    public function render()
    {
        // saldo por cliente (cargos - abonos)
        $rows = Client::query()
            ->when($this->search, fn($q)=> $q->where('nombre','like',"%{$this->search}%"))
            ->select('clients.id','clients.nombre')
            ->addSelect([
                'saldo' => AccountReceivable::selectRaw("COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0)")
                    ->whereColumn('client_id','clients.id')
            ])
            ->orderBy('nombre')
            ->paginate(10);

        return view('livewire.admin.datatables.ar-accounts-table', compact('rows'));
    }
}
