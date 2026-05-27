<?php

// app/Http/Controllers/Admin/CashMovementController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Services\CashService;
use Illuminate\Http\Request;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CashMovementController extends Controller implements HasMiddleware
{
    public function __construct(private CashService $cash) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:abrir cajas', only: ['store']),
        ];
    }

  public function store(Request $r, CashRegister $cashRegister){
    $data = $r->validate([
      'tipo'=>'required|in:INGRESO,EGRESO',
      'monto'=>'required|numeric|min:0.01',
      'concepto'=>'nullable|string'
    ]);
    $this->cash->addMovement($cashRegister,$data['tipo'],$data['monto'],$data['concepto'] ?? null);
    session()->flash('swal',['icon'=>'success','title'=>'Movimiento registrado','text'=>'Se actualizó la caja.']);
    return back();
  }
}
