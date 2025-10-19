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

class SalesOrderController extends Controller
{
    public function index()
    {
        return view('admin.sales_orders.index');
    }

    public function create(Request $request)
    {
        $clients     = Client::orderBy('nombre')->get(['id','nombre','email','telefono']);
        $priceLists  = PriceList::orderBy('nombre')->get(['id','nombre']);
        $products    = Product::orderBy('nombre')->get(['id','nombre','precio_base']);
        $warehouses  = Warehouse::orderBy('nombre')->get(['id','nombre']);
        $drivers     = Driver::orderBy('nombre')->get(['id','nombre']);
        $routes      = ShippingRoute::orderBy('nombre')->get(['id','nombre']);

        // Overrides por cliente
        $overrides = DB::table('client_price_overrides')
            ->select('client_id','product_id','precio')
            ->whereIn('client_id', $clients->pluck('id'))
            ->get()
            ->groupBy('client_id')
            ->map(fn($rows) => $rows->pluck('precio','product_id')->map(fn($v)=>(float)$v)->toArray())
            ->toArray();

        // Items de listas
        $listItems = DB::table('price_list_items')
            ->select('price_list_id','product_id','precio')
            ->whereIn('price_list_id', $priceLists->pluck('id'))
            ->get()
            ->groupBy('price_list_id')
            ->map(fn($rows) => $rows->pluck('precio','product_id')->map(fn($v)=>(float)$v)->toArray())
            ->toArray();

        return view('admin.sales_orders.create', compact(
            'clients','priceLists','products','warehouses','drivers','routes','overrides','listItems'
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
        if ($order->status!=='BORRADOR') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo BORRADOR se puede aprobar.']);
        }
        $order->update(['status'=>'APROBADO']);
        return back()->with('swal',['icon'=>'success','title'=>'Aprobado','text'=>'Pedido aprobado']);
    }

    public function startPreparing(SalesOrder $order)
    {
        if ($order->status!=='APROBADO') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo APROBADO pasa a PREPARANDO.']);
        }
        $order->update(['status'=>'PREPARANDO','preparado_at'=>now()]);
        return back()->with('swal',['icon'=>'success','title'=>'Preparando','text'=>'Pedido en preparación.']);
    }

    public function process(SalesOrder $order)
    {
        if (!in_array($order->status, ['APROBADO','PREPARANDO'])) {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Debe estar APROBADO o PREPARANDO.']);
        }

        DB::transaction(function() use ($order){
            foreach ($order->items as $it) {
                if ($it->product_id) {
                    StockMovement::create([
                        'warehouse_id'    => $order->warehouse_id,
                        'product_id'      => $it->product_id,
                        'tipo'            => 'OUT',
                        'cantidad'        => $it->cantidad,
                        'motivo'          => 'Pedido procesado #'.$order->id,
                        'referencia_type' => SalesOrder::class,
                        'referencia_id'   => $order->id,
                        'user_id'         => auth()->id(),
                    ]);
                }
            }
            $order->update(['status' => 'PROCESADO','despachado_at'=>now()]);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Procesado','text'=>'Stock descontado y pedido PROCESADO.']);
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

    public function deliver(SalesOrder $order)
    {
        if ($order->status !== 'EN_RUTA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo EN_RUTA puede marcarse como entregado.']);
        }

        DB::transaction(function() use ($order){
            // (Opcional) Generar la venta aquí si así lo manejas
            // ...

            $order->update(['status' => 'ENTREGADO', 'entregado_at'=>now()]);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Entregado','text'=>'Pedido entregado correctamente.']);
    }

    public function notDelivered(Request $request, SalesOrder $order)
    {
        if ($order->status !== 'EN_RUTA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo EN_RUTA puede marcarse como No entregado.']);
        }

        $request->validate(['nota'=>'nullable|string|max:500']);
        $nota = $request->input('nota');

        $data = ['status'=>'NO_ENTREGADO','no_entregado_at'=>now()];
        if ($nota) {
            $data['delivery_notes'] = trim(($order->delivery_notes ?? '')."\n".$nota);
        }

        DB::transaction(function() use ($order, $data) {
            $order->update($data);
            $order->increment('delivery_attempts');
        });

        return back()->with('swal',['icon'=>'success','title'=>'No entregado','text'=>'Se marcó el pedido como no entregado.']);
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

    public function settleDriver(Request $request, SalesOrder $order)
    {
        // Si usas POS register para la liquidación del día:
        $posRegisterId = $request->input('pos_register_id');

        DB::transaction(function () use ($order, $posRegisterId) {
            $data = [
                'driver_settlement_status' => 'LIQUIDADO',
                'driver_settlement_at'     => now(),
            ];
            if ($posRegisterId) $data['pos_register_id'] = $posRegisterId;
            $order->update($data);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Liquidado','text'=>'Liquidación del chofer completada.']);
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
        $order->load('client','items.product','warehouse','driver','route');
        $pdf = Pdf::loadView('pdf.sales_order', ['order' => $order]);
        return $pdf->stream('remision-pedido-'.$order->id.'.pdf');
    }

    public function pdfDownload(SalesOrder $order)
    {
        $order->load('client','items.product','warehouse','driver','route');
        $pdf = Pdf::loadView('pdf.sales_order', ['order' => $order]);
        return $pdf->download('remision-pedido-'.$order->id.'.pdf');
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

        $order->load('client','items.product');

        $pdf     = Pdf::loadView('pdf.sales_order', ['order' => $order]);
        $pdfRaw  = $pdf->output();
        $fname   = 'remision-pedido-'.$order->id.'.pdf';

        $sentEmail = false;
        $sentWhatsapp = false;
        $errors = [];

        if (in_array('email', $request->channels, true)) {
            $to = $request->input('email') ?: ($order->client->email ?? null);
            if (!$to) {
                $errors[] = 'El cliente no tiene correo y no proporcionaste uno.';
            } else {
                try {
                    Mail::to($to)->send(new SalesOrderDeliveryNoteMailable($order, $pdfRaw, $fname));
                    $sentEmail = true;
                } catch (\Throwable $e) {
                    $errors[] = 'Error enviando email: '.$e->getMessage();
                }
            }
        }

        if (in_array('whatsapp', $request->channels, true)) {
            $phone  = $request->input('telefono') ?: ($order->client->telefono ?? null);
            $msg    = $request->input('mensaje', 'Te adjunto la remisión del pedido 📎');

            if (!$phone) {
                $errors[] = 'El cliente no tiene teléfono y no proporcionaste uno.';
            } else {
                try {
                    $resp = $whatsapp->sendPdf($phone, $msg, $fname, $pdfRaw);
                    if (!($resp['ok'] ?? false)) {
                        $errors[] = 'WhatsApp API respondió '.($resp['status'] ?? '500').': '.json_encode($resp['body'] ?? []);
                    } else {
                        $sentWhatsapp = true;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Error enviando WhatsApp: '.$e->getMessage();
                }
            }
        }

        // Ya no movemos a DESPACHADO automáticamente; la salida a ruta se hace con dispatchToRoute()
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
