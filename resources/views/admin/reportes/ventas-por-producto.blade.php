<x-admin-layout
    title="Ventas por Producto"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')],['name'=>'Reportes'],['name'=>'Ventas por Producto']]"
>
    <x-slot name="action">
        <a id="vp-export-btn" href="#"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
            <i class="fa-solid fa-file-excel"></i>
            Descargar Excel
        </a>
    </x-slot>

    <x-wire-card>

        {{-- Filtros --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
            <div class="md:col-span-2">
                <input type="text" id="vp-search"
                       placeholder="Buscar producto..."
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <input type="date" id="vp-desde"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <input type="date" id="vp-hasta"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex items-end">
                <button type="button" id="vp-clear" class="text-xs text-indigo-600 hover:underline">
                    Limpiar filtros
                </button>
            </div>
        </div>

        <div id="vp-summary-bar" class="flex flex-wrap gap-4 mb-4 text-sm text-gray-600"></div>

        {{-- Tabla --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Total KG</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Total $</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Precio Prom.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Piezas</th>
                    </tr>
                </thead>
                <tbody id="vp-tbody" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">Cargando...</td>
                    </tr>
                </tbody>
                <tfoot id="vp-tfoot" class="bg-gray-50 font-medium"></tfoot>
            </table>
        </div>

    </x-wire-card>

    <script>
    (function(){
        const DATA_URL   = '{{ route('admin.reportes.ventas-por-producto.data') }}';
        const EXPORT_URL = '{{ route('admin.reportes.ventas-por-producto.export') }}';

        let state = {
            search:     '',
            fechaDesde: '',
            fechaHasta: '',
        };

        const $ = id => document.getElementById(id);

        async function load() {
            $('vp-tbody').innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Cargando...</td></tr>`;
            $('vp-tfoot').innerHTML = '';
            $('vp-summary-bar').innerHTML = '';

            const params = new URLSearchParams({
                search:      state.search,
                fecha_desde: state.fechaDesde,
                fecha_hasta: state.fechaHasta,
            });

            const exportParams = new URLSearchParams({ search: state.search, fecha_desde: state.fechaDesde, fecha_hasta: state.fechaHasta });
            $('vp-export-btn').href = `${EXPORT_URL}?${exportParams}`;

            try {
                const res  = await fetch(`${DATA_URL}?${params}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                renderTable(data.rows, data.total_monto, data.total_kg);
                if (data.count > 0) {
                    $('vp-summary-bar').innerHTML = `
                        <span class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-medium">${data.count} productos</span>
                        <span class="bg-emerald-50 text-emerald-700 px-3 py-1 rounded-full text-xs font-medium">Total KG: ${data.total_kg}</span>
                        <span class="bg-amber-50 text-amber-700 px-3 py-1 rounded-full text-xs font-medium">Total $: $${data.total_monto}</span>
                    `;
                }
            } catch(e) {
                $('vp-tbody').innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-red-400">Error cargando datos.</td></tr>`;
            }
        }

        function renderTable(rows, totalMonto, totalKg) {
            const tbody = $('vp-tbody');
            const tfoot = $('vp-tfoot');

            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin resultados para los filtros seleccionados.</td></tr>`;
                tfoot.innerHTML = '';
                return;
            }

            tbody.innerHTML = '';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors';
                tr.innerHTML = `
                    <td class="px-4 py-2 text-gray-700 font-medium">${r.producto}</td>
                    <td class="px-4 py-2 text-right font-mono text-gray-700">${r.total_kg}</td>
                    <td class="px-4 py-2 text-right font-mono font-medium text-gray-800">$${r.monto_total}</td>
                    <td class="px-4 py-2 text-right font-mono text-gray-600">$${r.precio_promedio}</td>
                    <td class="px-4 py-2 text-right font-mono text-gray-600">${r.total_piezas}</td>
                `;
                tbody.appendChild(tr);
            });

            tfoot.innerHTML = `
                <tr class="border-t-2 border-gray-300">
                    <td class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase">Totales</td>
                    <td class="px-4 py-2 text-right font-mono font-semibold text-gray-800">${totalKg}</td>
                    <td class="px-4 py-2 text-right font-mono font-semibold text-gray-800">$${totalMonto}</td>
                    <td class="px-4 py-2"></td>
                    <td class="px-4 py-2"></td>
                </tr>
            `;
        }

        let searchTimeout;
        $('vp-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => { state.search = this.value; load(); }, 350);
        });

        $('vp-desde').addEventListener('change', function() { state.fechaDesde = this.value; load(); });
        $('vp-hasta').addEventListener('change', function() { state.fechaHasta = this.value; load(); });

        $('vp-clear').addEventListener('click', function() {
            state.search = ''; state.fechaDesde = ''; state.fechaHasta = '';
            $('vp-search').value = '';
            $('vp-desde').value  = ini;
            $('vp-hasta').value  = fin;
            state.fechaDesde = ini;
            state.fechaHasta = fin;
            load();
        });

        // Default: mes actual
        const hoy = new Date();
        const ini = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().slice(0,10);
        const fin = hoy.toISOString().slice(0,10);
        $('vp-desde').value = ini;
        $('vp-hasta').value = fin;
        state.fechaDesde = ini;
        state.fechaHasta = fin;

        load();
    })();
    </script>

</x-admin-layout>
