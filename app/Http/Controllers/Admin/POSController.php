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
    $reg = CashRegister::where('user_id', auth()->id())
        ->where('fecha', now()->toDateString())
        ->where('estatus','ABIERTO')
        ->latest('id')
        ->first();

    if (!$reg) {
        session()->flash('swal',['icon'=>'warning','title'=>'Sin caja abierta','text'=>'Abre tu caja antes de vender.']);
        return redirect()->route('admin.cash.create');
    }

    $clients  = Client::where('activo', 1)->orderBy('nombre')->get();
    $products = Product::where('activo', 1)->orderBy('nombre')->get(['id','nombre','sku','precio_base','tasa_iva','unidad']);

    $productsJson = $products->map(fn($p) => [
        'id'       => $p->id,
        'nombre'   => $p->nombre,
        'sku'      => $p->sku ?? '',
        'precio'   => (float) $p->precio_base,
        'tasa_iva' => (float) $p->tasa_iva,
        'unidad'   => $p->unidad,
    ]);

    return view('admin.pos.create', compact('reg', 'clients', 'products', 'productsJson'));
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
  public function ticket(\App\Models\PosSale $sale)
{
    $sale->load('items.product', 'client');
    $company = \App\Models\Company::first();
    return view('admin.pos.ticket', compact('sale', 'company'));
}


 public function ticketPdf(\App\Models\PosSale $sale)
{
    $sale->load('items.product', 'client');
    $company = \App\Models\Company::first();

    $ancho = 226.77;
    $alto  = 1200;

    $pdf = Pdf::loadView('admin.pos.ticket-pdf', compact('sale', 'company'))
        ->setPaper([0, 0, $ancho, $alto], 'portrait');

    return $pdf->stream("ticket-pos-{$sale->id}.pdf");
}

public function sendWhatsapp(\App\Models\PosSale $sale, \Illuminate\Http\Request $request)
{
    try {
        $sale->load('items.product', 'client');
        $company = \App\Models\Company::first();

        $telefono = $request->input('telefono');
        $nombre   = $sale->client?->nombre ?? 'Cliente';
        $total    = number_format($sale->total, 2);
        $pdfUrl   = route('admin.pos.ticket.pdf', $sale);

        $mensaje = "Hola {$nombre}, gracias por tu compra en "
            . ($company?->nombre_comercial ?? 'nuestra tienda') . ".\n"
            . "Ticket #{$sale->id} por \${$total}.\n"
            . "Descarga tu ticket aquí: {$pdfUrl}";
$pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.pos.ticket-pdf', compact('sale', 'company'));
        $pdfRaw = $pdf->output();

        $sender = new \App\Services\WhatsappSender();
        $result = $sender->sendPdf(
            telefono: $telefono,
            mensaje:  $mensaje,
            filename: 'ticket-' . $sale->id . '.pdf',
            pdfRaw:   $pdfRaw,
        );

        return response()->json([
            'ok'      => $result['ok'],
            'message' => $result['ok'] ? 'Enviado correctamente' : 'Error al enviar',
            'body'    => $result['body'],
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'ok'      => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
}
