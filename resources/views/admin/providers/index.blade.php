<x-admin-layout
    title="Listado de proveedores"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Proveedores'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.providers.create') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">
            Nuevo
        </a>
    </x-slot>

    <x-wire-card>
        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 mb-4">
            <input id="filter-search" type="text"
                placeholder="Buscar nombre / RFC / teléfono / email..."
                class="flex-1 min-w-[200px] rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            />
            <select id="filter-activo"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Todos los estatus</option>
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
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">RFC</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Teléfono</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Estatus</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Acciones</th>
                    </tr>
                </thead>
                <tbody id="provider-tbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($providers as $provider)
                    <tr
                        data-search="{{ strtolower($provider->nombre . ' ' . $provider->rfc . ' ' . $provider->telefono . ' ' . $provider->email) }}"
                        data-activo="{{ $provider->activo ? '1' : '0' }}"
                    >
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $provider->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $provider->nombre }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $provider->rfc ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $provider->telefono ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $provider->email ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if($provider->activo)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Activo</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.providers.edit', $provider) }}"
                                   class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                    Editar
                                </a>
                                <form action="{{ route('admin.providers.destroy', $provider) }}"
                                      method="POST" class="delete-form inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-red-600 text-red-600 hover:bg-red-50">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div id="no-results" class="hidden py-8 text-center text-sm text-gray-400">
                No se encontraron proveedores.
            </div>

            {{-- Paginación --}}
            <div class="flex items-center justify-between mt-4 text-sm text-gray-600 dark:text-gray-400">
                <span id="pagination-info"></span>
                <div class="flex gap-2">
                    <button id="btn-prev"
                        class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed dark:border-gray-600">
                        ← Anterior
                    </button>
                    <button id="btn-next"
                        class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed dark:border-gray-600">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>
    </x-wire-card>

    <script>
    (function () {
        const tbody          = document.getElementById('provider-tbody');
        const noResults      = document.getElementById('no-results');
        const paginationInfo = document.getElementById('pagination-info');
        const btnPrev        = document.getElementById('btn-prev');
        const btnNext        = document.getElementById('btn-next');
        const fSearch        = document.getElementById('filter-search');
        const fActivo        = document.getElementById('filter-activo');

        const PER_PAGE = 15;
        let currentPage  = 1;
        let filteredRows = [];

        function applyFilters() {
            const search = fSearch.value.toLowerCase().trim();
            const activo = fActivo.value;

            filteredRows = Array.from(tbody.querySelectorAll('tr')).filter(row => {
                const matchSearch = row.dataset.search.includes(search);
                const matchActivo = activo === '' || row.dataset.activo === activo;
                return matchSearch && matchActivo;
            });

            currentPage = 1;
            renderPage();
        }

        function renderPage() {
            Array.from(tbody.querySelectorAll('tr')).forEach(r => r.classList.add('hidden'));

            const start    = (currentPage - 1) * PER_PAGE;
            const end      = start + PER_PAGE;
            filteredRows.slice(start, end).forEach(r => r.classList.remove('hidden'));

            const total      = filteredRows.length;
            const totalPages = Math.ceil(total / PER_PAGE);

            noResults.classList.toggle('hidden', total > 0);
            paginationInfo.textContent = total > 0
                ? `Mostrando ${start + 1}–${Math.min(end, total)} de ${total} proveedores`
                : '';

            btnPrev.disabled = currentPage <= 1;
            btnNext.disabled = currentPage >= totalPages;
        }

        btnPrev.addEventListener('click', () => { currentPage--; renderPage(); });
        btnNext.addEventListener('click', () => { currentPage++; renderPage(); });
        fSearch.addEventListener('input', applyFilters);
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
                }).then(r => { if (r.isConfirmed) form.submit(); });
            });
        });

        applyFilters();
    })();
    </script>

</x-admin-layout>