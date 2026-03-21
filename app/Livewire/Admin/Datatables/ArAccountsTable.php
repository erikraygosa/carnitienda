<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Client;
use App\Models\ArMovement;
use App\Models\ArPayment;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class ArAccountsTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filtro = 'todos'; // todos | con_saldo | vencidos

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFiltro(): void { $this->resetPage(); }

    public function render()
    {
        $rows = Client::query()
            ->when($this->search, fn($q) =>
                $q->where('nombre', 'like', "%{$this->search}%")
                  ->orWhere('rfc', 'like', "%{$this->search}%")
            )
            ->select('clients.id', 'clients.nombre', 'clients.credito_limite', 'clients.credito_dias')
            // Saldo total = cargos - abonos
            ->addSelect([
                'saldo' => ArMovement::selectRaw(
                    "COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END), 0)"
                )->whereColumn('client_id', 'clients.id'),
            ])
            // Último pago
            ->addSelect([
                'ultimo_pago' => ArMovement::select('fecha')
                    ->whereColumn('client_id', 'clients.id')
                    ->where('tipo', 'ABONO')
                    ->latest('fecha')
                    ->limit(1),
            ])
            // Cargo más antiguo sin abonar (para calcular días vencido)
            ->addSelect([
                'cargo_mas_antiguo' => ArMovement::select('fecha')
                    ->whereColumn('client_id', 'clients.id')
                    ->where('tipo', 'CARGO')
                    ->oldest('fecha')
                    ->limit(1),
            ])
            ->when($this->filtro === 'con_saldo', fn($q) =>
                $q->havingRaw(
                    "COALESCE((SELECT SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END) FROM ar_movements WHERE client_id = clients.id), 0) > 0"
                )
            )
            ->when($this->filtro === 'vencidos', fn($q) =>
                $q->havingRaw(
                    "COALESCE((SELECT SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END) FROM ar_movements WHERE client_id = clients.id), 0) > 0
                     AND COALESCE((SELECT MIN(fecha) FROM ar_movements WHERE client_id = clients.id AND tipo = 'CARGO'), CURDATE()) < DATE_SUB(CURDATE(), INTERVAL COALESCE(clients.credito_dias, 30) DAY)"
                )
            )
            ->orderByRaw(
                "COALESCE((SELECT SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END) FROM ar_movements WHERE client_id = clients.id), 0) DESC"
            )
            ->paginate(15);

        // Totales para el header
        $totales = DB::selectOne("
            SELECT
                COALESCE(SUM(CASE WHEN tipo='CARGO'  THEN monto ELSE 0 END), 0) AS total_cargos,
                COALESCE(SUM(CASE WHEN tipo='ABONO'  THEN monto ELSE 0 END), 0) AS total_abonos
            FROM ar_movements
        ");
        $saldoGlobal = ($totales->total_cargos ?? 0) - ($totales->total_abonos ?? 0);

        return view('livewire.admin.datatables.ar-accounts-table', compact('rows', 'saldoGlobal'));
    }
}