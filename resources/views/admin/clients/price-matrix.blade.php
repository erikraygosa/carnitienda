<x-admin-layout
    title="Lista de precios personalizada"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Clientes','url'=>route('admin.clients.index')],
        ['name'=>'Lista de precios'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.clients.price-history') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50 mr-2">🕒 Historial</a>
        <a href="{{ route('admin.clients.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Volver</a>
    </x-slot>

    <x-wire-card>

        {{-- Filtros --}}
        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Buscar cliente</label>
                <input type="text" id="pm-cliente" placeholder="Nombre del cliente..."
                       class="w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Buscar producto</label>
                <input type="text" id="pm-producto" placeholder="Nombre o SKU del producto..."
                       class="w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Clientes por página</label>
                <select id="pm-per-page" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="ml-auto text-xs text-gray-400" id="pm-info"></div>
        </div>

        <div id="pm-warning" class="hidden mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"></div>

        {{-- Grid --}}
        <div class="border rounded-lg overflow-auto" style="max-height: 70vh;">
            <table class="text-sm border-collapse" id="pm-table">
                <thead id="pm-thead" class="sticky top-0 z-20"></thead>
                <tbody id="pm-tbody"></tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div id="pm-pagination" class="mt-4 flex items-center justify-between text-sm text-gray-500"></div>

        <p class="mt-3 text-xs text-gray-400">
            Los cambios se guardan solos al salir de cada celda (Tab / clic afuera). Celda verde = precio personalizado capturado.
            Todos los cambios quedan registrados en la auditoría del cliente.
        </p>
    </x-wire-card>

    {{-- Toast --}}
    <div id="pm-toast" class="hidden fixed bottom-6 right-6 px-4 py-2 rounded-lg text-white text-sm shadow-lg z-50"></div>

    <script>
    (function(){
        const DATA_URL = '{{ route('admin.clients.price-matrix.data') }}';
        const SAVE_URL = '{{ route('admin.clients.price-matrix.save') }}';
        const CSRF     = '{{ csrf_token() }}';

        let state = {
            cliente:  '',
            producto: '',
            perPage:  25,
            page:     1,
            lastPage: 1,
            products: [],
            saving:   {},   // "clientId:productId" -> true mientras guarda
        };

        const $   = id => document.getElementById(id);
        const fmt = n  => Number(n||0).toFixed(2);
        let searchTimer = null;

        function showToast(msg, ok = true) {
            const t = $('pm-toast');
            t.textContent = msg;
            t.className = `fixed bottom-6 right-6 px-4 py-2 rounded-lg text-white text-sm shadow-lg z-50 ${ok ? 'bg-emerald-600' : 'bg-red-600'}`;
            t.classList.remove('hidden');
            clearTimeout(t._t);
            t._t = setTimeout(() => t.classList.add('hidden'), 2500);
        }

        async function load() {
            $('pm-tbody').innerHTML = `<tr><td class="px-3 py-8 text-center text-gray-400">Cargando...</td></tr>`;

            const params = new URLSearchParams({
                cliente:  state.cliente,
                producto: state.producto,
                per_page: state.perPage,
                page:     state.page,
            });

            try {
                const res  = await fetch(`${DATA_URL}?${params}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                state.lastPage = data.last_page;
                state.products = data.products;

                $('pm-info').textContent = `${data.total_clientes} cliente(s) · ${data.total_productos} producto(s)`;

                const warn = $('pm-warning');
                if (data.productos_limitados) {
                    warn.textContent = `Hay más de ${data.products.length} productos — solo se muestran los primeros ${data.products.length}. Usa el buscador de producto para acotar.`;
                    warn.classList.remove('hidden');
                } else {
                    warn.classList.add('hidden');
                }

                renderHead(data.products);
                renderBody(data.clients, data.products, data.overrides);
                renderPagination(data.total_clientes, data.page, data.last_page);
            } catch(e) {
                $('pm-tbody').innerHTML = `<tr><td class="px-3 py-8 text-center text-red-400">Error cargando datos.</td></tr>`;
            }
        }

        function renderHead(products) {
            const thead = $('pm-thead');
            const thBase = th => `<th class="px-2 py-1 text-right text-xs font-medium text-gray-500 bg-orange-50 border-b whitespace-nowrap min-w-[110px]">${th}</th>`;

            let colsBase = '';
            let colsCosto = '';
            let colsNombre = '';
            products.forEach(p => {
                colsNombre += `<th class="px-2 py-2 text-center text-xs font-semibold text-gray-700 bg-gray-100 border-b border-l whitespace-nowrap min-w-[110px]">${p.nombre}</th>`;
                colsBase   += `<th class="px-2 py-1 text-right text-xs text-gray-500 bg-orange-50 border-l whitespace-nowrap">$${fmt(p.precio_base)}</th>`;
                colsCosto  += `<th class="px-2 py-1 text-right text-xs text-gray-400 bg-orange-50 border-l border-b whitespace-nowrap">$${fmt(p.costo_promedio)}</th>`;
            });

            thead.innerHTML = `
                <tr>
                    <th rowspan="3" class="sticky left-0 z-10 bg-gray-100 px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-r min-w-[220px]">Cliente</th>
                    ${colsNombre}
                </tr>
                <tr>${colsBase}</tr>
                <tr>${colsCosto}</tr>
            `;
        }

        function renderBody(clients, products, overrides) {
            const tbody = $('pm-tbody');
            if (!clients.length) {
                tbody.innerHTML = `<tr><td class="px-3 py-8 text-center text-gray-400">Sin resultados.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            clients.forEach(c => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';

                let cells = '';
                products.forEach(p => {
                    const val = (overrides[c.id] || {})[p.id];
                    const hasOverride = val !== undefined && val !== null;
                    cells += `
                        <td class="p-0 border-l">
                            <input type="number" step="0.01" min="0"
                                   class="pm-price-input w-full h-full px-2 py-1.5 text-right text-sm font-mono border-0 focus:ring-2 focus:ring-indigo-400 ${hasOverride ? 'bg-emerald-50' : ''}"
                                   data-client="${c.id}" data-product="${p.id}"
                                   data-original="${hasOverride ? val : ''}"
                                   placeholder="—"
                                   value="${hasOverride ? fmt(val) : ''}">
                        </td>
                    `;
                });

                tr.innerHTML = `
                    <td class="sticky left-0 z-10 bg-white px-3 py-1.5 border-r">
                        <div class="text-sm font-medium text-gray-800">${c.nombre}</div>
                        <div class="text-[10px] ${c.activo ? 'text-emerald-600' : 'text-gray-400'}">${c.activo ? 'ACTIVO' : 'INACTIVO'}</div>
                    </td>
                    ${cells}
                `;
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.pm-price-input').forEach(inp => {
                inp.addEventListener('blur', onCellBlur);
                inp.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') this.blur();
                });
            });
        }

        async function onCellBlur(e) {
            const inp       = e.target;
            const clientId  = inp.dataset.client;
            const productId = inp.dataset.product;
            const original  = inp.dataset.original;
            const raw       = inp.value.trim();

            // Sin cambio real, no llamamos al servidor
            const newVal = raw === '' ? '' : fmt(raw);
            const oldVal = original === '' ? '' : fmt(original);
            if (newVal === oldVal) return;

            const precio = raw === '' ? 0 : parseFloat(raw);
            if (isNaN(precio) || precio < 0) {
                showToast('Precio inválido', false);
                inp.value = original === '' ? '' : fmt(original);
                return;
            }

            inp.disabled = true;
            try {
                const res = await fetch(SAVE_URL, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ client_id: clientId, product_id: productId, precio }),
                });
                const data = await res.json();
                if (data.ok) {
                    inp.dataset.original = data.precio;
                    inp.value = fmt(data.precio);
                    inp.classList.add('bg-emerald-50');
                    showToast('✓ Precio actualizado');
                } else {
                    showToast('No se pudo guardar', false);
                    inp.value = original === '' ? '' : fmt(original);
                }
            } catch (err) {
                showToast('Error: ' + err.message, false);
                inp.value = original === '' ? '' : fmt(original);
            } finally {
                inp.disabled = false;
            }
        }

        function renderPagination(total, page, lastPage) {
            const wrap = $('pm-pagination');
            const from = total === 0 ? 0 : ((page - 1) * state.perPage) + 1;
            const to   = Math.min(page * state.perPage, total);
            wrap.innerHTML = `
                <span>${from}–${to} de ${total} cliente(s)</span>
                <div class="flex gap-1">
                    <button type="button" onclick="PM.goPage(${page - 1})"
                            class="px-3 py-1 rounded border text-xs ${page <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page <= 1 ? 'disabled' : ''}>← Ant</button>
                    <span class="px-3 py-1 text-xs">Pág ${page} / ${lastPage}</span>
                    <button type="button" onclick="PM.goPage(${page + 1})"
                            class="px-3 py-1 rounded border text-xs ${page >= lastPage ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page >= lastPage ? 'disabled' : ''}>Sig →</button>
                </div>
            `;
        }

        window.PM = {
            goPage(p) {
                if (p < 1 || p > state.lastPage) return;
                state.page = p;
                load();
            },
        };

        function onSearchChange() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.cliente  = $('pm-cliente').value.trim();
                state.producto = $('pm-producto').value.trim();
                state.page = 1;
                load();
            }, 350);
        }

        $('pm-cliente').addEventListener('input', onSearchChange);
        $('pm-producto').addEventListener('input', onSearchChange);
        $('pm-per-page').addEventListener('change', function() {
            state.perPage = parseInt(this.value, 10);
            state.page = 1;
            load();
        });

        load();
    })();
    </script>

</x-admin-layout>
