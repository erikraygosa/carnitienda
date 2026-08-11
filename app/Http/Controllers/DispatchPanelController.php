<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DispatchItem;
use App\Models\DispatchItemLine;
use App\Models\DocumentActivityLog;
use App\Models\ShippingRoute;
use App\Services\InventoryService;

class DispatchPanelController extends Controller
{
    use AuthorizesRequests;

    // ── 1. Lista pedidos PROCESADOS ──────────────────────────────────
    public function index(Request $request)
    {
        $this->authorize('salida de producto');

        $rutas     = ShippingRoute::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $rutaId    = $request->get('ruta_id');

        $pedidos = SalesOrder::with(['client', 'items.product'])
            ->where('status', SalesOrder::S_PROCESADO)
            ->when($rutaId, fn($q) => $q->where('shipping_route_id', $rutaId))
            ->orderByDesc('fecha')
            ->paginate(50)
            ->withQueryString();

        // sales_order_item_id de líneas ya guardadas (qty_despachada != null y > 0),
        // para poder marcarlas de otro color en la lista sin abrir el panel.
        $itemsDespachadosIds = DispatchItemLine::whereHas('dispatchItem', function ($q) use ($pedidos) {
                $q->whereIn('sales_order_id', $pedidos->pluck('id'));
            })
            ->whereNotNull('qty_despachada')
            ->where('qty_despachada', '>', 0)
            ->pluck('sales_order_item_id')
            ->flip();

        return view('admin.dispatch_panel.index', compact('pedidos', 'rutas', 'rutaId', 'itemsDespachadosIds'));
    }

    // ── Polling: conteo de pedidos PROCESADOS para notificaciones ───
    public function pollCount()
    {
        $this->authorize('salida de producto');
        return response()->json([
            'count' => SalesOrder::where('status', SalesOrder::S_PROCESADO)->count(),
        ]);
    }

    // ── Impresión de todos los pedidos pendientes ────────────────────
    public function printPendientes(Request $request)
    {
        $this->authorize('salida de producto');

        $rutaId = $request->get('ruta_id');

        $pedidos = SalesOrder::with(['client', 'items.product'])
            ->where('status', SalesOrder::S_PROCESADO)
            ->when($rutaId, fn($q) => $q->where('shipping_route_id', $rutaId))
            ->orderBy('fecha')
            ->get();

        $empresa = app(\App\Services\CompanyService::class)->activa();

        return view('admin.dispatch_panel.print', compact('pedidos', 'empresa'));
    }

    // ── 2. Carga líneas de un pedido para el panel lateral ───────────
    public function show(SalesOrder $order)
    {
        $this->authorize('salida de producto');

        $order->load(['items.product', 'client']);

        $dispatchItem = DispatchItem::with('lines')
            ->where('sales_order_id', $order->id)
            ->latest()
            ->first();

        $lines = $order->items->map(function ($item) use ($dispatchItem) {
            $line = $dispatchItem?->lines
                ->firstWhere('sales_order_item_id', $item->id);

            return [
                'sales_order_item_id' => $item->id,
                'producto'            => $item->product?->nombre ?? $item->descripcion,
                'unidad'              => $item->product?->unidad,
                'qty_solicitada'      => (float) $item->cantidad,
                'num_cajas'           => $item->num_cajas,
                'qty_despachada'      => $line ? (float) $line->qty_despachada : null,
                'nota'                => $line?->nota,
                'precio'              => (float) $item->precio,
                'total'               => (float) $item->total,
            ];
        });

        return response()->json([
            'order' => [
                'id'     => $order->id,
                'folio'  => $order->folio,
                'client' => $order->client?->nombre,
                'status' => $order->status,
                'total'  => $order->total,
            ],
            'lines' => $lines,
        ]);
    }

    // ── 2b. Guarda UNA sola línea, para ir avanzando producto por producto ──
    //       No toca inventario ni el estatus del pedido — solo deja registrado
    //       el avance para retomarlo después (show() ya lo recupera).
    public function saveLine(Request $request, SalesOrder $order, SalesOrderItem $item)
    {
        $this->authorize('salida de producto');

        abort_if($item->sales_order_id !== $order->id, 404);

        // 'sin_existencia' es la única puerta para guardar en 0 — el botón
        // "Guardar" normal siempre exige > 0. Así una línea en 0 nunca es un
        // descuido, siempre fue una decisión explícita (con nota).
        $sinExistencia = $request->boolean('sin_existencia');

        $request->validate([
            'qty_despachada' => ['required', 'numeric', $sinExistencia ? 'min:0' : 'min:0.001'],
            'num_cajas'      => ['nullable', 'integer', 'min:0'],
            'nota'           => ['nullable', 'string', 'max:255'],
        ], [
            'qty_despachada.required' => 'Captura la cantidad despachada.',
            'qty_despachada.min'      => 'La cantidad despachada no puede ser 0 — si el producto no está disponible, usa el botón "Sin existencia".',
        ]);

        // Una línea ya surtida con producto real no se puede volver a tocar
        // desde aquí (evita que se reduzca/borre después de haber salido).
        $existente = DispatchItemLine::whereHas('dispatchItem', fn ($q) => $q->where('sales_order_id', $order->id))
            ->where('sales_order_item_id', $item->id)
            ->first();
        if ($existente && (float) $existente->qty_despachada > 0) {
            return response()->json(['ok' => false, 'message' => 'Este producto ya se surtió, no se puede modificar.'], 422);
        }

        $dispatchItem = DispatchItem::firstOrCreate(
            ['sales_order_id' => $order->id],
            [
                'dispatch_id' => $this->getOrCreateDispatch(),
                'referencia'  => $order->folio,
                'status'      => 'ASIGNADO',
            ]
        );

        DispatchItemLine::updateOrCreate(
            ['dispatch_item_id' => $dispatchItem->id, 'sales_order_item_id' => $item->id],
            [
                'qty_solicitada' => (float) $item->cantidad,
                'qty_despachada' => (float) $request->qty_despachada,
                'nota'           => $request->nota,
            ]
        );

        if ($request->has('num_cajas')) {
            $item->update([
                'num_cajas' => $request->num_cajas !== null && $request->num_cajas !== ''
                    ? (int) $request->num_cajas
                    : null,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    // ── 3. Completa el despacho: exige que TODOS los productos ya se hayan
    //      guardado (con cantidad > 0), descuenta inventario y recalcula ────
    public function saveDespacho(Request $request, SalesOrder $order, InventoryService $inv)
    {
        $this->authorize('salida de producto');

        $request->validate([
            'lines'                       => 'required|array|min:1',
            'lines.*.sales_order_item_id' => 'required|integer|exists:sales_order_items,id',
            'lines.*.qty_despachada'      => ['required', 'numeric', 'min:0'],
            'lines.*.num_cajas'           => 'nullable|integer|min:0',
            'lines.*.nota'                => 'nullable|string|max:255',
        ]);

        // Un 0 solo es válido si ya pasó por el botón "Sin existencia" (que
        // deja guardada la línea en 0 vía saveLine) — así nadie completa el
        // despacho con cantidad 0 sin haberlo confirmado explícitamente antes.
        $lineasEnCero = collect($request->lines)->filter(fn ($l) => (float) $l['qty_despachada'] === 0.0);
        if ($lineasEnCero->isNotEmpty()) {
            $idsConfirmadosEnCero = DispatchItemLine::whereHas('dispatchItem', fn ($q) => $q->where('sales_order_id', $order->id))
                ->where('qty_despachada', 0)
                ->pluck('sales_order_item_id');

            $noConfirmadas = $lineasEnCero->pluck('sales_order_item_id')->diff($idsConfirmadosEnCero);
            if ($noConfirmadas->isNotEmpty()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Hay productos con cantidad 0 sin confirmar — usa el botón "Sin existencia" en esa línea antes de completar.',
                ], 422);
            }
        }

        $totalItemsPedido = $order->items()->count();
        if (count($request->lines) < $totalItemsPedido) {
            return response()->json([
                'ok'      => false,
                'message' => 'Faltan productos por despachar — no se puede completar hasta que todos estén marcados.',
            ], 422);
        }

        DB::transaction(function () use ($request, $order, $inv) {

            $dispatchItem = DispatchItem::firstOrCreate(
                ['sales_order_id' => $order->id],
                [
                    'dispatch_id' => $this->getOrCreateDispatch(),
                    'referencia'  => $order->folio,
                    'status'      => 'ASIGNADO',
                ]
            );

            $nuevoSubtotal  = 0;
            $nuevoDescuento = 0;
            $nuevoImpuestos = 0;
            $nuevoTotal     = 0;

            foreach ($request->lines as $lineData) {
                $orderItem     = SalesOrderItem::findOrFail($lineData['sales_order_item_id']);
                $qtyReal       = (float) $lineData['qty_despachada'];
                $qtySolicitada = (float) $orderItem->cantidad;

                // 1. Guardar línea de despacho
                DispatchItemLine::updateOrCreate(
                    [
                        'dispatch_item_id'    => $dispatchItem->id,
                        'sales_order_item_id' => $orderItem->id,
                    ],
                    [
                        'qty_solicitada' => $qtySolicitada,
                        'qty_despachada' => $qtyReal,
                        'nota'           => $lineData['nota'] ?? null,
                    ]
                );

                // 2. Descontar inventario con qty REAL
                if ($qtyReal > 0) {
                    $itemReal = (object)[
                        'product_id' => $orderItem->product_id,
                        'cantidad'   => $qtyReal,
                    ];
                    $inv->consumeForOrderItem(
                        $itemReal,
                        $order->warehouse_id,
                        $order,
                        auth()->id()
                    );
                }

                // 3. Recalcular línea proporcionalmente a qty real
                $ratioQty      = $qtySolicitada > 0 ? ($qtyReal / $qtySolicitada) : 0;
                $lineSubtotal  = round($qtyReal * (float) $orderItem->precio, 2);
                $lineDescuento = round((float) $orderItem->descuento * $ratioQty, 2);
                $lineImpuesto  = round((float) $orderItem->impuesto  * $ratioQty, 2);
                $lineTotal     = round(max($lineSubtotal - $lineDescuento, 0) + $lineImpuesto, 2);

                // 4. Actualizar la línea del pedido con valores reales
                $updateData = [
                    'cantidad'  => $qtyReal,
                    'descuento' => $lineDescuento,
                    'impuesto'  => $lineImpuesto,
                    'total'     => $lineTotal,
                ];
                if (array_key_exists('num_cajas', $lineData)) {
                    $updateData['num_cajas'] = $lineData['num_cajas'] !== null ? (int) $lineData['num_cajas'] : null;
                }
                $orderItem->update($updateData);

                $nuevoSubtotal  += $lineSubtotal;
                $nuevoDescuento += $lineDescuento;
                $nuevoImpuestos += $lineImpuesto;
                $nuevoTotal     += $lineTotal;
            }

            // 5. Recalcular totales del pedido
            $order->update([
                'subtotal'            => round($nuevoSubtotal, 2),
                'descuento'           => round($nuevoDescuento, 2),
                'impuestos'           => round($nuevoImpuestos, 2),
                'total'               => round($nuevoTotal, 2),
                'contraentrega_total' => $order->payment_method === 'CONTRAENTREGA'
                                            ? round($nuevoTotal, 2)
                                            : $order->contraentrega_total,
                'status'              => SalesOrder::S_DESPACHADO,
                'despachado_at'       => now(),
            ]);

            // 6. Log
            DocumentActivityLog::create([
                'document_type' => SalesOrder::class,
                'document_id'   => $order->id,
                'action'        => 'salida_de_producto',
                'old_status'    => SalesOrder::S_PROCESADO,
                'new_status'    => SalesOrder::S_DESPACHADO,
                'user_id'       => auth()->id(),
                'nota'          => 'Salida de producto registrada. Total real: $' . number_format($nuevoTotal, 2),
            ]);
        });

        return response()->json(['ok' => true, 'message' => 'Salida de producto guardada correctamente.']);
    }

    // ── Helper: obtiene o crea un dispatch del día ───────────────────
    private function getOrCreateDispatch(): int
    {
        $dispatch = \App\Models\Dispatch::whereDate('fecha', today())
            ->where('status', 'PLANEADO')
            ->first();

        if (!$dispatch) {
            $dispatch = \App\Models\Dispatch::create([
                'folio'  => 'AUTO-' . now()->format('Ymd'),
                'fecha'  => now(),
                'status' => 'PLANEADO',
            ]);
        }

        return $dispatch->id;
    }
}