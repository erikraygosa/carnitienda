<x-admin-layout
    title="Pedidos"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')],['name'=>'Pedidos']]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales-orders.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Nuevo pedido
        </a>
    </x-slot>

    <x-wire-card>

        {{-- Filtros --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
            <div class="md:col-span-2">
                <input type="text" id="so-search"
                       placeholder="Buscar folio, cliente..."
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <select id="so-status"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los estatus</option>
                    <option value="BORRADOR">Borrador</option>
                    <option value="APROBADO">Aprobado</option>
                    <option value="PREPARANDO">Preparando</option>
                    <option value="PROCESADO">Procesado</option>
                    <option value="EN_RUTA">En ruta</option>
                    <option value="ENTREGADO">Entregado</option>
                    <option value="NO_ENTREGADO">No entregado</option>
                    <option value="CANCELADO">Cancelado</option>
                </select>
            </div>
            <div>
                <input type="date" id="so-desde"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <input type="date" id="so-hasta"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        {{-- Fila inferior --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <select id="so-per-page"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="10">10</option>
                    <option value="15" selected>15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span class="text-xs text-gray-400">por página</span>
            </div>
            <button type="button" id="so-clear" class="text-xs text-indigo-600 hover:underline">
                Limpiar filtros
            </button>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th onclick="SOT.sort('folio')"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Folio <span id="sort-folio"></span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Almacén</th>
                        <th onclick="SOT.sort('fecha')"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Fecha <span id="sort-fecha"></span>
                        </th>
                        <th onclick="SOT.sort('status')"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Estatus <span id="sort-status"></span>
                        </th>
                        <th onclick="SOT.sort('total')"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Total <span id="sort-total"></span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody id="so-tbody" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div id="so-pagination" class="mt-4 flex items-center justify-between text-sm text-gray-500"></div>

    </x-wire-card>

    <script>
    (function(){
        const DATA_URL = '{{ route('admin.sales-orders.data') }}';

        let state = {
            search:     '',
            status:     '',
            fechaDesde: '',
            fechaHasta: '',
            sortBy:     'id',
            sortDir:    'desc',
            perPage:    15,
            page:       1,
            lastPage:   1,
        };

        const $ = id => document.getElementById(id);

        function updateSortIndicators() {
            ['folio','fecha','status','total'].forEach(col => {
                const el = $(`sort-${col}`);
                if (!el) return;
                el.textContent = state.sortBy === col
                    ? (state.sortDir === 'asc' ? ' ↑' : ' ↓')
                    : '';
            });
        }

        function renderActions(o) {
            const btn  = (url, label, color) =>
                `<a href="${url}" class="inline-flex px-2 py-1 text-xs rounded border ${color}">${label}</a>`;
            const form = (url, label, color, method = 'POST') =>
                `<form action="${url}" method="POST" class="inline">
                    <input type="hidden" name="_token" value="${o.csrf}">
                    ${method === 'DELETE' ? '<input type="hidden" name="_method" value="DELETE">' : ''}
                    <button type="submit" class="inline-flex px-2 py-1 text-xs rounded border ${color}">${label}</button>
                 </form>`;

            let html = btn(o.edit_url, 'Editar', 'border-indigo-300 text-indigo-700 hover:bg-indigo-50');

            const withPdf = ['PROCESADO','EN_RUTA','ENTREGADO','DESPACHADO'];
            if (withPdf.includes(o.status)) {
                html += btn(o.pdf_url,     'PDF',     'border-gray-300 text-gray-600 hover:bg-gray-50');
                html += btn(o.pdf_dl_url,  '↓ PDF',   'border-gray-300 text-gray-600 hover:bg-gray-50');
                html += btn(o.send_url,    'Enviar',   'border-violet-300 text-violet-700 hover:bg-violet-50');
                html += btn(o.invoice_url, 'Facturar', 'border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100');
            }

            if (o.status === 'BORRADOR') {
                html += form(o.approve_url, 'Aprobar',  'border-emerald-300 text-emerald-700 hover:bg-emerald-50');
                html += form(o.cancel_url,  'Cancelar', 'border-red-300 text-red-600 hover:bg-red-50');
            } else if (o.status === 'APROBADO') {
                html += form(o.process_url, 'Procesar', 'border-amber-300 text-amber-700 hover:bg-amber-50');
                html += form(o.cancel_url,  'Cancelar', 'border-red-300 text-red-600 hover:bg-red-50');
            } else if (o.status === 'PREPARANDO') {
                html += form(o.process_url, 'Procesar', 'border-amber-300 text-amber-700 hover:bg-amber-50');
            } else if (o.status === 'PROCESADO') {
                html += form(o.enruta_url, 'Despachar', 'border-violet-300 text-violet-700 hover:bg-violet-50');
            } else if (['EN_RUTA','DESPACHADO'].includes(o.status)) {
                html += form(o.deliver_url,    'Entregar',      'border-emerald-300 text-emerald-700 hover:bg-emerald-50');
                html += form(o.nodeliver_url,  'No entregado',  'border-orange-300 text-orange-600 hover:bg-orange-50');
            }

            return `<div class="flex items-center gap-1 flex-wrap">${html}</div>`;
        }

        async function load() {
            $('so-tbody').innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Cargando...</td></tr>`;

            const params = new URLSearchParams({
                search:      state.search,
                status:      state.status,
                fecha_desde: state.fechaDesde,
                fecha_hasta: state.fechaHasta,
                sort_by:     state.sortBy,
                sort_dir:    state.sortDir,
                per_page:    state.perPage,
                page:        state.page,
            });

            try {
                const res  = await fetch(`${DATA_URL}?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                state.lastPage = data.last_page;
                renderTable(data.rows);
                renderPagination(data.total, data.page, data.last_page);
                updateSortIndicators();
            } catch(e) {
                $('so-tbody').innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-red-400">Error cargando datos.</td></tr>`;
            }
        }

        function renderTable(rows) {
            const tbody = $('so-tbody');
            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No se encontraron pedidos.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            rows.forEach(o => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors';
                tr.innerHTML = `
                    <td class="px-4 py-3 font-mono text-indigo-700 font-medium">${o.folio}</td>
                    <td class="px-4 py-3 text-gray-700">${o.cliente}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">${o.almacen}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">${o.fecha ?? '—'}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full ${o.status_class}">
                            ${o.status_label}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-gray-700">$${o.total}</td>
                    <td class="px-4 py-3">${renderActions(o)}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function renderPagination(total, page, lastPage) {
            const wrap = $('so-pagination');
            const from = total === 0 ? 0 : ((page - 1) * state.perPage) + 1;
            const to   = Math.min(page * state.perPage, total);
            wrap.innerHTML = `
                <span>${from}–${to} de ${total} pedidos</span>
                <div class="flex gap-1">
                    <button type="button" onclick="SOT.goPage(${page - 1})"
                            class="px-3 py-1 rounded border text-xs ${page <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page <= 1 ? 'disabled' : ''}>← Ant</button>
                    <span class="px-3 py-1 text-xs">Pág ${page} / ${lastPage}</span>
                    <button type="button" onclick="SOT.goPage(${page + 1})"
                            class="px-3 py-1 rounded border text-xs ${page >= lastPage ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page >= lastPage ? 'disabled' : ''}>Sig →</button>
                </div>
            `;
        }

        window.SOT = {
            sort(col) {
                if (state.sortBy === col) {
                    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortBy  = col;
                    state.sortDir = 'asc';
                }
                state.page = 1;
                load();
            },
            goPage(p) {
                if (p < 1 || p > state.lastPage) return;
                state.page = p;
                load();
            },
        };

        let searchTimeout;
        $('so-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                state.search = this.value;
                state.page   = 1;
                load();
            }, 350);
        });

        $('so-status').addEventListener('change', function() {
            state.status = this.value;
            state.page   = 1;
            load();
        });

        $('so-desde').addEventListener('change', function() {
            state.fechaDesde = this.value;
            state.page       = 1;
            load();
        });

        $('so-hasta').addEventListener('change', function() {
            state.fechaHasta = this.value;
            state.page       = 1;
            load();
        });

        $('so-per-page').addEventListener('change', function() {
            state.perPage = parseInt(this.value);
            state.page    = 1;
            load();
        });

        $('so-clear').addEventListener('click', function() {
            state.search = ''; state.status = '';
            state.fechaDesde = ''; state.fechaHasta = '';
            state.page = 1;
            $('so-search').value = '';
            $('so-status').value = '';
            $('so-desde').value  = '';
            $('so-hasta').value  = '';
            load();
        });

        load();
    })();
    </script>

</x-admin-layout>