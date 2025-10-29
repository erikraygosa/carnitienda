<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Warehouse; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\QuotePdfMailable;
use App\Services\WhatsappSender;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class QuoteController extends Controller
{
    public function index()
    {
        return view('admin.quotes.index');
    }

    public function create(Request $request)
    {
        $clients    = Client::orderBy('nombre')->get(['id','nombre']);
        $priceLists = PriceList::orderBy('nombre')->get(['id','nombre']);
        $products   = Product::orderBy('nombre')->get(['id','nombre','precio_base']);

        // === Mapas de precios ===
        $overrides = DB::table('client_price_overrides')
            ->select('client_id','product_id','precio')
            ->whereIn('client_id', $clients->pluck('id'))
            ->get()
            ->groupBy('client_id')
            ->map(fn($rows) => $rows->pluck('precio','product_id')
                ->map(fn($v) => (float)$v)->toArray())
            ->toArray();

        $listItems = DB::table('price_list_items')
            ->select('price_list_id','product_id','precio')
            ->whereIn('price_list_id', $priceLists->pluck('id'))
            ->get()
            ->groupBy('price_list_id')
            ->map(fn($rows) => $rows->pluck('precio','product_id')
                ->map(fn($v) => (float)$v)->toArray())
            ->toArray();

        return view('admin.quotes.create', [
            'clients'       => $clients,
            'priceLists'    => $priceLists,
            'products'      => $products,
            'seedItems'     => [],
            'overridesMap'  => $overrides,   // { client_id: { product_id: precio } }
            'listPricesMap' => $listItems,   // { list_id:   { product_id: precio } }
        ]);
    }

    public function store(Request $request)
    {
        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'          => ['required','date'],
            'client_id'      => ['nullable','exists:clients,id'],
            'price_list_id'  => ['nullable','exists:price_lists,id'],
            'moneda'         => ['required','string','max:10'],
            'vigencia_hasta' => ['nullable','date'],

            'items'                => ['required','array','min:1'],
            'items.*.product_id'   => ['nullable','exists:products,id'],
            'items.*.descripcion'  => ['required','string','max:255'],
            'items.*.cantidad'     => ['required','numeric','gt:0'],
            'items.*.precio'       => ['required','numeric','gte:0'],
            'items.*.descuento'    => ['nullable','numeric','gte:0'],
            'items.*.impuesto'     => ['nullable','numeric','gte:0'],
        ]);

        $quote = null;

        DB::transaction(function () use (&$quote, $data) {
            $subtotal = 0; $descuento = 0; $impuestos = 0; $total = 0;

            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc = (float)($it['descuento'] ?? 0);
                $line_tax  = (float)($it['impuesto']  ?? 0);
                $line_tot  = max($line_sub - $line_desc, 0) + $line_tax;

                $subtotal += $line_sub;
                $descuento += $line_desc;
                $impuestos += $line_tax;
                $total     += $line_tot;
            }

            $quote = Quote::create([
                'fecha'          => $data['fecha'],
                'client_id'      => $data['client_id'] ?? null,
                'price_list_id'  => $data['price_list_id'] ?? null,
                'moneda'         => $data['moneda'],
                'subtotal'       => $subtotal,
                'impuestos'      => $impuestos,
                'descuento'      => $descuento,
                'total'          => $total,
                'vigencia_hasta' => $data['vigencia_hasta'] ?? null,
                'status'         => 'BORRADOR',
                'created_by'     => auth()->id(),
                'owner_id'       => auth()->id(),
            ]);

            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc = (float)($it['descuento'] ?? 0);
                $line_tax  = (float)($it['impuesto']  ?? 0);
                $line_tot  = max($line_sub - $line_desc, 0) + $line_tax;

                QuoteItem::create([
                    'quote_id'    => $quote->id,
                    'product_id'  => $it['product_id'] ?? null,
                    'descripcion' => $it['descripcion'],
                    'cantidad'    => $it['cantidad'],
                    'precio'      => $it['precio'],
                    'descuento'   => $line_desc,
                    'impuesto'    => $line_tax,
                    'total'       => $line_tot,
                ]);
            }
        });

        session()->flash('swal', ['icon'=>'success','title'=>'¡Creada!','text'=>'Cotización guardada.']);
        return redirect()->route('admin.quotes.edit', $quote);
    }

    public function edit(Quote $quote)
    {
        return view('admin.quotes.edit', [
            'quote'       => $quote->load('items.product','client','priceList'),
            'clients'     => Client::orderBy('nombre')->get(['id','nombre']),
            'priceLists'  => PriceList::orderBy('nombre')->get(['id','nombre']),
            'products'    => Product::orderBy('nombre')->get(['id','nombre','precio_base']),
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        if ($quote->status !== 'BORRADOR') {
            return back()->with('swal', ['icon'=>'error','title'=>'Error','text'=>'Solo borrador puede editarse.']);
        }

        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'          => ['required','date'],
            'client_id'      => ['nullable','exists:clients,id'],
            'price_list_id'  => ['nullable','exists:price_lists,id'],
            'moneda'         => ['required','string','max:10'],
            'vigencia_hasta' => ['nullable','date'],

            'items'                => ['required','array','min:1'],
            'items.*.product_id'   => ['nullable','exists:products,id'],
            'items.*.descripcion'  => ['required','string','max:255'],
            'items.*.cantidad'     => ['required','numeric','gt:0'],
            'items.*.precio'       => ['required','numeric','gte:0'],
            'items.*.descuento'    => ['nullable','numeric','gte:0'],
            'items.*.impuesto'     => ['nullable','numeric','gte:0'],
        ]);

        DB::transaction(function () use ($quote, $data) {
            $subtotal = 0; $descuento = 0; $impuestos = 0; $total = 0;

            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc = (float)($it['descuento'] ?? 0);
                $line_tax  = (float)($it['impuesto']  ?? 0);
                $line_tot  = max($line_sub - $line_desc, 0) + $line_tax;

                $subtotal += $line_sub;
                $descuento += $line_desc;
                $impuestos += $line_tax;
                $total     += $line_tot;
            }

            $quote->update([
                'fecha'          => $data['fecha'],
                'client_id'      => $data['client_id'] ?? null,
                'price_list_id'  => $data['price_list_id'] ?? null,
                'moneda'         => $data['moneda'],
                'subtotal'       => $subtotal,
                'impuestos'      => $impuestos,
                'descuento'      => $descuento,
                'total'          => $total,
                'vigencia_hasta' => $data['vigencia_hasta'] ?? null,
            ]);

            $quote->items()->delete();

            foreach ($data['items'] as $it) {
                $line_sub = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc = (float)($it['descuento'] ?? 0);
                $line_tax  = (float)($it['impuesto']  ?? 0);
                $line_tot  = max($line_sub - $line_desc, 0) + $line_tax;

                QuoteItem::create([
                    'quote_id'    => $quote->id,
                    'product_id'  => $it['product_id'] ?? null,
                    'descripcion' => $it['descripcion'],
                    'cantidad'    => $it['cantidad'],
                    'precio'      => $it['precio'],
                    'descuento'   => $line_desc,
                    'impuesto'    => $line_tax,
                    'total'       => $line_tot,
                ]);
            }
        });

        session()->flash('swal', ['icon'=>'success','title'=>'Actualizada','text'=>'Cotización actualizada.']);
        return redirect()->route('admin.quotes.edit', $quote);
    }

    public function destroy(Quote $quote)
    {
        if (! in_array($quote->status, ['BORRADOR','RECHAZADA'], true)) {
            return back()->with('swal', ['icon'=>'error','title'=>'Error','text'=>'No se puede eliminar en este estado.']);
        }

        $quote->delete();

        return redirect()->route('admin.quotes.index')
            ->with('swal', ['icon'=>'success','title'=>'Eliminada','text'=>'Cotización eliminada.']);
    }

    // ====== ENVÍO (FORM + ACCIÓN) ======
    public function sendForm(Quote $quote)
    {
        $quote->load('client','items.product');

        return view('admin.quotes.send', [
            'quote'       => $quote,
            'clientEmail' => $quote->client->email ?? '',
            'clientPhone' => $quote->client->telefono ?? '',
        ]);
    }

    public function send(Request $request, Quote $quote, WhatsappSender $whatsapp)
    {
        $request->validate([
            'channels'    => ['required','array','min:1'], // ['email','whatsapp']
            'channels.*'  => ['in:email,whatsapp'],
            'email'       => ['nullable','email'],
            'telefono'    => ['nullable','string'],
            'mensaje'     => ['nullable','string','max:500'],
        ]);

        $quote->load('client','items.product');

        // 1) Generar PDF
        $pdf     = Pdf::loadView('pdf.quote', ['quote' => $quote]);
        $pdfRaw  = $pdf->output();
        $fname   = 'cotizacion-'.$quote->id.'.pdf';

        $sentEmail    = false;
        $sentWhatsapp = false;
        $errors       = [];

        // 2) Email
        if (in_array('email', $request->channels, true)) {
            $to = $request->input('email') ?: ($quote->client->email ?? null);
            if (!$to) {
                $errors[] = 'El cliente no tiene correo y no proporcionaste uno.';
            } else {
                try {
                    Mail::to($to)->send(new QuotePdfMailable($quote, $pdfRaw, $fname));
                    $sentEmail = true;
                } catch (\Throwable $e) {
                    $errors[] = 'Error enviando email: '.$e->getMessage();
                }
            }
        }

        // 3) WhatsApp
        if (in_array('whatsapp', $request->channels, true)) {
            $phone  = $request->input('telefono') ?: ($quote->client->telefono ?? null);
            $msg    = $request->input('mensaje', 'Te adjunto la cotización 📎');

            if (!$phone) {
                $errors[] = 'El cliente no tiene teléfono y no proporcionaste uno.';
            } else {
                try {
                    $resp = $whatsapp->sendPdf($phone, $msg, $fname, $pdfRaw);
                    if (!($resp['ok'] ?? false)) {
                        $errors[] = 'WhatsApp API respondió '.($resp['status'] ?? '400').': '.json_encode($resp['body'] ?? []);
                    } else {
                        $sentWhatsapp = true;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Error enviando WhatsApp: '.$e->getMessage();
                }
            }
        }

        // Cambia estado si al menos un canal se envió
        if ($sentEmail || $sentWhatsapp) {
            if ($quote->status === 'BORRADOR') {
                $quote->update(['status' => 'ENVIADA']);
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
            'title' => 'Enviada',
            'text'  => 'La cotización se envió correctamente.',
        ]);
    }

    // ====== PDF ======
    public function pdf(Quote $quote)
    {
        $quote->load('client','items.product');
        $pdf = Pdf::loadView('pdf.quote', ['quote' => $quote]);
        return $pdf->stream('cotizacion-'.$quote->id.'.pdf');
    }

    public function pdfDownload(Quote $quote)
    {
        $quote->load('client','items.product');
        $pdf = Pdf::loadView('pdf.quote', ['quote' => $quote]);
        return $pdf->download('cotizacion-'.$quote->id.'.pdf');
    }

  public function approve(Request $request, Quote $quote)
{
    if (! in_array($quote->status, ['BORRADOR','ENVIADA'], true)) {
        return back()->with('swal', [
            'icon'=>'error','title'=>'No permitido',
            'text'=>'Solo BORRADOR o ENVIADA pueden aprobarse.'
        ]);
    }

    $quote->load('items');
    if ($quote->items->isEmpty()) {
        return back()->with('swal', [
            'icon'=>'error','title'=>'Sin conceptos',
            'text'=>'La cotización no tiene partidas.'
        ]);
    }

    // Defaults si no llegan en el form
    $warehouseId   = $request->input('warehouse_id') ?: Warehouse::query()->value('id');
    if (!$warehouseId) {
        return back()->with('swal', [
            'icon'=>'error','title'=>'Falta almacén',
            'text'=>'No hay almacenes registrados. Crea uno primero.'
        ]);
    }
    $deliveryType  = $request->input('delivery_type', 'RECOGER');
    $paymentMethod = $request->input('payment_method', 'EFECTIVO');
    $creditDays    = $paymentMethod === 'CREDITO' ? (int)$request->input('credit_days', 0) : null;
    $programado    = $request->input('programado_para');

    $order = null;

    DB::transaction(function () use ($quote, $warehouseId, $deliveryType, $paymentMethod, $creditDays, $programado, &$order) {
        $nextId = (SalesOrder::max('id') ?? 0) + 1;
        $folio  = 'SO-'.now()->format('Ymd').'-'.Str::padLeft((string)$nextId, 4, '0');

        $payload = [
            'client_id'         => $quote->client_id,
            'warehouse_id'      => $warehouseId,
            'price_list_id'     => $quote->price_list_id,
            'folio'             => $folio,
            'fecha'             => $quote->fecha ?? now(),
            'programado_para'   => $programado,

            'delivery_type'     => $deliveryType,
            'entrega_nombre'    => null,
            'entrega_telefono'  => null,
            'entrega_calle'     => null,
            'entrega_numero'    => null,
            'entrega_colonia'   => null,
            'entrega_ciudad'    => null,
            'entrega_estado'    => null,
            'entrega_cp'        => null,

            'shipping_route_id' => null,
            'driver_id'         => null,

            'payment_method'    => $paymentMethod,
            'credit_days'       => $creditDays,

            'moneda'            => $quote->moneda,
            'subtotal'          => $quote->subtotal,
            'impuestos'         => $quote->impuestos,
            'descuento'         => $quote->descuento,
            'total'             => $quote->total,
            'status'            => 'BORRADOR', // luego podrás aprobar el pedido desde su flujo
            'created_by'        => auth()->id(),
            'owner_id'          => auth()->id(),

            'contraentrega_total' => $paymentMethod === 'CONTRAENTREGA' ? $quote->total : 0,
        ];

        if (Schema::hasColumn('sales_orders','quote_id')) {
            $payload['quote_id'] = $quote->id;
        }

        $order = SalesOrder::create($payload);

        foreach ($quote->items as $it) {
            SalesOrderItem::create([
                'sales_order_id' => $order->id,
                'product_id'     => $it->product_id,
                'descripcion'    => $it->descripcion,
                'cantidad'       => $it->cantidad,
                'precio'         => $it->precio,
                'descuento'      => $it->descuento,
                'impuesto'       => $it->impuesto,
                'total'          => $it->total,
            ]);
        }

        $quote->update(['status' => 'APROBADA']);
    });

    return redirect()
        ->route('admin.sales-orders.edit', $order)
        ->with('swal', ['icon'=>'success','title'=>'Aprobada','text'=>'Se generó el Pedido.']);
}

}
