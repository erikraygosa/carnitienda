<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\ArMovement;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class ArClientLedgerTable extends Component
{
    use WithPagination;

    public int    $clientId;
    public string $search   = '';
    public string $tipo     = '';  // '' | CARGO | ABONO
    public int    $perPage  = 20;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingTipo(): void   { $this->resetPage(); }

    public function render()
    {
        // Saldo acumulado usando window function (MySQL 8+)
        // Calculamos el saldo corrido desde el más antiguo al más nuevo
        $subquery = ArMovement::where('client_id', $this->clientId)
            ->selectRaw("
                id, client_id, fecha, tipo, monto, descripcion,
                source_type, source_id, created_at,
                SUM(CASE WHEN tipo = 'CARGO' THEN monto ELSE -monto END)
                    OVER (ORDER BY fecha ASC, id ASC) AS saldo_acumulado
            ")
            ->toSql();

        $bindings = [$this->clientId];

        $query = DB::table(DB::raw("({$subquery}) as ledger"))
            ->setBindings($bindings)
            ->when($this->search, fn($q) =>
                $q->where('descripcion', 'like', "%{$this->search}%")
            )
            ->when($this->tipo, fn($q) =>
                $q->where('tipo', $this->tipo)
            )
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $movimientos = $query->paginate($this->perPage);

        // Saldo actual total del cliente
        $saldoActual = ArMovement::where('client_id', $this->clientId)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END), 0) as saldo")
            ->value('saldo') ?? 0;

        return view('livewire.admin.datatables.ar-client-ledger-table',
            compact('movimientos', 'saldoActual'));
    }
}