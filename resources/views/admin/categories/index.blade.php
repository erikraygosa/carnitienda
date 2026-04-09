<x-admin-layout
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Categorias'],
    ]"
    title="Categorías"
>
    <x-slot name="action">
        <a href="{{ route('admin.categories.create') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">
            Nuevo
        </a>
    </x-slot>

    <x-wire-card>
        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 mb-4">
            <input
                id="filter-nombre"
                type="text"
                placeholder="Buscar por nombre..."
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            />
            <input
                id="filter-descripcion"
                type="text"
                placeholder="Buscar por descripción..."
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            />
            <select
                id="filter-activo"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            >
                <option value="">Todos</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Descripción</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Estatus</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Acciones</th>
                    </tr>
                </thead>
                <tbody id="category-tbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($categories as $category)
                    <tr
                        data-nombre="{{ strtolower($category->nombre) }}"
                        data-descripcion="{{ strtolower($category->descripcion) }}"
                        data-activo="{{ $category->activo }}"
                    >
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $category->id }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $category->nombre }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $category->descripcion }}</td>
                        <td class="px-4 py-3">
                            @if($category->activo)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Activo</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.categories.edit', $category) }}"
                                   class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                    Editar
                                </a>
                                <form action="{{ route('admin.categories.destroy', $category) }}"
                                      method="POST"
                                      class="delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-red-600 text-white hover:bg-red-700">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Sin resultados --}}
            <div id="no-results" class="hidden py-8 text-center text-sm text-gray-400">
                No se encontraron categorías.
            </div>
        </div>
    </x-wire-card>

    <script>
        (function () {
            const tbody      = document.getElementById('category-tbody');
            const noResults  = document.getElementById('no-results');
            const fNombre    = document.getElementById('filter-nombre');
            const fDescripcion = document.getElementById('filter-descripcion');
            const fActivo    = document.getElementById('filter-activo');

            function applyFilters() {
                const nombre     = fNombre.value.toLowerCase().trim();
                const descripcion = fDescripcion.value.toLowerCase().trim();
                const activo     = fActivo.value;

                const rows = tbody.querySelectorAll('tr');
                let visible = 0;

                rows.forEach(row => {
                    const matchNombre     = row.dataset.nombre.includes(nombre);
                    const matchDescripcion = row.dataset.descripcion.includes(descripcion);
                    const matchActivo     = activo === '' || row.dataset.activo === activo;

                    if (matchNombre && matchDescripcion && matchActivo) {
                        row.classList.remove('hidden');
                        visible++;
                    } else {
                        row.classList.add('hidden');
                    }
                });

                noResults.classList.toggle('hidden', visible > 0);
            }

            fNombre.addEventListener('input', applyFilters);
            fDescripcion.addEventListener('input', applyFilters);
            fActivo.addEventListener('change', applyFilters);

            // SweetAlert para eliminar
            document.querySelectorAll('.delete-form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: '¡No podrás revertir esto!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then(result => {
                        if (result.isConfirmed) form.submit();
                    });
                });
            });
        })();
    </script>

</x-admin-layout>