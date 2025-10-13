<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function create(Request $request)
    {
        return view('admin.stock.transfers.create', [
            'warehouses' => \App\Models\Warehouse::orderBy('nombre')->get(),
            'products'   => \App\Models\Product::orderBy('nombre')->get(['id','nombre']),
            'prefill'    => [
                'from_warehouse_id' => $request->integer('from_warehouse_id'),
                'product_id'        => $request->integer('product_id'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_warehouse_id'  => ['required','different:to_warehouse_id','exists:warehouses,id'],
            'to_warehouse_id'    => ['required','exists:warehouses,id'],
            'fecha'              => ['required','date'],
            'notas'              => ['nullable','string'],
            'items'              => ['required','array','min:1'],
            'items.*.product_id' => ['required','exists:products,id'],
            'items.*.qty'        => ['required','numeric','min:0.001'],
        ]);

        $transfer = null;

        \DB::transaction(function() use($data, &$transfer){
            $transfer = \App\Models\StockTransfer::create([
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'user_id'           => auth()->id(),
                'fecha'             => $data['fecha'],
                'status'            => 'completed',
                'notas'             => $data['notas'] ?? null,
            ]);

            foreach ($data['items'] as $it) {
                $item = \App\Models\StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $it['product_id'],
                    'qty'               => $it['qty'],
                ]);

                // OUT origen
                \App\Models\StockMovement::create([
                    'warehouse_id'    => $transfer->from_warehouse_id,
                    'product_id'      => $item->product_id,
                    'tipo'            => 'OUT',
                    'cantidad'        => $item->qty,
                    'motivo'          => 'TRANSFERENCIA SALIDA',
                    'referencia_type' => \App\Models\StockTransfer::class,
                    'referencia_id'   => $transfer->id,
                    'user_id'         => auth()->id(),
                ]);

                // IN destino
                \App\Models\StockMovement::create([
                    'warehouse_id'    => $transfer->to_warehouse_id,
                    'product_id'      => $item->product_id,
                    'tipo'            => 'IN',
                    'cantidad'        => $item->qty,
                    'motivo'          => 'TRANSFERENCIA ENTRADA',
                    'referencia_type' => \App\Models\StockTransfer::class,
                    'referencia_id'   => $transfer->id,
                    'user_id'         => auth()->id(),
                ]);
            }
        });

        return redirect()->route('admin.stock.index')
            ->with('swal', ['icon'=>'success','title'=>'¡Transferido!','text'=>'Transferencia completada.']);
    }
}
