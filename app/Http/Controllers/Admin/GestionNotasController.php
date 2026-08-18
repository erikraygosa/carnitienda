<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\PosSale;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\CashService;
use App\Services\DocumentLogService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Módulo de gestión de notas (Pedidos + ventas POS) de cualquier sucursal,
 * incluyendo las ya cerradas/entregadas — para corregir errores después del
 * hecho. Todo movimiento de stock/CxC/caja que se derive de una acción aquí
 * queda auditado en document_activity_logs vía DocumentLogService.
 */
class GestionNotasController extends Controller implements HasMiddleware
{
    public function __construct(
        private InventoryService $inv,
        private DocumentLogService $log,
        private CashService $cash,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:gestionar notas todas sucursales'),
        ];
    }

    public function index(Request $request)
    {
        $tipo        = $request->get('tipo', 'todos'); // todos|pedido|pos
        $q           = trim((string) $request->get('q', ''));
        $warehouseId = $request->get('warehouse_id');
        $fechaDesde  = $request->get('fecha_desde');
        $fechaHasta  = $request->get('fecha_hasta');

        $resultados = collect();

        if (in_array($tipo, ['todos', 'pedido'])) {
            $pedidos = SalesOrder::with(['client:id,nombre', 'warehouse:id,nombre'])
                ->when($q, fn($qq) => $qq->where(fn($w) => $w
                    ->where('folio', 'like', "%{$q}%")
                    ->orWhereHas('client', fn($c) => $c->where('nombre', 'like', "%{$q}%"))
                ))
                ->when($warehouseId, fn($qq) => $qq->where('warehouse_id', $warehouseId))
                ->when($fechaDesde, fn($qq) => $qq->whereDate('fecha', '>=', $fechaDesde))
                ->when($fechaHasta, fn($qq) => $qq->whereDate('fecha', '<=', $fechaHasta))
                ->latest('fecha')
                ->limit(150)
                ->get()
                ->map(fn($o) => [
                    'tipo'       => 'pedido',
                    'id'         => $o->id,
                    'folio'      => $o->folio ?? '#' . $o->id,
                    'cliente'    => $o->client?->nombre ?? '—',
                    'almacen'    => $o->warehouse?->nombre ?? '—',
                    'fecha'      => optional($o->fecha)->format('d/m/Y'),
                    'total'      => (float) $o->total,
                    'estatus'    => $o->status,
                    'cancelable' => !in_array($o->status, ['CANCELADO']),
                    'url_ver'    => route('admin.sales-orders.edit', $o->id) . '?origen=gestion-notas',
                    'url_cancelar' => route('admin.gestion-notas.pedidos.cancelar', $o->id),
                ]);
            $resultados = $resultados->concat($pedidos);
        }

        if (in_array($tipo, ['todos', 'pos'])) {
            $ventas = PosSale::with(['client:id,nombre', 'warehouse:id,nombre'])
                ->when($q, fn($qq) => $qq->where(fn($w) => $w
                    ->where('id', 'like', "%{$q}%")
                    ->orWhereHas('client', fn($c) => $c->where('nombre', 'like', "%{$q}%"))
                ))
                ->when($warehouseId, fn($qq) => $qq->where('warehouse_id', $warehouseId))
                ->when($fechaDesde, fn($qq) => $qq->whereDate('fecha', '>=', $fechaDesde))
                ->when($fechaHasta, fn($qq) => $qq->whereDate('fecha', '<=', $fechaHasta))
                ->latest('fecha')
                ->limit(150)
                ->get()
                ->map(fn($s) => [
                    'tipo'       => 'pos',
                    'id'         => $s->id,
                    'folio'      => 'POS-' . $s->id,
                    'cliente'    => $s->client?->nombre ?? 'Público general',
                    'almacen'    => $s->warehouse?->nombre ?? '—',
                    'fecha'      => optional($s->fecha)->format('d/m/Y'),
                    'total'      => (float) $s->total,
                    'estatus'    => $s->estatus ?? 'COMPLETADA',
                    'cancelable' => ($s->estatus ?? 'COMPLETADA') !== 'CANCELADA',
                    'url_ver'    => route('admin.pos.ticket', $s->id),
                    'url_cancelar' => route('admin.gestion-notas.pos.cancelar', $s->id),
                ]);
            $resultados = $resultados->concat($ventas);
        }

        $resultados = $resultados->sortByDesc(fn($r) => $r['fecha'])->values()->take(150);

        $warehouses = Warehouse::orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.gestion_notas.index', [
            'resultados'  => $resultados,
            'warehouses'  => $warehouses,
            'filtros'     => compact('tipo', 'q', 'warehouseId', 'fechaDesde', 'fechaHasta'),
        ]);
    }

    /**
     * Cancela un pedido sin importar su estatus (incluso EN_RUTA/ENTREGADO),
     * revirtiendo el stock que ya se hubiera descontado y el cargo a CxC si
     * era a crédito. Requiere 'editar pedidos cerrados' además del permiso
     * general del módulo — es una acción de más alcance que solo verlo.
     */
    public function cancelarPedido(Request $request, SalesOrder $order)
    {
        abort_unless(auth()->user()->can('editar pedidos cerrados'), 403);

        if ($order->status === 'CANCELADO') {
            return back()->with('swal', ['icon' => 'info', 'title' => 'Ya estaba cancelado', 'text' => 'Este pedido ya se había cancelado.']);
        }

        $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);

        $order->load('items.product');
        $oldStatus = $order->status;

        DB::transaction(function () use ($order, $request) {
            // Revertir stock de las líneas que ya se hubieran surtido/entregado.
            foreach ($order->items as $item) {
                if (!$item->product_id || (float) $item->cantidad <= 0) continue;
                $this->inv->stockIn(
                    productId:   (int) $item->product_id,
                    warehouseId: $order->warehouse_id,
                    qty:         (float) $item->cantidad,
                    motivo:      'CANCELACION_PEDIDO_CERRADO',
                    referencia:  $order,
                    userId:      auth()->id(),
                );
            }

            // Revertir cargo a CxC si era crédito y ya se había cargado (al entregarse).
            if ($order->payment_method === 'CREDITO' && $order->client_id && in_array($order->status, ['ENTREGADO'])) {
                app(\App\Services\ArService::class)->charge(
                    clientId: $order->client_id,
                    monto:    -1 * (float) $order->total,
                    desc:     "Reversa por cancelación de pedido {$order->folio}",
                    source:   $order,
                    fecha:    now()->toDateString(),
                );
            }

            $order->update(['status' => 'CANCELADO']);
        });

        $this->log->log(
            $order, 'CANCELADO_DESDE_GESTION_NOTAS', $oldStatus, 'CANCELADO',
            note: 'Cancelado desde Gestión de notas (' . $oldStatus . ' → CANCELADO). Motivo: ' . ($request->input('motivo') ?: '(sin especificar)')
        );

        return back()->with('swal', ['icon' => 'success', 'title' => 'Pedido cancelado', 'text' => 'Se revirtió el stock y, si aplicaba, el cargo a CxC.']);
    }

    /**
     * Cancela una venta de POS: regresa el stock de cada línea y, si la caja
     * donde se registró sigue ABIERTA, descuenta el importe de sus ventas en
     * efectivo. Si la caja ya está CERRADA no se toca (evita corromper un
     * corte ya hecho) — queda anotado en la auditoría para conciliar aparte.
     */
    public function cancelarPos(Request $request, PosSale $sale)
    {
        abort_unless(auth()->user()->can('cancelar notas pos'), 403);

        if (($sale->estatus ?? 'COMPLETADA') === 'CANCELADA') {
            return back()->with('swal', ['icon' => 'info', 'title' => 'Ya estaba cancelada', 'text' => 'Esta venta ya se había cancelado.']);
        }

        $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);

        $sale->load('items.product', 'cashRegister');
        $cajaTocada = false;

        DB::transaction(function () use ($sale, $request, &$cajaTocada) {
            foreach ($sale->items as $item) {
                if (!$item->product_id || (float) $item->cantidad <= 0) continue;
                $this->inv->stockIn(
                    productId:   (int) $item->product_id,
                    warehouseId: $sale->warehouse_id,
                    qty:         (float) $item->cantidad,
                    motivo:      'CANCELACION_VENTA_POS',
                    referencia:  $sale,
                    userId:      auth()->id(),
                );
            }

            $register = $sale->cashRegister;
            if ($register && $register->estatus === 'ABIERTO' && in_array($sale->metodo_pago, ['EFECTIVO', 'MIXTO'])) {
                $montoEfectivo = $sale->metodo_pago === 'EFECTIVO' ? (float) $sale->total : (float) $sale->efectivo;
                if ($montoEfectivo > 0) {
                    $register->ventas_efectivo = max(0, (float) $register->ventas_efectivo - $montoEfectivo);
                    $register->monto_cierre = (float) $register->monto_apertura + (float) $register->ingresos
                        - (float) $register->egresos + (float) $register->ventas_efectivo;
                    $register->save();
                    $cajaTocada = true;
                }
            }

            $sale->update([
                'estatus'            => 'CANCELADA',
                'cancelado_at'       => now(),
                'cancelado_por'      => auth()->id(),
                'motivo_cancelacion' => $request->input('motivo'),
            ]);
        });

        $notaCaja = $cajaTocada
            ? 'Se descontó de la caja abierta.'
            : 'La caja de esta venta ya estaba cerrada (o no era efectivo) — no se ajustó, revisar manualmente si aplica.';

        $this->log->log(
            $sale, 'CANCELADO_DESDE_GESTION_NOTAS', 'COMPLETADA', 'CANCELADA',
            note: "Venta POS #{$sale->id} cancelada desde Gestión de notas. {$notaCaja} Motivo: " . ($request->input('motivo') ?: '(sin especificar)')
        );

        return back()->with('swal', ['icon' => 'success', 'title' => 'Venta cancelada', 'text' => "Se regresó el stock. {$notaCaja}"]);
    }
}
