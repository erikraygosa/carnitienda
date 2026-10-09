<x-admin-layout
    title="Pedidos"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')],['name'=>'Pedidos']]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.consolidada') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border border-indigo-300 text-indigo-700 hover:bg-indigo-50">
            Facturar varios pedidos
        </a>
        <a href="{{ route('admin.sales-orders.create') }}"
           class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Nuevo pedido
        </a>
    </x-slot>

    <x-wire-card>

        {{-- Filtros --}}
        <div class="grid grid-cols-1 md:grid-cols-7 gap-3 mb-4">
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
                <select id="so-facturada"
                        title="Filtrar por facturación"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Facturación: todas</option>
                    <option value="facturada">Facturada</option>
                    <option value="sin_facturar">Sin facturar</option>
                </select>
            </div>
            <div>
                <select id="so-pago"
                        title="Filtrar por pago"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Pago: todos</option>
                    <option value="pagado">Pagados</option>
                    <option value="no_pagado">Sin pagar</option>
                </select>
            </div>
            <div>
                <input type="date" id="so-desde" title="Programado desde"
                       value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <input type="date" id="so-hasta" title="Programado hasta"
                       value="{{ now()->endOfMonth()->format('Y-m-d') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        {{-- Fila inferior --}}
        <div class="flex items-center justify-between mb-4">
            <span id="so-count" class="text-xs text-gray-500"></span>
            <button type="button" id="so-clear" class="text-xs text-indigo-600 hover:underline">
                Limpiar filtros
            </button>
        </div>

        {{-- Tabla — sin paginación por página, se navega con scroll dentro
             de la tabla (antes: 15 por página, un mes con muchos pedidos
             quedaba repartido en decenas de páginas). --}}
        <div class="overflow-x-auto overflow-y-auto max-h-[70vh] rounded-lg border border-gray-200">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th onclick="SOT.sort('folio')"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Folio <span id="sort-folio"></span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Almacén</th>
                        <th onclick="SOT.sort('fecha')"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100"
                            title="Fecha programada para entrega">
                            Programado <span id="sort-fecha"></span>
                        </th>
                        <th onclick="SOT.sort('status')"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Estatus <span id="sort-status"></span>
                        </th>
                        <th onclick="SOT.sort('total')"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Total <span id="sort-total"></span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase" style="min-width: 300px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="so-tbody" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </x-wire-card>

    <script>
    (function(){
        const DATA_URL = '{{ route('admin.sales-orders.data') }}';

        // Los filtros se recuerdan en este navegador (localStorage) para
        // que entrar a un pedido y regresar (o cerrar y volver a abrir la
        // pestaña) no los reinicie al mes actual — antes se perdían porque
        // solo vivían en memoria de esta carga de página.
        const FILTROS_KEY = 'sales_orders_filtros';

        let state = {
            search:     '',
            status:     '',
            facturada:  '',
            pago:       '',
            fechaDesde: '{{ now()->startOfMonth()->format('Y-m-d') }}',
            fechaHasta: '{{ now()->endOfMonth()->format('Y-m-d') }}',
            sortBy:     'id',
            sortDir:    'desc',
        };

        // Los filtros guardados valen solo el día en que se capturaron: al
        // empezar un día nuevo la lista arranca sin filtros (mes actual).
        const hoyLocal = () => new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD en hora del navegador

        try {
            const guardado = JSON.parse(localStorage.getItem(FILTROS_KEY) || 'null');
            if (guardado && typeof guardado === 'object') {
                if (guardado.dia === hoyLocal()) {
                    const { dia, ...filtros } = guardado;
                    Object.assign(state, filtros);
                } else {
                    localStorage.removeItem(FILTROS_KEY);
                }
            }
        } catch (e) { /* localStorage bloqueado o dato corrupto — se queda con los defaults */ }

        function guardarFiltros() {
            try {
                localStorage.setItem(FILTROS_KEY, JSON.stringify({
                    dia: hoyLocal(),
                    search: state.search, status: state.status, facturada: state.facturada, pago: state.pago,
                    fechaDesde: state.fechaDesde, fechaHasta: state.fechaHasta,
                    sortBy: state.sortBy, sortDir: state.sortDir,
                }));
            } catch (e) { /* modo privado o storage lleno — no es crítico */ }
        }

        const $ = id => document.getElementById(id);

        // Reflejar el filtro restaurado en los controles antes del primer load().
        $('so-search').value    = state.search;
        $('so-status').value    = state.status;
        $('so-facturada').value = state.facturada;
        $('so-pago').value      = state.pago;
        $('so-desde').value     = state.fechaDesde;
        $('so-hasta').value     = state.fechaHasta;

        function updateSortIndicators() {
            ['folio','fecha','status','total'].forEach(col => {
                const el = $(`sort-${col}`);
                if (!el) return;
                el.textContent = state.sortBy === col
                    ? (state.sortDir === 'asc' ? ' ↑' : ' ↓')
                    : '';
            });
        }

        // ── Catálogos y URLs: una sola vez (las filas solo traen el id) ──
        const CSRF = '{{ csrf_token() }}';
        const URL_T = {
            edit:      '{{ route('admin.sales-orders.edit', '__ID__') }}',
            pdf:       '{{ route('admin.sales-orders.pdf', '__ID__') }}',
            pdfDl:     '{{ route('admin.sales-orders.pdf.download', '__ID__') }}',
            send:      '{{ route('admin.sales-orders.send.form', '__ID__') }}',
            invoice:   '{{ route('admin.invoices.create') }}?order_id=__ID__',
            approve:   '{{ route('admin.sales-orders.approve', '__ID__') }}',
            process:   '{{ route('admin.sales-orders.process', '__ID__') }}',
            cancel:    '{{ route('admin.sales-orders.cancel', '__ID__') }}',
            enruta:    '{{ route('admin.sales-orders.en-ruta', '__ID__') }}',
            deliver:   '{{ route('admin.sales-orders.deliver', '__ID__') }}',
            nodeliver: '{{ route('admin.sales-orders.not-delivered', '__ID__') }}',
            duplicate: '{{ route('admin.sales-orders.duplicate', '__ID__') }}',
            factura:   '{{ route('admin.invoices.edit', '__ID__') }}',
        };
        const urlDe = (k, id) => URL_T[k].replace('__ID__', id);

        const STATUS = {
            BORRADOR:     ['Borrador',     'bg-gray-100 text-gray-700'],
            APROBADO:     ['Aprobado',     'bg-blue-100 text-blue-700'],
            PREPARANDO:   ['Preparando',   'bg-sky-100 text-sky-700'],
            PROCESADO:    ['Procesado',    'bg-amber-100 text-amber-700'],
            DESPACHADO:   ['Despachado',   'bg-indigo-100 text-indigo-700'],
            EN_RUTA:      ['En ruta',      'bg-violet-100 text-violet-700'],
            ENTREGADO:    ['Entregado',    'bg-emerald-100 text-emerald-700'],
            NO_ENTREGADO: ['No entregado', 'bg-orange-100 text-orange-700'],
            CANCELADO:    ['Cancelado',    'bg-rose-100 text-rose-700'],
        };
        const FACTURA = {
            BORRADOR:              ['Factura en borrador',  'bg-gray-100 text-gray-600'],
            TIMBRADA:              ['Facturada',            'bg-emerald-100 text-emerald-700'],
            CANCELACION_PENDIENTE: ['Cancelación pendiente','bg-amber-100 text-amber-700'],
            CANCELADA:             ['Factura cancelada',    'bg-rose-100 text-rose-700'],
        };

        const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

        // Botones de acción POST: sin un <form> por botón (eran ~2 por fila,
        // con su token oculto); un solo formulario compartido los envía.
        const postBtn = (url, label, color) =>
            `<button type="button" data-post="${url}" class="inline-flex px-2 py-1 text-xs rounded border ${color}">${label}</button>`;
        const linkBtn = (url, label, color) =>
            `<a href="${url}" class="inline-flex px-2 py-1 text-xs rounded border ${color}">${label}</a>`;

        function renderActions(o) {
            let html = linkBtn(urlDe('edit', o.id), 'Editar', 'border-indigo-300 text-indigo-700 hover:bg-indigo-50');
            html += postBtn(urlDe('duplicate', o.id), 'Duplicar', 'border-gray-300 text-gray-600 hover:bg-gray-50');

            if (['PROCESADO','EN_RUTA','ENTREGADO','DESPACHADO'].includes(o.status)) {
                html += linkBtn(urlDe('pdf', o.id),   'PDF',   'border-gray-300 text-gray-600 hover:bg-gray-50');
                html += linkBtn(urlDe('pdfDl', o.id), '↓ PDF', 'border-gray-300 text-gray-600 hover:bg-gray-50');
                html += linkBtn(urlDe('send', o.id),  'Enviar', 'border-violet-300 text-violet-700 hover:bg-violet-50');
                // Ya tiene una factura viva (borrador/timbrada/cancelación
                // pendiente) — "Facturar" de nuevo crearía una duplicada, ya
                // que /admin/invoices/create no valida eso. Si la única
                // factura que tuvo se canceló, sí se puede volver a facturar.
                const facturaViva = o.fe && o.fe !== 'CANCELADA';
                if (!facturaViva) {
                    html += linkBtn(urlDe('invoice', o.id), 'Facturar', o.pagado ? 'border-emerald-600 text-white bg-emerald-600 hover:bg-emerald-700' : 'border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100');
                }
            }

            if (o.status === 'BORRADOR') {
                html += postBtn(urlDe('approve', o.id), 'Aprobar',  'border-emerald-300 text-emerald-700 hover:bg-emerald-50');
                html += postBtn(urlDe('cancel', o.id),  'Cancelar', 'border-red-300 text-red-600 hover:bg-red-50');
            } else if (o.status === 'APROBADO') {
                html += postBtn(urlDe('process', o.id), 'Procesar', 'border-amber-300 text-amber-700 hover:bg-amber-50');
                html += postBtn(urlDe('cancel', o.id),  'Cancelar', 'border-red-300 text-red-600 hover:bg-red-50');
            } else if (o.status === 'PREPARANDO') {
                html += postBtn(urlDe('process', o.id), 'Procesar', 'border-amber-300 text-amber-700 hover:bg-amber-50');
            } else if (o.status === 'PROCESADO') {
                html += postBtn(urlDe('enruta', o.id), 'Despachar', 'border-violet-300 text-violet-700 hover:bg-violet-50');
            } else if (['EN_RUTA','DESPACHADO'].includes(o.status)) {
                html += postBtn(urlDe('deliver', o.id),   'Entregar',     'border-emerald-300 text-emerald-700 hover:bg-emerald-50');
                html += postBtn(urlDe('nodeliver', o.id), 'No entregado', 'border-orange-300 text-orange-600 hover:bg-orange-50');
            }

            return `<div class="flex items-center gap-1 flex-wrap">${html}</div>`;
        }

        document.addEventListener('click', function (e) {
            const b = e.target.closest('button[data-post]');
            if (!b) return;
            const f = document.createElement('form');
            f.method = 'POST'; f.action = b.dataset.post; f.style.display = 'none';
            f.innerHTML = `<input type="hidden" name="_token" value="${CSRF}">`;
            document.body.appendChild(f);
            f.submit();
        });

        function rowHtml(o) {
            const [stLabel, stClass] = STATUS[o.status] || [o.status, 'bg-gray-100 text-gray-700'];
            const fac = o.fe ? (FACTURA[o.fe] || [o.fe, 'bg-gray-100 text-gray-600']) : null;
            return `
                <td class="px-4 py-3 font-mono text-indigo-700 font-medium">${esc(o.folio)}</td>
                <td class="px-4 py-3 text-gray-700">${esc(o.cliente)}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">${esc(o.almacen)}</td>
                <td class="px-4 py-3 text-gray-600 text-xs">${esc(o.fecha ?? '—')}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded-full ${stClass}">${stLabel}</span>
                    ${o.status !== 'CANCELADO' ? (o.pagado
                        ? `<span class="ml-1 inline-flex px-2 py-1 text-xs rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200" title="Pagado">💲 Pagado</span>`
                        : `<span class="ml-1 inline-flex px-2 py-1 text-xs rounded-full bg-amber-50 text-amber-700 border border-amber-200" title="Pendiente de cobro">Por cobrar</span>`) : ''}
                    ${fac ? `<a href="${urlDe('factura', o.fid)}" title="Ver factura"
                           class="ml-1 inline-flex px-2 py-1 text-xs rounded-full ${fac[1]} hover:opacity-75">🧾 ${fac[0]}</a>` : ''}
                </td>
                <td class="px-4 py-3 font-mono text-gray-700">$${esc(o.total)}</td>
                <td class="px-4 py-3">${renderActions(o)}</td>`;
        }

        // ── Carga por etapas ─────────────────────────────────────────────
        // 1) Un bloque corto (PRIMERO) para pintar la primera pantalla casi
        //    al instante. 2) La lista completa en paralelo; lo que falta se
        //    pinta en lotes para no congelar el navegador, y mientras tanto
        //    ya se puede leer y usar lo primero.
        const PRIMEROS = 40;
        const LOTE     = 120;
        let loadSeq = 0;
        let pintados = 0;       // filas ya pintadas de esta carga

        function params(extra = {}) {
            return new URLSearchParams({
                search:      state.search,
                status:      state.status,
                facturada:   state.facturada,
                pago:        state.pago,
                fecha_desde: state.fechaDesde,
                fecha_hasta: state.fechaHasta,
                sort_by:     state.sortBy,
                sort_dir:    state.sortDir,
                ...extra,
            });
        }

        const getJson = url => fetch(url, { headers: { 'Accept': 'application/json' } }).then(r => r.json());

        function pintarFilas(rows, desde, hasta) {
            const tbody = $('so-tbody');
            const frag = document.createDocumentFragment();
            for (let i = desde; i < hasta && i < rows.length; i++) {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors';
                tr.innerHTML = rowHtml(rows[i]);
                frag.appendChild(tr);
            }
            tbody.appendChild(frag);
        }

        function pintarRestante(rows, seq) {
            if (seq !== loadSeq || pintados >= rows.length) return;
            const hasta = Math.min(pintados + LOTE, rows.length);
            pintarFilas(rows, pintados, hasta);
            pintados = hasta;
            if (pintados < rows.length) requestAnimationFrame(() => pintarRestante(rows, seq));
        }

        async function load() {
            const seq = ++loadSeq;
            pintados = 0;
            $('so-tbody').innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Cargando...</td></tr>`;

            const rapida   = getJson(`${DATA_URL}?${params({ limit: PRIMEROS })}`);
            const completa = getJson(`${DATA_URL}?${params()}`);

            try {
                // Etapa 1: primeras filas
                const r1 = await rapida;
                if (seq !== loadSeq) return;
                $('so-count').textContent = r1.total + ' pedido(s)';
                updateSortIndicators();
                if (!r1.rows.length) {
                    $('so-tbody').innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No se encontraron pedidos.</td></tr>`;
                    completa.catch(() => {});
                    return;
                }
                $('so-tbody').innerHTML = '';
                pintarFilas(r1.rows, 0, r1.rows.length);
                pintados = r1.rows.length;

                // Etapa 2: resto
                const r2 = await completa;
                if (seq !== loadSeq) return;
                $('so-count').textContent = r2.total + ' pedido(s)';
                pintarRestante(r2.rows, seq);
            } catch (e) {
                if (seq !== loadSeq) return;
                if (pintados === 0) {
                    $('so-tbody').innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-red-400">Error cargando datos.</td></tr>`;
                }
            }
        }

        window.SOT = {
            sort(col) {
                if (state.sortBy === col) {
                    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortBy  = col;
                    state.sortDir = 'asc';
                }
                guardarFiltros();
                load();
            },
        };

        let searchTimeout;
        $('so-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                state.search = this.value;
                guardarFiltros();
                load();
            }, 350);
        });

        $('so-status').addEventListener('change', function() {
            state.status = this.value;
            guardarFiltros();
            load();
        });

        $('so-facturada').addEventListener('change', function() {
            state.facturada = this.value;
            guardarFiltros();
            load();
        });

        $('so-pago').addEventListener('change', function() {
            state.pago = this.value;
            guardarFiltros();
            load();
        });

        $('so-desde').addEventListener('change', function() {
            // Vacío (alguien le dio clic a la "x" del input date) = volver
            // al mes actual, no "sin filtro" — evita el caso real donde
            // esto mostraba TODOS los pedidos de la historia (112 páginas).
            const mesDesde = '{{ now()->startOfMonth()->format('Y-m-d') }}';
            state.fechaDesde = this.value || mesDesde;
            this.value = state.fechaDesde;
            guardarFiltros();
            load();
        });

        $('so-hasta').addEventListener('change', function() {
            const mesHasta = '{{ now()->endOfMonth()->format('Y-m-d') }}';
            state.fechaHasta = this.value || mesHasta;
            this.value = state.fechaHasta;
            guardarFiltros();
            load();
        });

        $('so-clear').addEventListener('click', function() {
            const mesDesde = '{{ now()->startOfMonth()->format('Y-m-d') }}';
            const mesHasta = '{{ now()->endOfMonth()->format('Y-m-d') }}';
            state.search = ''; state.status = ''; state.facturada = ''; state.pago = '';
            state.fechaDesde = mesDesde; state.fechaHasta = mesHasta;
            $('so-search').value = '';
            $('so-status').value = '';
            $('so-facturada').value = '';
            $('so-pago').value = '';
            $('so-desde').value  = mesDesde;
            $('so-hasta').value  = mesHasta;
            guardarFiltros();
            load();
        });

        load();
    })();
    </script>

</x-admin-layout>