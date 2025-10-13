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

            <x-wire-select
                label="Estatus"
                name="activo"
                :options="[
                    ['name' => 'Activo', 'id' => 1],
                    ['name' => 'Inactivo', 'id' => 0],
                ]"
                :option-label="'name'"
                :option-value="'id'"
                :selected="old('activo', 1)"
            />
        </form>
    </x-wire-card>
</x-admin-layout>
