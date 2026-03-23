<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Driver;
use App\Models\ShippingRoute;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\AccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\SalesOrderDeliveryNoteMailable;
use App\Models\StockMovement;
use App\Services\InventoryService;

class SalesOrderController extends Controller
{
    public function index()
{
    return view('admin.sales_orders.index');
}

public function data(Request $request)
{
    $search     = $request->get('search', '');
    $status     = $request->get('status', '');
    $fechaDesde = $request->get('fecha_desde', '');
    $fechaHasta = $request->get('fecha_hasta', '');
    $sortBy     = in_array($request->get('sort_by'), ['folio','fecha','status','total']) ? $request->get('sort_by') : 'id';
    $sortDir    = $request->get('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
    $perPage    = in_array((int)$request->get('per_page'), [10,15,25,50]) ? (int)$request->get('per_page') : 15;
    $page       = max(1, (int)$request->get('page', 1));

    $q = SalesOrder::with(['client','warehouse'])
        ->when($search, fn($q) =>
            $q->where(fn($q) =>
                $q->where('folio','like',"%$search%")
                  ->orWhereHas('client', fn($q) => $q->where('nombre','like',"%$search%"))
            )
        )
        ->when($status,     fn($q) => $q->where('status', $status))
        ->when($fechaDesde, fn($q) => $q->whereDate('fecha', '>=', $fechaDesde))
        ->when($fechaHasta, fn($q) => $q->whereDate('fecha', '<=', $fechaHasta))
        ->orderBy($sortBy, $sortDir);

    $total   = $q->count();
    $orders  = $q->skip(($page - 1) * $perPage)->take($perPage)->get();

    $statusClasses = [
        'BORRADOR'     => 'bg-gray-100 text-gray-700',
        'APROBADO'     => 'bg-blue-100 text-blue-700',
        'PREPARANDO'   => 'bg-sky-100 text-sky-700',
        'PROCESADO'    => 'bg-amber-100 text-amber-700',
        'EN_RUTA'      => 'bg-violet-100 text-violet-700',
        'ENTREGADO'    => 'bg-emerald-100 text-emerald-700',
        'NO_ENTREGADO' => 'bg-orange-100 text-orange-700',
        'CANCELADO'    => 'bg-rose-100 text-rose-700',
    ];

        $rows = $orders->map(fn($o) => [
    'id'            => $o->id,
    'folio'         => $o->folio,
    'cliente'       => $o->client?->nombre ?? '—',
    'almacen'       => $o->warehouse?->nombre ?? '—',
    'fecha'         => optional($o->fecha)->format('d/m/Y H:i'),
    'status'        => $o->status,
    'status_label'  => $o->status_label ?? $o->status,
    'status_class'  => $statusClasses[$o->status] ?? 'bg-gray-100 text-gray-700',
    'total'         => number_format((float)$o->total, 2),
    'csrf'          => csrf_token(),
    'edit_url'      => route('admin.sales-orders.edit',        $o),
    'pdf_url'       => route('admin.sales-orders.pdf',         $o),
    'pdf_dl_url'    => route('admin.sales-orders.pdf.download', $o),
    'send_url'      => route('admin.sales-orders.send.form',   $o),
    'invoice_url' => route('admin.invoices.create').'?order_id='.$o->id,
    'approve_url'   => route('admin.sales-orders.approve',     $o),
    'process_url'   => route('admin.sales-orders.process',     $o),
    'cancel_url'    => route('admin.sales-orders.cancel',      $o),
    'enruta_url'    => route('admin.sales-orders.en-ruta',     $o),
    'deliver_url'   => route('admin.sales-orders.deliver',     $o),
    'nodeliver_url' => route('admin.sales-orders.not-delivered',$o),
]);

    return response()->json([
        'rows'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => (int) ceil($total / $perPage),
    ]);
}

    public function create(Request $request)
{
    $clients    = Client::orderBy('nombre')->get();  // necesitamos todos los campos
    $priceLists = PriceList::orderBy('nombre')->get(['id','nombre']);
    $products   = Product::orderBy('nombre')->get(['id','nombre','precio_base']);
    $warehouses = Warehouse::orderBy('nombre')->get(['id','nombre']);
    $drivers    = Driver::orderBy('nombre')->get(['id','nombre']);
    $routes     = ShippingRoute::orderBy('nombre')->get(['id','nombre']);
 
    $mainWarehouseId = DB::table('warehouses')->where('is_primary', 1)->value('id')
        ?? DB::table('warehouses')->orderBy('id')->value('id');
 
    // Overrides de precios por cliente
    $overrides = DB::table('client_price_overrides')
        ->select('client_id','product_id','precio')
        ->whereIn('client_id', $clients->pluck('id'))
        ->get()
        ->groupBy('client_id')
        ->map(fn($rows) => $rows->pluck('precio','product_id')->map(fn($v) => (float)$v)->toArray())
        ->toArray();
 
    // Items de listas de precios
    $listItems = DB::table('price_list_items')
        ->select('price_list_id','product_id','precio')
        ->whereIn('price_list_id', $priceLists->pluck('id'))
        ->get()
        ->groupBy('price_list_id')
        ->map(fn($rows) => $rows->pluck('precio','product_id')->map(fn($v) => (float)$v)->toArray())
        ->toArray();
 
    // Defaults por cliente para Alpine (ruta, pago, crédito, dirección de entrega)
    $clientDefaults = $clients->mapWithKeys(fn($c) => [(string)$c->id => [
        'shipping_route_id' => (string) ($c->shipping_route_id ?? ''),
        'price_list_id'     => (string) ($c->price_list_id ?? ''),
        'credito_dias'      => (int)   ($c->credito_dias ?? 0),
        'credito_limite'    => (float) ($c->credito_limite ?? 0),
        'telefono'          => $c->telefono ?? '',
        // Dirección de entrega efectiva (si igual_fiscal, usa la fiscal)
        'entrega_calle'    => $c->entrega_igual_fiscal ? ($c->fiscal_calle   ?? '') : ($c->entrega_calle   ?? ''),
        'entrega_numero'   => $c->entrega_igual_fiscal ? ($c->fiscal_numero  ?? '') : ($c->entrega_numero  ?? ''),
        'entrega_colonia'  => $c->entrega_igual_fiscal ? ($c->fiscal_colonia ?? '') : ($c->entrega_colonia ?? ''),
        'entrega_ciudad'   => $c->entrega_igual_fiscal ? ($c->fiscal_ciudad  ?? '') : ($c->entrega_ciudad  ?? ''),
        'entrega_estado'   => $c->entrega_igual_fiscal ? ($c->fiscal_estado  ?? '') : ($c->entrega_estado  ?? ''),
        'entrega_cp'       => $c->entrega_igual_fiscal ? ($c->fiscal_cp      ?? '') : ($c->entrega_cp      ?? ''),
    ]])->toArray();
 
    return view('admin.sales_orders.create', compact(
        'clients','priceLists','products','warehouses',
        'drivers','routes','overrides','listItems',
        'mainWarehouseId','clientDefaults'
    ));
    }

    public function store(Request $request)
    {
        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'           => ['required','date'],
            'client_id'       => ['nullable','exists:clients,id'],
            'warehouse_id'    => ['required','exists:warehouses,id'],
            'price_list_id'   => ['nullable','exists:price_lists,id'],
            'programado_para' => ['nullable','date'],

            'delivery_type'    => ['required','in:RECOGER,ENVIO'],
            'entrega_nombre'   => ['nullable','string','max:255'],
            'entrega_telefono' => ['nullable','string','max:50'],
            'entrega_calle'    => ['nullable','string','max:255'],
            'entrega_numero'   => ['nullable','string','max:50'],
            'entrega_colonia'  => ['nullable','string','max:255'],
            'entrega_ciudad'   => ['nullable','string','max:255'],
            'entrega_estado'   => ['nullable','string','max:255'],
            'entrega_cp'       => ['nullable','string','max:10'],

            'shipping_route_id'=> ['nullable','exists:shipping_routes,id'],
            'driver_id'        => ['nullable','exists:drivers,id'],

            'payment_method'  => ['required','in:CREDITO,TRANSFERENCIA,CONTRAENTREGA,EFECTIVO'],
            'credit_days'     => ['nullable','integer','min:0'],

            'moneda'          => ['required','string','max:10'],

            'items'                => ['required','array','min:1'],
            'items.*.product_id'   => ['nullable','exists:products,id'],
            'items.*.descripcion'  => ['required','string','max:255'],
            'items.*.cantidad'     => ['required','numeric','gt:0'],
            'items.*.precio'       => ['required','numeric','gte:0'],
            'items.*.descuento'    => ['nullable','numeric','gte:0'],
            'items.*.impuesto'     => ['nullable','numeric','gte:0'],
        ]);

        $order = null;

        DB::transaction(function () use (&$order, $data) {
            $subtotal=0; $descuento=0; $impuestos=0; $total=0;

            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc= (float)($it['descuento'] ?? 0);
                $line_tax = (float)($it['impuesto']  ?? 0);
                $line_tot = max($line_sub - $line_desc, 0) + $line_tax;
                $subtotal += $line_sub; $descuento += $line_desc; $impuestos += $line_tax; $total += $line_tot;
            }

            $nextId = (SalesOrder::max('id') ?? 0) + 1;

            $order = SalesOrder::create([
                'client_id'      => $data['client_id'] ?? null,
                'warehouse_id'   => $data['warehouse_id'],
                'price_list_id'  => $data['price_list_id'] ?? null,
                'folio'          => 'SO-'.now()->format('Ymd').'-'.Str::padLeft((string)$nextId, 4, '0'),
                'fecha'          => $data['fecha'],
                'programado_para'=> $data['programado_para'] ?? null,

                'delivery_type'   => $data['delivery_type'],
                'entrega_nombre'  => $data['entrega_nombre'] ?? null,
                'entrega_telefono'=> $data['entrega_telefono'] ?? null,
                'entrega_calle'   => $data['entrega_calle'] ?? null,
                'entrega_numero'  => $data['entrega_numero'] ?? null,
                'entrega_colonia' => $data['entrega_colonia'] ?? null,
                'entrega_ciudad'  => $data['entrega_ciudad'] ?? null,
                'entrega_estado'  => $data['entrega_estado'] ?? null,
                'entrega_cp'      => $data['entrega_cp'] ?? null,

                'shipping_route_id'=> $data['shipping_route_id'] ?? null,
                'driver_id'        => $data['driver_id'] ?? null,

                'payment_method' => $data['payment_method'],
                'credit_days'    => $data['payment_method']==='CREDITO' ? ($data['credit_days'] ?? 0) : null,

                'moneda'         => $data['moneda'],
                'subtotal'       => $subtotal,
                'impuestos'      => $impuestos,
                'descuento'      => $descuento,
                'total'          => $total,
                'status'         => 'BORRADOR',
                'created_by'     => auth()->id(),
                'owner_id'       => auth()->id(),

                // Contraentrega: se espera cobrar "total" salvo que manejes redondeos/comisiones
                'contraentrega_total' => $data['payment_method'] === 'CONTRAENTREGA' ? $total : 0,
            ]);

            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc= (float)($it['descuento'] ?? 0);
                $line_tax = (float)($it['impuesto']  ?? 0);
                $line_tot = max($line_sub - $line_desc, 0) + $line_tax;

                SalesOrderItem::create([
                    'sales_order_id'=> $order->id,
                    'product_id'    => $it['product_id'] ?? null,
                    'descripcion'   => $it['descripcion'],
                    'cantidad'      => $it['cantidad'],
                    'precio'        => $it['precio'],
                    'descuento'     => $line_desc,
                    'impuesto'      => $line_tax,
                    'total'         => $line_tot,
                ]);
            }
        });

        return redirect()->route('admin.sales-orders.edit', $order)
            ->with('swal',['icon'=>'success','title'=>'Creado','text'=>'Pedido creado']);
    }

    public function edit(SalesOrder $sales_order)
    {
        $order      = $sales_order->load('items.product','client','priceList','warehouse','driver','route');

        $clients    = Client::orderBy('nombre')->get(['id','nombre']);
        $priceLists = PriceList::orderBy('nombre')->get(['id','nombre']);
        $products   = Product::orderBy('nombre')->get(['id','nombre','precio_base']);
        $warehouses = Warehouse::orderBy('nombre')->get(['id','nombre']);
        $drivers    = Driver::orderBy('nombre')->get(['id','nombre']);
        $routes     = ShippingRoute::orderBy('nombre')->get(['id','nombre']);

        return view('admin.sales_orders.edit', compact(
            'order','clients','priceLists','products','warehouses','drivers','routes'
        ));
    }

    public function update(Request $request, SalesOrder $sales_order)
    {
        if ($sales_order->status !== 'BORRADOR') {
            return back()->with('swal',['icon'=>'error','title'=>'Error','text'=>'Solo borrador puede editarse.']);
        }

        $data = $request->validate([
            'fecha'           => ['required','date'],
            'client_id'       => ['nullable','exists:clients,id'],
            'warehouse_id'    => ['required','exists:warehouses,id'],
            'price_list_id'   => ['nullable','exists:price_lists,id'],
            'programado_para' => ['nullable','date'],

            'delivery_type'    => ['required','in:RECOGER,ENVIO'],
            'entrega_nombre'   => ['nullable','string','max:255'],
            'entrega_telefono' => ['nullable','string','max:50'],
            'entrega_calle'    => ['nullable','string','max:255'],
            'entrega_numero'   => ['nullable','string','max:50'],
            'entrega_colonia'  => ['nullable','string','max:255'],
            'entrega_ciudad'   => ['nullable','string','max:255'],
            'entrega_estado'   => ['nullable','string','max:255'],
            'entrega_cp'       => ['nullable','string','max:10'],

            'shipping_route_id'=> ['nullable','exists:shipping_routes,id'],
            'driver_id'        => ['nullable','exists:drivers,id'],

            'payment_method'  => ['required','in:CREDITO,TRANSFERENCIA,CONTRAENTREGA,EFECTIVO'],
            'credit_days'     => ['nullable','integer','min:0'],

            'moneda'          => ['required','string','max:10'],

            'items'                => ['required','array','min:1'],
            'items.*.product_id'   => ['nullable','exists:products,id'],
            'items.*.descripcion'  => ['required','string','max:255'],
            'items.*.cantidad'     => ['required','numeric','gt:0'],
            'items.*.precio'       => ['required','numeric','gte:0'],
            'items.*.descuento'    => ['nullable','numeric','gte:0'],
            'items.*.impuesto'     => ['nullable','numeric','gte:0'],
        ]);

        DB::transaction(function () use ($sales_order, $data) {
            $subtotal=0; $descuento=0; $impuestos=0; $total=0;

            // borra items previos y recalcula
            $sales_order->items()->delete();

            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc= (float)($it['descuento'] ?? 0);
                $line_tax = (float)($it['impuesto']  ?? 0);
                $line_tot = max($line_sub - $line_desc, 0) + $line_tax;

                $subtotal += $line_sub; $descuento += $line_desc; $impuestos += $line_tax; $total += $line_tot;

                SalesOrderItem::create([
                    'sales_order_id'=> $sales_order->id,
                    'product_id'    => $it['product_id'] ?? null,
                    'descripcion'   => $it['descripcion'],
                    'cantidad'      => $it['cantidad'],
                    'precio'        => $it['precio'],
                    'descuento'     => $line_desc,
                    'impuesto'      => $line_tax,
                    'total'         => $line_tot,
                ]);
            }

            $sales_order->update([
                'client_id'       => $data['client_id'] ?? null,
                'warehouse_id'    => $data['warehouse_id'],
                'price_list_id'   => $data['price_list_id'] ?? null,
                'fecha'           => $data['fecha'],
                'programado_para' => $data['programado_para'] ?? null,

                'delivery_type'    => $data['delivery_type'],
                'entrega_nombre'   => $data['entrega_nombre'] ?? null,
                'entrega_telefono' => $data['entrega_telefono'] ?? null,
                'entrega_calle'    => $data['entrega_calle'] ?? null,
                'entrega_numero'   => $data['entrega_numero'] ?? null,
                'entrega_colonia'  => $data['entrega_colonia'] ?? null,
                'entrega_ciudad'   => $data['entrega_ciudad'] ?? null,
                'entrega_estado'   => $data['entrega_estado'] ?? null,
                'entrega_cp'       => $data['entrega_cp'] ?? null,

                'shipping_route_id'=> $data['shipping_route_id'] ?? null,
                'driver_id'        => $data['driver_id'] ?? null,

                'payment_method'   => $data['payment_method'],
                'credit_days'      => $data['payment_method']==='CREDITO' ? ($data['credit_days'] ?? 0) : null,

                'moneda'           => $data['moneda'],
                'subtotal'         => $subtotal,
                'impuestos'        => $impuestos,
                'descuento'        => $descuento,
                'total'            => $total,

                'contraentrega_total' => $data['payment_method'] === 'CONTRAENTREGA' ? $total : 0,
            ]);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Actualizado','text'=>'Pedido actualizado']);
    }

    // ======== Transiciones de estado ========

public function approve(SalesOrder $order)
{
    if ($order->status !== 'BORRADOR') {
        return back()->with('swal', ['icon'=>'error','title'=>'No permitido','text'=>'Solo BORRADOR se puede aprobar.']);
    }

    if ($order->payment_method === 'CREDITO' && $order->client_id) {
        $client = $order->client;
        $limite = (float) ($client->credito_limite ?? 0);

        if ($limite > 0) {
            $saldoActual = app(\App\Services\ArService::class)->saldoCliente($client->id);
            if (($saldoActual + $order->total) > $limite) {
                return back()->with('swal', [
                    'icon'  => 'error',
                    'title' => 'Límite de crédito excedido',
                    'text'  => 'Saldo pendiente: $' . number_format($saldoActual, 2)
                             . ' + Este pedido: $' . number_format($order->total, 2)
                             . ' › Límite: $' . number_format($limite, 2),
                ]);
            }
        }
    }

    $order->update(['status' => 'APROBADO']);
    return back()->with('swal', ['icon'=>'success','title'=>'Aprobado','text'=>'Pedido aprobado.']);
}

    public function startPreparing(SalesOrder $order)
    {
        if ($order->status!=='APROBADO') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo APROBADO pasa a PREPARANDO.']);
        }
        $order->update(['status'=>'PREPARANDO','preparado_at'=>now()]);
        return back()->with('swal',['icon'=>'success','title'=>'Preparando','text'=>'Pedido en preparación.']);
    }

  

    public function process(SalesOrder $order, InventoryService $inv)
    {
        if (!in_array($order->status, ['APROBADO','PREPARANDO'])) {
            return back()->with('swal', ['icon'=>'error','title'=>'No permitido','text'=>'Debe estar APROBADO o PREPARANDO.']);
        }

        DB::transaction(function () use ($order, $inv) {
            foreach ($order->items as $it) {
                if (!$it->product_id || $it->cantidad <= 0) continue;

                // 🔑 Aquí se resuelve: si es subproducto con regla -> descuenta PADRE,
                // si es padre compuesto -> descuenta padre + BOM, si es simple -> descuenta él mismo.
                $inv->consumeForOrderItem($it, $order->warehouse_id, $order, auth()->id());
            }

            $order->update(['status' => 'PROCESADO', 'despachado_at' => now()]);
        });

        return back()->with('swal', ['icon'=>'success','title'=>'Procesado','text'=>'Stock descontado y pedido PROCESADO.']);
    }


    

    public function dispatchToRoute(SalesOrder $order)
    {
        if ($order->status !== 'PROCESADO') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo PROCESADO puede salir a ruta.']);
        }
        if (!$order->driver_id) {
            return back()->with('swal',['icon'=>'warning','title'=>'Sin chofer','text'=>'Asigna un chofer antes de salir a ruta.']);
        }
        $order->update(['status'=>'EN_RUTA','en_ruta_at'=>now()]);
        return back()->with('swal',['icon'=>'success','title'=>'En ruta','text'=>'El pedido salió a ruta.']);
    }

    public function deliver(SalesOrder $order, \App\Services\ArService $ar)
{
    if ($order->status !== 'EN_RUTA') {
        return back()->with('swal', ['icon'=>'error','title'=>'No permitido','text'=>'Solo EN_RUTA puede marcarse como entregado.']);
    }

    DB::transaction(function () use ($order, $ar) {
        $order->update(['status' => 'ENTREGADO', 'entregado_at' => now()]);

        // Solo crédito genera deuda en ar_movements.
        // Efectivo/transferencia/contraentrega se liquidan con el chofer (settleDriver).
        if ($order->payment_method === 'CREDITO' && $order->client_id) {
            $ar->charge(
                clientId: $order->client_id,
                monto:    $order->total,
                desc:     "Entrega pedido {$order->folio}",
                source:   $order,
                fecha:    now()->toDateString(),
            );
        }
    });

    return back()->with('swal', ['icon'=>'success','title'=>'Entregado','text'=>'Pedido entregado correctamente.']);
}

    public function notDelivered(Request $request, SalesOrder $order, \App\Services\InventoryService $inv)
{
    if ($order->status !== 'EN_RUTA') {
        return back()->with('swal', ['icon'=>'error','title'=>'No permitido','text'=>'Solo EN_RUTA puede marcarse como No entregado.']);
    }

    $request->validate(['nota' => 'nullable|string|max:500']);

    DB::transaction(function () use ($order, $request, $inv) {
        // El stock fue consumido en process(). Se devuelve ítem por ítem.
        foreach ($order->items as $it) {
            if (!$it->product_id || $it->cantidad <= 0) continue;
            $inv->stockIn(
                productId:   (int) $it->product_id,
                warehouseId: $order->warehouse_id,
                qty:         (float) $it->cantidad,
                motivo:      'DEVOLUCION_NO_ENTREGADO',
                referencia:  $order,
                userId:      auth()->id(),
            );
        }

        $data = ['status' => 'NO_ENTREGADO', 'no_entregado_at' => now()];
        if ($nota = $request->input('nota')) {
            $data['delivery_notes'] = trim(($order->delivery_notes ?? '') . "\n" . $nota);
        }

        $order->update($data);
        $order->increment('delivery_attempts');
    });

    return back()->with('swal', ['icon'=>'success','title'=>'No entregado','text'=>'Pedido marcado y stock revertido.']);
}

    // ======== Cobro y liquidación del chofer ========

    public function recordCash(Request $request, SalesOrder $order)
    {
        $request->validate(['monto'=>'required|numeric|min:0']);
        $monto = (float)$request->monto;

        DB::transaction(function () use ($order, $monto) {
            $order->update([
                'cobrado_efectivo'       => round(($order->cobrado_efectivo ?? 0) + $monto, 2),
                'cobrado_confirmado_at'  => now(),
                'cobrado_confirmado_por' => auth()->id(),
            ]);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Cobro registrado','text'=>'Se actualizó el cobro en efectivo.']);
    }

        public function settleDriver(
    Request $request,
    SalesOrder $order,
    \App\Services\ArService $ar,
    \App\Services\CashService $cash
) {
    $request->validate([
        'monto_entregado' => 'required|numeric|min:0',
        'payment_type_id' => 'required|exists:payment_types,id',
        'pos_register_id' => 'nullable|exists:cash_registers,id',
        'referencia'      => 'nullable|string|max:255',
    ]);

    DB::transaction(function () use ($order, $request, $ar, $cash) {
        $monto = (float) $request->monto_entregado;

        // 1) Abono en CxC — queda trazado contra el cliente
        if ($order->client_id && $monto > 0) {
            $ar->payment(
                clientId:      $order->client_id,
                amount:        $monto,
                paymentTypeId: (int) $request->payment_type_id,
                reference:     $request->referencia ?? $order->folio,
                notes:         "Liquidación chofer — {$order->folio}",
                fecha:         now()->toDateString(),
                driverId:      $order->driver_id,
            );
        }

        // 2) Registrar ingreso en la caja abierta del día
        if ($request->pos_register_id && $monto > 0) {
            $register = \App\Models\CashRegister::find($request->pos_register_id);
            if ($register && $register->estatus === 'ABIERTO') {
                $cash->addMovement(
                    $register,
                    'INGRESO',
                    $monto,
                    "Cobro pedido {$order->folio}",
                    $order
                );
            }
        }

        // 3) Marcar pedido como liquidado
        $order->update([
            'cobrado_efectivo'         => round(($order->cobrado_efectivo ?? 0) + $monto, 2),
            'driver_settlement_status' => 'LIQUIDADO',
            'driver_settlement_at'     => now(),
            'cobrado_confirmado_at'    => now(),
            'cobrado_confirmado_por'   => auth()->id(),
        ]);
    });

    return back()->with('swal', ['icon'=>'success','title'=>'Liquidado','text'=>'Liquidación completada.']);
}

    public function cancel(SalesOrder $order)
    {
        if (in_array($order->status,['EN_RUTA','ENTREGADO'])) {
            return back()->with('swal',['icon'=>'error','title'=>'Error','text'=>'No se puede cancelar en este estado.']);
        }
        $order->update(['status'=>'CANCELADO']);
        return back()->with('swal',['icon'=>'success','title'=>'Cancelado','text'=>'Pedido cancelado']);
    }

    // ========= PDF =========
        public function pdf(SalesOrder $order)
{
    $order->load('client', 'items.product', 'warehouse', 'driver', 'route');
    $empresa = app(\App\Services\CompanyService::class)->activa();

    $pdf = Pdf::loadView('pdf.sales_order', [
        'order'   => $order,
        'empresa' => $empresa,
    ]);
    return $pdf->stream('remision-pedido-' . $order->id . '.pdf');
}

public function pdfDownload(SalesOrder $order)
{
    $order->load('client', 'items.product', 'warehouse', 'driver', 'route');
    $empresa = app(\App\Services\CompanyService::class)->activa();

    $pdf = Pdf::loadView('pdf.sales_order', [
        'order'   => $order,
        'empresa' => $empresa,
    ]);
    return $pdf->download('remision-pedido-' . $order->id . '.pdf');
}

    // ========= Envío (email + WhatsApp) =========
    public function sendForm(SalesOrder $order)
    {
        $order->load('client','items.product');
        return view('admin.sales_orders.send', [
            'order'       => $order,
            'clientEmail' => $order->client->email ?? '',
            'clientPhone' => $order->client->telefono ?? '',
        ]);
    }

   public function send(Request $request, SalesOrder $order, \App\Services\WhatsappSender $whatsapp)
{
    $request->validate([
        'channels'    => ['required','array','min:1'],
        'channels.*'  => ['in:email,whatsapp'],
        'email'       => ['nullable','email'],
        'telefono'    => ['nullable','string'],
        'mensaje'     => ['nullable','string','max:500'],
    ]);

    $empresa = app(\App\Services\CompanyService::class)->activa();
    $order->loadMissing(['client','items.product','warehouse','driver','route']);

    $pdf    = Pdf::loadView('pdf.sales_order', ['order' => $order, 'empresa' => $empresa]);
    $pdfRaw = $pdf->output();
    $fname  = 'remision-' . ($order->folio ?? $order->id) . '.pdf';

    $errors = [];

    if (in_array('email', $request->channels, true)) {
        $to = $request->input('email') ?: ($order->client?->email ?? null);
        if (!$to) {
            $errors[] = 'El cliente no tiene correo y no proporcionaste uno.';
        } else {
            try {
                Mail::to($to)->send(new SalesOrderDeliveryNoteMailable(
                    order:    $order,
                    pdfRaw:   $pdfRaw,
                    filename: $fname,
                    mensaje:  $request->input('mensaje', ''),
                    empresa:  $empresa,
                ));
            } catch (\Throwable $e) {
                $errors[] = 'Error enviando email: ' . $e->getMessage();
            }
        }
    }

    if (in_array('whatsapp', $request->channels, true)) {
        $phone = $request->input('telefono') ?: ($order->client?->telefono ?? null);
        $msg   = $request->input('mensaje', 'Te adjunto la remisión del pedido 📎');

        if (!$phone) {
            $errors[] = 'El cliente no tiene teléfono y no proporcionaste uno.';
        } else {
            try {
                $resp = $whatsapp->sendPdf($phone, $msg, $fname, $pdfRaw);
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
        

    return back()->with('swal', [
        'icon'  => 'success',
        'title' => 'Enviado',
        'text'  => 'Se envió la remisión correctamente.',
    ]);
}
}
