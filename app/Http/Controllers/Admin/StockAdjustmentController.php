<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function create()
    {
        return view('admin.stock.adjustments.create', [
            'warehouses' => \App\Models\Warehouse::orderBy('nombre')->get(),
            'products'   => \App\Models\Product::orderBy('nombre')->get(['id','nombre']),
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
            \App\Models\StockMovement::create([
                'warehouse_id'    => $data['warehouse_id'],
                'product_id'      => $data['product_id'],
                'tipo'            => $data['tipo'],
                'cantidad'        => $data['cantidad'],
                'motivo'          => $data['motivo'] ?? 'AJUSTE MANUAL',
                'referencia_type' => null,
                'referencia_id'   => null,
                'user_id'         => auth()->id(),
            ]);
        });

        return back()->with('swal', ['icon'=>'success','title'=>'¡Listo!','text'=>'Ajuste registrado.']);
    }
}
