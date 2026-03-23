<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Invoice, InvoiceItem, Client, SalesOrder, Sale, Product};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\PacCfdiService;
use App\Services\CompanyService;

class InvoiceController extends Controller
{
    public function index()
    {
           $invoices = Invoice::with('client')
        ->latest('id')
        ->paginate(20);

    return view('admin.invoices.index', compact('invoices'));
    }

    // Crear desde: pedido, venta o directa
    public function create(Request $req)
{
    $fromOrderId = $req->query('order_id');
    $fromSaleId  = $req->query('sale_id');

    $clients = Client::orderBy('nombre')->get([
        'id', 'nombre', 'rfc', 'razon_social',
        'cp', 'fiscal_cp', 'regimen_fiscal', 'uso_cfdi_default',
        'tipo_persona',
    ]);

    $products = Product::orderBy('nombre')->get([
        'id', 'nombre', 'precio_base',
        'clave_prod_serv', 'clave_unidad', 'unidad',
    ]);

    $empresa    = app(CompanyService::class)->activa();
    $fiscalData = $empresa?->fiscalData;

    $emisorDefaults = [
        'lugar_expedicion'      => $fiscalData?->codigo_postal ?? '',
        'regimen_fiscal_emisor' => $fiscalData?->regimen_fiscal ?? '',
        'rfc_emisor'            => $empresa?->rfc ?? '',
        'razon_social_emisor'   => $empresa?->razon_social ?? '',
    ];

    $prefill = null;

    if ($fromOrderId) {
        $order   = SalesOrder::with('items.product', 'client')->findOrFail($fromOrderId);
        $prefill = $this->mapFromOrder($order);
    } elseif ($fromSaleId) {
        $sale    = Sale::with('items.product', 'client')->findOrFail($fromSaleId);
        $prefill = $this->mapFromSale($sale);
    }

    $clientsMap = $clients->keyBy('id')->map(fn($c) => [
        'rfc'            => $c->rfc ?? '',
        'razon_social'   => $c->razon_social ?? $c->nombre ?? '',
        'regimen_fiscal' => $c->regimen_fiscal ?? '',
        'uso_cfdi'       => $c->uso_cfdi_default ?? 'G03',
        'fiscal_cp'      => $c->fiscal_cp ?? $c->cp ?? '',
    ]);

    $productsMap = $products->keyBy('id')->map(fn($p) => [
        'nombre'          => $p->nombre,
        'precio_base'     => (float) ($p->precio_base ?? 0),
        'clave_prod_serv' => $p->clave_prod_serv ?? '01010101',
        'clave_unidad'    => $p->clave_unidad ?? 'H87',
        'unidad'          => $p->unidad ?? 'PZA',
    ]);

    // Serie y folio desde configuración
    $series = \App\Models\InvoiceSeries::where('es_default', 1)
        ->where('tipo_comprobante', 'I')
        ->first();

    $nextSerie = $series?->serie ?? 'A';
    $nextFolio = $series ? ($series->folio_actual + 1) : 1;

    return view('admin.invoices.create', compact(
        'clients', 'products', 'prefill',
        'empresa', 'emisorDefaults',
        'clientsMap', 'productsMap',
        'nextSerie', 'nextFolio'
    ));
}

public function store(Request $request)
{
    $data = $request->validate([
        'client_id'               => ['required', 'exists:clients,id'],
        'sales_order_id'          => ['nullable', 'exists:sales_orders,id'],
        'sale_id'                 => ['nullable', 'exists:sales,id'],
        'serie'                   => ['nullable', 'string', 'max:10'],
        'folio'                   => ['nullable', 'string', 'max:20'],
        'fecha'                   => ['required', 'date'],
        'tipo_comprobante'        => ['required', 'in:I,E,P,N'],
        'moneda'                  => ['required', 'string', 'max:5'],
        'uso_cfdi'                => ['required', 'string', 'max:5'],
        'forma_pago'              => ['nullable', 'string', 'max:3'],
        'metodo_pago'             => ['nullable', 'string', 'max:3'],
        'lugar_expedicion'        => ['required', 'string', 'max:10'],
        'regimen_fiscal_emisor'   => ['required', 'string', 'max:3'],
        'regimen_fiscal_receptor' => ['required', 'string', 'max:3'],
        'items'                   => ['required', 'array', 'min:1'],
        'items.*.product_id'      => ['nullable', 'exists:products,id'],
        'items.*.descripcion'     => ['required', 'string', 'max:255'],
        'items.*.clave_prod_serv' => ['nullable', 'string', 'max:8'],
        'items.*.clave_unidad'    => ['nullable', 'string', 'max:3'],
        'items.*.unidad'          => ['nullable', 'string', 'max:20'],
        'items.*.cantidad'        => ['required', 'numeric', 'gt:0'],
        'items.*.valor_unitario'  => ['required', 'numeric', 'gte:0'],
        'items.*.descuento'       => ['nullable', 'numeric', 'gte:0'],
        'items.*.objeto_imp'      => ['required', 'in:01,02,03'],
        'items.*.iva_pct'         => ['nullable', 'numeric', 'gte:0'],
        'items.*.ieps_pct'        => ['nullable', 'numeric', 'gte:0'],
    ]);

    if (!empty($data['sales_order_id']) && !empty($data['sale_id'])) {
        return back()
            ->with('swal', ['icon' => 'error', 'title' => 'Datos inválidos', 'text' => 'Elige pedido o nota, no ambos.'])
            ->withInput();
    }

    $invoice = null;

    DB::transaction(function () use (&$invoice, $data) {
        $subtotal  = 0;
        $iva       = 0;
        $ieps      = 0;
        $impuestos = 0;
        $total     = 0;

        $invoice = Invoice::create([
            'client_id'               => $data['client_id'],
            'sales_order_id'          => $data['sales_order_id'] ?? null,
            'sale_id'                 => $data['sale_id'] ?? null,
            'serie'                   => $data['serie'] ?? null,
            'folio'                   => $data['folio'] ?? null,
            'fecha'                   => $data['fecha'],
            'tipo_comprobante'        => $data['tipo_comprobante'],
            'moneda'                  => $data['moneda'],
            'uso_cfdi'                => $data['uso_cfdi'],
            'forma_pago'              => $data['forma_pago'] ?? null,
            'metodo_pago'             => $data['metodo_pago'] ?? null,
            'lugar_expedicion'        => $data['lugar_expedicion'],
            'regimen_fiscal_emisor'   => $data['regimen_fiscal_emisor'],
            'regimen_fiscal_receptor' => $data['regimen_fiscal_receptor'],
            'estatus'                 => 'BORRADOR',
            'version_cfdi'            => '4.0',
            'created_by'              => auth()->id(),
            'owner_id'                => auth()->id(),
        ]);

        foreach ($data['items'] as $row) {
            $cantidad = (float) $row['cantidad'];
            $vu       = (float) $row['valor_unitario'];
            $desc     = (float) ($row['descuento'] ?? 0);

            $linea    = $cantidad * $vu;
            $base     = max($linea - $desc, 0);
            $iva_pct  = (float) ($row['iva_pct']  ?? 0) / 100;
            $ieps_pct = (float) ($row['ieps_pct'] ?? 0) / 100;

            $iva_imp  = round($base * $iva_pct,  6);
            $ieps_imp = round($base * $ieps_pct, 6);
            $importe  = $base + $iva_imp + $ieps_imp;

            InvoiceItem::create([
                'invoice_id'          => $invoice->id,
                'product_id'          => $row['product_id'] ?? null,
                'clave_prod_serv'     => $row['clave_prod_serv'] ?? null,
                'clave_unidad'        => $row['clave_unidad'] ?? null,
                'unidad'              => $row['unidad'] ?? null,
                'descripcion'         => $row['descripcion'],
                'cantidad'            => $cantidad,
                'valor_unitario'      => $vu,
                'precio_unitario'     => $vu,
                'descuento'           => $desc,
                'objeto_imp'          => $row['objeto_imp'],
                'base'                => $base,
                'iva_pct'             => (float) ($row['iva_pct']  ?? 0),
                'iva_importe'         => $iva_imp,
                'ieps_pct'            => (float) ($row['ieps_pct'] ?? 0),
                'ieps_importe'        => $ieps_imp,
                'importe'             => $importe,
                'impuesto_trasladado' => $iva_imp,
                'total'               => $importe,
            ]);

            $subtotal += $linea;
            $iva      += $iva_imp;
            $ieps     += $ieps_imp;
            $total    += $importe;
        }

        $impuestos = $iva + $ieps;

        $invoice->update([
            'subtotal'  => $subtotal,
            'impuestos' => $impuestos,
            'total'     => $total,
        ]);

        

    
    });
    $series = \App\Models\InvoiceSeries::where('es_default', 1)
    ->where('tipo_comprobante', 'I')
    ->first();
if ($series && $data['folio']) {
    // Solo actualizar si el folio guardado es mayor al actual
    $folioGuardado = (int) $data['folio'];
    if ($folioGuardado > $series->folio_actual) {
        $series->update(['folio_actual' => $folioGuardado]);
    }
}


    return redirect()->route('admin.invoices.edit', $invoice)
        ->with('swal', ['icon' => 'success', 'title' => 'Creada', 'text' => 'Factura en borrador creada.']);
}

    public function edit(Invoice $invoice)
{
    $invoice->load('client', 'items.product', 'salesOrder', 'sale');

    $clients = Client::orderBy('nombre')->get([
        'id', 'nombre', 'rfc', 'razon_social',
        'cp', 'fiscal_cp', 'regimen_fiscal', 'uso_cfdi_default',
        'tipo_persona',
    ]);

    $products = Product::orderBy('nombre')->get([
        'id', 'nombre', 'precio_base',
        'clave_prod_serv', 'clave_unidad', 'unidad',
    ]);

    $empresa    = app(CompanyService::class)->activa();
    $fiscalData = $empresa?->fiscalData;

    $emisorDefaults = [
        'lugar_expedicion'      => $fiscalData?->codigo_postal ?? '',
        'regimen_fiscal_emisor' => $fiscalData?->regimen_fiscal ?? '',
        'rfc_emisor'            => $empresa?->rfc ?? '',
        'razon_social_emisor'   => $empresa?->razon_social ?? '',
    ];

    $clientsMap = $clients->keyBy('id')->map(fn($c) => [
        'rfc'            => $c->rfc ?? '',
        'razon_social'   => $c->razon_social ?? $c->nombre ?? '',
        'regimen_fiscal' => $c->regimen_fiscal ?? '',
        'uso_cfdi'       => $c->uso_cfdi_default ?? 'G03',
        'fiscal_cp'      => $c->fiscal_cp ?? $c->cp ?? '',
    ]);

    $productsMap = $products->keyBy('id')->map(fn($p) => [
        'nombre'          => $p->nombre,
        'precio_base'     => (float) ($p->precio_base ?? 0),
        'clave_prod_serv' => $p->clave_prod_serv ?? '01010101',
        'clave_unidad'    => $p->clave_unidad ?? 'H87',
        'unidad'          => $p->unidad ?? 'PZA',
    ]);

    return view('admin.invoices.edit', compact(
    'invoice', 'clients', 'products',
    'empresa', 'emisorDefaults',
    'clientsMap', 'productsMap'
));
}
    // TIMBRAR
   

// En el método stamp():
    public function stamp(Invoice $invoice, PacCfdiService $pac, CompanyService $company)
{
    if (! $invoice->isDraft()) {
        return back()->with('swal', ['icon'=>'error','title'=>'No permitido','text'=>'Solo BORRADOR se puede timbrar.']);
    }

    $empresa = $invoice->company ?? $company->activa();

    if (! $empresa) {
        return back()->with('swal', ['icon'=>'error','title'=>'Sin empresa','text'=>'No hay empresa activa configurada.']);
    }

    // Solo validar CSD en producción
    $pacConfig = \App\Models\PacConfiguration::activo()->first();
    $esSandbox = $pacConfig?->ambiente === 'sandbox';

    if (! $esSandbox && ! $empresa->tieneCsd()) {
        return back()->with('swal', ['icon'=>'error','title'=>'Sin CSD','text'=>'La empresa no tiene Sello Digital (CSD) vigente.']);
    }

    if (! $esSandbox && ! $empresa->tieneConfiguracionCompleta()) {
        return back()->with('swal', ['icon'=>'error','title'=>'Configuración incompleta','text'=>'Completa los datos fiscales antes de timbrar.']);
    }

    $invoice->loadMissing(['items', 'client', 'company.fiscalData']);

    $xml    = $pac->buildXml($invoice, $empresa);
    $result = $pac->stamp($invoice, $xml, $empresa);

    if (! ($result['ok'] ?? false)) {
        return back()->with('swal', ['icon'=>'error','title'=>'Error PAC','text'=>$result['error'] ?? 'Fallo al timbrar.']);
    }

    return back()->with('swal', ['icon'=>'success','title'=>'Timbrada','text'=>'Factura timbrada. UUID: ' . $result['uuid']]);
}

    // CANCELAR CFDI
    public function cancel(Request $request, Invoice $invoice, PacCfdiService $pac)
    {
        if (!$invoice->isStamped()) {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo TIMBRADA puede cancelarse.']);
        }

        $data = $request->validate([
            'motivo' => ['required','in:01,02,03,04'],
            'folio_sustitucion' => ['nullable','string','max:50']
        ]);

        $resp = $pac->cancel($invoice, $data['motivo'], $data['folio_sustitucion'] ?? null);

        if (!($resp['ok'] ?? false)) {
            return back()->with('swal',['icon'=>'error','title'=>'Error PAC','text'=>$resp['error'] ?? 'Fallo al cancelar.']);
        }

        $invoice->update(['estatus'=>'CANCELADA']);

        return back()->with('swal',['icon'=>'success','title'=>'Cancelada','text'=>'Factura cancelada en SAT.']);
    }

    // PDF y envío (opcional: usa tu layout PDF)
    public function pdf(Invoice $invoice)
{
    $invoice->load('client', 'items', 'company.fiscalData');

    $empresa = $invoice->company
        ?? \App\Models\Company::first(); // fallback directo a BD

    $pdf = Pdf::loadView('pdf.invoice', [
        'invoice'       => $invoice,
        'empresaActiva' => $empresa,
    ]);

    return $pdf->stream('factura-' . $invoice->serie . $invoice->folio . '.pdf');
}

public function pdfDownload(Invoice $invoice)
{
    $invoice->load('client', 'items', 'company.fiscalData');

    $empresa = $invoice->company
        ?? \App\Models\Company::first();

    $pdf = Pdf::loadView('pdf.invoice', [
        'invoice'       => $invoice,
        'empresaActiva' => $empresa,
    ]);

    return $pdf->download('factura-' . $invoice->serie . $invoice->folio . '.pdf');
}

    // ===== Helpers para precargar desde pedido/nota =====
    protected function mapFromOrder(SalesOrder $order): array
    {
        return [
            'client_id' => $order->client_id,
            'moneda'    => $order->moneda,
            'items'     => $order->items->map(function ($it) {
                $p = $it->product;
                return [
                    'product_id'      => $it->product_id,
                    'descripcion'     => $it->descripcion ?? ($p->nombre ?? ''),
                    'clave_prod_serv' => $p->clave_prod_serv ?? null,
                    'clave_unidad'    => $p->clave_unidad ?? null,
                    'unidad'          => $p->unidad ?? null,
                    'cantidad'        => (float)$it->cantidad,
                    'valor_unitario'  => (float)$it->precio,
                    'descuento'       => (float)$it->descuento,
                    'objeto_imp'      => '02',
                    'iva_pct'         => 0,     // ajústalo si aplicas IVA por producto
                    'ieps_pct'        => 0,
                ];
            })->values()->toArray(),
        ];
    }

    protected function mapFromSale(Sale $sale): array
    {
        return [
            'client_id' => $sale->client_id,
            'moneda'    => $sale->moneda,
            'items'     => $sale->items->map(function ($it) {
                $p = $it->product;
                return [
                    'product_id'      => $it->product_id,
                    'descripcion'     => $it->descripcion ?? ($p->nombre ?? ''),
                    'clave_prod_serv' => $p->clave_prod_serv ?? null,
                    'clave_unidad'    => $p->clave_unidad ?? null,
                    'unidad'          => $p->unidad ?? null,
                    'cantidad'        => (float)$it->cantidad,
                    'valor_unitario'  => (float)$it->precio,
                    'descuento'       => (float)$it->descuento,
                    'objeto_imp'      => '02',
                    'iva_pct'         => 0,
                    'ieps_pct'        => 0,
                ];
            })->values()->toArray(),
        ];
    }

    public function sendForm(Invoice $invoice)
{
    $invoice->load('client');
    return view('admin.invoices.send', [
        'invoice'     => $invoice,
        'clientEmail' => $invoice->client?->email ?? '',
    ]);
}

    public function send(Request $request, Invoice $invoice)
{
    $request->validate([
        'email'   => ['required', 'email'],
        'mensaje' => ['nullable', 'string', 'max:500'],
    ]);

    $empresa = app(\App\Services\CompanyService::class)->activa();
    $invoice->loadMissing(['client', 'items', 'company.fiscalData']);

    $pdf   = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
        'invoice' => $invoice,
        'empresa' => $empresa,
    ]);
    $raw   = $pdf->output();
    $fname = 'factura-' . ($invoice->serie ?? '') . ($invoice->folio ?? $invoice->id) . '.pdf';

    try {
        \Illuminate\Support\Facades\Mail::to($request->email)
            ->send(new \App\Mail\InvoiceMailable(
                invoice: $invoice,
                pdfRaw:  $raw,
                pdfName: $fname,
               mensaje: $request->input('mensaje') ?? '',
            ));

        return back()->with('swal', ['icon'=>'success','title'=>'Enviada','text'=>'Factura enviada correctamente.']);
    } catch (\Throwable $e) {
        return back()->with('swal', ['icon'=>'error','title'=>'Error al enviar','text'=>$e->getMessage()]);
    }
}
}