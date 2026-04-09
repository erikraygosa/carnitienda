<x-admin-layout
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Proveedores','url'=>route('admin.providers.index')],
        ['name'=>'Crear'],
    ]"
    title="Crear proveedor"
>
    <x-slot name="action">
        <a href="{{ route('admin.providers.index') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
           Cancelar
        </a>
        <button form="provider-create-form" type="submit"
           class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
           Guardar
        </button>
    </x-slot>

    <x-wire-card>
        <form id="provider-create-form" action="{{ route('admin.providers.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @csrf

            <x-wire-input label="Nombre" name="nombre" required :value="old('nombre')" />
            <x-wire-input label="RFC" name="rfc" :value="old('rfc')" />

            <x-wire-input label="Email" name="email" type="email" :value="old('email')" />
            <x-wire-input label="Teléfono" name="telefono" :value="old('telefono')" />

            <x-wire-input label="Contacto" name="contacto" :value="old('contacto')" />
            <x-wire-input label="Dirección" name="direccion" :value="old('direccion')" />

            <x-wire-input label="Ciudad" name="ciudad" :value="old('ciudad')" />
            <x-wire-input label="Estado" name="estado" :value="old('estado')" />

            <x-wire-input label="CP" name="cp" :value="old('cp')" />

            <div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estatus</label>
    <select name="activo"
        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
        <option value="1" {{ old('activo', '1') == '1' ? 'selected' : '' }}>Activo</option>
        <option value="0" {{ old('activo', '1') == '0' ? 'selected' : '' }}>Inactivo</option>
    </select>
</div>

            <div class="md:col-span-2">
                <x-wire-textarea label="Notas" name="notas">{{ old('notas') }}</x-wire-textarea>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>
