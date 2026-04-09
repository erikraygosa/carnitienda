<x-admin-layout
    :breadcrumbs="[
        ['name' => 'Dashboard',  'url' => route('admin.dashboard')],
        ['name' => 'Categorias', 'url' => route('admin.categories.index')],
        ['name' => 'Editar'],
    ]"
    title="Editar categoría"
>
    <x-wire-card>
        <form id="category-edit-form"
              action="{{ route('admin.categories.update', $category) }}"
              method="POST"
              class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Nombre --}}
            <div>
                <x-wire-input
                    label="Nombre"
                    name="nombre"
                    type="text"
                    placeholder="Nombre de la categoría"
                    :value="old('nombre', $category->nombre)"
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
                >{{ old('descripcion', $category->descripcion) }}</x-wire-textarea>
                @error('descripcion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

     {{-- Estatus --}}
<div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Estatus
    </label>
    <select
        name="activo"
        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
    >
        <option value="1" {{ old('activo', $category->activo) == '1' ? 'selected' : '' }}>Activo</option>
        <option value="0" {{ old('activo', $category->activo) == '0' ? 'selected' : '' }}>Inactivo</option>
    </select>
    @error('activo')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

            {{-- Botones --}}
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
