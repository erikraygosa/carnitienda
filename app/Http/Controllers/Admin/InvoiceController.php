<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Invoice, InvoiceItem, Client, SalesOrder, Sale, Product, StampCounter};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\PacCfdiService;
use App\Services\CompanyService;
use App\Services\DocumentLogService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InvoiceController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentLogService $log) {}

    /**
     * El SAT solo permite el RFC genérico (XAXX010101000, "público en
     * general") junto con régimen fiscal 616 y uso CFDI S01. Si el cliente
     * no tiene RFC real capturado, el sistema manda ese RFC genérico a
     * Facturapi sin importar qué régimen/uso haya elegido el usuario en el
     * formulario — así que forzamos aquí los únicos valores válidos para
     * no generar un "Error PAC: tax_system no tiene un valor permitido"
     * al timbrar.
     *
     * Además, el régimen 616 ("Sin obligaciones fiscales") en la práctica
     * solo acepta Uso CFDI S01 — cualquier otro uso (G03, I01, etc.) truena
     * en el PAC con "La clave del campo UsoCFDI debe corresponder con el
     * tipo de persona ... conforme al catálogo c_UsoCFDI", aunque el
     * cliente sí tenga RFC. Se fuerza igual, tenga o no RFC.
     */
    private function forceGenericRfcRegimen(array $data): array
    {
        $cliente = Client::find($data['client_id'] ?? null);

        if ($cliente && empty($cliente->rfc)) {
            $data['regimen_fiscal_receptor'] = '616';
            $data['uso_cfdi'] = 'S01';
        } elseif (($data['regimen_fiscal_receptor'] ?? null) === '616') {
            $data['uso_cfdi'] = 'S01';
        }

        return $data;
    }

    /**
     * Contador de timbres vigente de la empresa activa (o null si no hay
     * ninguno configurado — no bloquea nada, solo es informativo).
     */
    private function timbresInfo(): ?array
    {
        $empresa = app(CompanyService::class)->activa();
        if (! $empresa) return null;

        $counter = StampCounter::activoParaEmpresa($empresa->id);
        if (! $counter) return null;

        return [
            'restantes'   => $counter->timbresRestantes(),
            'usados'      => $counter->timbres_usados,
            'contratados' => $counter->timbres_contratados,
            'alerta'      => $counter->alertaRestantes(),
        ];
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver facturas', only: ['index', 'edit', 'pdf', 'pdfDownload', 'sendForm', 'send']),
            new Middleware('can:crear facturas', only: ['create', 'store', 'update', 'fromSalesOrder', 'fromSale']),
            new Middleware('can:facturar varios pedidos', only: ['consolidadaIndex', 'consolidadaData', 'prepararConsolidada']),
            new Middleware('can:timbrar facturas', only: ['stamp']),
            new Middleware('can:cancelar facturas', only: ['cancel', 'refreshCancellation']),
            new Middleware('can:ver facturas', only: ['download']),
        ];
    }

    public function index()
    {
           $invoices = Invoice::with('client')
        ->latest('id')
        ->paginate(20);

    $timbresInfo = $this->timbresInfo();

    return view('admin.invoices.index', compact('invoices', 'timbresInfo'));
    }

    /**
     * Pantalla para armar una factura consolidada: filtra pedidos "sin
     * facturar" (opcionalmente de un cliente en particular), se seleccionan
     * varios con checkbox y se genera UNA sola factura que los cubre a
     * todos — a nombre del cliente (si todos son del mismo) o a Público en
     * general.
     */
    public function consolidadaIndex()
    {
        $clients = Client::orderBy('nombre')->get(['id', 'nombre']);
        return view('admin.invoices.consolidada', compact('clients'));
    }

    public function consolidadaData(Request $request)
    {
        $tipo     = $request->get('tipo', 'pedidos') === 'notas' ? 'notas' : 'pedidos';
        $clientId = $request->get('client_id', '');
        $search   = $request->get('search', '');
        $desde    = $request->get('fecha_desde', '');
        $hasta    = $request->get('fecha_hasta', '');

        if ($tipo === 'notas') {
            $items = Sale::with('client:id,nombre')
                ->whereNotIn('status', ['BORRADOR', 'CANCELADO'])
                ->whereDoesntHave('invoices', fn($q) => $q->where('estatus', '!=', 'CANCELADA'))
                ->when($clientId, fn($q) => $q->where('client_id', $clientId))
                ->when($search, fn($q) =>
                    $q->where(fn($q2) =>
                        $q2->where('folio', 'like', "%$search%")
                           ->orWhereHas('client', fn($q3) => $q3->where('nombre', 'like', "%$search%"))
                    )
                )
                ->when($desde, fn($q) => $q->whereDate('fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('fecha', '<=', $hasta))
                ->orderByDesc('fecha')
                ->limit(500)
                ->get(['id', 'folio', 'client_id', 'fecha', 'total']);
        } else {
            $items = SalesOrder::with('client:id,nombre')
                ->whereNotIn('status', ['BORRADOR', 'CANCELADO'])
                // "Sin facturar" con el mismo criterio que el filtro de Pedidos:
                // que no tenga ninguna factura viva (timbrada/borrador/
                // cancelación pendiente) cubriéndolo ya.
                ->whereDoesntHave('invoices', fn($q) => $q->where('estatus', '!=', 'CANCELADA'))
                ->when($clientId, fn($q) => $q->where('client_id', $clientId))
                ->when($search, fn($q) =>
                    $q->where(fn($q2) =>
                        $q2->where('folio', 'like', "%$search%")
                           ->orWhereHas('client', fn($q3) => $q3->where('nombre', 'like', "%$search%"))
                    )
                )
                ->when($desde, fn($q) => $q->whereDate('fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('fecha', '<=', $hasta))
                ->orderByDesc('fecha')
                ->limit(500)
                ->get(['id', 'folio', 'client_id', 'fecha', 'total', 'payment_method']);
        }

        $rows = $items->map(fn($o) => [
            'id'        => $o->id,
            'folio'     => $o->folio,
            'client_id' => $o->client_id,
            'cliente'   => $o->client?->nombre ?? '—',
            'fecha'     => optional($o->fecha)->format('d/m/Y'),
            'total'     => (float) $o->total,
        ])->values();

        return response()->json(['rows' => $rows]);
    }

    public function prepararConsolidada(Request $request)
    {
        $data = $request->validate([
            'order_ids'      => ['required', 'array', 'min:1'],
            'order_ids.*'    => ['integer'],
            'tipo'           => ['required', 'in:pedidos,notas'],
            'modo_receptor'  => ['required', 'in:publico_general,cliente'],
        ], [
            'order_ids.required' => 'Selecciona al menos un pedido o nota.',
        ]);

        $esNotas = $data['tipo'] === 'notas';

        if ($esNotas) {
            $request->validate(['order_ids.*' => ['exists:sales,id']]);
            $orders = Sale::with('items.product', 'client')->whereIn('id', $data['order_ids'])->get();
        } else {
            $request->validate(['order_ids.*' => ['exists:sales_orders,id']]);
            $orders = SalesOrder::with('items.product', 'client')->whereIn('id', $data['order_ids'])->get();
        }

        $yaFacturados = $orders->filter(fn($o) => $o->invoices()->where('estatus', '!=', 'CANCELADA')->exists());
        if ($yaFacturados->isNotEmpty()) {
            return back()->with('swal', [
                'icon' => 'error', 'title' => 'Ya facturado',
                'text' => 'Estos ya tienen una factura viva: ' . $yaFacturados->pluck('folio')->implode(', '),
            ]);
        }

        if ($data['modo_receptor'] === 'cliente') {
            $clientIds = $orders->pluck('client_id')->unique()->filter();
            if ($clientIds->count() !== 1) {
                return back()->with('swal', [
                    'icon' => 'error', 'title' => 'No se puede facturar así',
                    'text' => 'Para facturar con los datos del cliente, todos los pedidos seleccionados deben ser del mismo cliente. Usa "Público en general" si son de clientes distintos.',
                ]);
            }
            $clientId = $clientIds->first();
        } else {
            $publico = Client::where('nombre', 'PUBLICO EN GENERAL')->first();
            if (! $publico) {
                return back()->with('swal', [
                    'icon' => 'error', 'title' => 'Falta configurar',
                    'text' => 'No se encontró el cliente "PUBLICO EN GENERAL" — créalo primero en Clientes (RFC XAXX010101000).',
                ]);
            }
            $clientId = $publico->id;
        }

        session(['consolidated_invoice_prefill' => $this->mapFromOrders($orders, $clientId, $esNotas)]);

        return redirect()->route('admin.invoices.create', ['consolidado' => 1]);
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

    if ($req->boolean('consolidado')) {
        // Se llenó vía prepararConsolidada() — de un solo uso, se limpia de
        // la sesión al leerla para que un F5 no reabra la misma consolidada.
        $prefill = session()->pull('consolidated_invoice_prefill');
        if (! $prefill) {
            return redirect()->route('admin.invoices.consolidada')
                ->with('swal', ['icon' => 'error', 'title' => 'Expiró', 'text' => 'Vuelve a seleccionar los pedidos a facturar.']);
        }
    } elseif ($fromOrderId) {
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

    $timbresInfo = $this->timbresInfo();

    return view('admin.invoices.create', compact(
        'clients', 'products', 'prefill',
        'empresa', 'emisorDefaults',
        'clientsMap', 'productsMap',
        'nextSerie', 'nextFolio', 'timbresInfo'
    ));
}

public function store(Request $request)
{
    $data = $request->validate([
        'client_id'               => ['required', 'exists:clients,id'],
        'sales_order_id'          => ['nullable', 'exists:sales_orders,id'],
        // Factura consolidada: varios pedidos en una sola factura (ver
        // InvoiceController::prepararConsolidada()). sales_order_id puede
        // venir vacío en ese caso — lo que manda es esta lista.
        'sales_order_ids'         => ['nullable', 'array'],
        'sales_order_ids.*'       => ['integer', 'exists:sales_orders,id'],
        'sale_id'                 => ['nullable', 'exists:sales,id'],
        // Consolidada de Notas de venta — mismo criterio que sales_order_ids.
        'sale_ids'                => ['nullable', 'array'],
        'sale_ids.*'              => ['integer', 'exists:sales,id'],
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

    // Todos los pedidos/notas que va a cubrir esta factura — el de siempre
    // (sales_order_id/sale_id) más, si viene de una consolidada, la lista
    // completa.
    $ordenesACubrir = collect($data['sales_order_ids'] ?? [])
        ->push($data['sales_order_id'] ?? null)
        ->filter()
        ->unique()
        ->values();
    $notasACubrir = collect($data['sale_ids'] ?? [])
        ->push($data['sale_id'] ?? null)
        ->filter()
        ->unique()
        ->values();

    if ($ordenesACubrir->isNotEmpty()) {
        $yaFacturados = SalesOrder::whereIn('id', $ordenesACubrir)
            ->whereHas('invoices', fn($q) => $q->where('estatus', '!=', 'CANCELADA'))
            ->pluck('folio');
        if ($yaFacturados->isNotEmpty()) {
            return back()
                ->with('swal', ['icon' => 'error', 'title' => 'Ya facturado', 'text' => 'Estos pedidos ya tienen una factura viva: ' . $yaFacturados->implode(', ')])
                ->withInput();
        }
    }
    if ($notasACubrir->isNotEmpty()) {
        $yaFacturadas = Sale::whereIn('id', $notasACubrir)
            ->whereHas('invoices', fn($q) => $q->where('estatus', '!=', 'CANCELADA'))
            ->pluck('folio');
        if ($yaFacturadas->isNotEmpty()) {
            return back()
                ->with('swal', ['icon' => 'error', 'title' => 'Ya facturado', 'text' => 'Estas notas ya tienen una factura viva: ' . $yaFacturadas->implode(', ')])
                ->withInput();
        }
    }

    $data = $this->forceGenericRfcRegimen($data);

    $invoice = null;

    DB::transaction(function () use (&$invoice, $data, $ordenesACubrir, $notasACubrir) {
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

        if ($ordenesACubrir->isNotEmpty()) {
            $invoice->salesOrders()->attach($ordenesACubrir);
        }
        if ($notasACubrir->isNotEmpty()) {
            $invoice->sales()->attach($notasACubrir);
        }

        if ($data['folio'] ?? null) {
            $folioGuardado = (int) $data['folio'];
            $series = \App\Models\InvoiceSeries::where('es_default', 1)
                ->where('tipo_comprobante', 'I')
                ->lockForUpdate()
                ->first();
            if ($series && $folioGuardado > $series->folio_actual) {
                $series->update(['folio_actual' => $folioGuardado]);
            }
        }
    });

    $this->log->log($invoice, 'CREADO', null, 'BORRADOR');
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

    $timbresInfo = $this->timbresInfo();

    return view('admin.invoices.edit', compact(
    'invoice', 'clients', 'products',
    'empresa', 'emisorDefaults',
    'clientsMap', 'productsMap', 'timbresInfo'
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

    $this->log->log($invoice, 'CAMBIO_ESTADO', 'BORRADOR', 'TIMBRADA', null, 'UUID: ' . $result['uuid']);
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
            // Motivo 01 = "con relación": el SAT exige el UUID del CFDI que
            // sustituye a este; sin él Facturapi/SAT puede rechazar la cancelación.
            'folio_sustitucion' => ['required_if:motivo,01','nullable','string','max:50'],
        ], [
            'folio_sustitucion.required_if' => 'El motivo 01 (con relación) requiere el UUID de la factura que sustituye a esta.',
        ]);

        $resp = $pac->cancel($invoice, $data['motivo'], $data['folio_sustitucion'] ?? null);

        if (!($resp['ok'] ?? false)) {
            return back()->with('swal',['icon'=>'error','title'=>'Error PAC','text'=>$resp['error'] ?? 'Fallo al cancelar.']);
        }

        // $pac->cancel() ya actualizó $invoice->estatus (CANCELADA o
        // CANCELACION_PENDIENTE, según lo que haya confirmado el PAC).
        $invoice->refresh();

        if ($invoice->isCancellationPending()) {
            $this->log->log($invoice, 'CAMBIO_ESTADO', 'TIMBRADA', 'CANCELACION_PENDIENTE', null, 'Motivo: ' . $data['motivo']);
            return back()->with('swal', [
                'icon'  => 'info',
                'title' => 'Cancelación pendiente',
                'text'  => 'El SAT requiere que el receptor acepte la cancelación (hasta 72h). La factura sigue vigente mientras tanto; usa "Verificar estatus" para actualizarla.',
            ]);
        }

        $this->log->log($invoice, 'CAMBIO_ESTADO', 'TIMBRADA', 'CANCELADA', null, 'Motivo: ' . $data['motivo']);
        return back()->with('swal',['icon'=>'success','title'=>'Cancelada','text'=>'Factura cancelada en SAT.']);
    }

    // Consulta al PAC el estatus real de una factura en CANCELACION_PENDIENTE
    // y actualiza el estatus local si ya se resolvió (aceptada/rechazada).
    public function refreshCancellation(Invoice $invoice, PacCfdiService $pac)
    {
        if (!$invoice->isCancellationPending()) {
            return back()->with('swal',['icon'=>'info','title'=>'Sin cambios','text'=>'Esta factura no tiene una cancelación pendiente.']);
        }

        $estatusAnterior = $invoice->estatus;
        $resp = $pac->refreshCancellationStatus($invoice);

        if (!($resp['ok'] ?? false)) {
            return back()->with('swal',['icon'=>'error','title'=>'Error PAC','text'=>$resp['error'] ?? 'No se pudo consultar el estatus.']);
        }

        $invoice->refresh();

        if ($invoice->estatus === $estatusAnterior) {
            return back()->with('swal',['icon'=>'info','title'=>'Sigue pendiente','text'=>'El SAT aún no resuelve la cancelación.']);
        }

        $this->log->log($invoice, 'CAMBIO_ESTADO', $estatusAnterior, $invoice->estatus, null, 'Verificación de estatus PAC');
        return back()->with('swal',['icon'=>'success','title'=>'Actualizada','text'=>"Estatus actualizado a {$invoice->estatus}."]);
    }

    // PDF y envío (opcional: usa tu layout PDF)
    public function pdf(Invoice $invoice)
{
    $invoice->load('client', 'items', 'company.fiscalData', 'complementDocs.relatedInvoice', 'arPayment.paymentType', 'relatedInvoiceOriginal');

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
    $invoice->load('client', 'items', 'company.fiscalData', 'complementDocs.relatedInvoice', 'arPayment.paymentType', 'relatedInvoiceOriginal');

    $empresa = $invoice->company
        ?? \App\Models\Company::first();

    $pdf = Pdf::loadView('pdf.invoice', [
        'invoice'       => $invoice,
        'empresaActiva' => $empresa,
    ]);

    return $pdf->download('factura-' . $invoice->serie . $invoice->folio . '.pdf');
}

    /**
     * Factura consolidada: junta las partidas de varios pedidos en una sola
     * factura — una línea por producto, sumando cantidad e importe entre
     * todos los pedidos seleccionados (no una línea por pedido). El precio
     * unitario de la línea combinada se recalcula como importe/cantidad
     * para que valorUnitario × cantidad siga cuadrando con el importe real,
     * incluso si el mismo producto se vendió a precios distintos entre
     * pedidos (precio por cliente, ajustes, etc.).
     */
    protected function mapFromOrders(\Illuminate\Support\Collection $orders, int $clientId, bool $esNotas = false): array
    {
        $grupos = [];

        foreach ($orders as $order) {
            foreach ($order->items as $it) {
                $p   = $it->product;
                // Agrupa por producto si existe; si es una partida libre sin
                // producto (descripción a mano), agrupa por esa descripción
                // — dos partidas libres con el mismo texto sí se combinan,
                // pero nunca se mezclan con las de un producto real.
                $key = $it->product_id ? 'p:' . $it->product_id : 'd:' . mb_strtolower(trim($it->descripcion ?? ''));

                $cantidad = (float) $it->cantidad;
                $importe  = $cantidad * (float) $it->precio - (float) $it->descuento;

                if (! isset($grupos[$key])) {
                    $grupos[$key] = [
                        'product_id'      => $it->product_id,
                        'descripcion'     => $it->descripcion ?? ($p->nombre ?? ''),
                        'clave_prod_serv' => $p->clave_prod_serv ?? null,
                        'clave_unidad'    => $p->clave_unidad ?? null,
                        'unidad'          => $p->unidad ?? null,
                        'cantidad'        => 0.0,
                        'importe'         => 0.0,
                        'iva_pct'         => (float) ($p?->tasa_iva ?? 0),
                    ];
                }

                $grupos[$key]['cantidad'] += $cantidad;
                $grupos[$key]['importe']  += $importe;
            }
        }

        $items = collect($grupos)->values()->map(function ($g) {
            $valorUnitario = $g['cantidad'] > 0 ? round($g['importe'] / $g['cantidad'], 6) : 0;
            return [
                'product_id'      => $g['product_id'],
                'descripcion'     => $g['descripcion'],
                'clave_prod_serv' => $g['clave_prod_serv'],
                'clave_unidad'    => $g['clave_unidad'],
                'unidad'          => $g['unidad'],
                'cantidad'        => $g['cantidad'],
                'valor_unitario'  => $valorUnitario,
                'descuento'       => 0,
                'objeto_imp'      => '02',
                'iva_pct'         => $g['iva_pct'],
                'ieps_pct'        => 0,
            ];
        })->values()->toArray();

        $prefill = [
            'client_id'      => $clientId,
            'moneda'         => $orders->first()->moneda ?? 'MXN',
            'items'          => $items,
            'consolidado_de' => $orders->pluck('folio')->values()->toArray(),
        ];
        $prefill[$esNotas ? 'sale_ids' : 'sales_order_ids'] = $orders->pluck('id')->values()->toArray();

        return $prefill;
    }

    // ===== Helpers para precargar desde pedido/nota =====
    protected function mapFromOrder(SalesOrder $order): array
    {
        return [
            'client_id'      => $order->client_id,
            'sales_order_id' => $order->id,
            'moneda'         => $order->moneda,
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
                    'iva_pct'         => (float)($p?->tasa_iva ?? 0),
                    'ieps_pct'        => 0,
                ];
            })->values()->toArray(),
        ];
    }

    protected function mapFromSale(Sale $sale): array
    {
        return [
            'client_id' => $sale->client_id,
            'sale_id'   => $sale->id,
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
                    'iva_pct'         => (float)($p?->tasa_iva ?? 0),
                    'ieps_pct'        => 0,
                ];
            })->values()->toArray(),
        ];
    }

    public function fromSalesOrder(\App\Models\SalesOrder $order)
    {
        return redirect()->route('admin.invoices.create', ['order_id' => $order->id]);
    }

    public function fromSale(\App\Models\Sale $sale)
    {
        return redirect()->route('admin.invoices.create', ['sale_id' => $sale->id]);
    }

    public function download(Invoice $invoice)
    {
        return $this->pdfDownload($invoice);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if (!$invoice->isDraft()) {
            return back()->with('swal', ['icon'=>'error','title'=>'No permitido','text'=>'Solo BORRADOR puede editarse.']);
        }

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

        $data = $this->forceGenericRfcRegimen($data);

        DB::transaction(function () use (&$invoice, $data) {
            $subtotal  = 0;
            $iva       = 0;
            $ieps      = 0;
            $total     = 0;

            $invoice->items()->delete();

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

            $invoice->update([
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
                'subtotal'                => $subtotal,
                'impuestos'               => $iva + $ieps,
                'total'                   => $total,
            ]);
        });

        $this->log->log($invoice, 'EDITADO', null, null, null, 'Datos actualizados');

        return back()->with('swal', ['icon'=>'success','title'=>'Actualizada','text'=>'Factura en borrador actualizada.']);
    }

    public function sendForm(Invoice $invoice)
{
    $invoice->load('client');
    return view('admin.invoices.send', [
        'invoice'     => $invoice,
        'clientEmail' => $invoice->client?->email ?? '',
        'clientPhone' => $invoice->client?->telefono ?? '',
    ]);
}

    public function send(Request $request, Invoice $invoice, \App\Services\WhatsappSender $whatsapp)
{
    $request->validate([
        'channels'    => ['required', 'array', 'min:1'],
        'channels.*'  => ['in:email,whatsapp'],
        'email'       => ['nullable', 'email'],
        'telefono'    => ['nullable', 'string'],
        'mensaje'     => ['nullable', 'string', 'max:500'],
    ]);

    $empresa = app(\App\Services\CompanyService::class)->activa();
    $invoice->loadMissing(['client', 'items', 'company.fiscalData', 'complementDocs.relatedInvoice', 'arPayment.paymentType', 'relatedInvoiceOriginal']);

    $pdf   = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
        'invoice' => $invoice,
        'empresa' => $empresa,
    ]);
    $raw   = $pdf->output();
    $fname = 'factura-' . ($invoice->serie ?? '') . ($invoice->folio ?? $invoice->id) . '.pdf';

    $errors = [];

    if (in_array('email', $request->channels, true)) {
        $to = $request->input('email') ?: ($invoice->client?->email ?? null);
        if (!$to) {
            $errors[] = 'El cliente no tiene correo y no proporcionaste uno.';
        } else {
            try {
                \Illuminate\Support\Facades\Mail::to($to)
                    ->send(new \App\Mail\InvoiceMailable(
                        invoice: $invoice,
                        pdfRaw:  $raw,
                        pdfName: $fname,
                        mensaje: $request->input('mensaje') ?? '',
                    ));
            } catch (\Throwable $e) {
                $errors[] = 'Error enviando email: ' . $e->getMessage();
            }
        }
    }

    if (in_array('whatsapp', $request->channels, true)) {
        $phone = $request->input('telefono') ?: ($invoice->client?->telefono ?? null);
        $msg   = $request->input('mensaje') ?: 'Te adjunto tu factura 📎';

        if (!$phone) {
            $errors[] = 'El cliente no tiene teléfono y no proporcionaste uno.';
        } else {
            try {
                $resp = $whatsapp->sendPdf($phone, $msg, $fname, $raw);
                if (!($resp['ok'] ?? false)) {
                    $errors[] = 'WhatsApp API respondió ' . ($resp['status'] ?? '500') . ': ' . json_encode($resp['body'] ?? []);
                }
            } catch (\Throwable $e) {
                $errors[] = 'Error enviando WhatsApp: ' . $e->getMessage();
            }
        }
    }

    if ($errors) {
        return back()->with('swal', [
            'icon'  => 'error',
            'title' => 'Envío parcial',
            'text'  => implode(' | ', $errors),
        ]);
    }

    return back()->with('swal', ['icon'=>'success','title'=>'Enviada','text'=>'Factura enviada correctamente.']);
}
}