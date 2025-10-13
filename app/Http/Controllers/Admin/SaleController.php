<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Driver;
use App\Models\ShippingRoute;
use App\Models\PosRegister;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\SaleNoteMailable;
use App\Services\WhatsappSender;

class SaleController extends Controller
{
    public function index()
    {
        return view('admin.sales.index');
    }

    public function create(Request $request)
    {
        $clients      = Client::orderBy('nombre')->get(['id','nombre','email','telefono']);
        $priceLists   = PriceList::orderBy('nombre')->get(['id','nombre']);
        $products     = Product::orderBy('nombre')->get(['id','nombre','precio_base']);
        $warehouses   = Warehouse::orderBy('nombre')->get(['id','nombre']);
        $drivers      = Driver::orderBy('nombre')->get(['id','nombre']);
        $routes       = ShippingRoute::orderBy('nombre')->get(['id','nombre']);
        $posRegisters = PosRegister::orderBy('id')->get(['id','nombre']);
       $payTypes = PaymentType::query()
    ->select('id','clave','descripcion')
    ->orderBy('clave')
    ->get()
    // opcional: normalizamos una etiqueta amigable para la vista
    ->map(fn($p) => (object)[
        'id'    => $p->id,
        'clave' => $p->clave,                        // EFECTIVO, TRANSFERENCIA, etc.
        'label' => $p->descripcion ?: $p->clave,     // “Pago en efectivo”, etc.
    ]);

        // Mapas de precios (personalizado por cliente y listas), igual que en quotes
        $overrides = DB::table('client_price_overrides')
            ->select('client_id','product_id','precio')
            ->whereIn('client_id', $clients->pluck('id'))
            ->get()
            ->groupBy('client_id')
            ->map(fn($rows)=> $rows->pluck('precio','product_id')->map(fn($v)=>(float)$v)->toArray())
            ->toArray();

        $listItems = DB::table('price_list_items')
            ->select('price_list_id','product_id','precio')
            ->whereIn('price_list_id', $priceLists->pluck('id'))
            ->get()
            ->groupBy('price_list_id')
            ->map(fn($rows)=> $rows->pluck('precio','product_id')->map(fn($v)=>(float)$v)->toArray())
            ->toArray();

        return view('admin.sales.create', compact(
            'clients','priceLists','products','warehouses','drivers','routes','posRegisters','payTypes','overrides','listItems'
        ));
    }

    public function store(Request $request)
    {
        // Normaliza "client" -> null
        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'             => ['required','date'],
            'pos_register_id'   => ['required','exists:pos_registers,id'],
            'warehouse_id'      => ['required','exists:warehouses,id'],
            'client_id'         => ['nullable','exists:clients,id'],
            'payment_type_id'   => ['nullable','exists:payment_types,id'],
            'price_list_id'     => ['nullable','exists:price_lists,id'],
            'moneda'            => ['required','string','max:10'],

            'tipo_venta'        => ['required','in:CONTADO,CREDITO,CONTRAENTREGA'],
            'credit_days'       => ['nullable','integer','min:0'],

            'delivery_type'     => ['required','in:ENVIO,RECOGER'],
            'entrega_nombre'    => ['nullable','string','max:255'],
            'entrega_telefono'  => ['nullable','string','max:50'],
            'entrega_calle'     => ['nullable','string','max:255'],
            'entrega_numero'    => ['nullable','string','max:50'],
            'entrega_colonia'   => ['nullable','string','max:255'],
            'entrega_ciudad'    => ['nullable','string','max:255'],
            'entrega_estado'    => ['nullable','string','max:255'],
            'entrega_cp'        => ['nullable','string','max:10'],
            'shipping_route_id' => ['nullable','exists:shipping_routes,id'],
            'driver_id'         => ['nullable','exists:drivers,id'],

            'items'                 => ['required','array','min:1'],
            'items.*.product_id'    => ['required','exists:products,id'],
            'items.*.descripcion'   => ['required','string','max:255'],
            'items.*.cantidad'      => ['required','numeric','gt:0'],
            'items.*.precio'        => ['required','numeric','gte:0'],
            'items.*.descuento'     => ['nullable','numeric','gte:0'],
            'items.*.impuesto'      => ['nullable','numeric','gte:0'],
        ]);

        $sale = null;

        DB::transaction(function () use (&$sale, $data) {
            $subtotal=0; $descuento=0; $impuestos=0; $total=0;
            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc= (float)($it['descuento'] ?? 0);
                $line_tax = (float)($it['impuesto']  ?? 0);
                $line_tot = max($line_sub - $line_desc, 0) + $line_tax;
                $subtotal += $line_sub; $descuento += $line_desc; $impuestos += $line_tax; $total += $line_tot;
            }

            $nextId = (Sale::max('id') ?? 0) + 1;
            $folio  = 'NV-'.now()->format('Ymd').'-'.str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);

            $sale = Sale::create([
                'folio'           => $folio,
                'fecha'           => $data['fecha'],
                'pos_register_id' => $data['pos_register_id'],
                'warehouse_id'    => $data['warehouse_id'],
                'client_id'       => $data['client_id'] ?? null,
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'price_list_id'   => $data['price_list_id'] ?? null,
                'moneda'          => $data['moneda'],

                'tipo_venta'      => $data['tipo_venta'],
                'credit_days'     => $data['tipo_venta']==='CREDITO' ? ($data['credit_days'] ?? 0) : null,

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

                'subtotal'        => $subtotal,
                'impuestos'       => $impuestos,
                'descuento'       => $descuento,
                'total'           => $total,
                'status'          => 'ABIERTA',
                'user_id'         => auth()->id(),
            ]);

            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc= (float)($it['descuento'] ?? 0);
                $line_tax = (float)($it['impuesto']  ?? 0);
                $line_tot = max($line_sub - $line_desc, 0) + $line_tax;

                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $it['product_id'],
                    'descripcion' => $it['descripcion'],
                    'cantidad'    => $it['cantidad'],
                    'precio'      => $it['precio'],
                    'descuento'   => $line_desc,
                    'impuesto'    => $line_tax,
                    'total'       => $line_tot,
                ]);
            }
        });

        return redirect()->route('admin.sales.edit', $sale)
            ->with('swal',['icon'=>'success','title'=>'Creada','text'=>'Nota de venta creada.']);
    }

    public function edit(Sale $sale)
    {
        $sale->load('items.product','client','priceList','warehouse','driver','route','posRegister','paymentType');

        $clients      = Client::orderBy('nombre')->get(['id','nombre','email','telefono']);
        $priceLists   = PriceList::orderBy('nombre')->get(['id','nombre']);
        $products     = Product::orderBy('nombre')->get(['id','nombre','precio_base']);
        $warehouses   = Warehouse::orderBy('nombre')->get(['id','nombre']);
        $drivers      = Driver::orderBy('nombre')->get(['id','nombre']);
        $routes       = ShippingRoute::orderBy('nombre')->get(['id','nombre']);
        $posRegisters = PosRegister::orderBy('id')->get(['id','nombre']);
        $payTypes = PaymentType::query()
    ->select('id','clave','descripcion')
    ->orderBy('clave')
    ->get()
    // opcional: normalizamos una etiqueta amigable para la vista
    ->map(fn($p) => (object)[
        'id'    => $p->id,
        'clave' => $p->clave,                        // EFECTIVO, TRANSFERENCIA, etc.
        'label' => $p->descripcion ?: $p->clave,     // “Pago en efectivo”, etc.
    ]);

        return view('admin.sales.edit', compact(
            'sale','clients','priceLists','products','warehouses','drivers','routes','posRegisters','payTypes'
        ));
    }

    public function update(Request $request, Sale $sale)
    {
        if ($sale->status !== 'ABIERTA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo ABIERTO puede editarse.']);
        }

        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'             => ['required','date'],
            'pos_register_id'   => ['required','exists:pos_registers,id'],
            'warehouse_id'      => ['required','exists:warehouses,id'],
            'client_id'         => ['nullable','exists:clients,id'],
            'payment_type_id'   => ['nullable','exists:payment_types,id'],
            'price_list_id'     => ['nullable','exists:price_lists,id'],
            'moneda'            => ['required','string','max:10'],

            'tipo_venta'        => ['required','in:CONTADO,CREDITO,CONTRAENTREGA'],
            'credit_days'       => ['nullable','integer','min:0'],

            'delivery_type'     => ['required','in:ENVIO,RECOGER'],
            'entrega_nombre'    => ['nullable','string','max:255'],
            'entrega_telefono'  => ['nullable','string','max:50'],
            'entrega_calle'     => ['nullable','string','max:255'],
            'entrega_numero'    => ['nullable','string','max:50'],
            'entrega_colonia'   => ['nullable','string','max:255'],
            'entrega_ciudad'    => ['nullable','string','max:255'],
            'entrega_estado'    => ['nullable','string','max:255'],
            'entrega_cp'        => ['nullable','string','max:10'],
            'shipping_route_id' => ['nullable','exists:shipping_routes,id'],
            'driver_id'         => ['nullable','exists:drivers,id'],

            'items'                 => ['required','array','min:1'],
            'items.*.product_id'    => ['required','exists:products,id'],
            'items.*.descripcion'   => ['required','string','max:255'],
            'items.*.cantidad'      => ['required','numeric','gt:0'],
            'items.*.precio'        => ['required','numeric','gte:0'],
            'items.*.descuento'     => ['nullable','numeric','gte:0'],
            'items.*.impuesto'      => ['nullable','numeric','gte:0'],
        ]);

        DB::transaction(function () use ($sale, $data) {
            $subtotal=0; $descuento=0; $impuestos=0; $total=0;
            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc= (float)($it['descuento'] ?? 0);
                $line_tax = (float)($it['impuesto']  ?? 0);
                $line_tot = max($line_sub - $line_desc, 0) + $line_tax;
                $subtotal += $line_sub; $descuento += $line_desc; $impuestos += $line_tax; $total += $line_tot;
            }

            $sale->update([
                'fecha'           => $data['fecha'],
                'pos_register_id' => $data['pos_register_id'],
                'warehouse_id'    => $data['warehouse_id'],
                'client_id'       => $data['client_id'] ?? null,
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'price_list_id'   => $data['price_list_id'] ?? null,
                'moneda'          => $data['moneda'],

                'tipo_venta'      => $data['tipo_venta'],
                'credit_days'     => $data['tipo_venta']==='CREDITO' ? ($data['credit_days'] ?? 0) : null,

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

                'subtotal'        => $subtotal,
                'impuestos'       => $impuestos,
                'descuento'       => $descuento,
                'total'           => $total,
            ]);

            $sale->items()->delete();
            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc= (float)($it['descuento'] ?? 0);
                $line_tax = (float)($it['impuesto']  ?? 0);
                $line_tot = max($line_sub - $line_desc, 0) + $line_tax;

                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $it['product_id'],
                    'descripcion' => $it['descripcion'],
                    'cantidad'    => $it['cantidad'],
                    'precio'      => $it['precio'],
                    'descuento'   => $line_desc,
                    'impuesto'    => $line_tax,
                    'total'       => $line_tot,
                ]);
            }
        });

        return redirect()->route('admin.sales.edit', $sale)
            ->with('swal',['icon'=>'success','title'=>'Actualizada','text'=>'Nota de venta actualizada.']);
    }

    public function destroy(Sale $sale)
    {
        if ($sale->status !== 'ABIERTA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo notas ABiertas se pueden eliminar.']);
        }
        $sale->delete();
        return redirect()->route('admin.sales.index')
            ->with('swal',['icon'=>'success','title'=>'Eliminada','text'=>'Nota de venta eliminada.']);
    }

    // ====== Acciones ======
    public function close(Sale $sale)
    {
        if ($sale->status !== 'ABIERTA') return back();

        DB::transaction(function () use ($sale) {
            // Baja de inventario
            foreach ($sale->items as $it) {
                \App\Models\StockMovement::create([
                    'warehouse_id'    => $sale->warehouse_id,
                    'product_id'      => $it->product_id,
                    'tipo'            => 'OUT',
                    'cantidad'        => $it->cantidad,
                    'motivo'          => 'Nota de venta #'.$sale->id,
                    'referencia_type' => Sale::class,
                    'referencia_id'   => $sale->id,
                    'user_id'         => auth()->id(),
                ]);
            }

            // CxC si crédito
            if ($sale->tipo_venta === 'CREDITO') {
                \App\Models\AccountsReceivable::create([
                    'client_id'   => $sale->client_id,
                    'sale_id'     => $sale->id,
                    'fecha'       => now(),
                    'vencimiento' => now()->addDays($sale->credit_days ?? 0),
                    'moneda'      => $sale->moneda,
                    'monto'       => $sale->total,
                    'saldo'       => $sale->total,
                    'status'      => 'ABIERTO',
                ]);
            }

            $sale->update(['status' => 'CERRADA']);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Cerrada','text'=>'Nota cerrada y stock actualizado.']);
    }

    public function cancel(Sale $sale)
    {
        if ($sale->status === 'CERRADA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'No puedes cancelar una nota cerrada.']);
        }
        $sale->update(['status' => 'CANCELADA']);
        return back()->with('swal',['icon'=>'success','title'=>'Cancelada','text'=>'Nota cancelada.']);
    }

    // ====== PDF ======
    public function pdf(Sale $sale)
    {
        $sale->load('client','items.product','warehouse','driver','route');
        $pdf = Pdf::loadView('pdf.sales_note', ['sale' => $sale]);
        return $pdf->stream('nota-venta-'.$sale->id.'.pdf');
    }

    public function pdfDownload(Sale $sale)
    {
        $sale->load('client','items.product','warehouse','driver','route');
        $pdf = Pdf::loadView('pdf.sales_note', ['sale' => $sale]);
        return $pdf->download('nota-venta-'.$sale->id.'.pdf');
    }

    // ====== Envío ======
    public function sendForm(Sale $sale)
    {
        $sale->load('client');
        return view('admin.sales.send', [
            'sale'        => $sale,
            'clientEmail' => $sale->client->email ?? '',
            'clientPhone' => $sale->client->telefono ?? '',
        ]);
    }

    public function send(Request $request, Sale $sale, WhatsappSender $whatsapp)
    {
        $request->validate([
            'channels'    => ['required','array','min:1'],
            'channels.*'  => ['in:email,whatsapp'],
            'email'       => ['nullable','email'],
            'telefono'    => ['nullable','string'],
            'mensaje'     => ['nullable','string','max:500'],
        ]);

        $sale->load('client','items.product','warehouse','driver','route');

        $pdf    = Pdf::loadView('pdf.sales_note', ['sale' => $sale]);
        $raw    = $pdf->output();
        $fname  = 'nota-venta-'.$sale->id.'.pdf';

        $sentEmail=false; $sentWhatsapp=false; $errors=[];

        if (in_array('email', $request->channels, true)) {
            $to = $request->input('email') ?: ($sale->client->email ?? null);
            if (!$to) {
                $errors[] = 'Sin email de cliente y no proporcionaste uno.';
            } else {
                try {
                    Mail::to($to)->send(new SaleNoteMailable($sale, $raw, $fname));
                    $sentEmail = true;
                } catch (\Throwable $e) {
                    $errors[] = 'Error email: '.$e->getMessage();
                }
            }
        }

        if (in_array('whatsapp', $request->channels, true)) {
            $phone = $request->input('telefono') ?: ($sale->client->telefono ?? null);
            $msg   = $request->input('mensaje', 'Te adjunto la nota de venta 📎');
            if (!$phone) {
                $errors[] = 'Sin teléfono de cliente y no proporcionaste uno.';
            } else {
                try {
                    $resp = $whatsapp->sendPdf($phone, $msg, $fname, $raw);
                    if (!$resp['ok']) {
                        $errors[] = 'WhatsApp API '.$resp['status'].': '.json_encode($resp['body']);
                    } else {
                        $sentWhatsapp = true;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Error WhatsApp: '.$e->getMessage();
                }
            }
        }

        if ($errors) {
            return back()->with('swal', ['icon'=>'error','title'=>'Envío parcial','text'=>implode(' | ', $errors)]);
        }

        return back()->with('swal', ['icon'=>'success','title'=>'Enviado','text'=>'Se envió la nota correctamente.']);
    }
}
