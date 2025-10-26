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

    public function index()
    {
        return view('admin.ar.index'); // datatable Livewire por cliente
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
