<?php
// app/Http/Controllers/Admin/StockTransferController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function index()
{
    $warehouses = \App\Models\Warehouse::orderBy('nombre')->get(['id','nombre']);

    $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator'])
        ->when(request('search'), fn($q) =>
            $q->where('folio', 'like', '%'.request('search').'%')
        )
        ->when(request('status'), fn($q) =>
            $q->where('status', request('status'))
        )
        ->when(request('from_warehouse'), fn($q) =>
            $q->where('from_warehouse_id', request('from_warehouse'))
        )
        ->when(request('to_warehouse'), fn($q) =>
            $q->where('to_warehouse_id', request('to_warehouse'))
        )
        ->latest()
        ->paginate(20)
        ->withQueryString();

    return view('admin.stock.transfers.index', compact('transfers', 'warehouses'));
}

    public function create(Request $request)
    {
        $warehouses = Warehouse::orderBy('nombre')->get(['id', 'nombre']);
        $products   = Product::orderBy('nombre')->get(['id', 'nombre', 'unidad']);

        $prefill = [];
        if ($request->filled('product_id')) {
            $prefill['product_id'] = $request->product_id;
        }
        if ($request->filled('from_warehouse_id')) {
            $prefill['from_warehouse_id'] = $request->from_warehouse_id;
        }

        return view('admin.stock.transfers.create', compact('warehouses', 'products', 'prefill'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'fecha'             => ['required', 'date'],
            'notas'             => ['nullable', 'string', 'max:500'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id'=> ['required', 'exists:products,id'],
            'items.*.qty'       => ['required', 'numeric', 'min:0.001'],
        ]);

        $transfer = DB::transaction(function () use ($data) {
            $transfer = StockTransfer::create([
                'folio'             => StockTransfer::generateFolio(),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'fecha'             => $data['fecha'],
                'status'            => 'PENDIENTE',
                'notas'             => $data['notas'] ?? null,
                'created_by'        => auth()->id(),
            ]);

            foreach ($data['items'] as $it) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $it['product_id'],
                    'qty'               => $it['qty'],
                ]);
            }

            return $transfer;
        });

        return redirect()
            ->route('admin.stock.transfers.show', $transfer)
            ->with('swal', ['icon' => 'success', 'title' => 'Traspaso creado', 'text' => "Folio: {$transfer->folio}"]);
    }

    public function show(StockTransfer $transfer)
    {
        $transfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'creator', 'dispatch']);

        $statusClasses = [
            'PENDIENTE'  => 'bg-gray-100 text-gray-700',
            'ASIGNADO'   => 'bg-sky-100 text-sky-700',
            'EN_RUTA'    => 'bg-violet-100 text-violet-700',
            'COMPLETADO' => 'bg-emerald-100 text-emerald-700',
            'CANCELADO'  => 'bg-rose-100 text-rose-700',
        ];

        return view('admin.stock.transfers.show', compact('transfer', 'statusClasses'));
    }

    public function cancel(StockTransfer $transfer)
    {
        if (!in_array($transfer->status, ['PENDIENTE', 'ASIGNADO'])) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo PENDIENTE o ASIGNADO pueden cancelarse.']);
        }

        $transfer->update(['status' => 'CANCELADO']);
        return back()->with('swal', ['icon' => 'success', 'title' => 'Cancelado', 'text' => 'Traspaso cancelado.']);
    }

    /**
     * Completar manualmente (sin despacho) — descuenta origen, suma destino.
     */
    public function complete(StockTransfer $transfer, InventoryService $inv)
    {
        if (!in_array($transfer->status, ['PENDIENTE', 'ASIGNADO', 'EN_RUTA'])) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Este traspaso no puede completarse.']);
        }

        $transfer->load('items');

        DB::transaction(function () use ($transfer, $inv) {
            foreach ($transfer->items as $it) {
                // Salida del origen
                $inv->stockOut(
                    productId:   $it->product_id,
                    warehouseId: $transfer->from_warehouse_id,
                    qty:         $it->qty,
                    motivo:      'TRASPASO_SALIDA',
                    referencia:  $transfer,
                    userId:      auth()->id(),
                );
                // Entrada al destino
                $inv->stockIn(
                    productId:   $it->product_id,
                    warehouseId: $transfer->to_warehouse_id,
                    qty:         $it->qty,
                    motivo:      'TRASPASO_ENTRADA',
                    referencia:  $transfer,
                    userId:      auth()->id(),
                );
            }

            $transfer->update([
                'status'        => 'COMPLETADO',
                'completado_at' => now(),
            ]);
        });

        return back()->with('swal', ['icon' => 'success', 'title' => 'Completado', 'text' => 'Stock transferido correctamente.']);
    }

    /**
     * Vista de impresión.
     */
    public function print(StockTransfer $transfer)
    {
        $transfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'creator', 'dispatch.driver']);
        return view('admin.stock.transfers.print', compact('transfer'));
    }
}