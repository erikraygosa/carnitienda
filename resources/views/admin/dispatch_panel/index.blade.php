<x-admin-layout
    title="Panel de Despacho"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Panel de Despacho'],
    ]"
>
    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
        @php
            $kpis = [
                ['label'=>'Procesados pendientes', 'color'=>'text-amber-600',
                 'count'=> \App\Models\SalesOrder::where('status','PROCESADO')->count()],
                ['label'=>'Despachados hoy',       'color'=>'text-indigo-600',
                 'count'=> \App\Models\SalesOrder::where('status','DESPACHADO')->whereDate('despachado_at', today())->count()],
                ['label'=>'Entregados hoy',         'color'=>'text-emerald-600',
                 'count'=> \App\Models\SalesOrder::where('status','ENTREGADO')->whereDate('entregado_at', today())->count()],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <x-wire-card>
                <div class="text-xs text-gray-500">{{ $kpi['label'] }}</div>
                <div class="text-2xl font-bold mt-1 {{ $kpi['color'] }}">{{ $kpi['count'] }}</div>
            </x-wire-card>
        @endforeach
    </div>

    <div class="flex gap-4">
        {{-- Lista de pedidos --}}
        <x-wire-card class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-700">Pedidos procesados</h2>
                <input id="filter-search" type="text" placeholder="Buscar folio / cliente..."
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 w-56"/>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Folio</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Fecha</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Items</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Total</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="pedidos-tbody" class="divide-y divide-gray-100">
                        @foreach($pedidos as $pedido)
                        <tr
                            data-search="{{ strtolower($pedido->folio . ' ' . ($pedido->client?->nombre ?? '')) }}"
                            class="hover:bg-gray-50 transition"
                        >
                            <td class="px-3 py-2 font-mono text-indigo-700 font-semibold">
                                {{ $pedido->folio }}
                            </td>
                            <td class="px-3 py-2 text-gray-700">{{ $pedido->client?->nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs">
                                {{ $pedido->fecha ? $pedido->fecha->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ $pedido->items_count }}</td>
                            <td class="px-3 py-2 font-semibold text-gray-800">
                                ${{ number_format($pedido->total, 2) }}
                            </td>
                            <td class="px-3 py-2">
                                <button
                                    onclick="abrirDespacho({{ $pedido->id }}, '{{ $pedido->folio }}')"
                                    class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700">
                                    Despachar
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div id="no-results" class="hidden py-6 text-center text-sm text-gray-400">
                    No hay pedidos procesados pendientes.
                </div>
            </div>

            {{-- Paginación --}}
            <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
                <span id="pagination-info"></span>
                <div class="flex gap-2">
                    <button id="btn-prev"
                        class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        ← Anterior
                    </button>
                    <button id="btn-next"
                        class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        Siguiente →
                    </button>
                </div>
            </div>
        </x-wire-card>

        {{-- Panel lateral de despacho --}}
        <div id="panel-despacho" class="hidden w-[420px] shrink-0">
            <x-wire-card>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="font-semibold text-gray-800" id="panel-titulo">Despacho</h2>
                        <p class="text-xs text-gray-500" id="panel-cliente"></p>
                    </div>
                    <button onclick="cerrarPanel()"
                        class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
                </div>

                {{-- Loading --}}
                <div id="panel-loading" class="py-10 text-center text-sm text-gray-400">
                    Cargando líneas...
                </div>

                {{-- Tabla de líneas --}}
                <div id="panel-contenido" class="hidden">
                    <table class="min-w-full text-sm mb-4">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-2 text-left text-xs text-gray-500">Producto</th>
                                <th class="px-2 py-2 text-center text-xs text-gray-500">Solicitado</th>
                                <th class="px-2 py-2 text-center text-xs text-gray-500">Despachado</th>
                                <th class="px-2 py-2 text-center text-xs text-gray-500">Dif.</th>
                            </tr>
                        </thead>
                        <tbody id="panel-lines" class="divide-y divide-gray-100"></tbody>
                    </table>

                    <textarea id="panel-nota-global" rows="2"
                        placeholder="Nota general del despacho (opcional)"
                        class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm mb-3 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </textarea>

                    <button onclick="guardarDespacho()"
                        class="w-full py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition">
                        Guardar despacho real
                    </button>
                </div>
            </x-wire-card>
        </div>
    </div>

    <script>
    (function () {
        // ── Filtro + paginación ──────────────────────────────────────────
        var tbody     = document.getElementById('pedidos-tbody');
        var noResults = document.getElementById('no-results');
        var btnPrev   = document.getElementById('btn-prev');
        var btnNext   = document.getElementById('btn-next');
        var pagInfo   = document.getElementById('pagination-info');
        var fSearch   = document.getElementById('filter-search');
        var PER_PAGE  = 15, currentPage = 1, filteredRows = [];

        function applyFilters() {
            var s = fSearch.value.toLowerCase().trim();
            filteredRows = Array.from(tbody.querySelectorAll('tr')).filter(function(r) {
                return r.dataset.search.includes(s);
            });
            currentPage = 1;
            renderPage();
        }

        function renderPage() {
            Array.from(tbody.querySelectorAll('tr')).forEach(function(r) { r.classList.add('hidden'); });
            var start = (currentPage - 1) * PER_PAGE;
            var end   = start + PER_PAGE;
            filteredRows.slice(start, end).forEach(function(r) { r.classList.remove('hidden'); });
            var total = filteredRows.length, totalPages = Math.ceil(total / PER_PAGE);
            noResults.classList.toggle('hidden', total > 0);
            pagInfo.textContent = total > 0
                ? 'Mostrando ' + (start+1) + '–' + Math.min(end,total) + ' de ' + total
                : '';
            btnPrev.disabled = currentPage <= 1;
            btnNext.disabled = currentPage >= totalPages;
        }

        btnPrev.addEventListener('click', function() { currentPage--; renderPage(); });
        btnNext.addEventListener('click', function() { currentPage++; renderPage(); });
        fSearch.addEventListener('input', applyFilters);
        applyFilters();

        // ── Panel de despacho ────────────────────────────────────────────
        var currentOrderId = null;
        var linesData      = [];

        window.abrirDespacho = function(orderId, folio) {
            currentOrderId = orderId;
            document.getElementById('panel-titulo').textContent  = 'Folio: ' + folio;
            document.getElementById('panel-cliente').textContent = 'Cargando...';
            document.getElementById('panel-loading').classList.remove('hidden');
            document.getElementById('panel-contenido').classList.add('hidden');
            document.getElementById('panel-despacho').classList.remove('hidden');

            fetch('/admin/despacho/pedido/' + orderId, {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                linesData = data.lines;
                document.getElementById('panel-cliente').textContent = data.order.client ?? '—';
                renderLines(data.lines);
                document.getElementById('panel-loading').classList.add('hidden');
                document.getElementById('panel-contenido').classList.remove('hidden');
            })
            .catch(function() {
                document.getElementById('panel-loading').textContent = 'Error al cargar. Intenta de nuevo.';
            });
        };

        function renderLines(lines) {
            var tbody = document.getElementById('panel-lines');
            tbody.innerHTML = '';
            lines.forEach(function(line, idx) {
                var qty = line.qty_despachada !== null ? line.qty_despachada : line.qty_solicitada;
                var dif = qty - line.qty_solicitada;
                var difColor = dif > 0 ? 'text-amber-600' : dif < 0 ? 'text-red-600' : 'text-gray-400';
                var difStr   = dif === 0 ? '—' : (dif > 0 ? '+' : '') + dif.toFixed(3);

                var tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';
                tr.innerHTML =
                    '<td class="px-2 py-2 text-gray-700">' + escHtml(line.producto) + '</td>' +
                    '<td class="px-2 py-2 text-center text-gray-600">' + line.qty_solicitada.toFixed(3) + '</td>' +
                    '<td class="px-2 py-1 text-center">' +
                        '<input type="number" step="0.001" min="0"' +
                        '   class="w-24 text-center rounded border border-gray-300 px-1 py-0.5 text-sm focus:ring-1 focus:ring-indigo-400"' +
                        '   value="' + qty.toFixed(3) + '"' +
                        '   data-idx="' + idx + '"' +
                        '   onchange="actualizarDif(this, ' + idx + ')"' +
                        '/>' +
                    '</td>' +
                    '<td class="px-2 py-2 text-center font-mono text-xs ' + difColor + '" id="dif-' + idx + '">' +
                        difStr +
                    '</td>';
                tbody.appendChild(tr);
            });
        }

        window.actualizarDif = function(input, idx) {
            var qty = parseFloat(input.value) || 0;
            var sol = linesData[idx].qty_solicitada;
            linesData[idx].qty_despachada = qty;
            var dif      = qty - sol;
            var difColor = dif > 0 ? 'text-amber-600' : dif < 0 ? 'text-red-600' : 'text-gray-400';
            var difStr   = dif === 0 ? '—' : (dif > 0 ? '+' : '') + dif.toFixed(3);
            var cell = document.getElementById('dif-' + idx);
            cell.textContent  = difStr;
            cell.className    = 'px-2 py-2 text-center font-mono text-xs ' + difColor;
        };

        window.cerrarPanel = function() {
            document.getElementById('panel-despacho').classList.add('hidden');
            currentOrderId = null;
        };

        window.guardarDespacho = function() {
            if (!currentOrderId) return;

            var inputs = document.querySelectorAll('#panel-lines input[type=number]');
            var lines  = linesData.map(function(line, idx) {
                return {
                    sales_order_item_id: line.sales_order_item_id,
                    qty_despachada:      parseFloat(inputs[idx]?.value) || 0,
                    nota:                document.getElementById('panel-nota-global').value.trim() || null,
                };
            });

            fetch('/admin/despacho/pedido/' + currentOrderId + '/guardar', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ lines: lines }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Despacho guardado',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false,
                    }).then(function() {
                        // Quitar la fila del pedido despachado de la tabla
                        var rows = Array.from(document.querySelectorAll('#pedidos-tbody tr'));
                        rows.forEach(function(r) {
                            var btn = r.querySelector('button');
                            if (btn && btn.getAttribute('onclick').includes('(' + currentOrderId + ',')) {
                                r.remove();
                            }
                        });
                        cerrarPanel();
                        applyFilters();
                    });
                } else {
                    Swal.fire('Error', data.message ?? 'Ocurrió un error', 'error');
                }
            })
            .catch(function() {
                Swal.fire('Error', 'No se pudo guardar el despacho.', 'error');
            });
        };

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    })();
    </script>

</x-admin-layout>