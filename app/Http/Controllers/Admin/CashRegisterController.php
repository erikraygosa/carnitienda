<?php

// app/Http/Controllers/Admin/CashRegisterController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Warehouse;
use App\Services\CashService;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
  public function __construct(private CashService $cash) {}

  public function index()
{
    $search = request('search');

    $registers = CashRegister::with(['warehouse:id,nombre', 'user:id,name'])
        ->when($search, function($q) use ($search) {
            $q->where('fecha', 'like', "%{$search}%")
              ->orWhereHas('warehouse', fn($q) => $q->where('nombre', 'like', "%{$search}%"))
              ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"))
              ->orWhere('estatus', 'like', "%{$search}%");
        })
        ->orderByDesc('fecha')
        ->paginate(15)
        ->withQueryString();

    return view('admin.cash.index', compact('registers'));
}

  public function create(){
    $warehouses = Warehouse::orderBy('nombre')->get();
    return view('admin.cash.create', compact('warehouses'));
  }

  public function store(Request $r){
    $data = $r->validate([
      'warehouse_id'=>'required|exists:warehouses,id',
      'fecha'=>'required|date',
      'monto'=>'nullable|numeric|min:0',
      'notas'=>'nullable|string',
    ]);
    $reg = $this->cash->open($data['warehouse_id'], auth()->id(), $data['fecha'], $data['monto'] ?? 0, $data['notas'] ?? null);
    session()->flash('swal',['icon'=>'success','title'=>'Caja abierta','text'=>'La caja quedó abierta.']);
    return redirect()->route('admin.cash.show', $reg);
  }

  public function show(CashRegister $cash){
    $cash->load('movements');
    return view('admin.cash.show', ['register'=>$cash]);
  }

  public function close(Request $r, CashRegister $cash){
    $this->cash->close($cash);
    session()->flash('swal',['icon'=>'success','title'=>'Caja cerrada','text'=>'Se cerró correctamente.']);
    return redirect()->route('admin.cash.index');
  }
public function ticket(\App\Models\CashRegister $cash)
{
    $cash->load(['user:id,name', 'warehouse:id,nombre', 'movements']);
    $company = \App\Models\Company::first();
    return view('admin.cash.ticket', ['register' => $cash, 'company' => $company]);
}
}
