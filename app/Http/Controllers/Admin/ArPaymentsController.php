<?php
// app/Http/Controllers/Admin/ArPaymentsController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PaymentType;
use App\Services\ArService;
use Illuminate\Http\Request;

class ArPaymentsController extends Controller
{
    public function __construct(private ArService $ar) {}

    public function create()
{
    $clients     = Client::where('activo', 1)->orderBy('nombre')->get();
    $types       = PaymentType::orderBy('descripcion')->get();
    $preClientId = request('client_id'); // ← agrega esta línea
    return view('admin.ar.payments.create', compact('clients', 'types', 'preClientId'));
}

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'fecha'           => 'required|date',
            'amount'          => 'required|numeric|min:0.01',
            'payment_type_id' => 'required|exists:payment_types,id',
            'reference'       => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        $this->ar->payment(
            $data['client_id'],
            $data['amount'],
            $data['payment_type_id'],
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $data['fecha']
        );

        session()->flash('swal', ['icon'=>'success','title'=>'Cobro registrado','text'=>'El pago se aplicó correctamente.']);
        return redirect()->route('admin.ar.index');
    }
}
