<?php

// app/Http/Controllers/Admin/POSController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Product;
use App\Services\PosService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf; // arriba
use Dompdf\Options;

class POSController extends Controller
{
  public function __construct(private PosService $pos) {}

  public function create(){
    $reg = CashRegister::where('user_id', auth()->id())->where('fecha', now()->toDateString())->where('estatus','ABIERTO')->latest('id')->first();
    if (!$reg) { session()->flash('swal',['icon'=>'warning','title'=>'Sin caja abierta','text'=>'Abre tu caja antes de vender.']); return redirect()->route('admin.cash.create'); }
    $clients = Client::where('activo',1)->orderBy('nombre')->get();
    $products = Product::orderBy('nombre')->limit(50)->get();
    return view('admin.pos.create', compact('reg','clients','products'));
  }

  public function store(Request $r){
    $data = $r->validate([
      'cash_register_id'=>'required|exists:cash_registers,id',
      'client_id'=>'nullable|exists:clients,id',
      'fecha'=>'required|date',
      'metodo_pago'=>'required|in:EFECTIVO,TARJETA,TRANSFERENCIA,MIXTO,OTRO',
      'efectivo'=>'nullable|numeric|min:0',
      'cambio'=>'nullable|numeric|min:0',
      'referencia'=>'nullable|string|max:255',
      'items'=>'required|array|min:1',
      'items.*.product_id'=>'required|exists:products,id',
      'items.*.cantidad'=>'required|numeric|min:0.001',
      'items.*.precio_unitario'=>'required|numeric|min:0',
      'items.*.descuento'=>'nullable|numeric|min:0',
      'items.*.impuestos'=>'nullable|numeric|min:0',
    ]);
    $reg = CashRegister::findOrFail($data['cash_register_id']);
    $sale = $this->pos->createSale($reg, $data, $data['items']);
    session()->flash('swal',['icon'=>'success','title'=>'Venta registrada','text'=>'La venta fue generada.']);
    return redirect()->route('admin.pos.ticket', $sale);
  }

  public function ticket(\App\Models\PosSale $sale){
    $sale->load('items','client');
    return view('admin.pos.ticket', compact('sale'));
  }
  public function ticketPdf(\App\Models\PosSale $sale)
{
    $sale->load('items.product','client');

    // 80mm de ancho = 226.77pt; alto flexible (aumenta si hay más partidas)
    // Definimos un alto suficientemente grande; Dompdf corta el sobrante.
    $ancho = 226.77;      // 80 mm
    $alto  = 1200;        // ajustable

    $pdf = Pdf::loadView('admin.pos.ticket-pdf', compact('sale'))
        ->setPaper([0, 0, $ancho, $alto], 'portrait'); // custom size

    // Para ver en navegador:
    return $pdf->stream("ticket-pos-{$sale->id}.pdf");

    // Para descargar automáticamente:
    // return $pdf->download("ticket-pos-{$sale->id}.pdf");
}
}
