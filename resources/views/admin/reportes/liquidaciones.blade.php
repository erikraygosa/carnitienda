<x-admin-layout
    title="Liquidaciones"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')],['name'=>'Reportes'],['name'=>'Liquidaciones']]"
>
    <x-slot name="action">
        <a id="lq-export-btn" href="#"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
            <i class="fa-solid fa-file-excel"></i>
            Descargar Excel
        </a>
    </x-slot>

    <x-wire-card>

        {{-- Filtros --}}
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-4">
            <div>
                <input type="date" id="lq-fecha"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <select id="lq-ruta"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todas las rutas</option>
                    @foreach($routes as $route)
                        <option value="{{ $route->id }}">{{ $route->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select id="lq-chofer"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los choferes</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select id="lq-estatus"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="1">Solo pendientes</option>
                    <option value="0">Todas</option>
                </select>
            </div>
            <div>
                <select id="lq-per-page"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="flex items-center">
                <button type="button" id="lq-clear" class="text-xs text-indigo-600 hover:underline">
                    Limpiar filtros
                </button>
            </div>
        </div>

        <div id="lq-summary-bar" class="flex flex-wrap gap-4 mb-4 text-sm text-gray-600"></div>

        {{-- Tabla --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Nota</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruta</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chofer</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estatus</th>
                    </tr>
                </thead>
                <tbody id="lq-tbody" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div id="lq-pagination" class="mt-4 flex items-center justify-between text-sm text-gray-500"></div>

    </x-wire-card>

    <script>
    (function(){
        const DATA_URL   = '{{ route('admin.reportes.liquidaciones.data') }}';
        const EXPORT_URL = '{{ route('admin.reportes.liquidaciones.export') }}';

        const hoy = new Date().toISOString().slice(0,10);

        let state = {
            fecha:          hoy,
            routeId:        '',
            driverId:       '',
            soloSinLiq:     '1',
            perPage:        50,
            page:           1,
            lastPage:       1,
        };

        const $ = id => document.getElementById(id);

        async function load() {
            $('lq-tbody').innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Cargando...</td></tr>`;
            $('lq-summary-bar').innerHTML = '';

            const params = new URLSearchParams({
                fecha:            state.fecha,
                route_id:         state.routeId,
                driver_id:        state.driverId,
                solo_sin_liquidar: state.soloSinLiq,
                per_page:         state.perPage,
                page:             state.page,
            });

            const exportParams = new URLSearchParams({ fecha: state.fecha, route_id: state.routeId, driver_id: state.driverId, solo_sin_liquidar: state.soloSinLiq });
            $('lq-export-btn').href = `${EXPORT_URL}?${exportParams}`;

            try {
                const res  = await fetch(`${DATA_URL}?${params}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                state.lastPage = data.last_page;
                renderTable(data.rows);
                renderPagination(data.total, data.page, data.last_page);
                if (data.total > 0) {
                    $('lq-summary-bar').innerHTML = `
                        <span class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-medium">${data.total} notas</span>
                        <span class="bg-amber-50 text-amber-700 px-3 py-1 rounded-full text-xs font-medium">Total: $${data.total_monto}</span>
                    `;
                }
            } catch(e) {
                $('lq-tbody').innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-red-400">Error cargando datos.</td></tr>`;
            }
        }

        function renderTable(rows) {
            const tbody = $('lq-tbody');
            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin resultados para los filtros seleccionados.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors';
                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-indigo-700 text-xs font-medium whitespace-nowrap">${r.folio}</td>
                    <td class="px-3 py-2 text-gray-700">${r.cliente}</td>
                    <td class="px-3 py-2 text-gray-600 text-xs">${r.ruta}</td>
                    <td class="px-3 py-2 text-gray-600 text-xs">${r.chofer}</td>
                    <td class="px-3 py-2 text-gray-600 text-xs whitespace-nowrap">${r.fecha ?? '—'}</td>
                    <td class="px-3 py-2 text-right font-mono font-medium text-gray-800">$${r.total}</td>
                    <td class="px-3 py-2">
                        <span class="px-2 py-0.5 text-xs rounded-full font-medium ${r.est_class}">${r.estatus}</span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function renderPagination(total, page, lastPage) {
            const wrap = $('lq-pagination');
            const from = total === 0 ? 0 : ((page - 1) * state.perPage) + 1;
            const to   = Math.min(page * state.perPage, total);
            wrap.innerHTML = `
                <span>${from}–${to} de ${total} registros</span>
                <div class="flex gap-1">
                    <button type="button" onclick="LQT.goPage(${page - 1})"
                            class="px-3 py-1 rounded border text-xs ${page <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page <= 1 ? 'disabled' : ''}>← Ant</button>
                    <span class="px-3 py-1 text-xs">Pág ${page} / ${lastPage}</span>
                    <button type="button" onclick="LQT.goPage(${page + 1})"
                            class="px-3 py-1 rounded border text-xs ${page >= lastPage ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page >= lastPage ? 'disabled' : ''}>Sig →</button>
                </div>
            `;
        }

        window.LQT = {
            goPage(p) {
                if (p < 1 || p > state.lastPage) return;
                state.page = p;
                load();
            },
        };

        $('lq-fecha').addEventListener('change',    function() { state.fecha    = this.value; state.page = 1; load(); });
        $('lq-ruta').addEventListener('change',     function() { state.routeId  = this.value; state.page = 1; load(); });
        $('lq-chofer').addEventListener('change',   function() { state.driverId = this.value; state.page = 1; load(); });
        $('lq-estatus').addEventListener('change',  function() { state.soloSinLiq = this.value; state.page = 1; load(); });
        $('lq-per-page').addEventListener('change', function() { state.perPage  = parseInt(this.value); state.page = 1; load(); });

        $('lq-clear').addEventListener('click', function() {
            state.fecha = hoy; state.routeId = ''; state.driverId = ''; state.soloSinLiq = '1'; state.page = 1;
            $('lq-fecha').value   = hoy;
            $('lq-ruta').value    = '';
            $('lq-chofer').value  = '';
            $('lq-estatus').value = '1';
            load();
        });

        // Inicializar fecha
        $('lq-fecha').value = hoy;
        load();
    })();
    </script>

</x-admin-layout>
