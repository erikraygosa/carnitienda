<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WarehouseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:gestionar almacenes'),
        ];
    }

    public function index()
    {
        $warehouses = \App\Models\Warehouse::orderBy('nombre')->get();
        return view('admin.warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        return view('admin.warehouses.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo'      => 'required|string|max:30|unique:warehouses,codigo',
            'nombre'      => 'required|string|max:120',
            'direccion'   => 'nullable|string|max:200',
            'activo'      => 'required|boolean',
            'is_primary'  => 'nullable|boolean',
        ]);
        $data['is_primary'] = $request->boolean('is_primary');

        $warehouse = Warehouse::create($data);

        if ($warehouse->is_primary) {
            Warehouse::where('id', '!=', $warehouse->id)->update(['is_primary' => false]);
        }

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
            'codigo'      => 'required|string|max:30|unique:warehouses,codigo,' . $warehouse->id,
            'nombre'      => 'required|string|max:120',
            'direccion'   => 'nullable|string|max:200',
            'activo'      => 'required|boolean',
            'is_primary'  => 'nullable|boolean',
        ]);
        $data['is_primary'] = $request->boolean('is_primary');

        $warehouse->update($data);

        // Solo puede haber un almacén Matriz — si este se marcó, se desmarcan los demás.
        if ($warehouse->is_primary) {
            Warehouse::where('id', '!=', $warehouse->id)->update(['is_primary' => false]);
        }

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