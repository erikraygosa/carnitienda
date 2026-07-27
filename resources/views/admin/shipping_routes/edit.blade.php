<x-admin-layout
    title="Editar ruta"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Rutas de entrega', 'url' => route('admin.shipping-routes.index')],
        ['name' => $shippingRoute->nombre],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.shipping-routes.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
    </x-slot>

    <x-wire-card class="max-w-lg">
        <form method="POST" action="{{ route('admin.shipping-routes.update', $shippingRoute) }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre', $shippingRoute->nombre) }}" required autofocus
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <input type="text" name="descripcion" value="{{ old('descripcion', $shippingRoute->descripcion) }}"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="Zona, días de visita, etc.">
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" id="activo" value="1"
                       class="rounded border-gray-300 text-indigo-600"
                       {{ old('activo', $shippingRoute->activo) ? 'checked' : '' }}>
                <label for="activo" class="text-sm text-gray-700">Ruta activa</label>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Guardar cambios
                </button>
                <span class="text-xs text-gray-400">{{ $shippingRoute->clients_count }} cliente(s) asignado(s)</span>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>
