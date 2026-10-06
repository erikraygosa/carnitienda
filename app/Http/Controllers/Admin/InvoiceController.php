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
            new Middleware('can:ver facturas', only: ['download', 'xml', 'xmlDownload']),
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
                ->get(['id', 'folio', 'client_id', 'fecha', 'total', 'saldo_pendiente', 'cobrado_at', 'driver_settlement_status']);
        } else {
            $items = SalesOrder::with(['client:id,nombre', 'items:id,sales_order_id', 'dispatchItem.lines'])
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
                ->get(['id', 'folio', 'client_id', 'fecha', 'total', 'payment_method', 'status', 'saldo_pendiente', 'cobrado_at', 'driver_settlement_status']);
        }

        $rows = $items->map(fn($o) => [
            'id'        => $o->id,
            'folio'     => $o->folio,
            'client_id' => $o->client_id,
            'cliente'   => $o->client?->nombre ?? '—',
            'fecha'     => optional($o->fecha)->format('d/m/Y'),
            'total'     => (float) $o->total,
            // Solo los pedidos ya surtidos se pueden facturar (las notas de
            // venta son de mostrador, no pasan por surtido).
            // Pagada: cobrada en CxC, liquidada por el chofer o sin saldo.
            'pagado'    => $o->cobrado_at !== null
                || $o->driver_settlement_status === 'LIQUIDADO'
                || ($o->saldo_pendiente !== null && (float) $o->saldo_pendiente <= 0),
            'surtido'   => $tipo === 'notas' ? true : $o->tieneSurtido(),
            'parcial'   => $tipo === 'notas' ? false : $o->surtidoParcial(),
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

        if (! $esNotas) {
            $orders->load('dispatchItem.lines');
            $sinSurtir = $orders->filter(fn($o) => ! $o->tieneSurtido());
            if ($sinSurtir->isNotEmpty()) {
                return back()->with('swal', [
                    'icon' => 'error', 'title' => 'Falta surtir',
                    'text' => 'Estos pedidos no tienen ningún producto surtido, no se pueden facturar: ' . $sinSurtir->pluck('folio')->implode(', '),
                ]);
            }
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

    // NOTA: antes había aquí una regla que bloqueaba objeto_imp=02 ("sí
    // objeto de impuesto") combinado con iva_pct=0, tratándolo como
    // contradictorio — eso tenía sentido mientras Facturapi no recibía el
    // impuesto explícito (omitirlo dejaba que Facturapi aplicara SU propio
    // 16% por default, causa real de la factura #3). Ya corregido eso
    // (FactuapiDriver ahora manda 'taxes' explícito, con tasa 0 incluida,
    // más 'taxability' y 'tax_included' explícitos), "02 + 0% IVA" pasó a
    // ser una combinación VÁLIDA y común: "Tasa 0%" es la clasificación
    // fiscal real de alimentos básicos sin preparar (carne incluida) según
    // el Art. 2-A de la Ley del IVA — distinta de "01 No objeto de
    // impuesto". Por eso ya no se bloquea.

    // El precio/IVA que ve el PAC al timbrar sale de lo que capture el
    // usuario en cada línea de la factura, no del catálogo — pero antes
    // esta consulta leía columnas 'clave_prod_serv'/'clave_unidad' que
    // NUNCA se llenan (el formulario de Productos guarda en las columnas
    // reales 'sat_clave_prod_serv'/'sat_clave_unidad'), así que el
    // catálogo SAT del producto jamás llegaba a la factura y todo caía en
    // los defaults genéricos ('01010101'/16%). Ahora sí se lee lo real, y
    // se marca 'configurado' => false cuando al producto le falta algo —
    // la vista usa esa bandera para NO asumir nada y obligar a capturarlo
    // a mano en esa línea (ver create.blade.php / edit.blade.php).
    private function productsMapForInvoices()
    {
        // Objeto de impuesto / % IVA por defecto del negocio (Superadmin →
        // Configuración → Facturación) — antes, si el producto no tenía
        // nada en su ficha SAT, se dejaba la línea en blanco obligando a
        // elegir el impuesto a mano en CADA factura. Como el negocio vende
        // carne (por default "01 – No objeto de impuesto" / 0%), ahora ese
        // caso usa este default configurable en vez de forzar captura
        // manual repetida — un producto que SÍ tenga su propio impuesto en
        // su ficha (ej. algo procesado con IVA) sigue usando el suyo,
        // nunca este default.
        $objetoImpDefault = \App\Models\SystemSetting::get('facturacion.objeto_imp_default', '01');
        $ivaPctDefault    = (int) \App\Models\SystemSetting::get('facturacion.iva_pct_default', '0');

        return Product::orderBy('nombre')->get([
            'id', 'nombre', 'precio_base', 'unidad',
            'sat_clave_prod_serv', 'sat_clave_unidad',
            'sat_objeto_imp', 'sat_tipo_factor', 'sat_tasa_iva',
        ])->keyBy('id')->map(function ($p) use ($objetoImpDefault, $ivaPctDefault) {
            // "02 – Sí objeto" con 0%/sin tasa NO cuenta como configuración
            // propia real — es justo el residuo que dejaba el formulario de
            // Productos antes de este fix (todo producto nuevo se guardaba
            // con ese combo por default, sin que nadie lo hubiera elegido a
            // propósito). 01/03 sí son deliberados aunque no lleven tasa.
            $tieneImpuestoPropio = match (true) {
                blank($p->sat_objeto_imp)        => false,
                $p->sat_objeto_imp !== '02'       => true,
                $p->sat_tipo_factor === 'Exento'  => true,
                default => $p->sat_tasa_iva !== null && (float) $p->sat_tasa_iva > 0,
            };

            if ($tieneImpuestoPropio) {
                $objetoImp = $p->sat_objeto_imp;
                $ivaPct    = $p->sat_tipo_factor === 'Exento' ? 0 : (int) round(((float) $p->sat_tasa_iva) * 100);
            } else {
                $objetoImp = $objetoImpDefault;
                $ivaPct    = $ivaPctDefault;
            }

            return [
                'nombre'          => $p->nombre,
                'precio_base'     => (float) ($p->precio_base ?? 0),
                'clave_prod_serv' => $p->sat_clave_prod_serv ?: '01010101',
                'clave_unidad'    => $p->sat_clave_unidad ?: 'H87',
                'unidad'          => $p->unidad ?? 'PZA',
                'objeto_imp'      => $objetoImp,
                'iva_pct'         => $ivaPct,
                'configurado'     => true,
            ];
        });
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

    $products = Product::orderBy('nombre')->get(['id', 'nombre']);

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

    $productsMap = $this->productsMapForInvoices();

    // Serie y folio desde configuración
    $series = \App\Models\InvoiceSeries::where('es_default', 1)
        ->where('tipo_comprobante', 'I')
        ->first();

    $nextSerie = $series?->serie ?? 'A';
    // El contador 'folio_actual' puede quedar desincronizado si algún folio
    // se guardó sin pasar por este flujo (ej. captura manual/de prueba) —
    // en ese caso seguía sugiriendo un folio ya usado y facturar tronaba
    // con error 500 (Duplicate entry) cada vez, sin forma de recuperarse
    // solo. Se autocorrige tomando también el folio máximo ya usado.
    $folioMaxUsado = (int) \App\Models\Invoice::where('serie', $nextSerie)
        ->where('tipo_comprobante', 'I')
        ->max('folio');
    $nextFolio = max($series->folio_actual ?? 0, $folioMaxUsado) + 1;

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
        'fecha'                   => ['required', 'date', $this->reglaFechaCfdi()],
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

    try {
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
    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
        // El folio sugerido ya se había usado (contador desincronizado o dos
        // personas facturando al mismo tiempo) — antes esto tronaba con
        // error 500 sin explicación. Ahora se avisa y se pide reintentar;
        // create() ya recalcula el folio tomando en cuenta el máximo real.
        return back()
            ->with('swal', ['icon' => 'error', 'title' => 'Folio ya usado', 'text' => 'El folio sugerido ya existe. Vuelve a intentar — se recalculó el siguiente folio disponible.'])
            ->withInput();
    }

    $this->log->log($invoice, 'CREADO', null, 'BORRADOR');
    return redirect()->route('admin.invoices.edit', $invoice)
        ->with('swal', ['icon' => 'success', 'title' => 'Creada', 'text' => 'Factura en borrador creada.']);
}

    public function edit(Invoice $invoice)
{
    $invoice->load('client', 'items.product', 'salesOrder', 'sale', 'arPayment.paymentType', 'complementDocs.relatedInvoice');

    $clients = Client::orderBy('nombre')->get([
        'id', 'nombre', 'rfc', 'razon_social',
        'cp', 'fiscal_cp', 'regimen_fiscal', 'uso_cfdi_default',
        'tipo_persona',
    ]);

    $products = Product::orderBy('nombre')->get(['id', 'nombre']);

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

    $productsMap = $this->productsMapForInvoices();

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

    // Fecha del CFDI: no futura ni anterior a 72 horas (regla del SAT). Los
    // complementos de pago toman la fecha del pago, no esta.
    if ($invoice->tipo_comprobante !== 'P' && ($errorFecha = self::errorFechaCfdi($invoice->fecha))) {
        return back()->with('swal', ['icon'=>'error','title'=>'Fecha no válida para timbrar','text'=>$errorFecha]);
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

    // Última barrera antes de mandarlo al PAC: si alguna línea se quedó sin
    // objeto de impuesto definido, NO se manda a timbrar — FactuapiDriver ya
    // manda 'taxability'/'unit_key'/'tax_included' explícitos, pero sin
    // objeto_imp no hay nada que mandar. "02 + 0% IVA" SÍ es válido (Tasa
    // 0%, la clasificación real de alimentos básicos sin preparar — Art.
    // 2-A Ley del IVA) y ya no se bloquea, porque ahora se manda explícito
    // (con tasa 0 incluida) en vez de omitirse como antes.
    $lineasSinImpuesto = $invoice->items->filter(fn ($item) => blank($item->objeto_imp));

    if ($lineasSinImpuesto->isNotEmpty()) {
        return back()->with('swal', [
            'icon'  => 'error',
            'title' => 'Falta configurar impuesto',
            'text'  => 'Esta línea no tiene impuesto bien definido: "' . $lineasSinImpuesto->first()->descripcion . '". Corrígela en Editar factura (o configura el producto en Productos → SAT) antes de timbrar.',
        ]);
    }

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
        // Configurable en Superadmin → Configuración → Facturación: si las
        // partidas repetidas del mismo producto (en distintos pedidos) se
        // suman en una sola línea (default, comportamiento de siempre) o se
        // dejan todas por separado tal como vienen en cada pedido.
        $sumarPartidas = (bool) \App\Models\SystemSetting::get('facturacion.consolidar_sumar_partidas', false);

        // En pedidos solo se facturan las partidas que ya se surtieron.
        $partidas = fn ($order) => $esNotas ? $order->items : $order->itemsSurtidos();

        if (! $sumarPartidas) {
            $items = $orders->flatMap(fn ($order) => $partidas($order)
                ->map(fn ($it) => $this->itemDesdeProducto($it, (float) $it->cantidad, (float) $it->precio, (float) $it->descuento))
            )->values()->toArray();

            $prefill = [
                'client_id'      => $clientId,
                'moneda'         => $orders->first()->moneda ?? 'MXN',
                'items'          => $items,
                'consolidado_de' => $orders->pluck('folio')->values()->toArray(),
            ];
            $prefill[$esNotas ? 'sale_ids' : 'sales_order_ids'] = $orders->pluck('id')->values()->toArray();

            return $prefill;
        }

        $grupos = [];
        $satMap = $this->productsMapForInvoices();

        foreach ($orders as $order) {
            foreach ($partidas($order) as $it) {
                $p   = $it->product ?? $this->productoPorDescripcion($it->descripcion);
                $sat = $p ? ($satMap[$p->id] ?? null) : null;
                // Agrupa por producto si existe; si es una partida libre sin
                // producto (descripción a mano), agrupa por esa descripción
                // — dos partidas libres con el mismo texto sí se combinan,
                // pero nunca se mezclan con las de un producto real.
                $key = $p ? 'p:' . $p->id : 'd:' . mb_strtolower(trim($it->descripcion ?? ''));

                $cantidad = (float) $it->cantidad;
                $importe  = $cantidad * (float) $it->precio - (float) $it->descuento;

                if (! isset($grupos[$key])) {
                    // El "Producto / Concepto" del CFDI usa el nombre REAL
                    // del catálogo, no la "descripción" (comentario interno
                    // de captura) — mismo criterio que en itemDesdeProducto().
                    $grupos[$key] = [
                        'product_id'      => $it->product_id ?: $p?->id,
                        'descripcion'     => $p->nombre ?? ($it->descripcion ?: ''),
                        'clave_prod_serv' => $sat['clave_prod_serv'] ?? '01010101',
                        'clave_unidad'    => $sat['clave_unidad']    ?? 'H87',
                        'unidad'          => $sat['unidad']          ?? ($p->unidad ?? null),
                        'cantidad'        => 0.0,
                        'importe'         => 0.0,
                        'objeto_imp'      => $sat['objeto_imp'] ?? null,
                        'iva_pct'         => $sat['iva_pct']    ?? null,
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
                'objeto_imp'      => $g['objeto_imp'],
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

    // El "Producto / Concepto" del CFDI es la descripción de lo que se
    // vendió, en los términos que maneja el negocio — NO tiene que copiar
    // el texto genérico del catálogo del SAT (la ClaveProdServ solo
    // clasifica el tipo de producto, el "Descripcion" del comprobante es
    // libre). Por eso aquí siempre gana el nombre REAL del producto tal
    // como está en el catálogo del sistema; "descripcion" del pedido es
    // solo un comentario interno para logística (mismo criterio que ya se
    // aplicó en el ticket al cliente y en Panel de Surtido — ver
    // ticket-body.blade.php / dispatch_panel/index.blade.php) y solo se
    // usa como respaldo si el renglón no tiene producto de catálogo.
    //
    // El impuesto (objeto_imp/iva_pct) sale de la ficha SAT real del
    // producto — antes se mandaba SIEMPRE objeto_imp=02 fijo e iva_pct del
    // campo 'tasa_iva' (que por default es 0), sin importar si el producto
    // tenía algo configurado. Si el producto no tiene nada configurado, se
    // deja en blanco para que la vista obligue a elegirlo a mano (igual
    // que al capturar una factura directa — ver productsMapForInvoices()).

    /**
     * Partidas capturadas sin producto del catálogo (solo texto, ej.
     * "ESPALDILLA (30 KG)"): se intenta vincular por nombre exacto — con o sin
     * el paréntesis final — para traer clave SAT, unidad e impuesto.
     */
    private function productoPorDescripcion(?string $descripcion): ?Product
    {
        $txt = mb_strtoupper(trim(preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $descripcion)));
        if ($txt === '') {
            return null;
        }

        static $porNombre = null;
        $porNombre ??= Product::get()->keyBy(fn ($p) => mb_strtoupper(trim($p->nombre)));

        return $porNombre->get($txt);
    }

    private function itemDesdeProducto($it, float $cantidad, float $precio, float $descuento): array
    {
        $p = $it->product ?? $this->productoPorDescripcion($it->descripcion);
        $sat = $p ? ($this->productsMapForInvoices()[$p->id] ?? null) : null;

        return [
            'product_id'      => $it->product_id ?: $p?->id,
            'descripcion'     => $p->nombre ?? ($it->descripcion ?: ''),
            'clave_prod_serv' => $sat['clave_prod_serv'] ?? '01010101',
            'clave_unidad'    => $sat['clave_unidad']    ?? 'H87',
            'unidad'          => $sat['unidad']          ?? ($p->unidad ?? null),
            'cantidad'        => $cantidad,
            'valor_unitario'  => $precio,
            'descuento'       => $descuento,
            'objeto_imp'      => $sat['objeto_imp'] ?? null,
            'iva_pct'         => $sat['iva_pct']    ?? null,
            'ieps_pct'        => 0,
        ];
    }

    protected function mapFromOrder(SalesOrder $order): array
    {
        return [
            'client_id'      => $order->client_id,
            'sales_order_id' => $order->id,
            'moneda'         => $order->moneda,
            'items'          => $order->items
                ->map(fn ($it) => $this->itemDesdeProducto($it, (float) $it->cantidad, (float) $it->precio, (float) $it->descuento))
                ->values()->toArray(),
        ];
    }

    protected function mapFromSale(Sale $sale): array
    {
        return [
            'client_id' => $sale->client_id,
            'sale_id'   => $sale->id,
            'moneda'    => $sale->moneda,
            'items'     => $sale->items
                ->map(fn ($it) => $this->itemDesdeProducto($it, (float) $it->cantidad, (float) $it->precio, (float) $it->descuento))
                ->values()->toArray(),
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

    /** Nombre del archivo XML: el UUID (lo que piden contabilidad y el SAT). */
    private function xmlNombre(Invoice $invoice): string
    {
        return ($invoice->uuid ?: ('factura-' . ($invoice->serie ?? '') . ($invoice->folio ?? $invoice->id))) . '.xml';
    }

    /** XML timbrado, o aborta con un mensaje claro si la factura no lo tiene. */
    private function xmlTimbrado(Invoice $invoice): string
    {
        $xml = trim((string) $invoice->xml_timbrado);

        abort_if(
            $xml === '' || ! in_array($invoice->estatus, ['TIMBRADA', 'CANCELACION_PENDIENTE', 'CANCELADA'], true),
            404,
            'Esta factura no tiene XML timbrado.'
        );

        return $xml;
    }

    // Ver el XML en el navegador (otra pestaña).
    public function xml(Invoice $invoice)
    {
        return response($this->xmlTimbrado($invoice), 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . $this->xmlNombre($invoice) . '"',
        ]);
    }

    // Descargar el XML.
    public function xmlDownload(Invoice $invoice)
    {
        return response($this->xmlTimbrado($invoice), 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->xmlNombre($invoice) . '"',
        ]);
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
            'fecha'                   => ['required', 'date', $this->reglaFechaCfdi()],
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
        'email'       => ['nullable', new \App\Rules\MultiEmail],
        'telefono'    => ['nullable', 'string'],
        'mensaje'     => ['nullable', 'string', 'max:500'],
        'adjuntar_xml' => ['nullable', 'boolean'],
    ]);

    $empresa = app(\App\Services\CompanyService::class)->activa();
    $invoice->loadMissing(['client', 'items', 'company.fiscalData', 'complementDocs.relatedInvoice', 'arPayment.paymentType', 'relatedInvoiceOriginal']);

    $pdf   = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
        'invoice' => $invoice,
        'empresa' => $empresa,
    ]);
    $raw   = $pdf->output();
    $fname = 'factura-' . ($invoice->serie ?? '') . ($invoice->folio ?? $invoice->id) . '.pdf';

    // XML timbrado (si la factura lo tiene y no se desmarcó la casilla).
    $xmlRaw   = null;
    $xmlNombre = $this->xmlNombre($invoice);
    if ($request->boolean('adjuntar_xml') && trim((string) $invoice->xml_timbrado) !== '') {
        $xmlRaw = trim((string) $invoice->xml_timbrado);
    }

    $errors = [];

    if (in_array('email', $request->channels, true)) {
        $to = \App\Support\EmailList::parse($request->input('email') ?: ($invoice->client?->email ?? ''));
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
                        xmlRaw:  $xmlRaw,
                        xmlName: $xmlNombre,
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
                } elseif ($xmlRaw !== null) {
                    $respXml = $whatsapp->sendFile($phone, 'XML de tu factura', $xmlNombre, $xmlRaw, 'application/xml');
                    if (!($respXml['ok'] ?? false)) {
                        $errors[] = 'El PDF se envió por WhatsApp, pero el XML no: ' . json_encode($respXml['body'] ?? []);
                    }
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

    /** Horas hacia atrás que el SAT permite entre la fecha del CFDI y su timbrado. */
    public const HORAS_MAX_RETROCESO = 72;

    /**
     * Mensaje de error si la fecha del CFDI no es válida para timbrar: no puede
     * ser futura ni anterior a 72 horas (3 días) — regla del SAT. Null si es válida.
     */
    public static function errorFechaCfdi($fecha): ?string
    {
        try {
            $f = \Carbon\Carbon::parse($fecha);
        } catch (\Throwable $e) {
            return 'La fecha de la factura no es válida.';
        }

        $ahora = now();
        if ($f->gt($ahora->copy()->addMinutes(5))) {
            return 'La fecha de la factura no puede ser futura.';
        }

        $limite = $ahora->copy()->subHours(self::HORAS_MAX_RETROCESO);
        if ($f->lt($limite)) {
            return 'La fecha de la factura (' . $f->format('d/m/Y H:i') . ') es anterior a 3 días. El SAT solo permite fechas desde '
                . $limite->format('d/m/Y H:i') . '. Corrige la fecha en Editar factura; si es más antigua, no se puede facturar con esa fecha.';
        }

        return null;
    }

    private function reglaFechaCfdi(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) {
            if ($msg = self::errorFechaCfdi($value)) {
                $fail($msg);
            }
        };
    }
}
