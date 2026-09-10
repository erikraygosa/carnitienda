<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Services\ArService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SalesOrder;
use App\Models\Sale;
use App\Models\ArMovement;
use App\Models\ArPayment;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ArPaymentsController extends Controller implements HasMiddleware
{
    public function __construct(private ArService $ar) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:registrar cobros'),
        ];
    }

    public function create()
    {
        $clients     = Client::where('activo', 1)->orderBy('nombre')->get();
        $types       = PaymentType::orderBy('descripcion')->get();
        $preClientId = request('client_id');

        $notasPendientes     = collect();
        $ventasPendientes    = collect();
        $facturasPendientes  = collect();
        if ($preClientId) {
            $notasPendientes    = $this->notasPendientesDe($preClientId);
            $ventasPendientes   = $this->notasVentaPendientesDe($preClientId);
            $facturasPendientes = $this->facturasLibresPendientesDe($preClientId);
        }

        return view('admin.ar.payments.create', compact('clients', 'types', 'preClientId', 'notasPendientes', 'ventasPendientes', 'facturasPendientes'));
    }

    private function notasPendientesDe($clientId)
    {
        return SalesOrder::where('client_id', $clientId)
            ->where('payment_method', 'CREDITO')
            ->whereIn('status', ['ENTREGADO'])
            ->whereNull('cobrado_at')
            ->where(function($q) {
                $q->whereNull('saldo_pendiente')
                  ->orWhere('saldo_pendiente', '>', 0);
            })
            ->orderBy('fecha')
            ->get(['id','folio','fecha','total','saldo_pendiente']);
    }

    /**
     * Notas de venta (mostrador) a crédito pendientes de cobro de un cliente
     * — igual que notasPendientesDe() pero para Sale, que no usaba este
     * formulario de cobro manual: solo aparecían aquí los pedidos, así que
     * un cliente con una nota de venta a crédito no la veía al registrar un
     * cobro (ni desde el link "Cobrar CxC" en Liquidaciones).
     */
    private function notasVentaPendientesDe($clientId)
    {
        return Sale::where('client_id', $clientId)
            ->where('tipo_venta', 'CREDITO')
            ->whereIn('status', ['ENTREGADO', 'COMPLETADA'])
            ->whereNull('cobrado_at')
            ->where(function($q) {
                $q->whereNull('saldo_pendiente')
                  ->orWhere('saldo_pendiente', '>', 0);
            })
            ->orderBy('fecha')
            ->get(['id','folio','fecha','total','saldo_pendiente']);
    }

    /**
     * Facturas libres (sin pedido/nota) timbradas en PPD con saldo por cobrar,
     * para poder registrarles un cobro directo y luego generarles su
     * complemento de pago.
     */
    private function facturasLibresPendientesDe($clientId)
    {
        return Invoice::where('client_id', $clientId)
            ->whereNull('sales_order_id')
            ->whereNull('sale_id')
            ->where('tipo_comprobante', 'I')
            ->where('metodo_pago', 'PPD')
            ->where('estatus', 'TIMBRADA')
            ->orderBy('fecha')
            ->get(['id','serie','folio','fecha','total'])
            ->map(function ($inv) {
                $inv->saldo_pendiente = $inv->saldoPendiente();
                return $inv;
            })
            ->filter(fn($inv) => $inv->saldo_pendiente > 0)
            ->values();
    }

    public function notas(Request $request)
    {
        $clientId = $request->query('client_id');

        $orders = $this->notasPendientesDe($clientId)
            ->map(fn($o) => [
                'id'              => $o->id,
                'folio'           => $o->folio,
                'fecha'           => \Carbon\Carbon::parse($o->fecha)->format('d/m/Y'),
                'total'           => (float) $o->total,
                'saldo_pendiente' => ($o->saldo_pendiente !== null && (float)$o->saldo_pendiente > 0)
                    ? (float) $o->saldo_pendiente
                    : (float) $o->total,
            ]);

        $ventas = $this->notasVentaPendientesDe($clientId)
            ->map(fn($s) => [
                'id'              => $s->id,
                'folio'           => $s->folio,
                'fecha'           => \Carbon\Carbon::parse($s->fecha)->format('d/m/Y'),
                'total'           => (float) $s->total,
                'saldo_pendiente' => ($s->saldo_pendiente !== null && (float)$s->saldo_pendiente > 0)
                    ? (float) $s->saldo_pendiente
                    : (float) $s->total,
            ]);

        $facturas = $this->facturasLibresPendientesDe($clientId)
            ->map(fn($inv) => [
                'id'              => $inv->id,
                'folio'           => $inv->serie . $inv->folio,
                'fecha'           => \Carbon\Carbon::parse($inv->fecha)->format('d/m/Y'),
                'total'           => (float) $inv->total,
                'saldo_pendiente' => (float) $inv->saldo_pendiente,
            ]);

        return response()->json(['ordenes' => $orders->values(), 'ventas' => $ventas->values(), 'facturas' => $facturas->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'fecha'           => 'required|date',
            'amount'          => 'required|numeric|min:0.01',
            'payment_type_id' => 'required|exists:payment_types,id',
            'reference'       => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'order_ids'       => 'nullable|array',
            'order_ids.*'     => 'integer|exists:sales_orders,id',
            'sale_ids'        => 'nullable|array',
            'sale_ids.*'      => 'integer|exists:sales,id',
            'invoice_ids'     => 'nullable|array',
            'invoice_ids.*'   => 'integer|exists:invoices,id',
        ]);

        if (empty($data['order_ids']) && empty($data['sale_ids']) && empty($data['invoice_ids'])) {
            return back()
                ->withInput()
                ->withErrors(['order_ids' => 'Debes seleccionar al menos una nota o factura a cubrir.']);
        }

        $this->registrarCobro($data);

        session()->flash('swal', ['icon'=>'success','title'=>'Cobro registrado','text'=>'El pago se aplicó correctamente.']);
        return redirect()->route('admin.ar.index');
    }

    /**
     * Núcleo compartido para aplicar un cobro (ABONO en ar_movements + registro
     * en ar_payments + reparto FIFO sobre notas/facturas). Usado tanto por el
     * formulario manual (store) como por la liquidación masiva en efectivo
     * desde el reporte de Liquidaciones.
     */
    private function registrarCobro(array $data): ArPayment
    {
        return DB::transaction(function () use ($data) {
            $mov = ArMovement::create([
                'client_id'   => $data['client_id'],
                'fecha'       => $data['fecha'],
                'tipo'        => 'ABONO',
                'monto'       => $data['amount'],
                'descripcion' => 'Cobro' . (!empty($data['notes']) ? ': '.$data['notes'] : ''),
                'created_by'  => auth()->id(),
            ]);

            $payment = ArPayment::create([
                'fecha'           => $data['fecha'],
                'payment_type_id' => $data['payment_type_id'],
                'monto'           => $data['amount'],
                'referencia'      => $data['reference'] ?? null,
                'nota'            => $data['notes'] ?? null,
                'recibido_por'    => auth()->id(),
                'order_ids'       => $data['order_ids'] ?? [],
            ]);

            $mov->source_type = ArPayment::class;
            $mov->source_id   = $payment->id;
            $mov->save();

            $restante = (float) $data['amount'];

            $ordenes = SalesOrder::whereIn('id', $data['order_ids'] ?? [])
                ->orderBy('fecha')
                ->get();

            foreach ($ordenes as $orden) {
                if ($restante <= 0) break;

                $saldo = ($orden->saldo_pendiente !== null && (float)$orden->saldo_pendiente > 0)
                    ? (float) $orden->saldo_pendiente
                    : (float) $orden->total;

                $abono      = min($restante, $saldo);
                $nuevoSaldo = round($saldo - $abono, 2);

                // ← Guardar exactamente cuánto se aplicó a esta nota
                \App\Models\ArPaymentItem::create([
                    'ar_payment_id'  => $payment->id,
                    'sales_order_id' => $orden->id,
                    'monto_aplicado' => $abono,
                ]);

                $updateData = ['saldo_pendiente' => $nuevoSaldo];
                if ($nuevoSaldo <= 0) {
                    // Saldo en $0: la nota queda cobrada Y liquidada con el
                    // chofer (para pedidos a crédito no hay un paso de
                    // "Cerrar cobranza" separado como en efectivo/contraentrega
                    // — cobrar la CxC completa ES la liquidación).
                    $updateData['cobrado_at']              = now();
                    $updateData['driver_settlement_status'] = 'LIQUIDADO';
                    $updateData['driver_settlement_at']     = now();
                } elseif ($abono > 0 && $orden->driver_settlement_status !== 'LIQUIDADO') {
                    // Se abonó algo pero no se cubrió todo — que quede
                    // reflejado como PARCIAL en vez de seguir en PENDIENTE
                    // como si no se le hubiera cobrado nada.
                    $updateData['driver_settlement_status'] = 'PARCIAL';
                }

                $orden->update($updateData);
                $restante = round($restante - $abono, 2);
            }

            // Notas de venta (mostrador) a crédito — mismo reparto FIFO que
            // los pedidos, con el sobrante que haya quedado.
            $notasVenta = Sale::whereIn('id', $data['sale_ids'] ?? [])
                ->orderBy('fecha')
                ->get();

            foreach ($notasVenta as $nota) {
                if ($restante <= 0) break;

                $saldo = ($nota->saldo_pendiente !== null && (float) $nota->saldo_pendiente > 0)
                    ? (float) $nota->saldo_pendiente
                    : (float) $nota->total;

                $abono      = min($restante, $saldo);
                $nuevoSaldo = round($saldo - $abono, 2);

                \App\Models\ArPaymentItem::create([
                    'ar_payment_id'  => $payment->id,
                    'sale_id'        => $nota->id,
                    'monto_aplicado' => $abono,
                ]);

                $updateData = ['saldo_pendiente' => $nuevoSaldo];
                if ($nuevoSaldo <= 0) {
                    $updateData['cobrado_at']              = now();
                    $updateData['driver_settlement_status'] = 'LIQUIDADO';
                    $updateData['driver_settlement_at']     = now();
                } elseif ($abono > 0 && $nota->driver_settlement_status !== 'LIQUIDADO') {
                    $updateData['driver_settlement_status'] = 'PARCIAL';
                }

                $nota->update($updateData);
                $restante = round($restante - $abono, 2);
            }

            // Facturas libres (sin pedido) — mismo reparto FIFO, con el
            // sobrante que haya quedado después de cubrir notas.
            $facturas = Invoice::whereIn('id', $data['invoice_ids'] ?? [])
                ->orderBy('fecha')
                ->get();

            foreach ($facturas as $factura) {
                if ($restante <= 0) break;

                $saldo = $factura->saldoPendiente();
                if ($saldo <= 0) continue;

                $abono = min($restante, $saldo);

                \App\Models\ArPaymentItem::create([
                    'ar_payment_id' => $payment->id,
                    'invoice_id'    => $factura->id,
                    'monto_aplicado'=> $abono,
                ]);

                $restante = round($restante - $abono, 2);
            }

            return $payment;
        });
    }

    /**
     * Liquidación rápida: selecciona varias notas (de uno o varios clientes,
     * como en el reporte de Liquidaciones) y las cobra de un jalón en efectivo
     * — un ArPayment por cliente, cada uno por el saldo pendiente exacto de
     * las notas que le tocaron. Evita abrir el formulario manual una por una.
     */
    public function liquidarMasivo(Request $request)
    {
        $data = $request->validate([
            'order_ids'   => 'nullable|array',
            'order_ids.*' => 'integer|exists:sales_orders,id',
            'sale_ids'    => 'nullable|array',
            'sale_ids.*'  => 'integer|exists:sales,id',
            'fecha'       => 'nullable|date',
        ]);

        if (empty($data['order_ids']) && empty($data['sale_ids'])) {
            return response()->json(['ok' => false, 'message' => 'No se seleccionó ninguna nota.'], 422);
        }

        $fecha = $data['fecha'] ?? now()->toDateString();

        $efectivo = PaymentType::where('descripcion', 'like', '%efectivo%')->first();
        if (!$efectivo) {
            return response()->json(['ok' => false, 'message' => 'No se encontró la forma de pago "Efectivo".'], 422);
        }

        $ordenes = SalesOrder::where('payment_method', 'CREDITO')
            ->whereIn('status', ['ENTREGADO'])
            ->whereNull('cobrado_at')
            ->whereIn('id', $data['order_ids'] ?? [])
            ->where(fn($q) => $q->whereNull('saldo_pendiente')->orWhere('saldo_pendiente', '>', 0))
            ->get();

        $notasVenta = Sale::where('tipo_venta', 'CREDITO')
            ->whereIn('status', ['ENTREGADO', 'COMPLETADA'])
            ->whereNull('cobrado_at')
            ->whereIn('id', $data['sale_ids'] ?? [])
            ->where(fn($q) => $q->whereNull('saldo_pendiente')->orWhere('saldo_pendiente', '>', 0))
            ->get();

        if ($ordenes->isEmpty() && $notasVenta->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Las notas seleccionadas ya no están pendientes de cobro.'], 422);
        }

        // Un cobro por cliente, cubriendo de un jalón sus pedidos Y sus notas
        // de venta a crédito seleccionadas.
        $porCliente = [];
        foreach ($ordenes->groupBy('client_id') as $clientId => $notasCliente) {
            $porCliente[$clientId]['order_ids'] = $notasCliente->pluck('id')->all();
            $porCliente[$clientId]['monto'] = ($porCliente[$clientId]['monto'] ?? 0) + $notasCliente->sum(
                fn($o) => ($o->saldo_pendiente !== null && (float) $o->saldo_pendiente > 0)
                    ? (float) $o->saldo_pendiente
                    : (float) $o->total
            );
        }
        foreach ($notasVenta->groupBy('client_id') as $clientId => $notasCliente) {
            $porCliente[$clientId]['sale_ids'] = $notasCliente->pluck('id')->all();
            $porCliente[$clientId]['monto'] = ($porCliente[$clientId]['monto'] ?? 0) + $notasCliente->sum(
                fn($s) => ($s->saldo_pendiente !== null && (float) $s->saldo_pendiente > 0)
                    ? (float) $s->saldo_pendiente
                    : (float) $s->total
            );
        }

        $pagos = [];

        foreach ($porCliente as $clientId => $info) {
            $monto = $info['monto'] ?? 0;
            if ($monto <= 0) continue;

            $payment = $this->registrarCobro([
                'client_id'       => $clientId,
                'fecha'           => $fecha,
                'amount'          => round($monto, 2),
                'payment_type_id' => $efectivo->id,
                'reference'       => null,
                'notes'           => 'Liquidación masiva en efectivo (reporte de liquidaciones)',
                'order_ids'       => $info['order_ids'] ?? [],
                'sale_ids'        => $info['sale_ids'] ?? [],
                'invoice_ids'     => [],
            ]);

            $pagos[] = ['client_id' => $clientId, 'monto' => round($monto, 2), 'payment_id' => $payment->id];
        }

        return response()->json([
            'ok'          => true,
            'pagos'       => $pagos,
            'total'       => round(collect($pagos)->sum('monto'), 2),
            'notas'       => $ordenes->count() + $notasVenta->count(),
            'clientes'    => count($pagos),
        ]);
    }

    public function notasIndex(Request $request)
    {
        $search     = $request->get('search', '');
        $estado     = $request->get('estado', '');   // pendiente | pagado | parcial
        $clientId   = $request->get('client_id', '');

        $clients = Client::where('activo', 1)->orderBy('nombre')->get(['id','nombre']);

        $query = SalesOrder::with(['client'])
            ->where('payment_method', 'CREDITO')
            ->whereIn('status', ['ENTREGADO', 'EN_RUTA', 'DESPACHADO'])
            ->when($search, fn($q) =>
                $q->where('folio', 'like', "%$search%")
                ->orWhereHas('client', fn($c) => $c->where('nombre', 'like', "%$search%"))
            )
            ->when($clientId, fn($q) => $q->where('client_id', $clientId))
            ->when($estado === 'pagado',   fn($q) => $q->whereNotNull('cobrado_at'))
            ->when($estado === 'pendiente', fn($q) =>
                $q->whereNull('cobrado_at')
                ->where(fn($q) => $q->whereNull('saldo_pendiente')->orWhere('saldo_pendiente', '>', 0))
            )
            ->when($estado === 'parcial', fn($q) =>
                $q->whereNull('cobrado_at')
                ->whereNotNull('saldo_pendiente')
                ->whereRaw('saldo_pendiente < total')
                ->where('saldo_pendiente', '>', 0)
            )
            ->orderByDesc('fecha')
            ->paginate(25)
            ->withQueryString();

        // Para cada nota, buscar su(s) pago(s) via order_ids JSON
        $orderIds = $query->pluck('id')->toArray();

    $pagos = \App\Models\ArPaymentItem::with(['payment.paymentType'])
        ->whereIn('sales_order_id', $orderIds)
        ->get();

    $pagosPorNota = $pagos->groupBy(fn($item) => (string) $item->sales_order_id);

        // PaymentTypes para mostrar forma de pago
    

        return view('admin.ar.notas_index', compact(
            'query', 'clients', 'search', 'estado', 'clientId',
            'pagosPorNota',
        ));
    }
}