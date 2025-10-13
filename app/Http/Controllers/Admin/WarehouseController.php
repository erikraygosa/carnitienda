<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        return view('admin.warehouses.index');
    }

    public function create()
    {
        return view('admin.warehouses.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo'    => 'required|string|max:30|unique:warehouses,codigo',
            'nombre'    => 'required|string|max:120',
            'direccion' => 'nullable|string|max:200',
            'activo'    => 'required|boolean',
        ]);

        $warehouse = Warehouse::create($data);

        session()->flash('swal',[
            'icon'  => 'success',
            'title' => 'Bien Hecho!',
            'text'  => 'Almacén creado exitosamente.'
        ]);

        return redirect()->route('admin.warehouses.edit', $warehouse);
    }

    public function edit(Warehouse $warehouse)
    {
        return view('admin.warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'codigo'    => 'required|string|max:30|unique:warehouses,codigo,' . $warehouse->id,
            'nombre'    => 'required|string|max:120',
            'direccion' => 'nullable|string|max:200',
            'activo'    => 'required|boolean',
        ]);

        $warehouse->update($data);

        session()->flash('swal',[
            'icon'  => 'success',
            'title' => 'Bien Hecho!',
            'text'  => 'Almacén actualizado exitosamente.'
        ]);

        return redirect()->route('admin.warehouses.edit', $warehouse);
    }

    public function destroy(Warehouse $warehouse)
    {
        // Si requieres validar que no tenga stock_movements asociados, aquí es el lugar.
        $warehouse->delete();

        session()->flash('swal',[
            'icon'  => 'success',
            'title' => 'Bien Hecho!',
            'text'  => 'Almacén eliminado exitosamente.'
        ]);

        return redirect()->route('admin.warehouses.index');
    }
}