<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index()
    {
        $providers = \App\Models\Provider::orderBy('nombre')->get();
        return view('admin.providers.index', compact('providers'));
    }

    public function create()
    {
        return view('admin.providers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'     => 'required|string|max:255|unique:providers,nombre',
            'rfc'        => 'nullable|string|max:20',
            'email'      => 'nullable|email|max:255',
            'telefono'   => 'nullable|string|max:30',
            'contacto'   => 'nullable|string|max:255',
            'direccion'  => 'nullable|string|max:255',
            'ciudad'     => 'nullable|string|max:120',
            'estado'     => 'nullable|string|max:120',
            'cp'         => 'nullable|string|max:10',
            'activo'     => 'required|boolean',
            'notas'      => 'nullable|string',
        ]);

        $provider = Provider::create($data);

        session()->flash('swal',[
            'icon' => 'success',
            'title' => 'Bien Hecho!',
            'text' => 'Proveedor creado exitosamente.'
        ]);

        return redirect()->route('admin.providers.edit', $provider);
    }

    public function edit(Provider $provider)
    {
        return view('admin.providers.edit', compact('provider'));
    }

    public function update(Request $request, Provider $provider)
    {
        $data = $request->validate([
            'nombre'     => 'required|string|max:255|unique:providers,nombre,' . $provider->id,
            'rfc'        => 'nullable|string|max:20',
            'email'      => 'nullable|email|max:255',
            'telefono'   => 'nullable|string|max:30',
            'contacto'   => 'nullable|string|max:255',
            'direccion'  => 'nullable|string|max:255',
            'ciudad'     => 'nullable|string|max:120',
            'estado'     => 'nullable|string|max:120',
            'cp'         => 'nullable|string|max:10',
            'activo'     => 'required|boolean',
            'notas'      => 'nullable|string',
        ]);

        $provider->update($data);

        session()->flash('swal',[
            'icon' => 'success',
            'title' => 'Bien Hecho!',
            'text' => 'Proveedor actualizado exitosamente.'
        ]);

        return redirect()->route('admin.providers.edit', $provider);
    }

    public function destroy(Provider $provider)
    {
        $provider->delete();

        session()->flash('swal',[
            'icon' => 'success',
            'title' => 'Bien Hecho!',
            'text' => 'Proveedor eliminado exitosamente.'
        ]);

        return redirect()->route('admin.providers.index');
    }
}
