<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Invoice, InvoiceItem, Client, SalesOrder, Sale, Product};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\PacCfdiService;

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

        $clients = Client::orderBy('nombre')->get(['id','nombre','rfc','cp','regimen_fiscal']);

        $products  = Product::orderBy('nombre')->get(['id','nombre','precio_base','clave_prod_serv','clave_unidad','unidad']);
        $prefill   = null;

        if ($fromOrderId) {
            $order = SalesOrder::with('items.product','client')->findOrFail($fromOrderId);
            $prefill = $this->mapFromOrder($order);
        } elseif ($fromSaleId) {
            $sale = Sale::with('items.product','client')->findOrFail($fromSaleId);
            $prefill = $this->mapFromSale($sale);
        }

        return view('admin.invoices.create', compact('clients','products','prefill'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // encabezado
            'client_id'     => ['required','exists:clients,id'],
            'sales_order_id'=> ['nullable','exists:sales_orders,id'],
            'sale_id'       => ['nullable','exists:sales,id'],

            'serie'         => ['nullable','string','max:10'],
            'folio'         => ['nullable','string','max:20'],
            'fecha'         => ['required','date'],
            'tipo_comprobante' => ['required','in:I,E,P,N'],
            'moneda'        => ['required','string','max:5'],

            // Receptor / SAT
            'uso_cfdi'      => ['required','string','max:5'],
            'forma_pago'    => ['nullable','string','max:3'],
            'metodo_pago'   => ['nullable','string','max:3'],

            'lugar_expedicion'       => ['required','string','max:10'],
            'regimen_fiscal_emisor'  => ['required','string','max:3'],
            'regimen_fiscal_receptor'=> ['required','string','max:3'],

            // partidas
            'items' => ['required','array','min:1'],
            'items.*.product_id'     => ['nullable','exists:products,id'],
            'items.*.descripcion'    => ['required','string','max:255'],
            'items.*.clave_prod_serv'=> ['nullable','string','max:8'],
            'items.*.clave_unidad'   => ['nullable','string','max:3'],
            'items.*.unidad'         => ['nullable','string','max:20'],
            'items.*.cantidad'       => ['required','numeric','gt:0'],
            'items.*.valor_unitario' => ['required','numeric','gte:0'],
            'items.*.descuento'      => ['nullable','numeric','gte:0'],
            'items.*.objeto_imp'     => ['required','in:01,02,03'],
            'items.*.iva_pct'        => ['nullable','numeric','gte:0'],
            'items.*.ieps_pct'       => ['nullable','numeric','gte:0'],
        ]);

        // Regla: no permitir ambos sales_order_id y sale_id al mismo tiempo
        if (!empty($data['sales_order_id']) && !empty($data['sale_id'])) {
            return back()->with('swal',['icon'=>'error','title'=>'Datos inválidos','text'=>'Elige pedido o nota, no ambos.'])->withInput();
        }

        $invoice = null;

        DB::transaction(function () use (&$invoice, $data) {
            // Totales
            $subtotal=0; $iva=0; $ieps=0; $impuestos=0; $total=0;

            $invoice = Invoice::create([
                'client_id'      => $data['client_id'],
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'sale_id'        => $data['sale_id'] ?? null,
                'serie'          => $data['serie'] ?? null,
                'folio'          => $data['folio'] ?? null,
                'fecha'          => $data['fecha'],
                'tipo_comprobante' => $data['tipo_comprobante'],
                'moneda'         => $data['moneda'],
                'uso_cfdi'       => $data['uso_cfdi'],
                'forma_pago'     => $data['forma_pago'] ?? null,
                'metodo_pago'    => $data['metodo_pago'] ?? null,
                'lugar_expedicion'       => $data['lugar_expedicion'],
                'regimen_fiscal_emisor'  => $data['regimen_fiscal_emisor'],
                'regimen_fiscal_receptor'=> $data['regimen_fiscal_receptor'],
                'estatus'        => 'BORRADOR',
                'version_cfdi'   => '4.0',
                'created_by'     => auth()->id(),
                'owner_id'       => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                $cantidad  = (float)$row['cantidad'];
                $vu        = (float)$row['valor_unitario'];
                $desc      = (float)($row['descuento'] ?? 0);

                $linea     = $cantidad * $vu;
                $base      = max($linea - $desc, 0);
                $iva_pct   = (float)($row['iva_pct']  ?? 0) / 100;
                $ieps_pct  = (float)($row['ieps_pct'] ?? 0) / 100;

                $iva_imp   = round($base * $iva_pct, 6);
                $ieps_imp  = round($base * $ieps_pct, 6);
                $importe   = $base + $iva_imp + $ieps_imp;

                InvoiceItem::create([
                    'invoice_id'      => $invoice->id,
                    'product_id'      => $row['product_id'] ?? null,
                    'clave_prod_serv' => $row['clave_prod_serv'] ?? null,
                    'clave_unidad'    => $row['clave_unidad'] ?? null,
                    'unidad'          => $row['unidad'] ?? null,
                    'descripcion'     => $row['descripcion'],
                    'cantidad'        => $cantidad,
                    'valor_unitario'  => $vu,
                    'descuento'       => $desc,
                    'objeto_imp'      => $row['objeto_imp'],
                    'base'            => $base,
                    'iva_pct'         => (float)($row['iva_pct'] ?? 0),
                    'iva_importe'     => $iva_imp,
                    'ieps_pct'        => (float)($row['ieps_pct'] ?? 0),
                    'ieps_importe'    => $ieps_imp,
                    'importe'         => $importe,
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

        return redirect()->route('admin.invoices.edit', $invoice)
            ->with('swal',['icon'=>'success','title'=>'Creada','text'=>'Factura en borrador creada.']);
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('client','items.product','salesOrder','sale');
        $clients  = Client::orderBy('nombre')->get(['id','nombre','rfc','cp','regimen_fiscal']);
        $products = Product::orderBy('nombre')->get(['id','nombre','precio_base','clave_prod_serv','clave_unidad','unidad']);

        return view('admin.invoices.edit', compact('invoice','clients','products'));
    }

    // TIMBRAR
    public function stamp(Invoice $invoice, PacCfdiService $pac)
    {
        if (!$invoice->isDraft()) {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo BORRADOR se puede timbrar.']);
        }

        $unsigned = $pac->buildXml($invoice);
        $resp = $pac->stamp($invoice, $unsigned);

        if (!($resp['ok'] ?? false)) {
            return back()->with('swal',['icon'=>'error','title'=>'Error PAC','text'=>$resp['error'] ?? 'Fallo al timbrar.']);
        }

        $invoice->update([
            'uuid'        => $resp['uuid'],
            'xml_timbrado'=> $resp['xml_timbrado'],
            'estatus'     => 'TIMBRADA',
        ]);

        return back()->with('swal',['icon'=>'success','title'=>'Timbrada','text'=>'Factura timbrada correctamente.']);
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
        $invoice->load('client','items');
        $pdf = Pdf::loadView('pdf.invoice', ['invoice'=>$invoice]);
        return $pdf->stream('factura-'.$invoice->serie.$invoice->folio.'.pdf');
    }

    public function pdfDownload(Invoice $invoice)
    {
        $invoice->load('client','items');
        $pdf = Pdf::loadView('pdf.invoice', ['invoice'=>$invoice]);
        return $pdf->download('factura-'.$invoice->serie.$invoice->folio.'.pdf');
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
}