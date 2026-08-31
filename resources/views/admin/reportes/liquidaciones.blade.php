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
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Fecha</label>
                <input type="date" id="lq-fecha"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Ruta</label>
                <select id="lq-ruta"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todas las rutas</option>
                    @foreach($routes as $route)
                        <option value="{{ $route->id }}">{{ $route->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Estatus</label>
                <select id="lq-estatus"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="pendientes">Solo pendientes</option>
                    <option value="todas" selected>Todas</option>
                    <option value="no_entregado">No entregados</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Ruta del día</label>
                <select id="lq-ronda"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Ambas</option>
                    <option value="1">1ra ruta</option>
                    <option value="2">2da ruta</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" id="lq-clear" class="text-xs text-indigo-600 hover:underline">
                    Limpiar filtros
                </button>
            </div>
        </div>

        {{-- Resumen --}}
        <div id="lq-summary-bar" class="flex flex-wrap gap-3 mb-3 text-sm text-gray-600"></div>

        {{-- Barra de liquidación masiva (aparece al marcar checks) --}}
        <div id="lq-bulk-bar" class="hidden sticky top-0 z-10 mb-4 flex items-center justify-between gap-3 px-4 py-2.5 rounded-lg border border-emerald-300 bg-emerald-50">
            <span id="lq-bulk-count" class="text-sm font-medium text-emerald-800"></span>
            <div class="flex items-center gap-3">
                <span id="lq-bulk-total" class="text-sm font-bold text-emerald-800"></span>
                <button type="button" id="lq-bulk-clear" class="text-xs text-emerald-700 hover:underline">Quitar selección</button>
                <button type="button" id="lq-bulk-liquidar"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                    <i class="fa-solid fa-money-bill-wave"></i>
                    Liquidar en efectivo
                </button>
            </div>
        </div>

        {{-- Concentrado por ruta --}}
        <div id="lq-body" class="space-y-6">
            <div class="text-center py-8 text-gray-400">Cargando...</div>
        </div>

    </x-wire-card>

    <script>
    (function(){
        const CONCENTRADO_URL     = '{{ route('admin.reportes.liquidaciones.concentrado') }}';
        const EXPORT_URL          = '{{ route('admin.reportes.liquidaciones.export') }}';
        const LIQUIDAR_MASIVO_URL = '{{ route('admin.ar-payments.liquidar-masivo') }}';
        const CSRF                = '{{ csrf_token() }}';

        const hoy = new Date().toISOString().slice(0,10);

        let state = {
            fecha:         hoy,
            routeId:       '',
            filtroEstatus: 'todas',
            ronda:         '',
        };

        // folio -> {order_id, cliente, total} de las notas PENDIENTES seleccionadas
        // para liquidar de un jalón en efectivo.
        let seleccionadas = new Map();

        const $ = id => document.getElementById(id);

        const fmtMoney = v => '$' + parseFloat(v || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        function escHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        async function load() {
            $('lq-body').innerHTML = `<div class="text-center py-8 text-gray-400">Cargando...</div>`;
            $('lq-summary-bar').innerHTML = '';
            seleccionadas.clear();
            actualizarBarraSeleccion();

            const params = new URLSearchParams({
                fecha:          state.fecha,
                route_id:       state.routeId,
                filtro_estatus: state.filtroEstatus,
                ronda:          state.ronda,
            });

            const exportParams = new URLSearchParams({ fecha: state.fecha, route_id: state.routeId, filtro_estatus: state.filtroEstatus, ronda: state.ronda });
            $('lq-export-btn').href = `${EXPORT_URL}?${exportParams}`;

            try {
                const res  = await fetch(`${CONCENTRADO_URL}?${params}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                renderConcentrado(data);
            } catch(e) {
                $('lq-body').innerHTML = `<div class="text-center py-8 text-red-400">Error cargando datos.</div>`;
            }
        }

        function pedidoBadge(label, cls) {
            return `<span class="px-2 py-0.5 text-xs rounded-full font-medium ${cls}">${label}</span>`;
        }

        // Liquidación PENDIENTE + cliente conocido → link directo a Cobrar CxC
        // ya con el cliente preseleccionado. Se abre en ventana aparte y, al
        // cerrarla (ya sea que se cobró o no), este reporte se recarga solo
        // para reflejar el estatus actualizado — sin que el usuario tenga
        // que refrescar a mano.
        function liqBadge(n, arPaymentUrl) {
            const badge = pedidoBadge(n.estatus, n.liq_class);
            if (n.estatus !== 'PENDIENTE' || !n.client_id || !arPaymentUrl) return badge;
            const url = `${arPaymentUrl}?client_id=${n.client_id}`;
            return `<a href="${url}" onclick="abrirCobroCxc(event, '${url}')" class="hover:opacity-75" title="Cobrar CxC de este cliente">${badge}</a>`;
        }

        window.abrirCobroCxc = function (event, url) {
            event.preventDefault();
            const win = window.open(url, 'cobro-cxc', 'width=1100,height=800');
            if (!win) { window.location.href = url; return; }
            const timer = setInterval(function () {
                if (win.closed) {
                    clearInterval(timer);
                    load();
                }
            }, 500);
        };

        function renderConcentrado(data) {
            const body = $('lq-body');

            const cxcPorRuta = {};
            (data.cxc_asignadas || []).forEach(g => { cxcPorRuta[g.ruta] = g; });

            if (!data.rutas || data.rutas.length === 0) {
                let sinRutasHtml = `<div class="text-center py-8 text-gray-400">Sin resultados para los filtros seleccionados.</div>`;
                (data.cxc_asignadas || []).forEach(g => { sinRutasHtml += renderCxc(g); });
                sinRutasHtml += renderPendientes(data.pendientes_procesar || [], data.pendientes_label, data.pendientes_url);
                body.innerHTML = sinRutasHtml;
                return;
            }

            // Summary bar
            $('lq-summary-bar').innerHTML = `
                <span class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-medium">${data.total} notas</span>
                <span class="bg-amber-50 text-amber-700 px-3 py-1 rounded-full text-xs font-medium">Total general: $${data.total_monto}</span>
                <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-medium">${data.rutas.length} ruta(s)</span>
            `;

            // Render each route block
            let html = '';
            data.rutas.forEach(grupo => {
                const rows = grupo.notas.map(n => {
                    const seleccionable = n.estatus === 'PENDIENTE' && n.client_id && n.order_id;
                    const marcada = seleccionable && seleccionadas.has(n.order_id);
                    const check = seleccionable
                        ? `<input type="checkbox" class="lq-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                  ${marcada ? 'checked' : ''}
                                  data-order-id="${n.order_id}" data-cliente="${escHtml(n.cliente)}" data-monto="${n.total}"
                                  onchange="toggleSeleccion(this)">`
                        : '';
                    return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-center">${check}</td>
                        <td class="px-3 py-2 font-mono text-xs text-indigo-700 font-medium whitespace-nowrap">
                            <a href="${n.url}" class="hover:underline" title="Ir al pedido">${n.folio}</a>
                        </td>
                        <td class="px-3 py-2 text-sm text-gray-700">
                            ${n.cliente}
                            <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-medium ${n.ronda === 2 ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-500'}">${n.ronda === 2 ? '2da' : '1ra'}</span>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">${n.fecha}</td>
                        <td class="px-3 py-2 text-sm text-right font-mono font-semibold text-gray-800">${fmtMoney(n.total)}</td>
                        <td class="px-3 py-2 text-center">${pedidoBadge(n.estatus_pedido, n.pedido_class)}</td>
                        <td class="px-3 py-2 text-center">${liqBadge(n, data.ar_payment_url)}</td>
                    </tr>
                `;
                }).join('');

                html += `
                    <div class="rounded-lg border border-gray-200 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-2.5 bg-indigo-600">
                            <span class="text-sm font-bold text-white uppercase tracking-wide">
                                <i class="fa-solid fa-route mr-1.5 opacity-75"></i>${grupo.ruta}
                            </span>
                            <div class="flex items-center gap-4">
                                <span class="text-xs text-indigo-200">${grupo.count} nota(s)</span>
                                <span class="text-sm font-bold text-white">${fmtMoney(grupo.subtotal)}</span>
                            </div>
                        </div>
                        <table class="min-w-full text-sm divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-8"></th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estatus pedido</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Liquidación</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                ${rows}
                            </tbody>
                            <tfoot class="bg-gray-50 border-t border-gray-200">
                                <tr>
                                    <td colspan="4" class="px-3 py-2 text-xs font-semibold text-gray-600 text-right">Subtotal ${grupo.ruta}:</td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-gray-800">${fmtMoney(grupo.subtotal)}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `;

                // CxC asignadas al chofer de esta misma ruta/despacho
                if (cxcPorRuta[grupo.ruta]) {
                    html += renderCxc(cxcPorRuta[grupo.ruta]);
                    delete cxcPorRuta[grupo.ruta];
                }
            });

            // Grand total
            html += `
                <div class="flex items-center justify-end gap-4 px-4 py-3 bg-gray-800 rounded-lg text-white">
                    <span class="text-sm font-medium">TOTAL GENERAL</span>
                    <span class="text-lg font-bold font-mono">$${data.total_monto}</span>
                </div>
            `;

            // Cualquier CxC cuya ruta no tuvo notas en este filtro (caso raro) va al final
            Object.values(cxcPorRuta).forEach(g => { html += renderCxc(g); });

            html += renderPendientes(data.pendientes_procesar || [], data.pendientes_label, data.pendientes_url);

            body.innerHTML = html;
        }

        function cxcBadge(status) {
            const cls = {
                COBRADO:    'bg-emerald-100 text-emerald-700',
                PARCIAL:    'bg-amber-100 text-amber-700',
                NO_COBRADO: 'bg-red-100 text-red-700',
                PENDIENTE:  'bg-gray-100 text-gray-600',
            }[status] || 'bg-gray-100 text-gray-600';
            return pedidoBadge(status, cls);
        }

        function renderCxc(cxc) {
            if (!cxc || !cxc.clientes || cxc.clientes.length === 0) return '';

            const rows = cxc.clientes.map(c => `
                <div class="flex items-center gap-3 px-4 py-3 border-b last:border-b-0 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-gray-800">${c.cliente}</div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            ${c.notas_pendientes > 0
                                ? c.notas_pendientes + ' nota(s) pendiente(s) — ' + (c.folios_pendientes || '')
                                : 'Sin notas pendientes'}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-400">Saldo pendiente</div>
                        <div class="font-semibold text-amber-700">${fmtMoney(c.saldo_pendiente)}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-400">Cobrado</div>
                        <div class="font-semibold text-emerald-700">${c.monto_cobrado > 0 ? fmtMoney(c.monto_cobrado) : '—'}</div>
                    </div>
                    ${cxcBadge(c.status)}
                </div>
            `).join('');

            return `
                <div class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-violet-600">
                        <span class="text-sm font-bold text-white uppercase tracking-wide">
                            <i class="fa-solid fa-hand-holding-dollar mr-1.5 opacity-75"></i>CxC asignadas al chofer — ${cxc.ruta}
                        </span>
                        <span class="text-xs text-violet-200">${cxc.count} cliente(s)</span>
                    </div>
                    ${rows}
                    <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-t">
                        <span class="text-xs font-semibold text-gray-600">Totales:</span>
                        <div class="flex gap-6">
                            <div class="text-right">
                                <div class="text-xs text-gray-400">Saldo pendiente</div>
                                <div class="font-bold text-amber-700">${fmtMoney(cxc.total_saldo)}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-400">Cobrado</div>
                                <div class="font-bold text-emerald-700">${fmtMoney(cxc.total_cobrado)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderPendientes(pendientes, label, url) {
            label = label || 'pendientes por procesar';
            url = url || '{{ route('admin.sales-orders.index') }}';
            const rows = pendientes.map(p => `
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono text-xs text-indigo-700 font-medium whitespace-nowrap">
                        <a href="${p.url}" class="hover:underline">${p.folio}</a>
                    </td>
                    <td class="px-3 py-2 text-sm text-gray-700">${p.cliente}</td>
                    <td class="px-3 py-2 text-sm text-gray-500">${p.ruta}</td>
                    <td class="px-3 py-2 text-center">${pedidoBadge(p.estatus, p.estatus === 'Preparando' ? 'bg-sky-100 text-sky-700' : 'bg-blue-100 text-blue-700')}</td>
                    <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">${p.fecha}</td>
                    <td class="px-3 py-2 text-sm text-right font-mono font-semibold text-gray-800">${fmtMoney(p.total)}</td>
                </tr>
            `).join('');

            return `
                <div class="rounded-lg border border-amber-200 overflow-hidden mt-2">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-amber-500">
                        <span class="text-sm font-bold text-white uppercase tracking-wide">
                            <i class="fa-solid fa-triangle-exclamation mr-1.5 opacity-75"></i>
                            Pedidos ${label}
                        </span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-amber-100">${pendientes.length} pedido(s)</span>
                            <a href="${url}"
                               class="text-xs bg-white text-amber-700 font-semibold px-2 py-1 rounded hover:bg-amber-50">
                                Ir a pedidos
                            </a>
                        </div>
                    </div>
                    ${pendientes.length === 0
                        ? `<div class="text-center py-4 text-sm text-gray-400">No hay pedidos ${label}.</div>`
                        : `<table class="min-w-full text-sm divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ruta</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estatus</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Programado</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">${rows}</tbody>
                        </table>`
                    }
                </div>
            `;
        }

        // ── Liquidación masiva en efectivo ──────────────────────────────────
        window.toggleSeleccion = function (checkbox) {
            const orderId = parseInt(checkbox.dataset.orderId, 10);
            if (checkbox.checked) {
                seleccionadas.set(orderId, {
                    cliente: checkbox.dataset.cliente,
                    monto:   parseFloat(checkbox.dataset.monto || 0),
                });
            } else {
                seleccionadas.delete(orderId);
            }
            actualizarBarraSeleccion();
        };

        function actualizarBarraSeleccion() {
            const bar = $('lq-bulk-bar');
            if (seleccionadas.size === 0) { bar.classList.add('hidden'); return; }

            let total = 0;
            seleccionadas.forEach(v => total += v.monto);

            $('lq-bulk-count').textContent = `${seleccionadas.size} nota(s) seleccionada(s) para liquidar`;
            $('lq-bulk-total').textContent = fmtMoney(total);
            bar.classList.remove('hidden');
        }

        $('lq-bulk-clear').addEventListener('click', function() {
            document.querySelectorAll('.lq-check:checked').forEach(cb => cb.checked = false);
            seleccionadas.clear();
            actualizarBarraSeleccion();
        });

        $('lq-bulk-liquidar').addEventListener('click', function() {
            if (seleccionadas.size === 0) return;

            let total = 0;
            const clientes = new Set();
            seleccionadas.forEach(v => { total += v.monto; clientes.add(v.cliente); });

            Swal.fire({
                title: '¿Liquidar en efectivo?',
                html: `Se van a marcar como <b>LIQUIDADAS</b> ${seleccionadas.size} nota(s) de
                       ${clientes.size} cliente(s), por un total de <b>${fmtMoney(total)}</b>,
                       registrando el cobro como <b>pago en efectivo</b>.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, liquidar',
                cancelButtonText: 'Cancelar',
            }).then(async r => {
                if (!r.isConfirmed) return;

                const btn = $('lq-bulk-liquidar');
                btn.disabled = true;
                btn.classList.add('opacity-60');

                try {
                    const res = await fetch(LIQUIDAR_MASIVO_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                        },
                        body: JSON.stringify({ order_ids: Array.from(seleccionadas.keys()), fecha: state.fecha }),
                    });
                    const data = await res.json();

                    if (!res.ok || !data.ok) {
                        Swal.fire('No se pudo liquidar', data.message || 'Intenta de nuevo.', 'error');
                        return;
                    }

                    seleccionadas.clear();
                    Swal.fire('Liquidado', `Se registraron ${data.clientes} cobro(s) en efectivo por ${fmtMoney(data.total)}.`, 'success');
                    load();
                } catch (e) {
                    Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                } finally {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60');
                }
            });
        });

        $('lq-fecha').addEventListener('change',   function() { state.fecha         = this.value; load(); });
        $('lq-ruta').addEventListener('change',    function() { state.routeId       = this.value; load(); });
        $('lq-estatus').addEventListener('change', function() { state.filtroEstatus = this.value; load(); });
        $('lq-ronda').addEventListener('change',   function() { state.ronda         = this.value; load(); });

        $('lq-clear').addEventListener('click', function() {
            state.fecha = hoy; state.routeId = ''; state.filtroEstatus = 'todas'; state.ronda = '';
            $('lq-fecha').value   = hoy;
            $('lq-ruta').value    = '';
            $('lq-estatus').value = 'todas';
            $('lq-ronda').value   = '';
            load();
        });

        $('lq-fecha').value = hoy;
        load();
    })();
    </script>

</x-admin-layout>
