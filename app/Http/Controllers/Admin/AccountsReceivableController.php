<?php
// app/Http/Controllers/Admin/AccountsReceivableController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ArService;
use Illuminate\Http\Request;

class AccountsReceivableController extends Controller
{
    public function __construct(private ArService $ar) {}

   public function index(Request $request)
{
    $search = $request->get('search', '');
    $filtro = $request->get('filtro', 'todos'); // todos | con_saldo | vencidos

    $rows = Client::query()
        ->when($search, fn($q) =>
            $q->where('nombre', 'like', "%{$search}%")
              ->orWhere('rfc', 'like', "%{$search}%")
        )
        ->select('clients.id', 'clients.nombre', 'clients.credito_limite', 'clients.credito_dias')
        ->addSelect([
            'saldo' => \App\Models\ArMovement::selectRaw(
                "COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END), 0)"
            )->whereColumn('client_id', 'clients.id'),
        ])
        ->addSelect([
            'ultimo_pago' => \App\Models\ArMovement::select('fecha')
                ->whereColumn('client_id', 'clients.id')
                ->where('tipo', 'ABONO')
                ->latest('fecha')
                ->limit(1),
        ])
        ->addSelect([
            'cargo_mas_antiguo' => \App\Models\ArMovement::select('fecha')
                ->whereColumn('client_id', 'clients.id')
                ->where('tipo', 'CARGO')
                ->oldest('fecha')
                ->limit(1),
        ])
        ->when($filtro === 'con_saldo', fn($q) =>
            $q->havingRaw(
                "COALESCE((SELECT SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END) FROM ar_movements WHERE client_id = clients.id), 0) > 0"
            )
        )
        ->when($filtro === 'vencidos', fn($q) =>
            $q->havingRaw(
                "COALESCE((SELECT SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END) FROM ar_movements WHERE client_id = clients.id), 0) > 0
                 AND COALESCE((SELECT MIN(fecha) FROM ar_movements WHERE client_id = clients.id AND tipo = 'CARGO'), CURDATE()) < DATE_SUB(CURDATE(), INTERVAL COALESCE(clients.credito_dias, 30) DAY)"
            )
        )
        ->orderByRaw(
            "COALESCE((SELECT SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END) FROM ar_movements WHERE client_id = clients.id), 0) DESC"
        )
        ->paginate(20)
        ->withQueryString();

    $totales = \Illuminate\Support\Facades\DB::selectOne("
        SELECT
            COALESCE(SUM(CASE WHEN tipo='CARGO'  THEN monto ELSE 0 END), 0) AS total_cargos,
            COALESCE(SUM(CASE WHEN tipo='ABONO'  THEN monto ELSE 0 END), 0) AS total_abonos
        FROM ar_movements
    ");
    $saldoGlobal = ($totales->total_cargos ?? 0) - ($totales->total_abonos ?? 0);

    return view('admin.ar.index', compact('rows', 'saldoGlobal', 'search', 'filtro'));
}

    public function show(Client $client)
    {
        // Vista con movimientos del cliente y saldo
        return view('admin.ar.show', [
            'client' => $client,
            'saldo'  => $this->ar->saldoCliente($client->id),
        ]);
    }

    /** (Opcional) CARGO manual */
    public function charge(Request $request, Client $client)
    {
        $data = $request->validate([
            'monto'       => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string',
            'fecha'       => 'nullable|date'
        ]);
        $this->ar->charge($client->id, $data['monto'], $data['descripcion'] ?? null, null, $data['fecha'] ?? null);

        session()->flash('swal',['icon'=>'success','title'=>'Cargo registrado','text'=>'Se agregó al estado de cuenta.']);
        return back();
    }
}
