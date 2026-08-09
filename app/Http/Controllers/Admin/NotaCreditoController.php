<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Invoice, InvoiceItem, InvoiceSeries, Product, StampCounter};
use App\Services\CompanyService;
use App\Services\DocumentLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NotaCreditoController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentLogService $log) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver notas credito', only: ['index']),
            new Middleware('can:crear notas credito', only: ['create', 'store']),
        ];
    }

    /**
     * Facturas de Ingreso timbradas, con lo ya afectado por notas de
     * crédito previas (para saber cuánto saldo le queda disponible).
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');

        $facturas = Invoice::with(['client', 'notasCredito'])
            ->where('tipo_comprobante', 'I')
            ->where('estatus', 'TIMBRADA')
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('folio', 'like', "%$search%")
                   ->orWhereHas('client', fn($c) => $c->where('nombre', 'like', "%$search%"));
            }))
            ->latest('fecha')
            ->paginate(25)
            ->withQueryString();

        $facturas->each(function ($f) {
            $f->_afectado    = $f->notasCredito->where('estatus', '!=', 'CANCELADA')->sum('total');
            $f->_disponible  = round((float) $f->total - (float) $f->_afectado, 2);
        });

        $notasCredito = Invoice::with('client', 'relatedInvoiceOriginal')
            ->where('tipo_comprobante', 'E')
            ->latest('id')
            ->paginate(25, ['*'], 'notas_page')
            ->withQueryString();

        return view('admin.notas_credito.index', compact('facturas', 'search', 'notasCredito'));
    }

    public function create(Request $request)
    {
        $invoice = Invoice::with('items', 'client')
            ->where('tipo_comprobante', 'I')
            ->where('estatus', 'TIMBRADA')
            ->findOrFail($request->query('invoice_id'));

        $afectadoPrevio = Invoice::where('related_invoice_id', $invoice->id)
            ->where('estatus', '!=', 'CANCELADA')
            ->sum('total');

        $disponible = round((float) $invoice->total - (float) $afectadoPrevio, 2);
        abort_if($disponible <= 0, 422, 'Esta factura ya fue afectada por el total en notas de crédito previas.');

        $products = Product::orderBy('nombre')->get(['id', 'nombre', 'precio_base', 'clave_prod_serv', 'clave_unidad', 'unidad']);

        $empresa    = app(CompanyService::class)->activa();
        $fiscalData = $empresa?->fiscalData;

        $series = InvoiceSeries::where('tipo_comprobante', 'E')->where('activa', true)->get();

        $emisorDefaults = [
            'lugar_expedicion'      => $fiscalData?->codigo_postal ?? '',
            'regimen_fiscal_emisor' => $fiscalData?->regimen_fiscal ?? '',
        ];

        $productsMap = $products->keyBy('id')->map(fn($p) => [
            'nombre'          => $p->nombre,
            'precio_base'     => (float) ($p->precio_base ?? 0),
            'clave_prod_serv' => $p->clave_prod_serv ?? '01010101',
            'clave_unidad'    => $p->clave_unidad ?? 'H87',
            'unidad'          => $p->unidad ?? 'PZA',
        ]);

        return view('admin.notas_credito.create', compact(
            'invoice', 'disponible', 'products', 'productsMap',
            'series', 'emisorDefaults', 'empresa'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id'              => ['required', 'exists:invoices,id'],
            'serie'                   => ['required', 'string', 'max:10'],
            'fecha'                   => ['required', 'date'],
            'forma_pago'              => ['nullable', 'string', 'max:3'],
            'lugar_expedicion'        => ['required', 'string', 'max:10'],
            'regimen_fiscal_emisor'   => ['required', 'string', 'max:3'],
            'regimen_fiscal_receptor' => ['required', 'string', 'max:3'],
            'motivo'                  => ['nullable', 'string', 'max:255'],
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

        $original = Invoice::with('client')->findOrFail($data['invoice_id']);
        $cliente  = $original->client;

        // Igual que en facturas y complementos: sin RFC real, el SAT solo
        // permite régimen 616 con el RFC genérico.
        if (empty($cliente?->rfc)) {
            $data['regimen_fiscal_receptor'] = '616';
        }

        $afectadoPrevio = Invoice::where('related_invoice_id', $original->id)
            ->where('estatus', '!=', 'CANCELADA')
            ->sum('total');
        $disponible = round((float) $original->total - (float) $afectadoPrevio, 2);

        $notaCredito = null;

        DB::transaction(function () use ($data, $original, $cliente, $disponible, &$notaCredito) {
            $series = InvoiceSeries::where('serie', $data['serie'])
                ->where('tipo_comprobante', 'E')
                ->lockForUpdate()
                ->firstOrFail();

            $folio = $series->siguienteFolio();

            $notaCredito = Invoice::create([
                'client_id'               => $original->client_id,
                'related_invoice_id'      => $original->id,
                'serie'                   => $data['serie'],
                'folio'                   => $folio,
                'fecha'                   => $data['fecha'],
                'tipo_comprobante'        => 'E',
                'uso_cfdi'                => $original->uso_cfdi ?? 'G03',
                'forma_pago'              => $data['forma_pago'] ?? $original->forma_pago,
                'metodo_pago'             => 'PUE',
                'lugar_expedicion'        => $data['lugar_expedicion'],
                'exportacion'             => '01',
                'regimen_fiscal_emisor'   => $data['regimen_fiscal_emisor'],
                'regimen_fiscal_receptor' => $data['regimen_fiscal_receptor'],
                'receptor_rfc'            => $cliente?->rfc ?? 'XAXX010101000',
                'receptor_razon_social'   => $cliente?->razon_social ?? $cliente?->nombre ?? 'PÚBLICO EN GENERAL',
                'receptor_cp'             => $cliente?->fiscal_cp ?? $cliente?->cp ?? '06600',
                'moneda'                  => $original->moneda ?? 'MXN',
                'condiciones_pago'        => $data['motivo'] ?? null,
                'subtotal'                => 0,
                'impuestos'               => 0,
                'total'                   => 0,
                'estatus'                 => 'BORRADOR',
                'version_cfdi'            => '4.0',
                'created_by'              => auth()->id(),
                'owner_id'                => auth()->id(),
            ]);

            $subtotal = 0; $iva = 0; $ieps = 0; $total = 0;

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
                    'invoice_id'          => $notaCredito->id,
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

            abort_if($total > $disponible + 0.01, 422,
                "El total de la nota (\${$total}) excede el saldo disponible de la factura (\${$disponible}).");

            $notaCredito->update([
                'subtotal'  => $subtotal,
                'impuestos' => $iva + $ieps,
                'total'     => $total,
            ]);
        });

        $this->log->log($notaCredito, 'CREADO', null, 'BORRADOR', null, 'Nota de crédito de factura #' . $original->id);

        return redirect()->route('admin.invoices.edit', $notaCredito->id)
            ->with('swal', ['icon' => 'success', 'title' => 'Creada', 'text' => "Borrador {$notaCredito->serie}{$notaCredito->folio} listo para timbrar."]);
    }
}
