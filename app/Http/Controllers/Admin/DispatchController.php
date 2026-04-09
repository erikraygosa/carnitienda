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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class DispatchController extends Controller
{
    public function __construct(
        private ArService        $ar,
        private InventoryService $inv,
        private CashService      $cash,
    ) {}

    public function index()
    {
        $dispatches = \App\Models\Dispatch::with(['driver', 'route', 'warehouse'])
            ->withCount([
                'items',
                'items as items_entregados'    => fn($q) => $q->where('status', 'ENTREGADO'),
                'items as items_no_entregados' => fn($q) => $q->where('status', 'NO_ENTREGADO'),
            ])
            ->orderBy('fecha', 'desc')
            ->get();

        return view('admin.dispatches.index', compact('dispatches'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(Request $req)
    {
        $warehouses = Warehouse::orderBy('nombre')->get(['id', 'nombre']);
        $routes     = ShippingRoute::orderBy('nombre')->get(['id', 'nombre']);
        $drivers    = Driver::orderBy('nombre')->get(['id', 'nombre']);

        // Pedidos PROCESADOS listos para salir
       $orders = SalesOrder::whereIn('status', ['PROCESADO'])
    ->with(['client:id,nombre', 'route:id,nombre'])  // ← agregar route
    ->latest()
    ->limit(200)
    ->get(['id','folio','client_id','shipping_route_id','status','total','programado_para','payment_method']);
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
            'warehouse_id'      => ['nullable', 'exists:warehouses,id'],
            'shipping_route_id' => ['nullable', 'exists:shipping_routes,id'],
            'driver_id'         => ['nullable', 'exists:drivers,id'],
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
            $dispatch = Dispatch::create([
                'warehouse_id'      => $data['warehouse_id']      ?? null,
                'shipping_route_id' => $data['shipping_route_id'] ?? null,
                'driver_id'         => $data['driver_id']         ?? null,
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
                    DispatchItem::create([
                        'dispatch_id'    => $dispatch->id,
                        'sales_order_id' => $o->id,
                        'referencia'     => $o->folio,
                        'status'         => 'ASIGNADO',
                    ]);
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
        $paymentTypes  = PaymentType::orderBy('descripcion')->get(['id', 'descripcion']);
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
            'driver_id'         => ['nullable', 'exists:drivers,id'],
            'vehicle'           => ['nullable', 'string', 'max:50'],
            'fecha'             => ['required', 'date'],
            'notas'             => ['nullable', 'string'],
        ]);

        $dispatch->update($data);
        session()->flash('swal', ['icon' => 'success', 'title' => 'Actualizado', 'text' => 'Despacho actualizado.']);
        return back();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Dispatch $dispatch)
    {
        $dispatch->delete();
        session()->flash('swal', ['icon' => 'success', 'title' => 'Eliminado', 'text' => 'Despacho eliminado.']);
        return redirect()->route('admin.dispatches.index');
    }

    // ── Flujo de estados ──────────────────────────────────────────────────────

    public function preparar(Dispatch $dispatch)
    {
        $dispatch->update(['status' => 'PREPARANDO']);
        return back()->with('swal', ['icon' => 'success', 'title' => 'Preparando', 'text' => 'Despacho en preparación.']);
    }

    public function cargar(Dispatch $dispatch)
    {
        $dispatch->update(['status' => 'CARGADO']);
        return back()->with('swal', ['icon' => 'success', 'title' => 'Cargado', 'text' => 'Vehículo cargado.']);
    }

    public function enRuta(Dispatch $dispatch)
    {
        DB::transaction(function () use ($dispatch) {
            $dispatch->update(['status' => 'EN_RUTA', 'en_ruta_at' => now()]);

            // Pedidos → EN_RUTA
            $dispatch->load('items.salesOrder');
            foreach ($dispatch->items as $item) {
                $order = $item->salesOrder;
                if ($order && $order->status === 'PROCESADO') {
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
        $dispatch->update(['status' => 'ENTREGADO']);
        return back();
    }

    public function cancelar(Dispatch $dispatch)
    {
        // Liberar traspasos asignados al cancelar el despacho
        StockTransfer::where('dispatch_id', $dispatch->id)
            ->whereIn('status', ['ASIGNADO', 'EN_RUTA'])
            ->update(['status' => 'PENDIENTE', 'dispatch_id' => null]);

        $dispatch->update(['status' => 'CANCELADO']);
        return back()->with('swal', ['icon' => 'success', 'title' => 'Cancelado', 'text' => 'Despacho cancelado.']);
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

    public function cobrarCxc(Request $request, Dispatch $dispatch, DispatchArAssignment $assignment)
    {
        $request->validate([
            'monto'           => 'required|numeric|min:0.01',
            'payment_type_id' => 'required|exists:payment_types,id',
            'referencia'      => 'nullable|string|max:255',
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

            $assignment->update([
                'monto_cobrado' => round($assignment->monto_cobrado + $monto, 2),
                'status'        => 'COBRADO',
            ]);
        });

        return back()->with('swal', ['icon' => 'success', 'title' => 'CxC cobrada', 'text' => 'Abono registrado.']);
    }

    public function noCobrarCxc(Request $request, Dispatch $dispatch, DispatchArAssignment $assignment)
    {
        $assignment->update(['status' => 'NO_COBRADO']);
        return back()->with('swal', ['icon' => 'info', 'title' => 'Marcado', 'text' => 'CxC marcada como no cobrada.']);
    }

    // ── Cierre ────────────────────────────────────────────────────────────────

    public function cerrar(Request $request, Dispatch $dispatch)
    {
        $request->validate([
            'monto_entregado' => 'required|numeric|min:0',
            'payment_type_id' => 'required|exists:payment_types,id',
            'pos_register_id' => 'nullable|exists:cash_registers,id',
            'referencia'      => 'nullable|string|max:255',
            'notas_cierre'    => 'nullable|string|max:500',
        ]);

        $dispatch->load('items.salesOrder');
        $pendientes = $dispatch->items->filter(
            fn($i) => $i->salesOrder && in_array($i->salesOrder->status, ['EN_RUTA', 'PROCESADO'])
        );

        if ($pendientes->count() > 0) {
            return back()->with('swal', [
                'icon'  => 'warning',
                'title' => 'Pedidos pendientes',
                'text'  => "Faltan {$pendientes->count()} pedido(s) por marcar.",
            ]);
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
                'status'          => 'CERRADO',
                'cerrado_at'      => now(),
                'monto_liquidado' => $monto,
                'notas_cierre'    => $request->notas_cierre,
            ]);
        });

        return back()->with('swal', ['icon' => 'success', 'title' => 'Despacho cerrado', 'text' => 'Liquidación completada.']);
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