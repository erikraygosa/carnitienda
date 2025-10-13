<x-admin-layout
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Proveedores','url'=>route('admin.providers.index')],
        ['name'=>'Editar'],
    ]"
    title="Editar proveedor"
>
    <x-slot name="action">
        <a href="{{ route('admin.providers.index') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
           Regresar
        </a>
        <button form="provider-edit-form" type="submit"
           class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
           Actualizar
        </button>
    </x-slot>

    <x-wire-card>
        <form id="provider-edit-form" action="{{ route('admin.providers.update', $provider) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @csrf
            @method('PUT')

            <x-wire-input label="Nombre" name="nombre" required :value="old('nombre',$provider->nombre)" />
            <x-wire-input label="RFC" name="rfc" :value="old('rfc',$provider->rfc)" />

            <x-wire-input label="Email" name="email" type="email" :value="old('email',$provider->email)" />
            <x-wire-input label="Teléfono" name="telefono" :value="old('telefono',$provider->telefono)" />

            <x-wire-input label="Contacto" name="contacto" :value="old('contacto',$provider->contacto)" />
            <x-wire-input label="Dirección" name="direccion" :value="old('direccion',$provider->direccion)" />

            <x-wire-input label="Ciudad" name="ciudad" :value="old('ciudad',$provider->ciudad)" />
            <x-wire-input label="Estado" name="estado" :value="old('estado',$provider->estado)" />

            <x-wire-input label="CP" name="cp" :value="old('cp',$provider->cp)" />

            <x-wire-select
                label="Estatus"
                name="activo"
                :options="[
                    ['name' => 'Activo', 'id' => 1],
                    ['name' => 'Inactivo', 'id' => 0],
                ]"
                :option-label="'name'"
                :option-value="'id'"
                :selected="old('activo', (int)$provider->activo)"
            />

            <div class="md:col-span-2">
                <x-wire-textarea label="Notas" name="notas">{{ old('notas',$provider->notas) }}</x-wire-textarea>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>
