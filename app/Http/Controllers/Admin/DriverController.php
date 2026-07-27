<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DriverController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:ver configuracion')];
    }

    public function index()
    {
        $drivers = Driver::withCount('dispatches')->orderBy('nombre')->get();
        return view('admin.drivers.index', compact('drivers'));
    }

    public function create()
    {
        return view('admin.drivers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'   => 'required|string|max:120',
            'telefono' => 'nullable|string|max:30',
            'licencia' => 'nullable|string|max:50',
            'activo'   => 'boolean',
        ]);
        $data['activo'] = $request->boolean('activo', true);

        $driver = Driver::create($data);

        session()->flash('swal', ['icon' => 'success', 'title' => '¡Listo!', 'text' => 'Chofer registrado.']);
        return redirect()->route('admin.drivers.edit', $driver);
    }

    public function edit(Driver $driver)
    {
        $driver->loadCount('dispatches');
        return view('admin.drivers.edit', compact('driver'));
    }

    public function update(Request $request, Driver $driver)
    {
        $data = $request->validate([
            'nombre'   => 'required|string|max:120',
            'telefono' => 'nullable|string|max:30',
            'licencia' => 'nullable|string|max:50',
            'activo'   => 'boolean',
        ]);
        $data['activo'] = $request->boolean('activo', true);

        $driver->update($data);

        session()->flash('swal', ['icon' => 'success', 'title' => '¡Actualizado!', 'text' => 'Chofer guardado.']);
        return back();
    }

    public function destroy(Driver $driver)
    {
        if ($driver->dispatches()->exists()) {
            return back()->with('swal', [
                'icon'  => 'error',
                'title' => 'No se puede eliminar',
                'text'  => 'Este chofer tiene despachos asignados.',
            ]);
        }

        $driver->delete();
        return redirect()->route('admin.drivers.index')
            ->with('swal', ['icon' => 'success', 'title' => 'Eliminado', 'text' => 'Chofer eliminado.']);
    }
}
