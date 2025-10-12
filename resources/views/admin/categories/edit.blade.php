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
            @php
                $selectedActivo = old('activo', isset($category) ? (int) $category->activo : 1);
            @endphp
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
                    :selected="$selectedActivo"
                />
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
