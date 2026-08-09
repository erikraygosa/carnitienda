<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArPayment;
use App\Models\ArPaymentItem;
use App\Models\Invoice;
use App\Models\InvoiceComplementDoc;
use App\Models\InvoiceSeries;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ArComplementosController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:crear facturas'),
        ];
    }

    public function index(Request $request)
    {
        $search   = $request->get('search', '');
        $clientId = $request->get('client_id', '');

        $payments = ArPayment::with([
                'paymentType',
                'items.salesOrder.client',
                'items.salesOrder.invoice',
                'complementInvoice',
            ])
            ->whereHas('items.salesOrder.invoice', fn($q) =>
                $q->where('metodo_pago', 'PPD')->where('estatus', 'TIMBRADA')
            )
            ->whereDoesntHave('complementInvoice')
            ->when($search, fn($q) =>
                $q->whereHas('items.salesOrder.client', fn($c) =>
                    $c->where('nombre', 'like', "%$search%")
                )
            )
            ->when($clientId, fn($q) =>
                $q->whereHas('items.salesOrder', fn($so) =>
                    $so->where('client_id', $clientId)
                )
            )
            ->latest('fecha')
            ->paginate(25)
            ->withQueryString();

        // Determinar cliente y facturas relacionadas por payment
        $payments->each(function ($p) {
            $invoices = $p->items
                ->map(fn($item) => $item->salesOrder?->invoice)
                ->filter(fn($inv) => $inv && $inv->metodo_pago === 'PPD' && $inv->estatus === 'TIMBRADA')
                ->unique('id');

            $p->_related_invoices = $invoices;
            $p->_cliente = $p->items->first()?->salesOrder?->client?->nombre ?? '—';
        });

        return view('admin.ar.complementos.index', compact('payments', 'search', 'clientId'));
    }

    public function create(Request $request)
    {
        $payment = ArPayment::with([
                'paymentType',
                'items.salesOrder.client',
                'items.salesOrder.invoice.items',
                'complementInvoice',
            ])
            ->findOrFail($request->query('payment_id'));

        abort_if($payment->complementInvoice, 404, 'Este cobro ya tiene un complemento generado.');

        $empresa    = app(CompanyService::class)->activa();
        $fiscalData = $empresa?->fiscalData;

        // Construir docs relacionados con cálculos de saldo
        $docs = $payment->items
            ->map(function (ArPaymentItem $item) {
                $so  = $item->salesOrder;
                $inv = $so?->invoice;

                if (! $inv || $inv->metodo_pago !== 'PPD' || $inv->estatus !== 'TIMBRADA') {
                    return null;
                }

                // Calcular pagos anteriores ya complementados para esta factura
                $pagadoAnterior = InvoiceComplementDoc::where('related_invoice_id', $inv->id)
                    ->sum('imp_pagado');

                $numParcialidad   = InvoiceComplementDoc::where('related_invoice_id', $inv->id)->count() + 1;
                $impSaldoAnterior = round((float)$inv->total - $pagadoAnterior, 2);
                $impPagado        = min((float)$item->monto_aplicado, $impSaldoAnterior);
                $impSaldoInsoluto = round($impSaldoAnterior - $impPagado, 2);

                return [
                    'related_invoice_id' => $inv->id,
                    'invoice'            => $inv,
                    'sales_order'        => $so,
                    'num_parcialidad'    => $numParcialidad,
                    'imp_saldo_anterior' => $impSaldoAnterior,
                    'imp_pagado'         => $impPagado,
                    'imp_saldo_insoluto' => $impSaldoInsoluto,
                ];
            })
            ->filter()
            ->values();

        abort_if($docs->isEmpty(), 422, 'No hay facturas PPD timbradas asociadas a este cobro.');

        $cliente = $payment->items->first()?->salesOrder?->client;

        $series = InvoiceSeries::where('tipo_comprobante', 'P')
            ->where('activa', true)
            ->get();

        $emisorDefaults = [
            'lugar_expedicion'      => $fiscalData?->codigo_postal ?? '',
            'regimen_fiscal_emisor' => $fiscalData?->regimen_fiscal ?? '',
        ];

        return view('admin.ar.complementos.create', compact(
            'payment', 'docs', 'cliente', 'series', 'emisorDefaults', 'empresa'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_id'                     => 'required|exists:ar_payments,id',
            'serie'                          => 'required|string|max:10',
            'fecha'                          => 'required|date',
            'lugar_expedicion'               => 'required|string|max:10',
            'regimen_fiscal_emisor'          => 'required|string|max:3',
            'regimen_fiscal_receptor'        => 'required|string|max:3',
            'docs'                           => 'required|array|min:1',
            'docs.*.related_invoice_id'      => 'required|exists:invoices,id',
            'docs.*.num_parcialidad'         => 'required|integer|min:1',
            'docs.*.imp_saldo_anterior'      => 'required|numeric|min:0',
            'docs.*.imp_pagado'              => 'required|numeric|min:0.01',
            'docs.*.imp_saldo_insoluto'      => 'required|numeric|min:0',
        ]);

        $payment = ArPayment::with(['paymentType', 'items.salesOrder.client'])->findOrFail($data['payment_id']);

        abort_if($payment->complementInvoice, 422, 'Este cobro ya tiene un complemento generado.');

        $cliente = $payment->items->first()?->salesOrder?->client;

        $empresa    = app(CompanyService::class)->activa();
        $fiscalData = $empresa?->fiscalData;

        $complemento = DB::transaction(function () use ($data, $payment, $cliente, $fiscalData) {
            // Reservar serie y folio
            $series = InvoiceSeries::where('serie', $data['serie'])
                ->where('tipo_comprobante', 'P')
                ->lockForUpdate()
                ->firstOrFail();

            $folio = $series->siguienteFolio();

            // Crear factura complemento tipo P
            $complemento = Invoice::create([
                'client_id'               => $cliente?->id,
                'ar_payment_id'           => $payment->id,
                'serie'                   => $data['serie'],
                'folio'                   => $folio,
                'fecha'                   => $data['fecha'],
                'tipo_comprobante'        => 'P',
                'uso_cfdi'                => 'CP01',
                'forma_pago'              => $payment->paymentType?->satFormaPago() ?? '99',
                'metodo_pago'             => 'PUE',
                'lugar_expedicion'        => $data['lugar_expedicion'],
                'exportacion'             => '01',
                'regimen_fiscal_emisor'   => $data['regimen_fiscal_emisor'],
                'regimen_fiscal_receptor' => $data['regimen_fiscal_receptor'],
                'receptor_rfc'            => $cliente?->rfc ?? 'XAXX010101000',
                'receptor_razon_social'   => $cliente?->razon_social ?? $cliente?->nombre ?? 'PÚBLICO EN GENERAL',
                'receptor_cp'             => $cliente?->fiscal_cp ?? $cliente?->cp ?? $fiscalData?->codigo_postal ?? '06600',
                'moneda'                  => 'MXN',
                'subtotal'                => 0,
                'impuestos'               => 0,
                'total'                   => 0,
                'estatus'                 => 'BORRADOR',
                'version_cfdi'            => '4.0',
                'created_by'              => auth()->id(),
                'owner_id'                => auth()->id(),
            ]);

            // Crear DoctoRelacionado por cada factura referenciada
            foreach ($data['docs'] as $doc) {
                InvoiceComplementDoc::create([
                    'invoice_id'         => $complemento->id,
                    'related_invoice_id' => $doc['related_invoice_id'],
                    'num_parcialidad'    => $doc['num_parcialidad'],
                    'imp_saldo_anterior' => $doc['imp_saldo_anterior'],
                    'imp_pagado'         => $doc['imp_pagado'],
                    'imp_saldo_insoluto' => $doc['imp_saldo_insoluto'],
                ]);
            }

            return $complemento;
        });

        session()->flash('swal', [
            'icon'  => 'success',
            'title' => 'Complemento creado',
            'text'  => "Borrador {$complemento->serie}{$complemento->folio} listo para timbrar.",
        ]);

        return redirect()->route('admin.invoices.edit', $complemento->id);
    }
}
