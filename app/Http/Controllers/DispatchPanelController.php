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
use App\Models\SystemSetting;
use App\Services\InventoryService;
use App\Services\ZplLabelService;

class DispatchPanelController extends Controller
{
    use AuthorizesRequests;

    // ── 1. Lista pedidos PROCESADOS ──────────────────────────────────
    public function index(Request $request)
    {
        $this->authorize('salida de producto');

        $rutas     = ShippingRoute::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $rutaId    = $request->get('ruta_id');
        $ronda     = $request->get('ronda'); // '' (ambas) | 1 | 2
        $fecha     = $request->get('fecha'); // '' (todas) | Y-m-d

        // Filtra por la misma fecha que se muestra en la tabla (programada
        // de entrega; si el pedido no tiene una, se usa la de captura).
        $pedidos = SalesOrder::with(['client', 'items.product'])
            ->where('status', SalesOrder::S_PROCESADO)
            ->when($rutaId, fn($q) => $q->where('shipping_route_id', $rutaId))
            ->when($ronda,  fn($q) => $q->where('ronda', $ronda))
            ->when($fecha,  fn($q) => $q->where(fn($q2) => $q2
                ->whereDate('programado_para', $fecha)
                ->orWhere(fn($q3) => $q3->whereNull('programado_para')->whereDate('fecha', $fecha))
            ))
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

        // Config de impresión de etiquetas (SuperAdmin → Configuración →
        // Etiquetas de surtido) — el JS del panel la usa para decidir si
        // muestra los campos de peso por caja y el botón "Imprimir etiqueta".
        $impresionZplActiva = SystemSetting::get('etiquetas.modo_impresion', 'ticket') === 'zpl';
        $imprimirPorCajas   = (bool) SystemSetting::get('etiquetas.imprimir_por_cajas', false);

        return view('admin.dispatch_panel.index', compact('pedidos', 'rutas', 'rutaId', 'ronda', 'fecha', 'itemsDespachadosIds', 'impresionZplActiva', 'imprimirPorCajas'));
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
        $ronda  = $request->get('ronda');
        $fecha  = $request->get('fecha');

        $pedidos = SalesOrder::with(['client', 'items.product'])
            ->where('status', SalesOrder::S_PROCESADO)
            ->when($rutaId, fn($q) => $q->where('shipping_route_id', $rutaId))
            ->when($ronda,  fn($q) => $q->where('ronda', $ronda))
            ->when($fecha,  fn($q) => $q->where(fn($q2) => $q2
                ->whereDate('programado_para', $fecha)
                ->orWhere(fn($q3) => $q3->whereNull('programado_para')->whereDate('fecha', $fecha))
            ))
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
                // Preferimos la descripción capturada en el pedido (ahí es
                // donde se agregan detalles/comentarios, ej. "1 piezas") por
                // encima del nombre pelón del producto.
                'producto'            => $item->descripcion ?: ($item->product?->nombre ?? '—'),
                'unidad'              => $item->product?->unidad,
                'qty_solicitada'      => (float) $item->cantidad,
                'num_cajas'           => $item->num_cajas,
                'pesos_cajas'         => $item->pesos_cajas,
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
            'qty_despachada'  => ['required', 'numeric', $sinExistencia ? 'min:0' : 'min:0.001'],
            'num_cajas'       => ['nullable', 'integer', 'min:0'],
            'pesos_cajas'     => ['nullable', 'array'],
            'pesos_cajas.*'   => ['nullable', 'numeric', 'min:0'],
            'nota'            => ['nullable', 'string', 'max:255'],
        ], [
            'qty_despachada.required' => 'Captura la cantidad despachada.',
            'qty_despachada.min'      => 'La cantidad despachada no puede ser 0 — si el producto no está disponible, usa el botón "Sin existencia".',
        ]);

        // Mientras el pedido siga PROCESADO, guardar una línea aquí NO toca
        // inventario (eso solo pasa al completar todo el surtido en
        // saveDespacho()) — así que sí se puede corregir un error de
        // cantidad/cajas las veces que haga falta antes de completar. Si el
        // pedido ya avanzó de estatus (se completó el surtido en otra
        // sesión), ahí sí ya es tarde para tocarlo desde aquí.
        if ($order->status !== SalesOrder::S_PROCESADO) {
            return response()->json(['ok' => false, 'message' => 'Este pedido ya no está Procesado — probablemente el surtido ya se completó.'], 422);
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

        if ($request->has('num_cajas') || $request->has('pesos_cajas')) {
            $update = [];
            if ($request->has('num_cajas')) {
                $update['num_cajas'] = $request->num_cajas !== null && $request->num_cajas !== ''
                    ? (int) $request->num_cajas
                    : null;
            }
            if ($request->has('pesos_cajas')) {
                $update['pesos_cajas'] = $request->pesos_cajas ?: null;
            }
            $item->update($update);
        }

        return response()->json(['ok' => true]);
    }

    // ── 2c. Imprime etiqueta(s) ZPL de una línea, mandándolas directo por
    //       socket a la impresora configurada en SuperAdmin. Solo disponible
    //       cuando etiquetas.modo_impresion = 'zpl'. No toca inventario ni
    //       el estatus del pedido — es independiente de guardar la línea.
    public function imprimirEtiqueta(Request $request, SalesOrder $order, SalesOrderItem $item, ZplLabelService $zpl)
    {
        $this->authorize('salida de producto');

        abort_if($item->sales_order_id !== $order->id, 404);

        if (!$zpl->modoZplActivo()) {
            return response()->json(['ok' => false, 'message' => 'El modo de etiquetas ZPL no está activo — actívalo en SuperAdmin → Configuración → Etiquetas de surtido.'], 422);
        }

        $porCajas = $zpl->imprimirPorCajas();

        $folio    = $order->folio;
        $cliente  = $order->client?->nombre ?? '—';
        $producto = $item->descripcion ?: ($item->product?->nombre ?? '—');

        $etiquetas = [];

        if ($porCajas) {
            $request->validate([
                'pesos_cajas'   => ['required', 'array', 'min:1'],
                'pesos_cajas.*' => ['required', 'numeric', 'min:0.001'],
            ], [
                'pesos_cajas.required' => 'Captura el peso de cada caja antes de imprimir.',
            ]);

            $pesos     = array_values($request->pesos_cajas);
            $cajaTotal = count($pesos);

            foreach ($pesos as $i => $peso) {
                $etiquetas[] = $zpl->construirEtiqueta([
                    'folio'      => $folio,
                    'cliente'    => $cliente,
                    'producto'   => $producto,
                    'caja_num'   => $i + 1,
                    'caja_total' => $cajaTotal,
                    'peso'       => (float) $peso,
                ]);
            }

            // Se persiste junto con la impresión — así si vuelven a abrir la
            // línea después, los pesos ya capturados siguen ahí.
            $item->update([
                'num_cajas'   => $cajaTotal,
                'pesos_cajas' => $pesos,
            ]);
        } else {
            $etiquetas[] = $zpl->construirEtiqueta([
                'folio'    => $folio,
                'cliente'  => $cliente,
                'producto' => $producto,
                'cantidad' => rtrim(rtrim(number_format((float) $item->cantidad, 3), '0'), '.') . ($item->product?->unidad ? ' ' . $item->product->unidad : ''),
            ]);
        }

        $resultado = $zpl->enviar($etiquetas);

        return response()->json($resultado, $resultado['ok'] ? 200 : 422);
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
            'lines.*.pesos_cajas'         => 'nullable|array',
            'lines.*.pesos_cajas.*'       => 'nullable|numeric|min:0',
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
                if (array_key_exists('pesos_cajas', $lineData)) {
                    $updateData['pesos_cajas'] = $lineData['pesos_cajas'] ?: null;
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