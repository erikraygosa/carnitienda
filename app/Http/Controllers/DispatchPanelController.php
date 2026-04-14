<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesOrder;
use App\Models\DispatchItem;
use App\Models\DispatchItemLine;
use App\Models\DocumentActivityLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 

class DispatchPanelController extends Controller
{

    use AuthorizesRequests;
    // Lista pedidos PROCESADOS
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

    // Carga líneas de un pedido para el modal/panel lateral
    public function show(SalesOrder $order)
    {
        $order->load(['items.product', 'client']);

        // Buscar si ya existe un dispatch_item para este pedido
        $dispatchItem = DispatchItem::with('lines')
            ->where('sales_order_id', $order->id)
            ->latest()
            ->first();

        $lines = $order->items->map(function ($item) use ($dispatchItem) {
            // Si ya hay línea guardada, cargarla
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
            'order'  => [
                'id'     => $order->id,
                'folio'  => $order->folio,
                'client' => $order->client?->nombre,
                'status' => $order->status,
                'total'  => $order->total,
            ],
            'lines' => $lines,
        ]);
    }

    // Guarda el despacho real
    public function saveDespacho(Request $request, SalesOrder $order)
    {
         $this->authorize('salida de producto');

        $request->validate([
            'lines'                       => 'required|array|min:1',
            'lines.*.sales_order_item_id' => 'required|integer|exists:sales_order_items,id',
            'lines.*.qty_despachada'      => 'required|numeric|min:0',
            'lines.*.nota'                => 'nullable|string|max:255',
        ]);

        // Buscar o crear dispatch_item para este pedido
        // (asume que ya existe un dispatch, si no, créalo mínimo)
        $dispatchItem = DispatchItem::firstOrCreate(
            ['sales_order_id' => $order->id],
            [
                'dispatch_id' => $this->getOrCreateDispatch(),
                'referencia'  => $order->folio,
                'status'      => 'ASIGNADO',
            ]
        );

        foreach ($request->lines as $lineData) {
            DispatchItemLine::updateOrCreate(
                [
                    'dispatch_item_id'    => $dispatchItem->id,
                    'sales_order_item_id' => $lineData['sales_order_item_id'],
                ],
                [
                    'qty_solicitada' => collect($order->items)
                        ->firstWhere('id', $lineData['sales_order_item_id'])
                        ?->cantidad ?? 0,
                    'qty_despachada' => $lineData['qty_despachada'],
                    'nota'           => $lineData['nota'] ?? null,
                ]
            );
        }

        // Cambiar status del pedido
        $oldStatus = $order->status;
        $order->update([
            'status'        => SalesOrder::S_DESPACHADO,
            'despachado_at' => now(),
        ]);

        // Log
        DocumentActivityLog::create([
            'document_type' => SalesOrder::class,
            'document_id'   => $order->id,
            'action'        => 'despacho_guardado',
            'old_status'    => $oldStatus,
            'new_status'    => SalesOrder::S_DESPACHADO,
            'user_id'       => auth()->id(),
            'nota'          => 'Despacho real registrado con ' . count($request->lines) . ' líneas.',
        ]);

        return response()->json(['ok' => true, 'message' => 'Despacho guardado correctamente.']);
    }

    // Helper: obtiene un dispatch genérico del día o crea uno
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