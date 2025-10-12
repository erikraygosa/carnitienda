{{-- resources/views/admin/categories/create.blade.php --}}
<x-admin-layout
    :breadcrumbs="[
        ['name' => 'Dashboard',  'url' => route('admin.dashboard')],
        ['name' => 'Categorias', 'url' => route('admin.categories.index')],
        ['name' => 'Crear'],
    ]"
    title="Crear categoría"
>
    {{-- Acciones (arriba a la derecha) --}}
    <x-slot name="action">
        <a href="{{ route('admin.categories.index') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
           Cancelar
        </a>
        <button form="category-create-form" type="submit"
           class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
           Guardar
        </button>
    </x-slot>

    <x-wire-card>
        <form id="category-create-form"
              action="{{ route('admin.categories.store') }}"
              method="POST"
              class="space-y-6">
            @csrf

            {{-- Nombre --}}
            <div>
                <x-wire-input
                    label="Nombre"
                    name="nombre"
                    type="text"
                    placeholder="Nombre de la categoría"
                    :value="old('nombre')"
                    required
                />
                @error('nombre')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Descripción (opcional) --}}
            <div>
                <x-wire-textarea
                    label="Descripción"
                    name="descripcion"
                    placeholder="Descripción (opcional)"
                >{{ old('descripcion') }}</x-wire-textarea>
                @error('descripcion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Estatus --}}
            <div>
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
                @error('activo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Botones secundarios (por si no usas el slot de acciones) --}}
            <div class="pt-2 flex items-center gap-2">
                <a href="{{ route('admin.categories.index') }}"
                   class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
                   Cancelar
                </a>
                <button type="submit"
                   class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                   Guardar
                </button>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>
