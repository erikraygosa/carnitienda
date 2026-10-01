<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\SalesOrder;
use App\Models\ShippingRoute;
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
            new Middleware('can:ver despachos', only: ['index', 'data']),
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

    public function data(Request $request)
    {
        $fecha  = $request->get('fecha', now()->toDateString());
        $search = trim((string) $request->get('search', ''));

        $routes = ShippingRoute::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        $dispatches = Dispatch::with(['items.salesOrder.client', 'arAssignments.client'])
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
            ->where(fn ($q) => $q
                ->whereDate('programado_para', $fecha)
                ->orWhere(fn ($q2) => $q2->whereNull('programado_para')->whereDate('fecha', $fecha)))
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('folio', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('nombre', 'like', "%{$search}%"))))
            ->with('client:id,nombre')
            ->orderBy('folio')
            ->get(['id', 'folio', 'client_id', 'total', 'payment_method', 'status'])
            ->map(fn ($o) => [
                'order_id' => $o->id,
                'folio'    => $o->folio,
                'cliente'  => $o->client?->nombre ?? '—',
                'total'    => (float) $o->total,
                'metodo'   => $o->payment_method,
                'status'   => $o->status,
            ])->values();

        return response()->json(['rutas' => $rutas, 'sueltos' => $sueltos, 'fecha' => $fecha]);
    }

    private function celdaData(?Dispatch $dispatch): array
    {
        if (! $dispatch) {
            return ['dispatch_id' => null, 'status' => null, 'pedidos' => [], 'cxc' => [], 'total' => 0];
        }

        $pedidos = $dispatch->items->map(fn ($it) => [
            'item_id'  => $it->id,
            'order_id' => $it->sales_order_id,
            'folio'    => $it->salesOrder?->folio,
            'cliente'  => $it->salesOrder?->client?->nombre ?? '—',
            'total'    => (float) ($it->salesOrder?->total ?? 0),
            'status'   => $it->salesOrder?->status,
        ])->values();

        $cxc = $dispatch->arAssignments->map(fn ($a) => [
            'id'              => $a->id,
            'cliente'         => $a->client?->nombre ?? '—',
            'saldo_asignado'  => (float) $a->saldo_asignado,
            'monto_cobrado'   => (float) $a->monto_cobrado,
            'status'          => $a->status,
        ])->values();

        return [
            'dispatch_id' => $dispatch->id,
            'status'      => $dispatch->status,
            'editable'    => $dispatch->status === 'PLANEADO',
            'pedidos'     => $pedidos,
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
     * Mueve un pedido al soltarlo en una celda ruta+ronda (o a "sueltos" si
     * route_id viene vacío). Crea el despacho destino si hace falta, y
     * actualiza la ruta/ronda guardada en el propio pedido para que quede
     * consistente la próxima vez que se reprocese.
     */
    public function mover(Request $request)
    {
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
}
