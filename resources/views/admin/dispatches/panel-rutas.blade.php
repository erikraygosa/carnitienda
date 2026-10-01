<x-admin-layout
    title="Panel de rutas"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')],['name'=>'Despachos','url'=>route('admin.dispatches.index')],['name'=>'Panel de rutas']]"
>
    <x-slot name="action">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-full font-medium {{ $modoAutomatico ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
            <i class="fa-solid fa-route"></i>
            {{ $modoAutomatico ? 'Rutas automáticas' : 'Manual' }}
        </span>
        <a href="{{ route('admin.dispatches.index') }}"
           class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">Ver despachos</a>
    </x-slot>

    <x-wire-card>
        <div id="pr-aviso-nuevos" class="hidden mb-3 flex items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            <span>🔔 Hay pedidos nuevos.</span>
            <button type="button" onclick="location.reload()"
                    class="px-3 py-1 text-xs rounded-md bg-amber-600 text-white hover:bg-amber-700 whitespace-nowrap">
                Recargar
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Día</label>
                <input type="date" id="pr-fecha"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">Buscar en sin asignación</label>
                <input type="text" id="pr-search" placeholder="Folio o cliente..."
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex items-end">
                <button type="button" id="pr-refresh" class="text-xs text-indigo-600 hover:underline">
                    Actualizar
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
            {{-- Sin asignación --}}
            <div class="rounded-lg border border-amber-200 overflow-hidden flex flex-col">
                <div class="px-3 py-2 bg-amber-500 text-white text-sm font-bold uppercase tracking-wide flex items-center justify-between">
                    <span><i class="fa-solid fa-box-open mr-1.5 opacity-75"></i>Sin Asignación</span>
                    <span id="pr-sueltos-count" class="text-xs font-normal"></span>
                </div>
                <div id="pr-sueltos" data-dropzone="sueltos"
                     class="p-2 space-y-1.5 min-h-[120px] bg-amber-50/40 flex-1 overflow-y-auto" style="max-height:75vh">
                </div>
            </div>

            {{-- Rutas --}}
            <div id="pr-rutas" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            </div>
        </div>
    </x-wire-card>

    <form id="pr-form-post" method="POST" style="display:none">@csrf</form>

    <script>
    (function(){
        const DATA_URL    = '{{ route('admin.dispatches.panel-rutas.data') }}';
        const MOVER_URL   = '{{ route('admin.dispatches.panel-rutas.mover') }}';
        const ASEGURAR_URL= '{{ route('admin.dispatches.panel-rutas.asegurar') }}';
        const POLL_URL    = '{{ route('admin.dispatches.panel-rutas.poll-count') }}';
        const CSRF         = '{{ csrf_token() }}';
        const FORMATO_IMPRESION = '{{ $formatoImpresion }}';
        const ROUTES = @json($routes->map(fn($r) => ['id' => $r->id, 'nombre' => $r->nombre])->values());

        const hoy = new Date().toISOString().slice(0,10);
        let state = { fecha: hoy, search: '' };

        const $ = id => document.getElementById(id);
        const fmtMoney = v => '$' + parseFloat(v || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        function escHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function urlDespacho(id) {
            return `{{ url('admin/dispatches') }}/${id}/edit`;
        }
        function urlPrint(id) {
            const path = FORMATO_IMPRESION === 'liquidaciones' ? 'liquidacion' : 'ruta';
            return `{{ url('admin/dispatches') }}/${id}/print/${path}`;
        }

        function postForm(action) {
            const f = $('pr-form-post');
            f.action = action;
            f.submit();
        }

        let loadSeq = 0;

        async function load() {
            const mySeq = ++loadSeq;
            $('pr-sueltos').innerHTML = '<div class="text-center py-6 text-gray-400 text-xs">Cargando...</div>';
            $('pr-rutas').innerHTML = '<div class="col-span-full text-center py-8 text-gray-400">Cargando...</div>';

            try {
                const params = new URLSearchParams({ fecha: state.fecha, search: state.search });
                const res  = await fetch(`${DATA_URL}?${params}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (mySeq !== loadSeq) return; // una carga más nueva ya está en curso/terminó, ignorar esta respuesta tardía
                renderSueltos(data.sueltos || []);
                renderRutas(data.rutas || []);
            } catch (e) {
                if (mySeq !== loadSeq) return;
                $('pr-rutas').innerHTML = '<div class="col-span-full text-center py-8 text-red-400">Error cargando datos.</div>';
            }
        }

        function pedidoChip(p, origenTipo, origenKey) {
            const envioBtn = origenTipo === 'sueltos'
                ? `<button type="button" class="shrink-0 w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] shadow hover:bg-indigo-700 flex items-center justify-center"
                           title="Enviar a ruta" onclick="event.stopPropagation(); prEnviarARuta(${p.order_id})">
                       <i class="fa-solid fa-paper-plane"></i>
                   </button>`
                : '';
            return `
                <div class="pr-chip rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs shadow-sm cursor-grab active:cursor-grabbing"
                     draggable="true" data-order-id="${p.order_id}" data-origen-tipo="${origenTipo}" data-origen-key="${origenKey || ''}">
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-mono font-medium text-indigo-700 truncate">${escHtml(p.folio)}</span>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="font-semibold text-gray-700 whitespace-nowrap">${fmtMoney(p.total)}</span>
                            ${envioBtn}
                        </div>
                    </div>
                    <div class="text-gray-500 truncate">${escHtml(p.cliente)}</div>
                    ${p.status === 'EN_RUTA' ? `
                        <button type="button" class="mt-1 w-full text-[11px] bg-emerald-50 text-emerald-700 rounded px-1 py-0.5 hover:bg-emerald-100"
                                onclick="prEntregarPedido(${p.dispatch_id}, ${p.item_id})">
                            <i class="fa-solid fa-check"></i> Entregar
                        </button>` : ''}
                </div>
            `;
        }

        function renderSueltos(sueltos) {
            $('pr-sueltos-count').textContent = sueltos.length ? `${sueltos.length}` : '';
            if (sueltos.length === 0) {
                $('pr-sueltos').innerHTML = '<div class="text-center py-6 text-gray-400 text-xs">Sin pedidos sin asignación.</div>';
                return;
            }
            $('pr-sueltos').innerHTML = sueltos.map(p => pedidoChip({
                order_id: p.order_id, folio: p.folio, cliente: p.cliente, total: p.total, status: p.status,
            }, 'sueltos', '')).join('');
            bindDraggables();
        }

        function celdaBadge(status) {
            const map = {
                PLANEADO: 'bg-gray-100 text-gray-600',
                CARGADO: 'bg-sky-100 text-sky-700',
                EN_RUTA: 'bg-violet-100 text-violet-700',
                CERRADO: 'bg-amber-100 text-amber-700',
                ENTREGADO: 'bg-emerald-100 text-emerald-700',
            };
            return `<span class="px-1.5 py-0.5 rounded text-[10px] font-medium ${map[status] || 'bg-gray-100 text-gray-600'}">${status}</span>`;
        }

        function renderCelda(routeId, routeNombre, ronda, celda) {
            const key = `${routeId}:${ronda}`;
            const pedidosHtml = (celda.pedidos || []).map(p => pedidoChip({
                order_id: p.order_id, folio: p.folio, cliente: p.cliente, total: p.total, status: p.status,
                dispatch_id: celda.dispatch_id, item_id: p.item_id,
            }, 'ruta', key)).join('');

            const cxcHtml = (celda.cxc || []).map(c => `
                <div class="text-[11px] bg-violet-50 text-violet-700 rounded px-1.5 py-1">
                    <div class="flex items-center justify-between gap-1">
                        <span class="truncate">${escHtml(c.cliente)}</span>
                        <span class="font-semibold whitespace-nowrap ml-1">${fmtMoney(c.saldo_asignado)}</span>
                    </div>
                    ${c.folios ? `<div class="font-mono text-violet-500 truncate">${escHtml(c.folios)}</div>` : ''}
                </div>
            `).join('');

            const acciones = [];
            if (celda.dispatch_id) {
                acciones.push(`<a href="${urlDespacho(celda.dispatch_id)}" target="_blank" class="text-gray-500 hover:text-indigo-600" title="Abrir despacho"><i class="fa-solid fa-up-right-from-square"></i></a>`);
                acciones.push(`<a href="${urlPrint(celda.dispatch_id)}" target="_blank" class="text-gray-500 hover:text-indigo-600" title="Imprimir"><i class="fa-solid fa-print"></i></a>`);
            }

            return `
                <div class="rounded-md border border-gray-200 overflow-hidden flex-1 flex flex-col" data-dropzone="ruta" data-route-id="${routeId}" data-ronda="${ronda}">
                    <div class="px-2.5 py-1.5 bg-gray-50 border-b flex items-center justify-between gap-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-semibold text-gray-600">${ronda === 1 ? '1ra' : '2da'}</span>
                            ${celda.status ? celdaBadge(celda.status) : ''}
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            ${acciones.join('')}
                        </div>
                    </div>
                    <div class="p-2 space-y-1.5 min-h-[90px] flex-1" data-chips="1">
                        ${pedidosHtml}
                        ${cxcHtml}
                        ${!pedidosHtml && !cxcHtml ? '<div class="text-center py-4 text-gray-300 text-[11px]">—</div>' : ''}
                    </div>
                    <div class="px-2.5 py-1.5 bg-gray-50 border-t flex items-center justify-between text-[11px]">
                        <span class="font-semibold text-gray-700">${fmtMoney(celda.total)}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" class="text-violet-600 hover:underline" onclick="prAgregarCxc(${routeId}, ${ronda})">+ CxC</button>
                            ${celda.status === 'EN_RUTA' || celda.status === 'CARGADO' ? `
                                <button type="button" class="text-emerald-600 hover:underline" onclick="prEntregarTodo(${celda.dispatch_id})">Entregar todo</button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderRutas(rutas) {
            if (rutas.length === 0) {
                $('pr-rutas').innerHTML = '<div class="col-span-full text-center py-8 text-gray-400">No hay rutas activas.</div>';
                return;
            }

            $('pr-rutas').innerHTML = rutas.map(r => `
                <div class="rounded-lg border border-indigo-200 overflow-hidden">
                    <div class="px-3 py-2 bg-indigo-600 text-white text-sm font-bold uppercase tracking-wide">
                        <i class="fa-solid fa-truck mr-1.5 opacity-75"></i>${escHtml(r.nombre)}
                    </div>
                    <div class="p-2 flex gap-2">
                        ${renderCelda(r.route_id, r.nombre, 1, r.rondas[1])}
                        ${renderCelda(r.route_id, r.nombre, 2, r.rondas[2])}
                    </div>
                </div>
            `).join('');

            bindDraggables();
            bindDropzones($('pr-rutas'));
        }

        function bindDraggables() {
            document.querySelectorAll('.pr-chip').forEach(chip => {
                chip.addEventListener('dragstart', e => {
                    e.dataTransfer.setData('text/plain', chip.dataset.orderId);
                    chip.classList.add('opacity-40');
                });
                chip.addEventListener('dragend', () => chip.classList.remove('opacity-40'));
            });
        }

        function bindDropzones(root) {
            const zones = root.matches('[data-dropzone]')
                ? [root, ...root.querySelectorAll('[data-dropzone]')]
                : [...root.querySelectorAll('[data-dropzone]')];
            zones.forEach(zone => {
                zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('ring-2', 'ring-indigo-400'); });
                zone.addEventListener('dragleave', () => zone.classList.remove('ring-2', 'ring-indigo-400'));
                zone.addEventListener('drop', async e => {
                    e.preventDefault();
                    e.stopPropagation();
                    document.querySelectorAll('[data-dropzone]').forEach(z => z.classList.remove('ring-2', 'ring-indigo-400'));
                    const orderId = e.dataTransfer.getData('text/plain');
                    if (!orderId) return;

                    const payload = { order_id: orderId, fecha: state.fecha };
                    if (zone.dataset.dropzone === 'ruta') {
                        payload.shipping_route_id = zone.dataset.routeId;
                        payload.ronda = zone.dataset.ronda;
                    }

                    try {
                        const res = await fetch(MOVER_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                            body: JSON.stringify(payload),
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) {
                            Swal.fire('No se pudo mover', data.message || 'Intenta de nuevo.', 'error');
                        }
                    } catch (err) {
                        Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                    } finally {
                        await load();
                    }
                });
            });
        }

        window.prEntregarTodo = function (dispatchId) {
            if (!dispatchId) return;
            Swal.fire({
                title: '¿Confirmar entrega de toda la ruta?',
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Sí, entregar', cancelButtonText: 'Cancelar',
            }).then(r => {
                if (r.isConfirmed) postForm(`{{ url('admin/dispatches') }}/${dispatchId}/entregar`);
            });
        };

        window.prEntregarPedido = function (dispatchId, itemId) {
            if (!dispatchId || !itemId) return;
            postForm(`{{ url('admin/dispatches') }}/${dispatchId}/pedido/${itemId}/entregar`);
        };

        window.prEnviarARuta = async function (orderId) {
            const routeOptions = ROUTES.map(r => `<option value="${r.id}">${escHtml(r.nombre)}</option>`).join('');

            const { value: form } = await Swal.fire({
                title: 'Enviar a ruta',
                html: `
                    <div class="text-left space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Ruta</label>
                            <select id="swal-ruta" class="swal2-select" style="width:100%; margin:0;">
                                <option value="">Selecciona una ruta...</option>
                                ${routeOptions}
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Ronda</label>
                            <select id="swal-ronda" class="swal2-select" style="width:100%; margin:0;">
                                <option value="1">1ra</option>
                                <option value="2">2da</option>
                            </select>
                        </div>
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const routeId = document.getElementById('swal-ruta').value;
                    const ronda   = document.getElementById('swal-ronda').value;
                    if (!routeId) {
                        Swal.showValidationMessage('Selecciona una ruta.');
                        return false;
                    }
                    return { routeId, ronda };
                },
            });

            if (!form) return;

            try {
                const res = await fetch(MOVER_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ order_id: orderId, shipping_route_id: form.routeId, ronda: form.ronda, fecha: state.fecha }),
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    Swal.fire('No se pudo mover', data.message || 'Intenta de nuevo.', 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            } finally {
                load();
            }
        };

        window.prAgregarCxc = async function (routeId, ronda) {
            try {
                const res = await fetch(ASEGURAR_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ shipping_route_id: routeId, ronda: ronda, fecha: state.fecha }),
                });
                const data = await res.json();
                if (data.ok && data.dispatch_id) {
                    window.open(urlDespacho(data.dispatch_id), '_blank');
                    load();
                }
            } catch (e) {
                Swal.fire('Error', 'No se pudo crear/abrir el despacho.', 'error');
            }
        };

        // ── Polling: detectar pedidos nuevos para el día seleccionado ───
        // Mismo criterio que Panel de Surtido: no recarga sola (si alguien
        // está a media acción arrastrando algo no se le interrumpe), solo
        // avisa con un botón "Recargar".
        let pollKnownCount = null;
        function pollNuevos() {
            fetch(`${POLL_URL}?fecha=${state.fecha}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (pollKnownCount === null) {
                        pollKnownCount = data.count;
                    } else if (data.count > pollKnownCount) {
                        $('pr-aviso-nuevos').classList.remove('hidden');
                    }
                })
                .catch(() => {});
        }
        setInterval(pollNuevos, 30000);
        pollNuevos();

        $('pr-fecha').addEventListener('change', function() {
            state.fecha = this.value;
            pollKnownCount = null;
            $('pr-aviso-nuevos').classList.add('hidden');
            pollNuevos();
            load();
        });
        $('pr-search').addEventListener('input', function() {
            state.search = this.value;
            clearTimeout(window._prSearchDebounce);
            window._prSearchDebounce = setTimeout(load, 300);
        });
        $('pr-refresh').addEventListener('click', load);

        bindDropzones($('pr-sueltos'));

        $('pr-fecha').value = hoy;
        load();
    })();
    </script>
</x-admin-layout>
