<x-admin-layout
    title="Compras"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Compras'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.purchases.create') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">
            Nuevo
        </a>
    </x-slot>

    <x-wire-card>
        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 mb-4">
            <input id="filter-search" type="text"
                placeholder="Buscar folio / proveedor / almacén..."
                class="flex-1 min-w-[200px] rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            />
            <select id="filter-status"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Todos los estatus</option>
                <option value="draft">Borrador</option>
                <option value="received">Recibido</option>
                <option value="cancelled">Cancelado</option>
            </select>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Folio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Proveedor</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Almacén</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Fecha</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Estatus</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Total</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Acciones</th>
                    </tr>
                </thead>
                <tbody id="purchases-tbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($purchases as $purchase)
                    <tr
                        data-search="{{ strtolower($purchase->folio . ' ' . $purchase->provider?->nombre . ' ' . $purchase->warehouse?->nombre) }}"
                        data-status="{{ $purchase->status }}"
                    >
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $purchase->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $purchase->folio }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $purchase->provider?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $purchase->warehouse?->nombre ?? '—' }}</td>
                      <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($purchase->fecha)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full {{ $purchase->status_badge_class }}">
                                {{ $purchase->status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">
                            ${{ number_format((float)$purchase->total, 2) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 flex-nowrap">
                                <a href="{{ route('admin.purchases.edit', $purchase) }}"
                                   class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                    Editar
                                </a>

                                @if($purchase->status === 'draft')
                                    <form action="{{ route('admin.purchases.receive', $purchase) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-emerald-500 text-white hover:bg-emerald-600 font-medium">
                                        Recibir
                                    </button>
                                </form>

                                    <form action="{{ route('admin.purchases.destroy', $purchase) }}" method="POST" class="delete-form inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-red-600 text-red-600 hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.purchases.cancel', $purchase) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-gray-400 text-gray-600 hover:bg-gray-50">
                                            Cancelar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div id="no-results" class="hidden py-8 text-center text-sm text-gray-400">
                No se encontraron compras.
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
        const tbody          = document.getElementById('purchases-tbody');
        const noResults      = document.getElementById('no-results');
        const paginationInfo = document.getElementById('pagination-info');
        const btnPrev        = document.getElementById('btn-prev');
        const btnNext        = document.getElementById('btn-next');
        const fSearch        = document.getElementById('filter-search');
        const fStatus        = document.getElementById('filter-status');

        const PER_PAGE = 15;
        let currentPage  = 1;
        let filteredRows = [];

        function applyFilters() {
            const search = fSearch.value.toLowerCase().trim();
            const status = fStatus.value;

            filteredRows = Array.from(tbody.querySelectorAll('tr')).filter(row => {
                const matchSearch = row.dataset.search.includes(search);
                const matchStatus = status === '' || row.dataset.status === status;
                return matchSearch && matchStatus;
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
                ? `Mostrando ${start + 1}–${Math.min(end, total)} de ${total} compras`
                : '';

            btnPrev.disabled = currentPage <= 1;
            btnNext.disabled = currentPage >= totalPages;
        }

        btnPrev.addEventListener('click', () => { currentPage--; renderPage(); });
        btnNext.addEventListener('click', () => { currentPage++; renderPage(); });
        fSearch.addEventListener('input', applyFilters);
        fStatus.addEventListener('change', applyFilters);

        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: 'Esta acción no se puede revertir',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(r => { if (r.isConfirmed) form.submit(); });
            });
        });

        applyFilters();
    })();
    </script>

</x-admin-layout>