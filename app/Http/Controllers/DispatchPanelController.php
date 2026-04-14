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
use App\Services\InventoryService;

class DispatchPanelController extends Controller
{
    use AuthorizesRequests;

    // ── 1. Lista pedidos PROCESADOS ──────────────────────────────────
    public function index(Request $request)
    {
        $this->authorize('salida de producto');

        $pedidos = SalesOrder::with(['client'])
            ->withCount('items')
            ->where('status', SalesOrder::S_PROCESADO)
            ->orderByDesc('fecha')
            ->paginate(50);

        return view('admin.dispatch_panel.index', compact('pedidos'));
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
                'qty_solicitada'      => (float) $item->cantidad,
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

    // ── 3. Guarda despacho real, descuenta inventario y recalcula ────
    public function saveDespacho(Request $request, SalesOrder $order, InventoryService $inv)
    {
        $this->authorize('salida de producto');

        $request->validate([
            'lines'                       => 'required|array|min:1',
            'lines.*.sales_order_item_id' => 'required|integer|exists:sales_order_items,id',
            'lines.*.qty_despachada'      => 'required|numeric|min:0',
            'lines.*.nota'                => 'nullable|string|max:255',
        ]);

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
                $orderItem->update([
                    'cantidad'  => $qtyReal,
                    'descuento' => $lineDescuento,
                    'impuesto'  => $lineImpuesto,
                    'total'     => $lineTotal,
                ]);

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