<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DocumentLogService;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentLogService $log) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ajustar inventario'),
        ];
    }

    public function create()
    {
        return view('admin.stock.adjustments.create', [
            'warehouses' => \App\Models\Warehouse::orderBy('nombre')->get(),
            'products'   => \App\Models\Product::active()->orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['required','exists:warehouses,id'],
            'product_id'   => ['required','exists:products,id'],
            'tipo'         => ['required','in:IN,OUT,AJUSTE'],
            'cantidad'     => ['required','numeric'],
            'motivo'       => ['nullable','string','max:150'],
        ]);

        \DB::transaction(function() use($data){
            $movement = \App\Models\StockMovement::create([
                'warehouse_id'    => $data['warehouse_id'],
                'product_id'      => $data['product_id'],
                'tipo'            => $data['tipo'],
                'cantidad'        => $data['cantidad'],
                'motivo'          => $data['motivo'] ?? 'AJUSTE MANUAL',
                 'referencia_type' => 'ManualAdjustment', // <- placeholder
                  'referencia_id'   => 0,                  // <- placeholder
                'user_id'         => auth()->id(),
            ]);

            $producto  = \App\Models\Product::find($data['product_id']);
            $almacen   = \App\Models\Warehouse::find($data['warehouse_id']);
            $this->log->log(
                $movement,
                'AJUSTE_MANUAL',
                null,
                null,
                null,
                "{$data['tipo']} {$data['cantidad']} — {$producto?->nombre} en {$almacen?->nombre}. Motivo: " . ($data['motivo'] ?? 'AJUSTE MANUAL')
            );
        });

        return back()->with('swal', ['icon'=>'success','title'=>'¡Listo!','text'=>'Ajuste registrado.']);
    }
}
