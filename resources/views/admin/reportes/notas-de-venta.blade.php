<x-admin-layout
    title="Notas de Venta"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')],['name'=>'Reportes'],['name'=>'Notas de Venta']]"
>
    <x-slot name="action">
        <a id="nv-export-btn" href="#"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
            <i class="fa-solid fa-file-excel"></i>
            Descargar Excel
        </a>
    </x-slot>

    <x-wire-card>

        {{-- Filtros --}}
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-4">
            <div class="md:col-span-2">
                <input type="text" id="nv-search"
                       placeholder="Buscar folio, cliente, producto..."
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <select id="nv-tipo-venta"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los tipos</option>
                    <option value="CONTADO">Contado</option>
                    <option value="CREDITO">Crédito</option>
                    <option value="CONTRAENTREGA">Contraentrega</option>
                </select>
            </div>
            <div>
                <input type="date" id="nv-desde"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <input type="date" id="nv-hasta"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex items-center gap-2">
                <select id="nv-per-page"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
                <span class="text-xs text-gray-400 whitespace-nowrap">por pág.</span>
            </div>
        </div>

        <div class="flex items-center justify-between mb-4">
            <span id="nv-total-badge" class="text-xs text-gray-500"></span>
            <button type="button" id="nv-clear" class="text-xs text-indigo-600 hover:underline">
                Limpiar filtros
            </button>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th onclick="NVT.sort('folio')"
                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100 whitespace-nowrap">
                            Nota <span id="nv-sort-folio"></span>
                        </th>
                        <th onclick="NVT.sort('fecha')"
                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Fecha <span id="nv-sort-fecha"></span>
                        </th>
                        <th onclick="NVT.sort('cliente')"
                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Cliente <span id="nv-sort-cliente"></span>
                        </th>
                        <th onclick="NVT.sort('producto')"
                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Producto <span id="nv-sort-producto"></span>
                        </th>
                        <th onclick="NVT.sort('cantidad')"
                            class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100 whitespace-nowrap">
                            Cantidad <span id="nv-sort-cantidad"></span>
                        </th>
                        <th onclick="NVT.sort('precio')"
                            class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Precio <span id="nv-sort-precio"></span>
                        </th>
                        <th onclick="NVT.sort('total')"
                            class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Total <span id="nv-sort-total"></span>
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Observaciones
                        </th>
                    </tr>
                </thead>
                <tbody id="nv-tbody" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-400">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div id="nv-pagination" class="mt-4 flex items-center justify-between text-sm text-gray-500"></div>

    </x-wire-card>

    <script>
    (function(){
        const DATA_URL   = '{{ route('admin.reportes.notas-de-venta.data') }}';
        const EXPORT_URL = '{{ route('admin.reportes.notas-de-venta.export') }}';

        let state = {
            search:     '',
            tipoVenta:  '',
            fechaDesde: '',
            fechaHasta: '',
            sortBy:     'fecha',
            sortDir:    'desc',
            perPage:    50,
            page:       1,
            lastPage:   1,
        };

        const $ = id => document.getElementById(id);
        const sortCols = ['folio','fecha','cliente','producto','cantidad','precio','total'];

        function updateSortIndicators() {
            sortCols.forEach(col => {
                const el = $(`nv-sort-${col}`);
                if (!el) return;
                el.textContent = state.sortBy === col
                    ? (state.sortDir === 'asc' ? ' ↑' : ' ↓')
                    : '';
            });
        }

        async function load() {
            $('nv-tbody').innerHTML = `<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Cargando...</td></tr>`;

            const params = new URLSearchParams({
                search:      state.search,
                tipo_venta:  state.tipoVenta,
                fecha_desde: state.fechaDesde,
                fecha_hasta: state.fechaHasta,
                sort_by:     state.sortBy,
                sort_dir:    state.sortDir,
                per_page:    state.perPage,
                page:        state.page,
            });

            try {
                const res  = await fetch(`${DATA_URL}?${params}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                state.lastPage = data.last_page;
                renderTable(data.rows);
                renderPagination(data.total, data.page, data.last_page);
                updateSortIndicators();
                updateExportLink();
                $('nv-total-badge').textContent = data.total > 0
                    ? `${data.total} registros · Total: $${data.total_monto}`
                    : '';
            } catch(e) {
                $('nv-tbody').innerHTML = `<tr><td colspan="8" class="px-4 py-8 text-center text-red-400">Error cargando datos.</td></tr>`;
            }
        }

        function renderTable(rows) {
            const tbody = $('nv-tbody');
            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Sin resultados para los filtros seleccionados.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors';
                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-indigo-700 text-xs font-medium whitespace-nowrap">${r.folio}</td>
                    <td class="px-3 py-2 text-gray-600 text-xs whitespace-nowrap">${r.fecha ?? '—'}</td>
                    <td class="px-3 py-2 text-gray-700">${r.cliente}</td>
                    <td class="px-3 py-2 text-gray-700">${r.producto}</td>
                    <td class="px-3 py-2 text-right font-mono text-gray-700">${r.cantidad}</td>
                    <td class="px-3 py-2 text-right font-mono text-gray-700">$${r.precio}</td>
                    <td class="px-3 py-2 text-right font-mono font-medium text-gray-800">$${r.total}</td>
                    <td class="px-3 py-2 text-gray-500 text-xs">${r.observacion}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function renderPagination(total, page, lastPage) {
            const wrap = $('nv-pagination');
            const from = total === 0 ? 0 : ((page - 1) * state.perPage) + 1;
            const to   = Math.min(page * state.perPage, total);
            wrap.innerHTML = `
                <span>${from}–${to} de ${total} registros</span>
                <div class="flex gap-1">
                    <button type="button" onclick="NVT.goPage(${page - 1})"
                            class="px-3 py-1 rounded border text-xs ${page <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page <= 1 ? 'disabled' : ''}>← Ant</button>
                    <span class="px-3 py-1 text-xs">Pág ${page} / ${lastPage}</span>
                    <button type="button" onclick="NVT.goPage(${page + 1})"
                            class="px-3 py-1 rounded border text-xs ${page >= lastPage ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page >= lastPage ? 'disabled' : ''}>Sig →</button>
                </div>
            `;
        }

        window.NVT = {
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
        $('nv-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => { state.search = this.value; state.page = 1; load(); }, 350);
        });

        $('nv-tipo-venta').addEventListener('change', function() { state.tipoVenta = this.value; state.page = 1; load(); });
        $('nv-desde').addEventListener('change',      function() { state.fechaDesde = this.value; state.page = 1; load(); });
        $('nv-hasta').addEventListener('change',      function() { state.fechaHasta = this.value; state.page = 1; load(); });
        $('nv-per-page').addEventListener('change',   function() { state.perPage = parseInt(this.value); state.page = 1; load(); });

        $('nv-clear').addEventListener('click', function() {
            state.search = ''; state.tipoVenta = '';
            state.fechaDesde = ''; state.fechaHasta = ''; state.page = 1;
            $('nv-search').value = ''; $('nv-tipo-venta').value = '';
            $('nv-desde').value  = ''; $('nv-hasta').value = '';
            load();
        });

        function updateExportLink() {
            const params = new URLSearchParams({
                search:      state.search,
                tipo_venta:  state.tipoVenta,
                fecha_desde: state.fechaDesde,
                fecha_hasta: state.fechaHasta,
            });
            $('nv-export-btn').href = `${EXPORT_URL}?${params}`;
        }

        // Default: mes actual
        const hoy  = new Date();
        const ini  = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().slice(0,10);
        const fin  = hoy.toISOString().slice(0,10);
        $('nv-desde').value = ini;
        $('nv-hasta').value = fin;
        state.fechaDesde = ini;
        state.fechaHasta = fin;

        load();
    })();
    </script>

</x-admin-layout>
