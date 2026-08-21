<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\DispatchArAssignment;
use App\Models\DispatchTransferAssignment;
use App\Models\StockTransfer;
use App\Models\ArMovement;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Models\ShippingRoute;
use App\Models\Driver;
use App\Models\CashRegister;
use App\Models\PaymentType;
use App\Models\Client;
use App\Services\ArService;
use App\Services\InventoryService;
use App\Services\CashService;
use App\Services\DocumentLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DispatchController extends Controller implements HasMiddleware
{
    public function __construct(
        private ArService          $ar,
        private InventoryService   $inv,
        private CashService        $cash,
        private DocumentLogService $log,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver despachos', only: ['index', 'edit', 'printRuta', 'printLiquidacion']),
            new Middleware('can:crear despachos', only: ['create', 'store']),
            new Middleware('can:editar despachos', only: [
                'update', 'preparar', 'cargar', 'enRuta', 'entregar', 'cancelar', 'destroy',
                'entregarPedido', 'noEntregarPedido',
                'completarTraspaso', 'noCompletarTraspaso',
                'cobrarCxc', 'noCobrarCxc',
                'bulkTraspasos', 'bulkPedidos', 'bulkCxc',
            ]),
            new Middleware('can:cerrar despachos', only: ['cerrarTraspasos', 'cerrarCobranza', 'cerrarCompleto']),
        ];
    }

    public function index(Request $request)
    {
        $fechaDesde = $request->get('fecha_desde', now()->startOfMonth()->toDateString());
        $fechaHasta = $request->get('fecha_hasta', now()->endOfMonth()->toDateString());

        $dispatches = \App\Models\Dispatch::with(['driver', 'route', 'warehouse'])
            ->withCount([
                'items',
                'items as items_entregados'    => fn($q) => $q->where('status', 'ENTREGADO'),
                'items as items_no_entregados' => fn($q) => $q->where('status', 'NO_ENTREGADO'),
            ])
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->orderBy('fecha', 'desc')
            ->get();

        // Pedidos PROCESADOS que aún no se han asignado a ningún despacho
        $pedidosSinAsignar = SalesOrder::where('status', 'PROCESADO')
            ->whereDoesntHave('dispatchItem')
            ->with(['client:id,nombre', 'route:id,nombre'])
            ->orderBy('programado_para')
            ->get(['id', 'folio', 'client_id', 'shipping_route_id', 'total', 'payment_method', 'programado_para']);

        return view('admin.dispatches.index', compact('dispatches', 'fechaDesde', 'fechaHasta', 'pedidosSinAsignar'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(Request $req)
    {
        $warehouses = Warehouse::orderBy('nombre')->get(['id', 'nombre']);
        $routes     = ShippingRoute::orderBy('nombre')->get(['id', 'nombre']);
        $drivers    = Driver::orderBy('nombre')->get(['id', 'nombre']);

        // Pedidos PROCESADOS listos para salir
      $orders = SalesOrder::whereIn('status', ['PROCESADO', 'DESPACHADO'])
    ->with(['client:id,nombre', 'route:id,nombre'])
    ->latest()
    ->limit(200)
    ->get(['id','folio','client_id','shipping_route_id','status','total','programado_para','payment_method','ticket_impreso']);
        // Traspasos PENDIENTES listos para asignar
        $traspasosPendientes = StockTransfer::where('status', 'PENDIENTE')
            ->with(['fromWarehouse:id,nombre', 'toWarehouse:id,nombre'])
            ->withCount('items')
            ->latest()
            ->get();

        // Clientes con saldo pendiente en ar_movements
        $clientesConSaldo = DB::table('ar_movements')
            ->join('clients', 'clients.id', '=', 'ar_movements.client_id')
            ->selectRaw("
                ar_movements.client_id,
                clients.nombre,
                SUM(CASE WHEN ar_movements.tipo = 'CARGO' THEN ar_movements.monto ELSE -ar_movements.monto END) as saldo
            ")
            ->groupBy('ar_movements.client_id', 'clients.nombre')
            ->havingRaw("SUM(CASE WHEN ar_movements.tipo = 'CARGO' THEN ar_movements.monto ELSE -ar_movements.monto END) > 0")
            ->orderBy('clients.nombre')
            ->get();

        return view('admin.dispatches.create', compact(
            'warehouses', 'routes', 'drivers', 'orders', 'clientesConSaldo', 'traspasosPendientes'
        ));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
               'warehouse_id'      => ['required', 'exists:warehouses,id'],
        'shipping_route_id' => ['required', 'exists:shipping_routes,id'],
        'vehicle'           => ['nullable', 'string', 'max:50'],
        'fecha'             => ['required', 'date'],
        'notas'             => ['nullable', 'string'],
        'transfers'         => ['nullable', 'array'],
        'transfers.*'       => ['integer', 'exists:stock_transfers,id'],
        'orders'            => ['nullable', 'array'],
        'orders.*'          => ['integer', 'exists:sales_orders,id'],
        'clientes_ar'       => ['nullable', 'array'],
        'clientes_ar.*'     => ['integer', 'exists:clients,id'],
        ]);

        // Debe tener al menos algo asignado
        if (empty($data['transfers']) && empty($data['orders']) && empty($data['clientes_ar'])) {
            return back()
                ->withErrors(['orders' => 'Selecciona al menos un traspaso, pedido o cuenta por cobrar.'])
                ->withInput();
        }

        return DB::transaction(function () use ($data) {
            // Chofer = mismo nombre que la ruta (se crea si no existe)
            $route    = \App\Models\ShippingRoute::find($data['shipping_route_id']);
            $driver   = \App\Models\Driver::firstOrCreate(
                ['nombre' => $route?->nombre ?? 'Sin nombre'],
                ['activo' => true]
            );

            $dispatch = Dispatch::create([
                'warehouse_id'      => $data['warehouse_id']      ?? null,
                'shipping_route_id' => $data['shipping_route_id'] ?? null,
                'driver_id'         => $driver->id,
                'vehicle'           => $data['vehicle']           ?? null,
                'fecha'             => Carbon::parse($data['fecha']),
                'status'            => 'PLANEADO',
                'notas'             => $data['notas']             ?? null,
            ]);

            // 1. Asignar traspasos
            if (!empty($data['transfers'])) {
                $transfers = StockTransfer::whereIn('id', $data['transfers'])
                    ->where('status', 'PENDIENTE')
                    ->get();

                foreach ($transfers as $t) {
                    DispatchTransferAssignment::create([
                        'dispatch_id'       => $dispatch->id,
                        'stock_transfer_id' => $t->id,
                        'status'            => 'PENDIENTE',
                    ]);
                    $t->update(['status' => 'ASIGNADO', 'dispatch_id' => $dispatch->id]);
                }
            }

            // 2. Asociar pedidos
            if (!empty($data['orders'])) {
                $orders = SalesOrder::whereIn('id', $data['orders'])->get();
                foreach ($orders as $o) {
                    // Si el pedido ya se surtió antes de asignarlo a este
                    // despacho (Panel de Surtido crea su propio DispatchItem
                    // vía getOrCreateDispatch(), que puede caer en otro
                    // despacho PLANEADO de hoy), NO crear uno nuevo y vacío
                    // — eso dejaba las líneas reales de surtido colgadas del
                    // despacho viejo mientras este quedaba con un duplicado
                    // vacío marcando "falta surtir" por error. En vez de
                    // eso, movemos el DispatchItem existente (con sus
                    // líneas) a este despacho.
                    DispatchItem::updateOrCreate(
                        ['sales_order_id' => $o->id],
                        [
                            'dispatch_id' => $dispatch->id,
                            'referencia'  => $o->folio,
                            'status'      => 'ASIGNADO',
                        ]
                    );
                }
            }

            // 3. Asignar clientes con CxC pendiente
            if (!empty($data['clientes_ar'])) {
                $saldos = DB::table('ar_movements')
                    ->whereIn('client_id', $data['clientes_ar'])
                    ->selectRaw("
                        client_id,
                        SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END) as saldo
                    ")
                    ->groupBy('client_id')
                    ->pluck('saldo', 'client_id');

                foreach ($data['clientes_ar'] as $clientId) {
                    DispatchArAssignment::create([
                        'dispatch_id'    => $dispatch->id,
                        'client_id'      => $clientId,
                        'saldo_asignado' => (float) ($saldos[$clientId] ?? 0),
                        'monto_cobrado'  => 0,
                        'status'         => 'PENDIENTE',
                    ]);
                }
            }

            $this->log->log($dispatch, 'CREADO', null, 'PLANEADO');
            session()->flash('swal', ['icon' => 'success', 'title' => 'Despacho creado', 'text' => 'Listo para salir a ruta.']);
            return redirect()->route('admin.dispatches.edit', $dispatch);
        });
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(Dispatch $dispatch)
    {
        $dispatch->load([
            'items.salesOrder.client',
            'items.salesOrder.items',
            'items.lines.salesOrderItem.product',
            'arAssignments.client',
            'transferAssignments.stockTransfer.fromWarehouse',
            'transferAssignments.stockTransfer.toWarehouse',
            'transferAssignments.stockTransfer.items.product',
            'driver',
            'warehouse',
        ]);

        $warehouses    = Warehouse::orderBy('nombre')->get(['id', 'nombre']);
        $routes        = ShippingRoute::orderBy('nombre')->get(['id', 'nombre']);
        $drivers       = Driver::orderBy('nombre')->get(['id', 'nombre']);
        $paymentTypes  = PaymentType::where('activo', 1)->orderBy('id')->get(['id', 'clave', 'descripcion']);
        $cajasAbiertas = CashRegister::where('estatus', 'ABIERTO')->latest()->get();

        $statusClasses = [
            'PLANEADO'   => 'bg-gray-100 text-gray-700',
            'PREPARANDO' => 'bg-sky-100 text-sky-700',
            'CARGADO'    => 'bg-amber-100 text-amber-700',
            'EN_RUTA'    => 'bg-violet-100 text-violet-700',
            'ENTREGADO'  => 'bg-emerald-100 text-emerald-700',
            'CERRADO'    => 'bg-blue-100 text-blue-700',
            'CANCELADO'  => 'bg-rose-100 text-rose-700',
        ];

        return view('admin.dispatches.edit', compact(
            'dispatch', 'warehouses', 'routes', 'drivers',
            'statusClasses', 'paymentTypes', 'cajasAbiertas'
        ));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, Dispatch $dispatch)
    {
        $data = $request->validate([
            'warehouse_id'      => ['nullable', 'exists:warehouses,id'],
            'shipping_route_id' => ['nullable', 'exists:shipping_routes,id'],
            'vehicle'           => ['nullable', 'string', 'max:50'],
            'fecha'             => ['required', 'date'],
            'notas'             => ['nullable', 'string'],
        ]);

        // Sincronizar chofer con la ruta
        if (!empty($data['shipping_route_id'])) {
            $route  = \App\Models\ShippingRoute::find($data['shipping_route_id']);
            $driver = \App\Models\Driver::firstOrCreate(
                ['nombre' => $route?->nombre ?? 'Sin nombre'],
                ['activo' => true]
            );
            $data['driver_id'] = $driver->id;
        }

        $dispatch->update($data);
        $this->log->log($dispatch, 'EDITADO', null, null, null, 'Datos generales actualizados');
        session()->flash('swal', ['icon' => 'success', 'title' => 'Actualizado', 'text' => 'Despacho actualizado.']);
        return back();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Dispatch $dispatch)
    {
        $this->log->log($dispatch, 'ELIMINADO', $dispatch->status, null);
        $dispatch->delete();
        session()->flash('swal', ['icon' => 'success', 'title' => 'Eliminado', 'text' => 'Despacho eliminado.']);
        return redirect()->route('admin.dispatches.index');
    }

    // ── Flujo de estados ──────────────────────────────────────────────────────

    /**
     * Pedidos asignados a este despacho que TODAVÍA no pasaron por Salida
     * de Producto (Panel de Surtido) — es decir, no tienen una línea de
     * despacho (qty_despachada no nula) por cada partida del pedido.
     *
     * Sin esta validación, un pedido se podía "Poner en ruta"/entregar
     * directo desde Despachos sin pasar por Surtido, llegando a ENTREGADO
     * sin haber descontado inventario nunca (bug real detectado: pedidos
     * #28 y #34 quedaron así).
     */
    private function pedidosSinSurtir(Dispatch $dispatch): \Illuminate\Support\Collection
    {
        $dispatch->loadMissing('items.salesOrder.items', 'items.lines');

        return $dispatch->items
            ->filter(function ($item) {
                $order = $item->salesOrder;
                if (!$order) return false;
                // Solo aplica a pedidos que TODAVÍA no pasan por Surtido
                // (PROCESADO). Llegar a DESPACHADO exige haber completado
                // saveDespacho() con cobertura total de items, así que un
                // pedido ya DESPACHADO nunca debe volver a marcarse "falta
                // surtir" — de lo contrario, un pedido reasignado a otro
                // despacho (que crea un DispatchItem nuevo y vacío) se ve
                // como pendiente aunque ya esté surtido de verdad.
                if ($order->status !== 'PROCESADO') return false;

                $totalItems     = $order->items->count();
                $itemsSurtidos  = $item->lines->whereNotNull('qty_despachada')->pluck('sales_order_item_id')->unique()->count();

                return $totalItems === 0 || $itemsSurtidos < $totalItems;
            })
            ->map(fn ($item) => $item->salesOrder->folio ?? ('#' . $item->salesOrder->id))
            ->values();
    }

    public function preparar(Dispatch $dispatch)
    {
        $pendientes = $this->pedidosSinSurtir($dispatch);
        if ($pendientes->isNotEmpty()) {
            return back()->with('swal', [
                'icon'  => 'warning',
                'title' => 'Faltan pedidos por surtir',
                'text'  => 'No se puede poner en ruta — primero completa Salida de Producto para: ' . $pendientes->implode(', '),
            ]);
        }

        // Salta directo a EN_RUTA
        $oldStatus = $dispatch->status;
        DB::transaction(function () use ($dispatch) {
            $dispatch->update(['status' => 'EN_RUTA', 'en_ruta_at' => now()]);

            $dispatch->load('items.salesOrder');
            foreach ($dispatch->items as $item) {
                $order = $item->salesOrder;
                if ($order && in_array($order->status, ['PROCESADO', 'DESPACHADO'])) {
                    $order->update([
                        'status'     => 'EN_RUTA',
                        'en_ruta_at' => now(),
                    ]);
                }
            }

            $dispatch->transferAssignments()
                ->whereIn('status', ['ASIGNADO', 'EN_RUTA'])
                ->update(['status' => 'EN_RUTA']);
        });

        $this->log->log($dispatch, 'CAMBIO_ESTADO', $oldStatus, 'EN_RUTA');
        return back()->with('swal', ['icon' => 'success', 'title' => 'En ruta', 'text' => 'Despacho salió en ruta.']);
    }

    public function cargar(Dispatch $dispatch)
    {
        $old = $dispatch->status;
        $dispatch->update(['status' => 'CARGADO']);
        $this->log->log($dispatch, 'CAMBIO_ESTADO', $old, 'CARGADO');
        return back()->with('swal', ['icon' => 'success', 'title' => 'Cargado', 'text' => 'Vehículo cargado.']);
    }

    public function enRuta(Dispatch $dispatch)
    {
        $pendientes = $this->pedidosSinSurtir($dispatch);
        if ($pendientes->isNotEmpty()) {
            return back()->with('swal', [
                'icon'  => 'warning',
                'title' => 'Faltan pedidos por surtir',
                'text'  => 'No se puede poner en ruta — primero completa Salida de Producto para: ' . $pendientes->implode(', '),
            ]);
        }

        DB::transaction(function () use ($dispatch) {
            $dispatch->update(['status' => 'EN_RUTA', 'en_ruta_at' => now()]);

            // Pedidos → EN_RUTA
            $dispatch->load('items.salesOrder');
            foreach ($dispatch->items as $item) {
                $order = $item->salesOrder;
                if ($order && in_array($order->status, ['PROCESADO', 'DESPACHADO'])) {
                    $order->update([
                        'status'     => 'EN_RUTA',
                        'en_ruta_at' => now(),
                        'driver_id'  => $dispatch->driver_id ?? $order->driver_id,
                    ]);
                }
            }

            // Traspasos → EN_RUTA
            StockTransfer::where('dispatch_id', $dispatch->id)
                ->where('status', 'ASIGNADO')
                ->update(['status' => 'EN_RUTA']);
        });

        return back()->with('swal', ['icon' => 'success', 'title' => 'En ruta', 'text' => 'Despacho, pedidos y traspasos enviados a ruta.']);
    }

    public function entregar(Dispatch $dispatch)
    {
        $old = $dispatch->status;
        $dispatch->update(['status' => 'ENTREGADO']);
        $this->log->log($dispatch, 'CAMBIO_ESTADO', $old, 'ENTREGADO');
        return back();
    }

    public function cancelar(Dispatch $dispatch)
    {
        $old = $dispatch->status;
        // Liberar traspasos asignados al cancelar el despacho
        StockTransfer::where('dispatch_id', $dispatch->id)
            ->whereIn('status', ['ASIGNADO', 'EN_RUTA'])
            ->update(['status' => 'PENDIENTE', 'dispatch_id' => null]);

        $dispatch->update(['status' => 'CANCELADO']);
        $this->log->log($dispatch, 'CAMBIO_ESTADO', $old, 'CANCELADO');
        return back()->with('swal', ['icon' => 'success', 'title' => 'Cancelado', 'text' => 'Despacho cancelado.']);
    }

    // ── Quitar asignaciones hechas por error (solo mientras PLANEADO) ─────────

    public function quitarPedido(Dispatch $dispatch, DispatchItem $item)
    {
        abort_unless($item->dispatch_id === $dispatch->id, 404);

        if ($dispatch->status !== 'PLANEADO') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo se puede quitar un pedido mientras el despacho está Planeado.']);
        }

        $item->load('lines');
        if ($item->lines->whereNotNull('qty_despachada')->isNotEmpty()) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Este pedido ya tiene productos surtidos — no se puede quitar del despacho así.']);
        }

        $folio = $item->salesOrder?->folio ?? ('#' . $item->sales_order_id);
        $item->lines()->delete();
        $item->delete();

        $this->log->log($dispatch, 'PEDIDO_QUITADO', null, null, null, "Pedido {$folio} quitado del despacho (asignado por error).");
        return back()->with('swal', ['icon' => 'success', 'title' => 'Quitado', 'text' => "Pedido {$folio} quitado del despacho."]);
    }

    public function quitarTraspaso(Dispatch $dispatch, DispatchTransferAssignment $assignment)
    {
        abort_unless($assignment->dispatch_id === $dispatch->id, 404);

        if ($dispatch->status !== 'PLANEADO') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo se puede quitar un traspaso mientras el despacho está Planeado.']);
        }

        $transfer = $assignment->stockTransfer;
        if ($transfer && $transfer->status === 'COMPLETADO') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Este traspaso ya se completó — no se puede quitar.']);
        }

        if ($transfer) {
            $transfer->update(['status' => 'PENDIENTE', 'dispatch_id' => null]);
        }
        $folio = $transfer?->folio ?? ('#' . $assignment->stock_transfer_id);
        $assignment->delete();

        $this->log->log($dispatch, 'TRASPASO_QUITADO', null, null, null, "Traspaso {$folio} quitado del despacho (asignado por error).");
        return back()->with('swal', ['icon' => 'success', 'title' => 'Quitado', 'text' => "Traspaso {$folio} quitado del despacho — regresó a Pendiente."]);
    }

    public function quitarCxc(Dispatch $dispatch, DispatchArAssignment $assignment)
    {
        abort_unless($assignment->dispatch_id === $dispatch->id, 404);

        if ($dispatch->status !== 'PLANEADO') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo se puede quitar una cuenta por cobrar mientras el despacho está Planeado.']);
        }

        if ((float) $assignment->monto_cobrado > 0) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Esta cuenta ya tiene un cobro registrado — no se puede quitar.']);
        }

        $cliente = $assignment->client?->nombre ?? ('#' . $assignment->client_id);
        $assignment->delete();

        $this->log->log($dispatch, 'CXC_QUITADA', null, null, null, "CxC de {$cliente} quitada del despacho (asignada por error).");
        return back()->with('swal', ['icon' => 'success', 'title' => 'Quitada', 'text' => "CxC de {$cliente} quitada del despacho."]);
    }

    // ── Pedidos individuales ──────────────────────────────────────────────────

    public function entregarPedido(Request $request, Dispatch $dispatch, DispatchItem $item)
    {
        $order = $item->salesOrder;
        if (!$order || $order->status !== 'EN_RUTA') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'El pedido no está EN_RUTA.']);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'ENTREGADO', 'entregado_at' => now()]);

            if ($order->payment_method === 'CREDITO' && $order->client_id) {
                $this->ar->charge(
                    clientId: $order->client_id,
                    monto:    $order->total,
                    desc:     "Entrega pedido {$order->folio}",
                    source:   $order,
                    fecha:    now()->toDateString(),
                );
            }
        });

        $this->log->log($dispatch, 'PEDIDO_ENTREGADO', null, null, null, "Pedido {$order->folio} entregado");
        return back()->with('swal', ['icon' => 'success', 'title' => 'Entregado', 'text' => "Pedido {$order->folio} entregado."]);
    }

    public function noEntregarPedido(Request $request, Dispatch $dispatch, DispatchItem $item)
    {
        $request->validate(['nota' => 'nullable|string|max:500']);
        $order = $item->salesOrder;

        if (!$order || $order->status !== 'EN_RUTA') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'El pedido no está EN_RUTA.']);
        }

        DB::transaction(function () use ($order, $request) {
            $order->load('items');
            foreach ($order->items as $it) {
                if (!$it->product_id || $it->cantidad <= 0) continue;
                $this->inv->stockIn(
                    productId:   (int) $it->product_id,
                    warehouseId: $order->warehouse_id,
                    qty:         (float) $it->cantidad,
                    motivo:      'DEVOLUCION_NO_ENTREGADO',
                    referencia:  $order,
                    userId:      auth()->id(),
                );
            }

            $data = ['status' => 'NO_ENTREGADO', 'no_entregado_at' => now()];
            if ($nota = $request->input('nota')) {
                $data['delivery_notes'] = trim(($order->delivery_notes ?? '') . "\n" . $nota);
            }
            $order->update($data);
            $order->increment('delivery_attempts');
        });

        $this->log->log($dispatch, 'PEDIDO_NO_ENTREGADO', null, null, null, "Pedido {$order->folio} no entregado, stock revertido");
        return back()->with('swal', ['icon' => 'success', 'title' => 'No entregado', 'text' => "Pedido {$order->folio} marcado y stock revertido."]);
    }

    // ── Traspasos individuales ────────────────────────────────────────────────

    public function completarTraspaso(
        Request $request,
        Dispatch $dispatch,
        DispatchTransferAssignment $assignment
    ) {
        $transfer = $assignment->stockTransfer;

        if (!$transfer || !in_array($transfer->status, ['ASIGNADO', 'EN_RUTA'])) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'Este traspaso no puede completarse.']);
        }

        $transfer->load('items');

        DB::transaction(function () use ($transfer, $assignment) {
            foreach ($transfer->items as $it) {
                // Salida del origen
                $this->inv->stockOut(
                    productId:   $it->product_id,
                    warehouseId: $transfer->from_warehouse_id,
                    qty:         $it->qty,
                    motivo:      'TRASPASO_SALIDA',
                    referencia:  $transfer,
                    userId:      auth()->id(),
                );
                // Entrada al destino
                $this->inv->stockIn(
                    productId:   $it->product_id,
                    warehouseId: $transfer->to_warehouse_id,
                    qty:         $it->qty,
                    motivo:      'TRASPASO_ENTRADA',
                    referencia:  $transfer,
                    userId:      auth()->id(),
                );
            }

            $transfer->update(['status' => 'COMPLETADO', 'completado_at' => now()]);
            $assignment->update(['status' => 'COMPLETADO']);
        });

        $this->log->log($dispatch, 'TRASPASO_COMPLETADO', null, null, null, "Traspaso {$transfer->folio} completado");
        return back()->with('swal', ['icon' => 'success', 'title' => 'Traspaso completado', 'text' => "Folio {$transfer->folio} transferido correctamente."]);
    }

    public function noCompletarTraspaso(
        Dispatch $dispatch,
        DispatchTransferAssignment $assignment
    ) {
        $transfer = $assignment->stockTransfer;
        if ($transfer) {
            $transfer->update(['status' => 'PENDIENTE', 'dispatch_id' => null]);
        }
        $assignment->update(['status' => 'NO_COMPLETADO']);

        return back()->with('swal', ['icon' => 'info', 'title' => 'Marcado', 'text' => 'Traspaso regresa a PENDIENTE para reasignarse.']);
    }

    // ── CxC asignadas ─────────────────────────────────────────────────────────
    // ── CxC asignadas ─────────────────────────────────────────────────────────

public function cobrarCxc(Request $request, Dispatch $dispatch, DispatchArAssignment $assignment)
{
    $request->validate([
        'monto'           => 'required|numeric|min:0.01',
        'payment_type_id' => 'required|exists:payment_types,id',
        'referencia'      => 'nullable|string|max:255',
        'order_ids'       => 'nullable|array',
        'order_ids.*'     => 'integer|exists:sales_orders,id',
    ]);

    DB::transaction(function () use ($assignment, $request, $dispatch) {
        $monto = (float) $request->monto;

        $this->ar->payment(
            clientId:      $assignment->client_id,
            amount:        $monto,
            paymentTypeId: (int) $request->payment_type_id,
            reference:     $request->referencia,
            notes:         "Cobro en ruta — despacho #{$dispatch->id}",
            fecha:         now()->toDateString(),
            driverId:      $dispatch->driver_id,
        );

        $nuevoCobrado  = round((float)$assignment->monto_cobrado + $monto, 2);
        $saldoAsignado = (float) $assignment->saldo_asignado;
        $nuevoStatus   = $nuevoCobrado >= $saldoAsignado ? 'COBRADO' : 'PARCIAL';

        $assignment->update([
            'monto_cobrado' => $nuevoCobrado,
            'status'        => $nuevoStatus,
        ]);

        // Si no se marcaron notas específicas, se aplica FIFO a las notas pendientes
        // del cliente — sin esto, el dinero quedaba trazado en dispatch_ar_assignments
        // pero sales_orders.saldo_pendiente nunca se actualizaba, y la nota seguía
        // apareciendo con el monto completo en Cuentas por cobrar.
        $ordenes = $request->filled('order_ids')
            ? SalesOrder::whereIn('id', $request->order_ids)->orderBy('fecha')->get()
            : SalesOrder::where('client_id', $assignment->client_id)
                ->where('payment_method', 'CREDITO')
                ->whereIn('status', ['ENTREGADO'])
                ->whereNull('cobrado_at')
                ->where(fn($q) => $q->whereNull('saldo_pendiente')->orWhere('saldo_pendiente', '>', 0))
                ->orderBy('fecha')
                ->get();

        $restante = $monto;

        foreach ($ordenes as $orden) {
            if ($restante <= 0) break;

            $saldo = ($orden->saldo_pendiente !== null && (float)$orden->saldo_pendiente > 0)
                ? (float) $orden->saldo_pendiente
                : (float) $orden->total;

            $abono      = min($restante, $saldo);
            $nuevoSaldo = round($saldo - $abono, 2);

            $updateData = ['saldo_pendiente' => $nuevoSaldo];
            if ($nuevoSaldo <= 0) {
                // Saldo en $0: la nota queda cobrada Y liquidada con el chofer
                // (para pedidos a crédito no hay un paso de "Cerrar cobranza"
                // separado como en efectivo/contraentrega — el cobro de la
                // CxC ES la liquidación).
                $updateData['cobrado_at']              = now();
                $updateData['driver_settlement_status'] = 'LIQUIDADO';
                $updateData['driver_settlement_at']     = now();
            }

            $orden->update($updateData);
            $restante = round($restante - $abono, 2);
        }
    });

    $this->log->log($dispatch, 'CXC_COBRADA', null, null, null, "Cobro $" . number_format($request->monto, 2) . " cliente #{$assignment->client_id}");
    return back()->with('swal', ['icon' => 'success', 'title' => 'CxC cobrada', 'text' => 'Abono registrado.']);
}

    public function noCobrarCxc(Request $request, Dispatch $dispatch, DispatchArAssignment $assignment)
    {
        $assignment->update(['status' => 'NO_COBRADO']);
        return back()->with('swal', ['icon' => 'info', 'title' => 'Marcado', 'text' => 'CxC marcada como no cobrada.']);
    }

    // ── Cierre ────────────────────────────────────────────────────────────────

    /**
     * Cierre parcial 1/2: traspasos + pedidos a CRÉDITO (no efectivo/contraentrega).
     * No toca dinero — es puramente logístico. Se puede hacer antes o después
     * del cierre de cobranza, en cualquier orden.
     */
    public function cerrarTraspasos(Dispatch $dispatch)
    {
        $error = $this->intentarCerrarTraspasos($dispatch);
        if ($error) {
            return back()->with('swal', $error);
        }

        return back()->with('swal', ['icon' => 'success', 'title' => 'Traspasos cerrados', 'text' => 'Traspasos y pedidos a crédito quedaron cerrados.']);
    }

    /**
     * Valida y cierra el lado de traspasos+crédito. Devuelve null si quedó
     * cerrado (o ya lo estaba), o un array ['icon','title','text'] con el
     * motivo del bloqueo si no se pudo cerrar.
     */
    private function intentarCerrarTraspasos(Dispatch $dispatch): ?array
    {
        if ($dispatch->traspasos_cerrado_at) {
            return null;
        }

        $dispatch->load('items.salesOrder', 'transferAssignments');

        $pedidosCreditoPendientes = $dispatch->items->filter(
            fn($i) => $i->salesOrder
                && !in_array($i->salesOrder->payment_method, ['EFECTIVO', 'CONTRAENTREGA'])
                && in_array($i->salesOrder->status, ['EN_RUTA', 'PROCESADO', 'DESPACHADO'])
        );

        if ($pedidosCreditoPendientes->count() > 0) {
            return [
                'icon'  => 'warning',
                'title' => 'Pedidos a crédito pendientes',
                'text'  => "Faltan {$pedidosCreditoPendientes->count()} pedido(s) a crédito por marcar.",
            ];
        }

        $traspasosPendientes = $dispatch->transferAssignments->whereNotIn('status', ['COMPLETADO', 'NO_COMPLETADO']);
        if ($traspasosPendientes->count() > 0) {
            return [
                'icon'  => 'warning',
                'title' => 'Traspasos pendientes',
                'text'  => "Faltan {$traspasosPendientes->count()} traspaso(s) por marcar.",
            ];
        }

        $dispatch->update(['traspasos_cerrado_at' => now()]);
        $this->log->log($dispatch, 'CIERRE_TRASPASOS', null, null, null, 'Traspasos y pedidos a crédito cerrados.');

        $this->intentarFinalizarDespacho($dispatch);

        return null;
    }

    /**
     * Cierre parcial 2/2: efectivo/contraentrega + CxC. Aquí se concilia el
     * dinero, se registra en caja y se liquida al chofer. Los pedidos de
     * efectivo/contraentrega se marcan entregados junto con el cobro, porque
     * para esos "entregar" y "cobrar" son el mismo momento.
     */
    public function cerrarCobranza(Request $request, Dispatch $dispatch)
    {
        $request->validate([
            'monto_entregado' => 'required|numeric|min:0',
            'payment_type_id' => 'nullable|exists:payment_types,id',
            'pos_register_id' => 'nullable|exists:cash_registers,id',
            'referencia'      => 'nullable|string|max:255',
            'notas_cierre'    => 'nullable|string|max:500',
        ]);

        $error = $this->intentarCerrarCobranza($dispatch, $request);
        if ($error) {
            return back()->with('swal', $error);
        }

        return back()->with('swal', ['icon' => 'success', 'title' => 'Cobranza cerrada', 'text' => 'Efectivo y CxC quedaron conciliados.']);
    }

    /**
     * Valida y cierra el lado de cobranza (efectivo/contraentrega + CxC).
     * Devuelve null si quedó cerrado (o ya lo estaba), o un array
     * ['icon','title','text'] con el motivo del bloqueo si no se pudo cerrar.
     */
    private function intentarCerrarCobranza(Dispatch $dispatch, Request $request): ?array
    {
        if ($dispatch->cobranza_cerrado_at) {
            return null;
        }

        $dispatch->load('items.salesOrder', 'arAssignments');

        $pedidosEfectivoPendientes = $dispatch->items->filter(
            fn($i) => $i->salesOrder
                && in_array($i->salesOrder->payment_method, ['EFECTIVO', 'CONTRAENTREGA'])
                && in_array($i->salesOrder->status, ['EN_RUTA', 'PROCESADO', 'DESPACHADO'])
        );

        if ($pedidosEfectivoPendientes->count() > 0) {
            return [
                'icon'  => 'warning',
                'title' => 'Pedidos de efectivo pendientes',
                'text'  => "Faltan {$pedidosEfectivoPendientes->count()} pedido(s) de efectivo/contraentrega por marcar.",
            ];
        }

        // Solo PENDIENTE (sin ningún abono) bloquea el cierre. PARCIAL ya se
        // resolvió — se registró lo cobrado ese día; el saldo restante sigue
        // como CxC normal y no detiene la liquidación del chofer.
        $cxcSinResolver = $dispatch->arAssignments->where('status', 'PENDIENTE');
        if ($cxcSinResolver->count() > 0) {
            return [
                'icon'  => 'warning',
                'title' => 'CxC sin resolver',
                'text'  => "Faltan {$cxcSinResolver->count()} cuenta(s) por cobrar sin ni siquiera un abono — cóbralas o márcalas como no cobradas.",
            ];
        }

        DB::transaction(function () use ($dispatch, $request) {
            $monto = (float) $request->monto_entregado;

            if ($request->pos_register_id && $monto > 0) {
                $register = CashRegister::find($request->pos_register_id);
                if ($register && $register->estatus === 'ABIERTO') {
                    $this->cash->addMovement(
                        $register,
                        'INGRESO',
                        $monto,
                        "Liquidación despacho #{$dispatch->id} — {$dispatch->driver?->nombre}",
                        $dispatch
                    );
                }
            }

            $dispatch->update([
                'cobranza_cerrado_at' => now(),
                'monto_liquidado'     => $monto,
                'notas_cierre'        => $request->notas_cierre,
            ]);

            // El cobro en efectivo/contraentrega del despacho ya se concilió (diferencia $0.00
            // validada en la vista antes de cerrar): marcar cada pedido como liquidado con el chofer.
            // Los pedidos a CRÉDITO no pasan por aquí — se liquidan por CxC.
            foreach ($dispatch->items as $item) {
                $order = $item->salesOrder;
                if (!$order) continue;
                if ($order->status !== 'ENTREGADO') continue;
                if (!in_array($order->payment_method, ['EFECTIVO', 'CONTRAENTREGA'])) continue;
                if ($order->driver_settlement_status === 'LIQUIDADO') continue;

                $order->update([
                    'cobrado_efectivo'         => $order->total,
                    'driver_settlement_status' => 'LIQUIDADO',
                    'driver_settlement_at'     => now(),
                    'cobrado_confirmado_at'    => now(),
                    'cobrado_confirmado_por'   => auth()->id(),
                ]);
            }
        });

        $this->log->log($dispatch, 'CIERRE_COBRANZA', null, null, null, 'Monto liquidado: $' . number_format((float)$request->monto_entregado, 2));

        $this->intentarFinalizarDespacho($dispatch);

        return null;
    }

    /**
     * Cierre completo en un solo clic: hace los dos cierres parciales
     * (traspasos+crédito y cobranza) de una vez, usando los mismos datos
     * del formulario de cobranza. Solo se muestra en la vista cuando ambos
     * lados ya están listos para cerrarse, pero igual se revalida aquí.
     */
    public function cerrarCompleto(Request $request, Dispatch $dispatch)
    {
        $request->validate([
            'monto_entregado' => 'required|numeric|min:0',
            'payment_type_id' => 'nullable|exists:payment_types,id',
            'pos_register_id' => 'nullable|exists:cash_registers,id',
            'referencia'      => 'nullable|string|max:255',
            'notas_cierre'    => 'nullable|string|max:500',
        ]);

        $errorTraspasos = $this->intentarCerrarTraspasos($dispatch);
        if ($errorTraspasos) {
            return back()->with('swal', $errorTraspasos);
        }

        $errorCobranza = $this->intentarCerrarCobranza($dispatch, $request);
        if ($errorCobranza) {
            return back()->with('swal', $errorCobranza);
        }

        return back()->with('swal', ['icon' => 'success', 'title' => 'Despacho cerrado', 'text' => 'Traspasos y cobranza quedaron cerrados por completo.']);
    }

    /**
     * Cuando AMBOS cierres parciales están hechos (sin importar el orden),
     * el despacho pasa a CERRADO automáticamente.
     */
    private function intentarFinalizarDespacho(Dispatch $dispatch): void
    {
        $dispatch->refresh();

        if ($dispatch->traspasos_cerrado_at && $dispatch->cobranza_cerrado_at && $dispatch->status !== 'CERRADO') {
            $old = $dispatch->status;
            $dispatch->update(['status' => 'CERRADO', 'cerrado_at' => now()]);
            $this->log->log($dispatch, 'CAMBIO_ESTADO', $old, 'CERRADO', null, 'Cierre completo: traspasos + cobranza.');
        }
    }

    // ── Impresión ─────────────────────────────────────────────────────────────

   public function printRuta(Dispatch $dispatch)
{
    $dispatch->load([
        'items.salesOrder.client',
        'items.salesOrder.items.product',   // ← agregar .product
        'arAssignments.client',
        'transferAssignments.stockTransfer.fromWarehouse',
        'transferAssignments.stockTransfer.toWarehouse',
        'transferAssignments.stockTransfer.items.product',
        'driver', 'warehouse', 'route',
    ]);
    return view('admin.dispatches.print.ruta', compact('dispatch'));
}

  public function printLiquidacion(Dispatch $dispatch)
{
    $dispatch->load([
        'items.salesOrder.client',
        'items.salesOrder.items.product',   // ← agregar .product
        'arAssignments.client',
        'transferAssignments.stockTransfer',
        'driver', 'warehouse',
    ]);
    return view('admin.dispatches.print.liquidacion', compact('dispatch'));
}
public function bulkTraspasos(Request $request, Dispatch $dispatch)
{
    $request->validate([
        'accion' => ['required', 'in:completar,no-completar'],
        'ids'    => ['required', 'array', 'min:1'],
        'ids.*'  => ['integer'],
    ]);
 
    $assignments = DispatchTransferAssignment::whereIn('id', $request->ids)
        ->where('dispatch_id', $dispatch->id)
        ->where('status', 'PENDIENTE')
        ->get();
 
    if ($request->accion === 'completar') {
        DB::transaction(function () use ($assignments) {
            foreach ($assignments as $assignment) {
                $transfer = $assignment->stockTransfer;
                if (!$transfer) continue;
                $transfer->load('items');
                foreach ($transfer->items as $it) {
                    $this->inv->stockOut(
                        productId:   $it->product_id,
                        warehouseId: $transfer->from_warehouse_id,
                        qty:         $it->qty,
                        motivo:      'TRASPASO_SALIDA',
                        referencia:  $transfer,
                        userId:      auth()->id(),
                    );
                    $this->inv->stockIn(
                        productId:   $it->product_id,
                        warehouseId: $transfer->to_warehouse_id,
                        qty:         $it->qty,
                        motivo:      'TRASPASO_ENTRADA',
                        referencia:  $transfer,
                        userId:      auth()->id(),
                    );
                }
                $transfer->update(['status' => 'COMPLETADO', 'completado_at' => now()]);
                $assignment->update(['status' => 'COMPLETADO']);
            }
        });
        $msg = "Se completaron {$assignments->count()} traspaso(s).";
    } else {
        DB::transaction(function () use ($assignments) {
            foreach ($assignments as $assignment) {
                $assignment->stockTransfer?->update(['status' => 'PENDIENTE', 'dispatch_id' => null]);
                $assignment->update(['status' => 'NO_COMPLETADO']);
            }
        });
        $msg = "Se marcaron {$assignments->count()} traspaso(s) como no completados.";
    }
 
    return back()->with('swal', ['icon' => 'success', 'title' => 'Listo', 'text' => $msg]);
}
 
// ── Bulk pedidos ──────────────────────────────────────────────────────────────
public function bulkPedidos(Request $request, Dispatch $dispatch)
{
    $request->validate([
        'accion' => ['required', 'in:entregar,no-entregar'],
        'ids'    => ['required', 'array', 'min:1'],
        'ids.*'  => ['integer'],
    ]);
 
    // ids son IDs de DispatchItem
    $items = DispatchItem::whereIn('id', $request->ids)
        ->where('dispatch_id', $dispatch->id)
        ->with('salesOrder.items')
        ->get();
 
    if ($request->accion === 'entregar') {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $order = $item->salesOrder;
                if (!$order || $order->status !== 'EN_RUTA') continue;
 
                $order->update(['status' => 'ENTREGADO', 'entregado_at' => now()]);
 
                if ($order->payment_method === 'CREDITO' && $order->client_id) {
                    $this->ar->charge(
                        clientId: $order->client_id,
                        monto:    $order->total,
                        desc:     "Entrega pedido {$order->folio}",
                        source:   $order,
                        fecha:    now()->toDateString(),
                    );
                }
            }
        });
        $msg = "Se entregaron {$items->count()} pedido(s).";
    } else {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $order = $item->salesOrder;
                if (!$order || $order->status !== 'EN_RUTA') continue;
 
                foreach ($order->items as $it) {
                    if (!$it->product_id || $it->cantidad <= 0) continue;
                    $this->inv->stockIn(
                        productId:   (int) $it->product_id,
                        warehouseId: $order->warehouse_id,
                        qty:         (float) $it->cantidad,
                        motivo:      'DEVOLUCION_NO_ENTREGADO',
                        referencia:  $order,
                        userId:      auth()->id(),
                    );
                }
                $order->update(['status' => 'NO_ENTREGADO', 'no_entregado_at' => now()]);
                $order->increment('delivery_attempts');
            }
        });
        $msg = "Se marcaron {$items->count()} pedido(s) como no entregados.";
    }
 
    return back()->with('swal', ['icon' => 'success', 'title' => 'Listo', 'text' => $msg]);
}
 
// ── Bulk CxC ──────────────────────────────────────────────────────────────────
public function bulkCxc(Request $request, Dispatch $dispatch)
{
    $request->validate([
        'accion'          => ['required', 'in:cobrar,no-cobrar'],
        'ids'             => ['required', 'array', 'min:1'],
        'ids.*'           => ['integer'],
        'payment_type_id' => ['required_if:accion,cobrar', 'nullable', 'exists:payment_types,id'],
    ]);
 
    $assignments = DispatchArAssignment::whereIn('id', $request->ids)
        ->where('dispatch_id', $dispatch->id)
        ->where('status', 'PENDIENTE')
        ->get();
 
    if ($request->accion === 'cobrar') {
        DB::transaction(function () use ($assignments, $request, $dispatch) {
            foreach ($assignments as $assignment) {
                $monto = (float) $assignment->saldo_asignado;
                if ($monto <= 0) continue;
 
                $this->ar->payment(
                    clientId:      $assignment->client_id,
                    amount:        $monto,
                    paymentTypeId: (int) $request->payment_type_id,
                    reference:     null,
                    notes:         "Cobro masivo en ruta — despacho #{$dispatch->id}",
                    fecha:         now()->toDateString(),
                    driverId:      $dispatch->driver_id,
                );
 
                $assignment->update([
                    'monto_cobrado' => round($assignment->monto_cobrado + $monto, 2),
                    'status'        => 'COBRADO',
                ]);
            }
        });
        $msg = "Se cobraron {$assignments->count()} CxC.";
    } else {
        $assignments->each(fn($a) => $a->update(['status' => 'NO_COBRADO']));
        $msg = "Se marcaron {$assignments->count()} CxC como no cobradas.";
    }
 
    return back()->with('swal', ['icon' => 'success', 'title' => 'Listo', 'text' => $msg]);
}
}