<x-admin-layout
    title="Listado de clientes"
    :breadcrumbs="[
        ['name'=>'Dashboard', 'url'=>route('admin.dashboard')],
        ['name'=>'Clientes'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.clients.create') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">
            Nuevo
        </a>
    </x-slot>

    <x-wire-card>
        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 mb-4">
            <input id="filter-search" type="text"
                placeholder="Buscar nombre / email / teléfono..."
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 flex-1 min-w-[200px]"
            />
            <select id="filter-activo"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Todos los estatus</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>
            <select id="filter-alerta"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Todas las alertas</option>
                <option value="3">🔴 Urgente</option>
                <option value="2">🟠 Moderado</option>
                <option value="1">🟡 Leve</option>
                <option value="0">🟢 Al día</option>
            </select>
        </div>

        {{-- Leyenda --}}
        <div class="flex flex-wrap gap-3 mb-4 text-xs text-gray-500">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> Urgente (+2x periodicidad)</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-orange-400 inline-block"></span> Moderado (+1.5x)</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-yellow-300 inline-block"></span> Leve (+1x)</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-green-400 inline-block"></span> Al día</span>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400 w-2"></th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Teléfono</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Ruta</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Lista precio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Días pedido</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Último pedido</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Estatus</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Acciones</th>
                    </tr>
                </thead>
                <tbody id="client-tbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($clients as $client)
                    @php
                        $alertColors = [
                            0 => 'bg-green-400',
                            1 => 'bg-yellow-300',
                            2 => 'bg-orange-400',
                            3 => 'bg-red-500',
                        ];
                        $rowColors = [
                            0 => '',
                            1 => 'bg-yellow-50',
                            2 => 'bg-orange-50',
                            3 => 'bg-red-50',
                        ];
                        $color = $alertColors[$client->alerta_nivel];
                        $rowBg = $rowColors[$client->alerta_nivel];
                        $diasPedido = $client->dias_pedido
                            ? implode(', ', array_map(fn($d) => ucfirst($d), $client->dias_pedido))
                            : '—';
                    @endphp
                    <tr class="{{ $rowBg }} hover:opacity-90 transition-colors"
                        data-search="{{ strtolower($client->nombre . ' ' . $client->email . ' ' . $client->telefono) }}"
                        data-activo="{{ $client->activo ? '1' : '0' }}"
                        data-alerta="{{ $client->alerta_nivel }}"
                    >
                        <td class="px-2 py-3">
                            <span class="block w-3 h-3 rounded-full {{ $color }}" title="Alerta nivel {{ $client->alerta_nivel }}"></span>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $client->nombre }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $client->email ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $client->telefono ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $client->shippingRoute?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $client->priceList?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">{{ $diasPedido }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">
                            @if($client->dias_sin_pedido !== null)
                                <span>Hace {{ $client->dias_sin_pedido }} día(s)</span>
                            @else
                                <span class="text-gray-400">Sin pedidos</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($client->activo)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-rose-100 text-rose-700">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @include('admin.clients.actions', ['client' => $client])
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div id="no-results" class="hidden py-8 text-center text-sm text-gray-400">
                No se encontraron clientes.
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
        const tbody          = document.getElementById('client-tbody');
        const noResults      = document.getElementById('no-results');
        const paginationInfo = document.getElementById('pagination-info');
        const btnPrev        = document.getElementById('btn-prev');
        const btnNext        = document.getElementById('btn-next');
        const fSearch        = document.getElementById('filter-search');
        const fActivo        = document.getElementById('filter-activo');
        const fAlerta        = document.getElementById('filter-alerta');

        const PER_PAGE = 15;
        let currentPage  = 1;
        let filteredRows = [];

        function applyFilters() {
            const search = fSearch.value.toLowerCase().trim();
            const activo = fActivo.value;
            const alerta = fAlerta.value;

            filteredRows = Array.from(tbody.querySelectorAll('tr')).filter(row => {
                const matchSearch = row.dataset.search.includes(search);
                const matchActivo = activo === '' || row.dataset.activo === activo;
                const matchAlerta = alerta === '' || row.dataset.alerta === alerta;
                return matchSearch && matchActivo && matchAlerta;
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
                ? `Mostrando ${start + 1}–${Math.min(end, total)} de ${total} clientes`
                : '';

            btnPrev.disabled = currentPage <= 1;
            btnNext.disabled = currentPage >= totalPages;
        }

        btnPrev.addEventListener('click', () => { currentPage--; renderPage(); });
        btnNext.addEventListener('click', () => { currentPage++; renderPage(); });
        fSearch.addEventListener('input', applyFilters);
        fActivo.addEventListener('change', applyFilters);
        fAlerta.addEventListener('change', applyFilters);

        // SweetAlert para desactivar
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Desactivar cliente?',
                    text: 'Podrás activarlo luego.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, desactivar',
                    cancelButtonText: 'Cancelar'
                }).then(r => { if (r.isConfirmed) form.submit(); });
            });
        });

        applyFilters();
    })();
    </script>

</x-admin-layout>