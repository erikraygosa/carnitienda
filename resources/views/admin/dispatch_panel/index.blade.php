<x-admin-layout
    title="Panel de Surtido"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Panel de Surtido'],
    ]"
>
    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
        @php
            $kpis = [
                ['label'=>'Procesados pendientes', 'color'=>'text-amber-600',
                 'count'=> \App\Models\SalesOrder::where('status','PROCESADO')->count()],
                ['label'=>'Surtidos hoy',          'color'=>'text-indigo-600',
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
                <div class="flex items-center gap-2">
                    {{-- Filtro por ruta --}}
                    <form method="GET" action="{{ route('admin.despacho.panel') }}" class="flex items-center gap-2">
                        <select name="ruta_id" onchange="this.form.submit()"
                                class="rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Todas las rutas</option>
                            @foreach($rutas as $r)
                                <option value="{{ $r->id }}" {{ (string)$rutaId === (string)$r->id ? 'selected' : '' }}>
                                    {{ $r->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @if($rutaId)
                            <a href="{{ route('admin.despacho.panel') }}"
                               class="text-xs text-gray-400 hover:text-red-500" title="Quitar filtro">✕</a>
                        @endif
                    </form>
                    <input id="filter-search" type="text" placeholder="Buscar folio / cliente..."
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 w-56"/>
                    <a href="{{ route('admin.despacho.print') }}{{ $rutaId ? '?ruta_id='.$rutaId : '' }}" target="_blank"
                        class="flex items-center gap-1 px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50 text-gray-600 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Imprimir pendientes
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Folio</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Fecha</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Producto</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Cant.</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">Cajas</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="pedidos-tbody">
                        @foreach($pedidos as $pedido)
                            @php $itemCount = $pedido->items->count(); @endphp
                            @foreach($pedido->items as $itemIdx => $item)
                            @php $yaDespachado = $itemsDespachadosIds->has($item->id); @endphp
                            <tr
                                data-order-id="{{ $pedido->id }}"
                                data-item-id="{{ $item->id }}"
                                data-search="{{ strtolower($pedido->folio . ' ' . ($pedido->client?->nombre ?? '') . ' ' . ($item->product?->nombre ?? $item->descripcion ?? '')) }}"
                                class="transition {{ $itemIdx === 0 ? 'border-t-2 border-gray-300' : 'border-t border-gray-100' }} {{ $yaDespachado ? 'bg-emerald-50 hover:bg-emerald-100' : 'hover:bg-indigo-50' }}"
                            >
                                {{-- Folio solo en primera fila del pedido --}}
                                <td class="px-3 py-2 font-mono text-indigo-700 font-semibold whitespace-nowrap">
                                    {{ $itemIdx === 0 ? $pedido->folio : '' }}
                                </td>
                                {{-- Cliente solo en primera fila --}}
                                <td class="px-3 py-2 text-gray-700 text-xs">
                                    {{ $itemIdx === 0 ? ($pedido->client?->nombre ?? '—') : '' }}
                                </td>
                                {{-- Fecha solo en primera fila --}}
                                <td class="px-3 py-2 text-gray-500 text-xs whitespace-nowrap">
                                    {{ $itemIdx === 0 && $pedido->fecha ? $pedido->fecha->format('d/m/Y') : '' }}
                                </td>
                                <td class="px-3 py-2 {{ $yaDespachado ? 'text-emerald-800 font-medium' : 'text-gray-800' }}">
                                    {{ $item->product?->nombre ?? $item->descripcion ?? '—' }}
                                    @if($yaDespachado)
                                        <span class="ml-1 text-emerald-600" title="Ya guardado">✓</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums {{ $yaDespachado ? 'text-emerald-700' : 'text-gray-600' }}">
                                    {{ number_format($item->cantidad, 3) }}
                                </td>
                                <td class="px-3 py-2 text-center {{ $yaDespachado ? 'text-emerald-700' : 'text-gray-500' }}">
                                    {{ $item->num_cajas ?? '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    @if($itemIdx === 0)
                                    <button
                                        onclick="abrirDespacho({{ $pedido->id }}, '{{ $pedido->folio }}')"
                                        class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 whitespace-nowrap">
                                        Surtir
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
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
                        <h2 class="font-semibold text-gray-800" id="panel-titulo">Surtido</h2>
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
                    <p class="text-xs text-gray-500 mb-2" id="panel-progreso"></p>
                    <table class="min-w-full text-sm mb-4">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-2 text-left text-xs text-gray-500">Producto</th>
                                <th class="px-2 py-2 text-center text-xs text-gray-500">Solicitado</th>
                                <th class="px-2 py-2 text-center text-xs text-gray-500">Surtido</th>
                                <th class="px-2 py-2 text-center text-xs text-gray-500">Cajas</th>
                                <th class="px-2 py-2 text-center text-xs text-gray-500">Dif.</th>
                                <th class="px-2 py-2 text-center text-xs text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody id="panel-lines" class="divide-y divide-gray-100"></tbody>
                    </table>

                    <textarea id="panel-nota-global" rows="2"
                        placeholder="Nota general del surtido (opcional)"
                        class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm mb-3 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </textarea>

                    <button onclick="guardarDespacho()" id="btn-completar"
                        class="w-full py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition disabled:opacity-40 disabled:cursor-not-allowed"
                        disabled>
                        Completar surtido
                    </button>
                </div>
            </x-wire-card>
        </div>
    </div>


    <script>
    var TICKET_BASE_URL = '{{ url('admin/sales-orders') }}';
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
                // Ya venía guardada de una sesión anterior (qty_despachada persistido,
                // 0 incluido — un 0 guardado significa "sin existencia" confirmado).
                line.guardado      = line.qty_despachada !== null;
                line.sinExistencia = line.guardado && line.qty_despachada === 0;
                // Ya se surtió con producto real: no se puede volver a tocar.
                line.bloqueada     = line.guardado && line.qty_despachada > 0;

                var qty = line.qty_despachada !== null ? line.qty_despachada : line.qty_solicitada;
                var dif = qty - line.qty_solicitada;
                var difColor = dif > 0 ? 'text-amber-600' : dif < 0 ? 'text-red-600' : 'text-gray-400';
                var difStr   = dif === 0 ? '—' : (dif > 0 ? '+' : '') + dif.toFixed(3);
                var unidad   = line.unidad ? line.unidad : '';

                var tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';
                tr.id = 'linea-' + idx;
                tr.innerHTML =
                    '<td class="px-2 py-2 text-gray-700">' + escHtml(line.producto) +
                        (unidad ? ' <span class="text-xs text-gray-400">(' + escHtml(unidad) + ')</span>' : '') +
                    '</td>' +
                    '<td class="px-2 py-2 text-center text-gray-600">' + line.qty_solicitada.toFixed(3) + '</td>' +
                    '<td class="px-2 py-1 text-center">' +
                        '<input type="number" step="0.001" min="0"' +
                        '   class="w-24 text-center rounded border border-gray-300 px-1 py-0.5 text-sm focus:ring-1 focus:ring-indigo-400"' +
                        '   value="' + qty.toFixed(3) + '"' +
                        '   data-idx="' + idx + '"' +
                        '   onchange="actualizarDif(this, ' + idx + ')"' +
                        (line.bloqueada ? '   disabled' : '') +
                        '/>' +
                    '</td>' +
                    '<td class="px-2 py-1 text-center">' +
                        '<input type="number" step="1" min="0"' +
                        '   class="w-16 text-center rounded border border-gray-300 px-1 py-0.5 text-sm focus:ring-1 focus:ring-indigo-400"' +
                        '   value="' + (line.num_cajas || '') + '"' +
                        '   placeholder="—"' +
                        '   data-cajas-idx="' + idx + '"' +
                        '   onchange="actualizarCajas(this, ' + idx + ')"' +
                        (line.bloqueada ? '   disabled' : '') +
                        '/>' +
                    '</td>' +
                    '<td class="px-2 py-2 text-center font-mono text-xs ' + difColor + '" id="dif-' + idx + '">' +
                        difStr +
                    '</td>' +
                    '<td class="px-2 py-1 text-center whitespace-nowrap">' +
                        (line.bloqueada
                            ? '<span class="px-2 py-1 text-xs rounded bg-emerald-100 text-emerald-700">✓ Surtido</span>'
                        : line.sinExistencia
                            ? '<span class="px-2 py-1 text-xs rounded bg-gray-200 text-gray-600">Sin existencia</span>'
                        :
                            '<button type="button" onclick="guardarLinea(' + idx + ')"' +
                            '   id="btn-linea-' + idx + '"' +
                            '   class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700">Guardar</button>' +
                            '<button type="button" onclick="marcarSinExistencia(' + idx + ')"' +
                            '   id="btn-sin-existencia-' + idx + '"' +
                            '   class="ml-1 px-2 py-1 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-50">Sin existencia</button>'
                        ) +
                    '</td>';
                tbody.appendChild(tr);
            });
            actualizarProgreso();
        }

        window.actualizarCajas = function(input, idx) {
            linesData[idx].num_cajas = input.value !== '' ? parseInt(input.value) : null;
        };

        window.actualizarDif = function(input, idx) {
            var qty = parseFloat(input.value) || 0;
            var sol = linesData[idx].qty_solicitada;
            linesData[idx].qty_despachada = qty;
            // Si el usuario toca la cantidad, esa línea deja de estar "guardada"
            // hasta que se vuelva a confirmar (evita completar con un valor
            // editado a último momento sin que quede persistido).
            marcarLineaComoPendiente(idx);
            var dif      = qty - sol;
            var difColor = dif > 0 ? 'text-amber-600' : dif < 0 ? 'text-red-600' : 'text-gray-400';
            var difStr   = dif === 0 ? '—' : (dif > 0 ? '+' : '') + dif.toFixed(3);
            var cell = document.getElementById('dif-' + idx);
            cell.textContent  = difStr;
            cell.className    = 'px-2 py-2 text-center font-mono text-xs ' + difColor;
        };

        function marcarLineaComoPendiente(idx) {
            linesData[idx].guardado = false;
            var btn = document.getElementById('btn-linea-' + idx);
            if (btn) {
                btn.textContent = 'Guardar';
                btn.className = 'px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700';
            }
            actualizarProgreso();
        }

        function actualizarProgreso() {
            var total    = linesData.length;
            var guardadas = linesData.filter(function(l) { return l.guardado; }).length;
            var progreso = document.getElementById('panel-progreso');
            if (progreso) {
                progreso.textContent = guardadas + ' / ' + total + ' producto(s) guardados';
            }
            var btnCompletar = document.getElementById('btn-completar');
            var aviso        = document.getElementById('panel-aviso-incompleto');
            var listo        = total > 0 && guardadas === total;
            if (btnCompletar) btnCompletar.disabled = !listo;
            if (aviso) aviso.classList.toggle('hidden', listo);
        }

        // ── Pinta la fila de la lista principal (sin recargar) — verde si se
        //    surtió con producto real, gris si quedó "sin existencia" ──────
        function marcarFilaComoDespachadaEnLista(salesOrderItemId, sinExistencia) {
            var tr = tbody.querySelector('tr[data-item-id="' + salesOrderItemId + '"]');
            if (!tr) return;
            tr.classList.remove('hover:bg-indigo-50');
            var celdaProducto = tr.children[3];
            if (sinExistencia) {
                tr.classList.add('bg-gray-50', 'hover:bg-gray-100');
                if (celdaProducto && !celdaProducto.querySelector('.marca-guardado')) {
                    celdaProducto.classList.add('text-gray-500', 'line-through');
                    var marcaGris = document.createElement('span');
                    marcaGris.className = 'ml-1 text-gray-400 marca-guardado';
                    marcaGris.title = 'Sin existencia';
                    marcaGris.textContent = '(sin existencia)';
                    celdaProducto.appendChild(marcaGris);
                }
                return;
            }
            tr.classList.add('bg-emerald-50', 'hover:bg-emerald-100');
            if (celdaProducto && !celdaProducto.querySelector('.marca-guardado')) {
                celdaProducto.classList.remove('text-gray-800');
                celdaProducto.classList.add('text-emerald-800', 'font-medium');
                var marca = document.createElement('span');
                marca.className = 'ml-1 text-emerald-600 marca-guardado';
                marca.title = 'Ya surtido';
                marca.textContent = '✓';
                celdaProducto.appendChild(marca);
            }
        }

        // ── Guardar UNA línea (item por item, se puede ir avanzando) ─────
        window.guardarLinea = function(idx) {
            var line  = linesData[idx];
            var input = document.querySelector('#panel-lines input[data-idx="' + idx + '"]');
            var qty   = parseFloat(input.value) || 0;

            if (qty <= 0) {
                Swal.fire('Cantidad inválida', 'La cantidad surtida no puede ser 0. Si el producto no está disponible, usa el botón "Sin existencia".', 'warning');
                return;
            }

            enviarLinea(idx, qty, false);
        };

        // ── Marcar una línea sin existencia (guarda en 0, con nota) ──────
        window.marcarSinExistencia = function(idx) {
            Swal.fire({
                title: 'Sin existencia',
                text: '¿Confirmas que este producto no está disponible? Se guardará en 0 y el pedido se actualizará para reflejarlo.',
                icon: 'question',
                input: 'text',
                inputPlaceholder: 'Motivo (opcional)',
                showCancelButton: true,
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Cancelar',
            }).then(function(result) {
                if (!result.isConfirmed) return;
                var linea = linesData[idx];
                linea.notaLinea = result.value || 'Sin existencia en almacén';
                enviarLinea(idx, 0, true);
            });
        };

        function enviarLinea(idx, qty, sinExistencia) {
            var line = linesData[idx];
            var btn  = document.getElementById(sinExistencia ? 'btn-sin-existencia-' + idx : 'btn-linea-' + idx);
            if (btn) btn.disabled = true;

            fetch('/admin/despacho/pedido/' + currentOrderId + '/linea/' + line.sales_order_item_id + '/guardar', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    qty_despachada: qty,
                    num_cajas:      line.num_cajas != null ? line.num_cajas : null,
                    nota:           sinExistencia
                        ? line.notaLinea
                        : (document.getElementById('panel-nota-global').value.trim() || null),
                    sin_existencia: sinExistencia,
                }),
            })
            .then(function(r) { return r.json().then(function(data) { return { status: r.status, data: data }; }); })
            .then(function(res) {
                if (res.status === 200 && res.data.ok) {
                    line.qty_despachada = qty;
                    renderLines(linesData);
                    marcarFilaComoDespachadaEnLista(line.sales_order_item_id, qty === 0);
                    actualizarProgreso();
                } else {
                    var msg = (res.data.errors && res.data.errors.qty_despachada)
                        ? res.data.errors.qty_despachada[0]
                        : (res.data.message || 'No se pudo guardar el producto.');
                    Swal.fire('Error', msg, 'error');
                    if (btn) btn.disabled = false;
                }
            })
            .catch(function() {
                if (btn) btn.disabled = false;
                Swal.fire('Error', 'No se pudo guardar el producto.', 'error');
            });
        }

        window.cerrarPanel = function() {
            document.getElementById('panel-despacho').classList.add('hidden');
            currentOrderId = null;
        };

        // ── Completa el despacho: exige que TODAS las líneas ya estén
        //    guardadas (botón viene disabled si no) ───────────────────────
        window.guardarDespacho = function() {
            if (!currentOrderId) return;

            var pendientes = linesData.filter(function(l) { return !l.guardado; });
            if (pendientes.length > 0) {
                Swal.fire('Faltan productos', 'Guarda todos los productos (botón "Guardar" de cada línea, o "Sin existencia") antes de completar el surtido.', 'warning');
                return;
            }

            var notaGlobal = document.getElementById('panel-nota-global').value.trim() || null;
            var lines = linesData.map(function(line) {
                return {
                    sales_order_item_id: line.sales_order_item_id,
                    qty_despachada:      line.qty_despachada,
                    num_cajas:           line.num_cajas != null ? line.num_cajas : null,
                    // No pisar la nota de "sin existencia" ya guardada por línea
                    // (line.nota = la que vino del server si se guardó en otra sesión).
                    nota:                line.sinExistencia ? (line.notaLinea || line.nota || null) : notaGlobal,
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
                    // Abrir ticket en nueva pestaña automáticamente
                    var ticketWin = window.open(TICKET_BASE_URL + '/' + currentOrderId + '/ticket', '_blank');

                    Swal.fire({
                        icon: 'success',
                        title: 'Surtido completado',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false,
                    }).then(function() {
                        // Quitar todas las filas del pedido despachado
                        document.querySelectorAll('#pedidos-tbody tr[data-order-id="' + currentOrderId + '"]')
                            .forEach(function(r) { r.remove(); });
                        cerrarPanel();
                        applyFilters();
                    });
                } else {
                    Swal.fire('Error', data.message ?? 'Ocurrió un error', 'error');
                }
            })
            .catch(function() {
                Swal.fire('Error', 'No se pudo completar el surtido.', 'error');
            });
        };

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ── Polling: detectar nuevos pedidos PROCESADOS ──────────────
        (function () {
            var POLL_URL     = '{{ route('admin.despacho.poll-count') }}';
            var knownCount = null; // null = primera carga, no notificar

            function poll() {
                fetch(POLL_URL, { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var count = data.count;
                        if (knownCount === null) {
                            knownCount = count; // primer check: solo memorizar
                        } else if (count > knownCount) {
                            // Nuevo pedido detectado → recargar automáticamente
                            location.reload();
                        }
                    })
                    .catch(function() {}); // silencioso si falla
            }

            setInterval(poll, 30000); // cada 30 segundos
            poll(); // primera llamada inmediata para inicializar
        })();
    })();
    </script>

</x-admin-layout>