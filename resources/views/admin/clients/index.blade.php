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
                </tbody>
            </table>

            <div id="loading" class="py-8 text-center text-sm text-gray-400">Cargando...</div>
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
        const loading        = document.getElementById('loading');
        const noResults       = document.getElementById('no-results');
        const paginationInfo = document.getElementById('pagination-info');
        const btnPrev        = document.getElementById('btn-prev');
        const btnNext        = document.getElementById('btn-next');
        const fSearch        = document.getElementById('filter-search');
        const fActivo        = document.getElementById('filter-activo');
        const fAlerta        = document.getElementById('filter-alerta');
        const csrfToken       = document.querySelector('meta[name="csrf-token"]').content;

        const DATA_URL = "{{ route('admin.clients.data') }}";
        const PER_PAGE = 15;

        let currentPage   = 1;
        let debounceTimer = null;

        const alertColors = { 0: 'bg-green-400', 1: 'bg-yellow-300', 2: 'bg-orange-400', 3: 'bg-red-500' };
        const rowColors    = { 0: '', 1: 'bg-yellow-50', 2: 'bg-orange-50', 3: 'bg-red-50' };

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function renderRow(client) {
            const color = alertColors[client.alerta_nivel] ?? 'bg-green-400';
            const rowBg = rowColors[client.alerta_nivel] ?? '';
            const diasPedido = (client.dias_pedido && client.dias_pedido.length)
                ? client.dias_pedido.map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(', ')
                : '—';
            const ultimoPedido = client.dias_sin_pedido !== null
                ? `<span>Hace ${client.dias_sin_pedido} día(s)</span>`
                : '<span class="text-gray-400">Sin pedidos</span>';
            const estatus = client.activo
                ? '<span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>'
                : '<span class="px-2 py-0.5 text-xs rounded-full bg-rose-100 text-rose-700">Inactivo</span>';

            const tr = document.createElement('tr');
            tr.className = `${rowBg} hover:opacity-90 transition-colors`;
            tr.innerHTML = `
                <td class="px-2 py-3"><span class="block w-3 h-3 rounded-full ${color}" title="Alerta nivel ${client.alerta_nivel}"></span></td>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">${escapeHtml(client.nombre)}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">${escapeHtml(client.email) || '—'}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">${escapeHtml(client.telefono) || '—'}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">${escapeHtml(client.shipping_route?.nombre) || '—'}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">${escapeHtml(client.price_list?.nombre) || '—'}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">${diasPedido}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">${ultimoPedido}</td>
                <td class="px-4 py-3">${estatus}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center space-x-2">
                        <a href="${client.edit_url}" class="inline-flex items-center px-2.5 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700">Editar</a>
                        <form action="${client.destroy_url}" method="POST" class="delete-form inline">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="inline-flex items-center px-2.5 py-1 text-xs rounded-md bg-red-600 text-white hover:bg-red-700">Desactivar</button>
                        </form>
                    </div>
                </td>
            `;
            return tr;
        }

        function attachDeleteHandlers() {
            tbody.querySelectorAll('.delete-form').forEach(form => {
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
        }

        function load() {
            loading.classList.remove('hidden');
            noResults.classList.add('hidden');

            const params = new URLSearchParams({
                search: fSearch.value.trim(),
                activo: fActivo.value,
                alerta: fAlerta.value,
                per_page: PER_PAGE,
                page: currentPage,
            });

            fetch(`${DATA_URL}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            })
                .then(res => res.json())
                .then(data => {
                    tbody.innerHTML = '';
                    data.data.forEach(client => tbody.appendChild(renderRow(client)));
                    attachDeleteHandlers();

                    loading.classList.add('hidden');
                    noResults.classList.toggle('hidden', data.total > 0);

                    paginationInfo.textContent = data.total > 0
                        ? `Mostrando ${data.from}–${data.to} de ${data.total} clientes`
                        : '';

                    btnPrev.disabled = data.current_page <= 1;
                    btnNext.disabled = data.current_page >= data.last_page;
                })
                .catch(() => {
                    loading.classList.add('hidden');
                });
        }

        function applyFilters() {
            currentPage = 1;
            load();
        }

        btnPrev.addEventListener('click', () => { currentPage--; load(); });
        btnNext.addEventListener('click', () => { currentPage++; load(); });

        fSearch.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyFilters, 300);
        });
        fActivo.addEventListener('change', applyFilters);
        fAlerta.addEventListener('change', applyFilters);

        load();
    })();
    </script>

</x-admin-layout>
