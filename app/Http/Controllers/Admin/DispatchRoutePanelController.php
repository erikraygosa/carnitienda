<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\DispatchTransferAssignment;
use App\Models\SalesOrder;
use App\Models\ShippingRoute;
use App\Models\StockTransfer;
use App\Models\SystemSetting;
use App\Services\AutoDespachoService;
use App\Services\DocumentLogService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Panel de rutas de despacho (fase 2) — vista tipo Liquidaciones con las
 * rutas como columnas, donde los pedidos se arrastran de una ruta/ronda a
 * otra. No reemplaza la lógica de Dispatch/DispatchItem ya existente en
 * DispatchController, solo la expone de otra forma; entregar, CxC, etc.
 * siguen siendo las mismas rutas/acciones de siempre.
 */
class DispatchRoutePanelController extends Controller implements HasMiddleware
{
    public function __construct(
        private AutoDespachoService $autoDespacho,
        private DocumentLogService $log,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver despachos', only: ['index', 'data', 'pollCount']),
            new Middleware('can:editar despachos', only: ['mover', 'asegurarDespacho']),
        ];
    }

    public function index()
    {
        $routes        = ShippingRoute::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $modoAutomatico = $this->autoDespacho->modoAutomatico();
        $formatoImpresion = SystemSetting::get('despacho.formato_impresion', 'despachos');

        return view('admin.dispatches.panel-rutas', compact('routes', 'modoAutomatico', 'formatoImpresion'));
    }

    /**
     * Polling (igual que Panel de Surtido): cuenta cuántos pedidos
     * PROCESADO/DESPACHADO/NO_ENTREGADO hay para el día seleccionado —
     * asignados o no — para avisar "hay pedidos nuevos" sin recargar sola la
     * página (eso ya se decidió que molesta si alguien está a media acción).
     */
    public function pollCount(Request $request)
    {
        $fecha = $request->get('fecha', now()->toDateString());

        $count = SalesOrder::whereIn('status', ['PROCESADO', 'DESPACHADO', 'NO_ENTREGADO'])
            ->where(fn ($q) => $q
                ->whereDate('programado_para', $fecha)
                ->orWhere(fn ($q2) => $q2->whereNull('programado_para')->whereDate('fecha', $fecha)))
            ->count();

        $count += StockTransfer::whereIn('status', ['PENDIENTE', 'ASIGNADO'])
            ->whereDate('fecha', $fecha)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function data(Request $request)
    {
        $fecha  = $request->get('fecha', now()->toDateString());
        $search = trim((string) $request->get('search', ''));
        // "Ver pedidos anteriores": además de los del día, trae los de días
        // anteriores que siguen sin despacho (se envían al despacho del día
        // que se está viendo).
        $atrasados = $request->boolean('atrasados');

        $routes = ShippingRoute::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        $dispatches = Dispatch::with(['items.salesOrder.client', 'arAssignments.client', 'transferAssignments.stockTransfer.fromWarehouse', 'transferAssignments.stockTransfer.toWarehouse'])
            ->whereDate('fecha', $fecha)
            ->whereIn('status', ['PLANEADO', 'CARGADO', 'EN_RUTA', 'CERRADO', 'ENTREGADO'])
            ->get()
            ->groupBy(fn ($d) => $d->shipping_route_id . ':' . $d->ronda);

        $rutas = $routes->map(function ($route) use ($dispatches) {
            $rondas = [];
            foreach ([1, 2] as $ronda) {
                $dispatch = $dispatches->get($route->id . ':' . $ronda)?->first();
                $rondas[$ronda] = $this->celdaData($dispatch);
            }
            return [
                'route_id' => $route->id,
                'nombre'   => $route->nombre,
                'rondas'   => $rondas,
            ];
        })->values();

        // Sueltos: PROCESADO/DESPACHADO/NO_ENTREGADO sin despacho, del día
        // programado (o de la fecha del pedido si no tiene programado_para)
        // — mismo criterio que ya usa DispatchPanelController para
        // "pendientes del día".
        $sueltos = SalesOrder::whereIn('status', ['PROCESADO', 'DESPACHADO', 'NO_ENTREGADO'])
            ->whereDoesntHave('dispatchItem')
            ->where(fn ($q) => $atrasados
                ? $q->whereDate('programado_para', '<=', $fecha)
                    ->orWhere(fn ($q2) => $q2->whereNull('programado_para')->whereDate('fecha', '<=', $fecha))
                : $q->whereDate('programado_para', $fecha)
                    ->orWhere(fn ($q2) => $q2->whereNull('programado_para')->whereDate('fecha', $fecha)))
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('folio', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('nombre', 'like', "%{$search}%"))))
            ->with('client:id,nombre')
            ->orderBy('folio')
            ->get(['id', 'folio', 'client_id', 'total', 'payment_method', 'status', 'programado_para', 'fecha'])
            ->map(fn ($o) => [
                'fecha_original' => $this->fechaAtrasada($o->programado_para ?: $o->fecha, $fecha),
                'tipo'     => 'pedido',
                'order_id' => $o->id,
                'folio'    => $o->folio,
                'cliente'  => $o->client?->nombre ?? '—',
                'total'    => (float) $o->total,
                'metodo'   => $o->payment_method,
                'status'   => $o->status,
            ])->values();

        // Traspasos sin despacho asignado, del mismo día — mismo criterio que
        // pedidos "sueltos": se pueden arrastrar o enviar a ruta igual.
        $traspasosSueltos = StockTransfer::where('status', 'PENDIENTE')
            ->whereNull('dispatch_id')
            ->whereDate('fecha', $atrasados ? '<=' : '=', $fecha)
            ->when($search, fn ($q) => $q->where('folio', 'like', "%{$search}%"))
            ->with(['fromWarehouse:id,nombre', 'toWarehouse:id,nombre'])
            ->orderBy('folio')
            ->get(['id', 'folio', 'from_warehouse_id', 'to_warehouse_id', 'status', 'fecha'])
            ->map(fn ($t) => [
                'fecha_original' => $this->fechaAtrasada($t->fecha, $fecha),
                'tipo'         => 'traspaso',
                'transfer_id'  => $t->id,
                'folio'        => $t->folio,
                'cliente'      => ($t->fromWarehouse?->nombre ?? '—') . ' → ' . ($t->toWarehouse?->nombre ?? '—'),
                'total'        => null,
                'status'       => $t->status,
            ])->values();

        $sueltos = $sueltos->concat($traspasosSueltos)->values();

        return response()->json(['rutas' => $rutas, 'sueltos' => $sueltos, 'fecha' => $fecha]);
    }

    /** dd/mm del documento si es de un día anterior al que se está viendo; null si es del mismo día. */
    private function fechaAtrasada($fechaDoc, string $fechaVista): ?string
    {
        if (! $fechaDoc) return null;
        $d = \Carbon\Carbon::parse($fechaDoc);
        return $d->toDateString() < $fechaVista ? $d->format('d/m') : null;
    }

    private function celdaData(?Dispatch $dispatch): array
    {
        if (! $dispatch) {
            return ['dispatch_id' => null, 'status' => null, 'pedidos' => [], 'traspasos' => [], 'cxc' => [], 'total' => 0];
        }

        $pedidos = $dispatch->items->map(fn ($it) => [
            'tipo'     => 'pedido',
            'item_id'  => $it->id,
            'order_id' => $it->sales_order_id,
            'folio'    => $it->salesOrder?->folio,
            'cliente'  => $it->salesOrder?->client?->nombre ?? '—',
            'total'    => (float) ($it->salesOrder?->total ?? 0),
            'status'   => $it->salesOrder?->status,
        ])->values();

        $traspasos = $dispatch->transferAssignments->map(fn ($a) => [
            'tipo'           => 'traspaso',
            'assignment_id'  => $a->id,
            'transfer_id'    => $a->stock_transfer_id,
            'folio'          => $a->stockTransfer?->folio,
            'cliente'        => ($a->stockTransfer?->fromWarehouse?->nombre ?? '—') . ' → ' . ($a->stockTransfer?->toWarehouse?->nombre ?? '—'),
            'total'          => null,
            'status'         => $a->stockTransfer?->status,
        ])->values();

        $cxc = $dispatch->arAssignments->map(fn ($a) => [
            'id'              => $a->id,
            'cliente'         => $a->client?->nombre ?? '—',
            'folios'          => $a->orders()->pluck('sales_orders.folio')->implode(', '),
            'saldo_asignado'  => (float) $a->saldo_asignado,
            'monto_cobrado'   => (float) $a->monto_cobrado,
            'status'          => $a->status,
        ])->values();

        // Un despacho PLANEADO sin pedidos ni CxC (ej. creado por error con el
        // botón "+ CxC" y nunca usado) se muestra como celda vacía — el status
        // "PLANEADO" ahí no aporta nada y solo confunde. Sigue existiendo
        // (dispatch_id se conserva para reusarlo si sueltan algo encima), solo
        // no se le pinta el badge de estatus.
        $vacio = $pedidos->isEmpty() && $traspasos->isEmpty() && $cxc->isEmpty();

        return [
            'dispatch_id' => $dispatch->id,
            'status'      => $vacio ? null : $dispatch->status,
            'editable'    => $dispatch->status === 'PLANEADO',
            'pedidos'     => $pedidos,
            'traspasos'   => $traspasos,
            'cxc'         => $cxc,
            'total'       => $pedidos->sum('total'),
        ];
    }

    /**
     * Garantiza que exista el despacho de esta ruta+ronda+día (lo crea si
     * hace falta) y regresa su id — usado por el botón "+ CxC" del panel
     * para poder abrir la pantalla de agregar CxC aunque la celda todavía
     * no tenga ningún pedido.
     */
    public function asegurarDespacho(Request $request)
    {
        $data = $request->validate([
            'shipping_route_id' => ['required', 'integer', 'exists:shipping_routes,id'],
            'ronda'             => ['required', 'integer', 'in:1,2'],
            'fecha'             => ['required', 'date'],
        ]);

        $dispatch = $this->autoDespacho->encontrarOCrearDespacho(
            (int) $data['shipping_route_id'],
            (int) $data['ronda'],
            $data['fecha'],
            'Creado desde el panel de rutas (CxC manual)'
        );

        return response()->json(['ok' => true, 'dispatch_id' => $dispatch->id]);
    }

    /**
     * Mueve un pedido o un traspaso al soltarlo en una celda ruta+ronda (o a
     * "Sin Asignación" si route_id viene vacío). Crea el despacho destino si
     * hace falta.
     */
    public function mover(Request $request)
    {
        $tipo = $request->get('tipo', 'pedido');

        if ($tipo === 'traspaso') {
            return $this->moverTraspaso($request);
        }

        $data = $request->validate([
            'order_id'          => ['required', 'integer', 'exists:sales_orders,id'],
            'shipping_route_id' => ['nullable', 'integer', 'exists:shipping_routes,id'],
            'ronda'             => ['nullable', 'integer', 'in:1,2'],
            'fecha'             => ['required', 'date'],
        ]);

        if (!empty($data['shipping_route_id']) && empty($data['ronda'])) {
            return response()->json(['ok' => false, 'message' => 'Falta la ronda.'], 422);
        }

        $order = SalesOrder::findOrFail($data['order_id']);
        if (! in_array($order->status, ['PROCESADO', 'DESPACHADO', 'NO_ENTREGADO'])) {
            return response()->json(['ok' => false, 'message' => 'Este pedido ya no se puede mover de ruta (status ' . $order->status . ').'], 422);
        }

        return DB::transaction(function () use ($order, $data) {
            $itemExistente = DispatchItem::where('sales_order_id', $order->id)->first();

            // Si ya estaba en un despacho que salió a ruta, no se puede mover.
            if ($itemExistente && $itemExistente->dispatch && $itemExistente->dispatch->status !== 'PLANEADO') {
                return response()->json(['ok' => false, 'message' => 'El despacho actual de este pedido ya salió a ruta, no se puede reasignar.'], 422);
            }

            // Soltar en "Sueltos": quitar del despacho, sin tocar ruta/ronda del pedido.
            if (empty($data['shipping_route_id'])) {
                if ($itemExistente) {
                    $dispatchOrigen = $itemExistente->dispatch;
                    $itemExistente->delete();
                    if ($dispatchOrigen) {
                        $this->log->log($dispatchOrigen, 'PEDIDO_QUITADO', null, null, null, "Pedido {$order->folio} regresado a sueltos desde el panel de rutas");
                    }
                }
                return response()->json(['ok' => true]);
            }

            $dispatch = $this->autoDespacho->encontrarOCrearDespacho(
                (int) $data['shipping_route_id'],
                (int) $data['ronda'],
                $data['fecha'],
                "Creado desde el panel de rutas para {$order->folio}"
            );

            if ($itemExistente?->dispatch_id === $dispatch->id) {
                return response()->json(['ok' => true]); // ya estaba ahí
            }

            $dispatchOrigenId = $itemExistente?->dispatch_id;

            DispatchItem::updateOrCreate(
                ['sales_order_id' => $order->id],
                ['dispatch_id' => $dispatch->id, 'referencia' => $order->folio, 'status' => 'ASIGNADO']
            );

            $order->update([
                'shipping_route_id' => $data['shipping_route_id'],
                'ronda'             => $data['ronda'],
            ]);

            $this->log->log($dispatch, 'PEDIDOS_AGREGADOS', null, null, null, "Pedido {$order->folio} movido aquí desde el panel de rutas");

            if ($dispatchOrigenId && $dispatchOrigenId !== $dispatch->id) {
                $origen = Dispatch::find($dispatchOrigenId);
                if ($origen && ! $origen->items()->exists() && ! $origen->arAssignments()->exists() && ! $origen->transferAssignments()->exists()) {
                    $origen->delete();
                }
            }

            return response()->json(['ok' => true]);
        });
    }

    /**
     * Mismo criterio que mover() pero para un traspaso — usa
     * DispatchTransferAssignment en vez de DispatchItem, y el status propio
     * de StockTransfer (PENDIENTE ↔ ASIGNADO) en vez del de SalesOrder.
     */
    private function moverTraspaso(Request $request)
    {
        $data = $request->validate([
            'order_id'          => ['required', 'integer', 'exists:stock_transfers,id'], // mismo nombre de campo que pedidos, para reusar el JS del panel
            'shipping_route_id' => ['nullable', 'integer', 'exists:shipping_routes,id'],
            'ronda'             => ['nullable', 'integer', 'in:1,2'],
            'fecha'             => ['required', 'date'],
        ]);

        if (!empty($data['shipping_route_id']) && empty($data['ronda'])) {
            return response()->json(['ok' => false, 'message' => 'Falta la ronda.'], 422);
        }

        $transfer = StockTransfer::findOrFail($data['order_id']);
        if (! in_array($transfer->status, ['PENDIENTE', 'ASIGNADO'])) {
            return response()->json(['ok' => false, 'message' => 'Este traspaso ya no se puede mover de ruta (status ' . $transfer->status . ').'], 422);
        }

        return DB::transaction(function () use ($transfer, $data) {
            $asignacionExistente = DispatchTransferAssignment::where('stock_transfer_id', $transfer->id)->first();

            if ($asignacionExistente && $asignacionExistente->dispatch && $asignacionExistente->dispatch->status !== 'PLANEADO') {
                return response()->json(['ok' => false, 'message' => 'El despacho actual de este traspaso ya salió a ruta, no se puede reasignar.'], 422);
            }

            // Soltar en "Sin Asignación": quitar del despacho, regresa a PENDIENTE.
            if (empty($data['shipping_route_id'])) {
                if ($asignacionExistente) {
                    $dispatchOrigen = $asignacionExistente->dispatch;
                    $asignacionExistente->delete();
                    $transfer->update(['status' => 'PENDIENTE', 'dispatch_id' => null]);
                    if ($dispatchOrigen) {
                        $this->log->log($dispatchOrigen, 'TRASPASO_QUITADO', null, null, null, "Traspaso {$transfer->folio} regresado a sin asignación desde el panel de rutas");
                    }
                }
                return response()->json(['ok' => true]);
            }

            $dispatch = $this->autoDespacho->encontrarOCrearDespacho(
                (int) $data['shipping_route_id'],
                (int) $data['ronda'],
                $data['fecha'],
                "Creado desde el panel de rutas para {$transfer->folio}"
            );

            if ($asignacionExistente?->dispatch_id === $dispatch->id) {
                return response()->json(['ok' => true]); // ya estaba ahí
            }

            $dispatchOrigenId = $asignacionExistente?->dispatch_id;
            $asignacionExistente?->delete();

            DispatchTransferAssignment::create([
                'dispatch_id'       => $dispatch->id,
                'stock_transfer_id' => $transfer->id,
                'status'            => 'PENDIENTE',
            ]);

            $transfer->update([
                'dispatch_id'       => $dispatch->id,
                'status'            => 'ASIGNADO',
                'shipping_route_id' => $data['shipping_route_id'],
                'ronda'             => $data['ronda'],
            ]);

            $this->log->log($dispatch, 'TRASPASOS_AGREGADOS', null, null, null, "Traspaso {$transfer->folio} movido aquí desde el panel de rutas");

            if ($dispatchOrigenId && $dispatchOrigenId !== $dispatch->id) {
                $origen = Dispatch::find($dispatchOrigenId);
                if ($origen && ! $origen->items()->exists() && ! $origen->arAssignments()->exists() && ! $origen->transferAssignments()->exists()) {
                    $origen->delete();
                }
            }

            return response()->json(['ok' => true]);
        });
    }
}
