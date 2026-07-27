<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingRoute;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ShippingRouteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:ver configuracion'),
        ];
    }

    public function index()
    {
        $routes = ShippingRoute::withCount('clients')->orderBy('nombre')->get();
        return view('admin.shipping_routes.index', compact('routes'));
    }

    public function create()
    {
        return view('admin.shipping_routes.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:120|unique:shipping_routes,nombre',
            'descripcion' => 'nullable|string|max:255',
            'activo'      => 'boolean',
        ]);

        $data['activo'] = $request->boolean('activo', true);

        $route = ShippingRoute::create($data);

        session()->flash('swal', ['icon' => 'success', 'title' => '¡Listo!', 'text' => 'Ruta creada correctamente.']);

        return redirect()->route('admin.shipping-routes.edit', $route);
    }

    public function edit(ShippingRoute $shippingRoute)
    {
        $shippingRoute->loadCount('clients');
        return view('admin.shipping_routes.edit', compact('shippingRoute'));
    }

    public function update(Request $request, ShippingRoute $shippingRoute)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:120|unique:shipping_routes,nombre,' . $shippingRoute->id,
            'descripcion' => 'nullable|string|max:255',
            'activo'      => 'boolean',
        ]);

        $data['activo'] = $request->boolean('activo', true);

        $shippingRoute->update($data);

        session()->flash('swal', ['icon' => 'success', 'title' => '¡Actualizado!', 'text' => 'Ruta guardada correctamente.']);

        return back();
    }

    public function destroy(ShippingRoute $shippingRoute)
    {
        if ($shippingRoute->clients()->exists()) {
            return back()->with('swal', [
                'icon'  => 'error',
                'title' => 'No se puede eliminar',
                'text'  => 'Esta ruta tiene clientes asignados. Reasígnalos primero.',
            ]);
        }

        $shippingRoute->delete();

        return redirect()->route('admin.shipping-routes.index')
            ->with('swal', ['icon' => 'success', 'title' => 'Eliminada', 'text' => 'Ruta eliminada.']);
    }
}
