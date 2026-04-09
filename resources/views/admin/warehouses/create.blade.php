<x-admin-layout
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Almacenes', 'url' => route('admin.warehouses.index')],
        ['name' => 'Crear'],
    ]"
    title="Crear almacén"
>
    <x-slot name="action">
        <a href="{{ route('admin.warehouses.index') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
           Cancelar
        </a>
        <button form="warehouse-create-form" type="submit"
           class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
           Guardar
        </button>
    </x-slot>

    <x-wire-card>
        <form id="warehouse-create-form" action="{{ route('admin.warehouses.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @csrf

            <x-wire-input label="Código" name="codigo" required :value="old('codigo')" />
            <x-wire-input label="Nombre" name="nombre" required :value="old('nombre')" />
            <x-wire-input label="Dirección" name="direccion" :value="old('direccion')" />
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estatus</label>
            <select name="activo"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="1" {{ old('activo', '1') == '1' ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ old('activo', '1') == '0' ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>
        </form>
    </x-wire-card>
</x-admin-layout>
