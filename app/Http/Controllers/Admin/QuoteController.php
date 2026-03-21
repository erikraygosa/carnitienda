<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Warehouse;
use App\Models\ShippingRoute;
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

    // ── Helpers privados para construir los mapas de precios ─────────────────

    private function buildPriceMaps(object $clients, object $priceLists): array
    {
        $overridesMap = DB::table('client_price_overrides')
            ->select('client_id', 'product_id', 'precio')
            ->whereIn('client_id', $clients->pluck('id'))
            ->get()
            ->groupBy('client_id')
            ->map(fn($rows) => $rows->pluck('precio', 'product_id')->map(fn($v) => (float)$v)->toArray())
            ->toArray();

        $listPricesMap = DB::table('price_list_items')
            ->select('price_list_id', 'product_id', 'precio')
            ->whereIn('price_list_id', $priceLists->pluck('id'))
            ->get()
            ->groupBy('price_list_id')
            ->map(fn($rows) => $rows->pluck('precio', 'product_id')->map(fn($v) => (float)$v)->toArray())
            ->toArray();

        return [$overridesMap, $listPricesMap];
    }

    private function buildClientDefaults(object $clients): array
    {
        return $clients->mapWithKeys(fn($c) => [(string)$c->id => [
            'shipping_route_id' => (string) ($c->shipping_route_id ?? ''),
            'price_list_id'     => (string) ($c->price_list_id ?? ''),
            'credito_dias'      => (int)    ($c->credito_dias  ?? 0),
            'credito_limite'    => (float)  ($c->credito_limite ?? 0),
            'telefono'          => (string) ($c->telefono ?? ''),
            'entrega_calle'    => $c->entrega_igual_fiscal ? ($c->fiscal_calle   ?? '') : ($c->entrega_calle   ?? ''),
            'entrega_numero'   => $c->entrega_igual_fiscal ? ($c->fiscal_numero  ?? '') : ($c->entrega_numero  ?? ''),
            'entrega_colonia'  => $c->entrega_igual_fiscal ? ($c->fiscal_colonia ?? '') : ($c->entrega_colonia ?? ''),
            'entrega_ciudad'   => $c->entrega_igual_fiscal ? ($c->fiscal_ciudad  ?? '') : ($c->entrega_ciudad  ?? ''),
            'entrega_estado'   => $c->entrega_igual_fiscal ? ($c->fiscal_estado  ?? '') : ($c->entrega_estado  ?? ''),
            'entrega_cp'       => $c->entrega_igual_fiscal ? ($c->fiscal_cp      ?? '') : ($c->entrega_cp      ?? ''),
        ]])->toArray();
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $clients    = Client::orderBy('nombre')->get();
        $priceLists = PriceList::orderBy('nombre')->get(['id', 'nombre']);
        $products   = Product::orderBy('nombre')->get(['id', 'nombre', 'precio_base']);
        $routes     = ShippingRoute::orderBy('nombre')->get(['id', 'nombre']);

        [$overridesMap, $listPricesMap] = $this->buildPriceMaps($clients, $priceLists);
        $clientDefaults = $this->buildClientDefaults($clients);

        return view('admin.quotes.create', [
            'clients'        => $clients,
            'priceLists'     => $priceLists,
            'products'       => $products,
            'routes'         => $routes,
            'seedItems'      => [],
            'overridesMap'   => $overridesMap,
            'listPricesMap'  => $listPricesMap,
            'clientDefaults' => $clientDefaults,
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'             => ['required', 'date'],
            'client_id'         => ['nullable', 'exists:clients,id'],
            'price_list_id'     => ['nullable', 'exists:price_lists,id'],
            'shipping_route_id' => ['nullable', 'exists:shipping_routes,id'],
            'moneda'            => ['required', 'string', 'max:10'],
            'vigencia_hasta'    => ['nullable', 'date'],
            'payment_method'    => ['nullable', 'in:CREDITO,TRANSFERENCIA,CONTRAENTREGA,EFECTIVO'],
            'credit_days'       => ['nullable', 'integer', 'min:0'],
            'delivery_type'     => ['nullable', 'in:RECOGER,ENVIO'],
            'entrega_nombre'    => ['nullable', 'string', 'max:255'],
            'entrega_telefono'  => ['nullable', 'string', 'max:50'],
            'entrega_calle'     => ['nullable', 'string', 'max:255'],
            'entrega_numero'    => ['nullable', 'string', 'max:50'],
            'entrega_colonia'   => ['nullable', 'string', 'max:255'],
            'entrega_ciudad'    => ['nullable', 'string', 'max:255'],
            'entrega_estado'    => ['nullable', 'string', 'max:255'],
            'entrega_cp'        => ['nullable', 'string', 'max:10'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['nullable', 'exists:products,id'],
            'items.*.descripcion'  => ['required', 'string', 'max:255'],
            'items.*.cantidad'     => ['required', 'numeric', 'gt:0'],
            'items.*.precio'       => ['required', 'numeric', 'gte:0'],
            'items.*.descuento'    => ['nullable', 'numeric', 'gte:0'],
            'items.*.impuesto'     => ['nullable', 'numeric', 'gte:0'],
        ]);

        $quote = null;

        DB::transaction(function () use (&$quote, $data) {
            [$subtotal, $descuento, $impuestos, $total] = $this->calcTotals($data['items']);

            $quote = Quote::create([
                'fecha'             => $data['fecha'],
                'client_id'         => $data['client_id'] ?? null,
                'price_list_id'     => $data['price_list_id'] ?? null,
                'shipping_route_id' => $data['shipping_route_id'] ?? null,
                'moneda'            => $data['moneda'],
                'subtotal'          => $subtotal,
                'impuestos'         => $impuestos,
                'descuento'         => $descuento,
                'total'             => $total,
                'vigencia_hasta'    => $data['vigencia_hasta'] ?? null,
                'payment_method'    => $data['payment_method'] ?? 'EFECTIVO',
                'credit_days'       => ($data['payment_method'] ?? '') === 'CREDITO' ? ($data['credit_days'] ?? 0) : null,
                'delivery_type'     => $data['delivery_type'] ?? 'ENVIO',
                'entrega_nombre'    => $data['entrega_nombre']   ?? null,
                'entrega_telefono'  => $data['entrega_telefono'] ?? null,
                'entrega_calle'     => $data['entrega_calle']    ?? null,
                'entrega_numero'    => $data['entrega_numero']   ?? null,
                'entrega_colonia'   => $data['entrega_colonia']  ?? null,
                'entrega_ciudad'    => $data['entrega_ciudad']   ?? null,
                'entrega_estado'    => $data['entrega_estado']   ?? null,
                'entrega_cp'        => $data['entrega_cp']       ?? null,
                'status'            => 'BORRADOR',
                'created_by'        => auth()->id(),
                'owner_id'          => auth()->id(),
            ]);

            $this->saveItems($quote->id, $data['items']);
        });

        session()->flash('swal', ['icon' => 'success', 'title' => '¡Creada!', 'text' => 'Cotización guardada.']);
        return redirect()->route('admin.quotes.edit', $quote);
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(Quote $quote)
    {
        $clients    = Client::orderBy('nombre')->get();
        $priceLists = PriceList::orderBy('nombre')->get(['id', 'nombre']);
        $products   = Product::orderBy('nombre')->get(['id', 'nombre', 'precio_base']);
        $warehouses = Warehouse::orderBy('nombre')->get(['id', 'nombre']);
        $routes     = ShippingRoute::orderBy('nombre')->get(['id', 'nombre']);

        [$overridesMap, $listPricesMap] = $this->buildPriceMaps($clients, $priceLists);
        $clientDefaults = $this->buildClientDefaults($clients);

        $mainWarehouseId = DB::table('warehouses')->where('is_primary', 1)->value('id')
            ?? $warehouses->first()?->id;

        return view('admin.quotes.edit', [
            'quote'          => $quote->load('items.product', 'client', 'priceList'),
            'clients'        => $clients,
            'priceLists'     => $priceLists,
            'products'       => $products,
            'warehouses'     => $warehouses,
            'routes'         => $routes,
            'overridesMap'   => $overridesMap,
            'listPricesMap'  => $listPricesMap,
            'clientDefaults' => $clientDefaults,
            'mainWarehouseId'=> $mainWarehouseId,
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, Quote $quote)
    {
        if ($quote->status !== 'BORRADOR') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'Solo borrador puede editarse.']);
        }

        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'             => ['required', 'date'],
            'client_id'         => ['nullable', 'exists:clients,id'],
            'price_list_id'     => ['nullable', 'exists:price_lists,id'],
            'shipping_route_id' => ['nullable', 'exists:shipping_routes,id'],
            'moneda'            => ['required', 'string', 'max:10'],
            'vigencia_hasta'    => ['nullable', 'date'],
            'payment_method'    => ['nullable', 'in:CREDITO,TRANSFERENCIA,CONTRAENTREGA,EFECTIVO'],
            'credit_days'       => ['nullable', 'integer', 'min:0'],
            'delivery_type'     => ['nullable', 'in:RECOGER,ENVIO'],
            'entrega_nombre'    => ['nullable', 'string', 'max:255'],
            'entrega_telefono'  => ['nullable', 'string', 'max:50'],
            'entrega_calle'     => ['nullable', 'string', 'max:255'],
            'entrega_numero'    => ['nullable', 'string', 'max:50'],
            'entrega_colonia'   => ['nullable', 'string', 'max:255'],
            'entrega_ciudad'    => ['nullable', 'string', 'max:255'],
            'entrega_estado'    => ['nullable', 'string', 'max:255'],
            'entrega_cp'        => ['nullable', 'string', 'max:10'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['nullable', 'exists:products,id'],
            'items.*.descripcion'  => ['required', 'string', 'max:255'],
            'items.*.cantidad'     => ['required', 'numeric', 'gt:0'],
            'items.*.precio'       => ['required', 'numeric', 'gte:0'],
            'items.*.descuento'    => ['nullable', 'numeric', 'gte:0'],
            'items.*.impuesto'     => ['nullable', 'numeric', 'gte:0'],
        ]);

        DB::transaction(function () use ($quote, $data) {
            [$subtotal, $descuento, $impuestos, $total] = $this->calcTotals($data['items']);

            $quote->update([
                'fecha'             => $data['fecha'],
                'client_id'         => $data['client_id'] ?? null,
                'price_list_id'     => $data['price_list_id'] ?? null,
                'shipping_route_id' => $data['shipping_route_id'] ?? null,
                'moneda'            => $data['moneda'],
                'subtotal'          => $subtotal,
                'impuestos'         => $impuestos,
                'descuento'         => $descuento,
                'total'             => $total,
                'vigencia_hasta'    => $data['vigencia_hasta'] ?? null,
                'payment_method'    => $data['payment_method'] ?? 'EFECTIVO',
                'credit_days'       => ($data['payment_method'] ?? '') === 'CREDITO' ? ($data['credit_days'] ?? 0) : null,
                'delivery_type'     => $data['delivery_type'] ?? 'ENVIO',
                'entrega_nombre'    => $data['entrega_nombre']   ?? null,
                'entrega_telefono'  => $data['entrega_telefono'] ?? null,
                'entrega_calle'     => $data['entrega_calle']    ?? null,
                'entrega_numero'    => $data['entrega_numero']   ?? null,
                'entrega_colonia'   => $data['entrega_colonia']  ?? null,
                'entrega_ciudad'    => $data['entrega_ciudad']   ?? null,
                'entrega_estado'    => $data['entrega_estado']   ?? null,
                'entrega_cp'        => $data['entrega_cp']       ?? null,
            ]);

            $quote->items()->delete();
            $this->saveItems($quote->id, $data['items']);
        });

        session()->flash('swal', ['icon' => 'success', 'title' => 'Actualizada', 'text' => 'Cotización actualizada.']);
        return redirect()->route('admin.quotes.edit', $quote);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Quote $quote)
    {
        if (!in_array($quote->status, ['BORRADOR', 'RECHAZADA'], true)) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'No se puede eliminar en este estado.']);
        }
        $quote->delete();
        return redirect()->route('admin.quotes.index')
            ->with('swal', ['icon' => 'success', 'title' => 'Eliminada', 'text' => 'Cotización eliminada.']);
    }

    // ── Envío ─────────────────────────────────────────────────────────────────

    public function sendForm(Quote $quote)
    {
        $quote->load('client', 'items.product');
        return view('admin.quotes.send', [
            'quote'       => $quote,
            'clientEmail' => $quote->client->email ?? '',
            'clientPhone' => $quote->client->telefono ?? '',
        ]);
    }

    public function send(Request $request, Quote $quote, WhatsappSender $whatsapp)
    {
        $request->validate([
            'channels'   => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,whatsapp'],
            'email'      => ['nullable', 'email'],
            'telefono'   => ['nullable', 'string'],
            'mensaje'    => ['nullable', 'string', 'max:500'],
        ]);

        $quote->load('client', 'items.product');
        $pdf    = Pdf::loadView('pdf.quote', ['quote' => $quote]);
        $pdfRaw = $pdf->output();
        $fname  = 'cotizacion-' . $quote->id . '.pdf';

        $sentEmail = $sentWa = false;
        $errors = [];

        if (in_array('email', $request->channels, true)) {
            $to = $request->input('email') ?: ($quote->client->email ?? null);
            if (!$to) {
                $errors[] = 'Sin correo del cliente.';
            } else {
                try {
                    Mail::to($to)->send(new QuotePdfMailable($quote, $pdfRaw, $fname));
                    $sentEmail = true;
                } catch (\Throwable $e) {
                    $errors[] = 'Email: ' . $e->getMessage();
                }
            }
        }

        if (in_array('whatsapp', $request->channels, true)) {
            $phone = $request->input('telefono') ?: ($quote->client->telefono ?? null);
            if (!$phone) {
                $errors[] = 'Sin teléfono del cliente.';
            } else {
                try {
                    $resp = $whatsapp->sendPdf($phone, $request->input('mensaje', 'Te adjunto la cotización 📎'), $fname, $pdfRaw);
                    if (!($resp['ok'] ?? false)) $errors[] = 'WhatsApp: ' . json_encode($resp['body'] ?? []);
                    else $sentWa = true;
                } catch (\Throwable $e) {
                    $errors[] = 'WhatsApp: ' . $e->getMessage();
                }
            }
        }

        if ($sentEmail || $sentWa) {
            if ($quote->status === 'BORRADOR') $quote->update(['status' => 'ENVIADA']);
        }

        if ($errors) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Envío parcial', 'text' => implode(' | ', $errors)]);
        }
        return back()->with('swal', ['icon' => 'success', 'title' => 'Enviada', 'text' => 'Cotización enviada correctamente.']);
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function pdf(Quote $quote)
    {
        $quote->load('client', 'items.product');
        return Pdf::loadView('pdf.quote', ['quote' => $quote])->stream('cotizacion-' . $quote->id . '.pdf');
    }

    public function pdfDownload(Quote $quote)
    {
        $quote->load('client', 'items.product');
        return Pdf::loadView('pdf.quote', ['quote' => $quote])->download('cotizacion-' . $quote->id . '.pdf');
    }

    // ── Transiciones ─────────────────────────────────────────────────────────

    public function reject(Quote $quote)
    {
        if (!in_array($quote->status, ['BORRADOR', 'ENVIADA'], true)) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo BORRADOR o ENVIADA pueden rechazarse.']);
        }
        $quote->update(['status' => 'RECHAZADA']);
        return back()->with('swal', ['icon' => 'success', 'title' => 'Rechazada', 'text' => 'Cotización rechazada.']);
    }

    public function cancel(Quote $quote)
    {
        if ($quote->status === 'CONVERTIDA') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'No se puede cancelar una cotización convertida.']);
        }
        $quote->update(['status' => 'CANCELADA']);
        return back()->with('swal', ['icon' => 'success', 'title' => 'Cancelada', 'text' => 'Cotización cancelada.']);
    }

    // ── Approve → genera SalesOrder ───────────────────────────────────────────

    public function approve(Request $request, Quote $quote)
    {
        if (!in_array($quote->status, ['BORRADOR', 'ENVIADA'], true)) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo BORRADOR o ENVIADA pueden aprobarse.']);
        }

        // warehouse_id viene del hidden input de la vista (usa mainWarehouseId por defecto)
        $request->validate([
            'warehouse_id'     => ['required', 'exists:warehouses,id'],
            'payment_method'   => ['nullable', 'in:CREDITO,TRANSFERENCIA,CONTRAENTREGA,EFECTIVO'],
            'delivery_type'    => ['nullable', 'in:RECOGER,ENVIO'],
            'credit_days'      => ['nullable', 'integer', 'min:0'],
            'programado_para'  => ['nullable', 'date'],
            'shipping_route_id'=> ['nullable', 'exists:shipping_routes,id'],
            'entrega_nombre'   => ['nullable', 'string', 'max:255'],
            'entrega_telefono' => ['nullable', 'string', 'max:50'],
            'entrega_calle'    => ['nullable', 'string', 'max:255'],
            'entrega_numero'   => ['nullable', 'string', 'max:50'],
            'entrega_colonia'  => ['nullable', 'string', 'max:255'],
            'entrega_ciudad'   => ['nullable', 'string', 'max:255'],
            'entrega_estado'   => ['nullable', 'string', 'max:255'],
            'entrega_cp'       => ['nullable', 'string', 'max:10'],
        ]);

        $quote->load('items');

        if ($quote->items->isEmpty()) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Sin partidas', 'text' => 'La cotización no tiene partidas.']);
        }

        $order = null;

        DB::transaction(function () use ($quote, $request, &$order) {
            $nextId = DB::table('sales_orders')->lockForUpdate()->max('id') + 1;
            $folio  = 'SO-' . now()->format('Ymd') . '-' . Str::padLeft((string)$nextId, 4, '0');

            // Toma del request si vino, si no usa lo guardado en la cotización
            $paymentMethod = $request->payment_method  ?? $quote->payment_method  ?? 'EFECTIVO';
            $deliveryType  = $request->delivery_type   ?? $quote->delivery_type   ?? 'ENVIO';
            $routeId       = $request->shipping_route_id ?? $quote->shipping_route_id ?? null;

            $payload = [
                'client_id'         => $quote->client_id,
                'warehouse_id'      => $request->warehouse_id,
                'price_list_id'     => $quote->price_list_id,
                'shipping_route_id' => $routeId,
                'folio'             => $folio,
                'fecha'             => now(),
                'programado_para'   => $request->programado_para ?? $quote->programado_para ?? null,
                'delivery_type'     => $deliveryType,
                'driver_id'         => null,
                'payment_method'    => $paymentMethod,
                'credit_days'       => $paymentMethod === 'CREDITO'
                    ? ($request->credit_days ?? $quote->credit_days ?? 0)
                    : null,
                'moneda'            => $quote->moneda,
                'subtotal'          => $quote->subtotal,
                'impuestos'         => $quote->impuestos,
                'descuento'         => $quote->descuento,
                'total'             => $quote->total,
                'status'            => 'BORRADOR',
                'created_by'        => auth()->id(),
                'owner_id'          => auth()->id(),
                'contraentrega_total' => $paymentMethod === 'CONTRAENTREGA' ? $quote->total : 0,
                // Dirección: request tiene prioridad (hidden inputs), fallback a la cotización
                'entrega_nombre'    => $request->entrega_nombre   ?? $quote->entrega_nombre   ?? null,
                'entrega_telefono'  => $request->entrega_telefono ?? $quote->entrega_telefono ?? null,
                'entrega_calle'     => $request->entrega_calle    ?? $quote->entrega_calle    ?? null,
                'entrega_numero'    => $request->entrega_numero   ?? $quote->entrega_numero   ?? null,
                'entrega_colonia'   => $request->entrega_colonia  ?? $quote->entrega_colonia  ?? null,
                'entrega_ciudad'    => $request->entrega_ciudad   ?? $quote->entrega_ciudad   ?? null,
                'entrega_estado'    => $request->entrega_estado   ?? $quote->entrega_estado   ?? null,
                'entrega_cp'        => $request->entrega_cp       ?? $quote->entrega_cp       ?? null,
            ];

            if (Schema::hasColumn('sales_orders', 'quote_id')) {
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
            ->with('swal', ['icon' => 'success', 'title' => 'Aprobada', 'text' => 'Se generó el pedido ' . $order->folio . '.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function calcTotals(array $items): array
    {
        $subtotal = $descuento = $impuestos = $total = 0;
        foreach ($items as $it) {
            $sub  = (float)$it['cantidad'] * (float)$it['precio'];
            $desc = (float)($it['descuento'] ?? 0);
            $tax  = (float)($it['impuesto']  ?? 0);
            $tot  = max($sub - $desc, 0) + $tax;
            $subtotal  += $sub;
            $descuento += $desc;
            $impuestos += $tax;
            $total     += $tot;
        }
        return [$subtotal, $descuento, $impuestos, $total];
    }

    private function saveItems(int $quoteId, array $items): void
    {
        foreach ($items as $it) {
            $sub  = (float)$it['cantidad'] * (float)$it['precio'];
            $desc = (float)($it['descuento'] ?? 0);
            $tax  = (float)($it['impuesto']  ?? 0);

            QuoteItem::create([
                'quote_id'    => $quoteId,
                'product_id'  => $it['product_id'] ?? null,
                'descripcion' => $it['descripcion'],
                'cantidad'    => $it['cantidad'],
                'precio'      => $it['precio'],
                'descuento'   => $desc,
                'impuesto'    => $tax,
                'total'       => max($sub - $desc, 0) + $tax,
            ]);
        }
    }
}