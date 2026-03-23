<x-admin-layout
    title="Editar cliente"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Clientes', 'url' => route('admin.clients.index')],
        ['name' => 'Editar'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">

        {{-- FORM PRINCIPAL (UPDATE) --}}
        <form id="client-update-form" method="POST"
              action="{{ route('admin.clients.update', $client) }}"
              class="space-y-6">
            @csrf
            @method('PUT')

            @include('admin.clients.partials._form', ['client' => $client])

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.clients.index') }}"
                   class="inline-flex px-4 py-2 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                    Volver
                </a>
                <button form="client-update-form" type="submit"
                        class="inline-flex px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Actualizar
                </button>
            </div>
        </form>

        {{-- ====== EDITOR DE PRECIOS ====== --}}
        <div class="mt-8 rounded-xl border bg-white shadow">
            <div class="px-5 py-4 border-b flex flex-col md:flex-row md:items-center justify-between gap-3">
                <h3 class="text-lg font-semibold">Lista de precios personalizada</h3>
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="text" id="pe-search"
                           placeholder="Buscar producto (SKU/Nombre)..."
                           class="w-64 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <select id="pe-per-page"
                            class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="15">15 / pág</option>
                        <option value="25">25 / pág</option>
                        <option value="50">50 / pág</option>
                    </select>
                    <button type="button" id="btn-zero"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                     
                    </button>
                    <button type="button" id="btn-save"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Guardar precios
                    </button>
                </div>
            </div>

            <div class="p-5 overflow-x-auto">
                <table class="min-w-full divide-y text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-700">
                            <th class="px-3 py-2">SKU</th>
                            <th class="px-3 py-2">Nombre</th>
                            <th class="px-3 py-2 text-right">Base</th>
                            <th class="px-3 py-2 text-right">Lista cliente</th>
                            <th class="px-3 py-2 text-right">Precio personalizado</th>
                        </tr>
                    </thead>
                    <tbody id="pe-tbody" class="divide-y">
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-gray-400">Cargando...</td>
                        </tr>
                    </tbody>
                </table>
                <div id="pe-pagination" class="mt-4 flex items-center justify-between text-sm text-gray-500"></div>
            </div>

            <div class="px-5 py-4 border-t flex justify-between items-center">
                <p class="text-xs text-gray-400">
                    "Guardar" actualiza <code>client_price_overrides</code>. Campo vacío = $0.00
                </p>
                <button type="button" id="btn-save-bottom"
                        class="inline-flex px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Guardar precios
                </button>
            </div>
        </div>

        {{-- DESACTIVAR --}}
        <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" class="mt-4">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex px-4 py-2 text-sm rounded-md border border-red-300 text-red-600 bg-white hover:bg-red-50">
                Desactivar
            </button>
        </form>

    </div>

    {{-- Toast --}}
    <div id="pe-toast" class="hidden fixed bottom-6 right-6 px-4 py-2 rounded-lg text-white text-sm shadow-lg z-50"></div>

    <script>
    (function(){
        const DATA_URL = '{{ route('admin.clients.prices.data', $client) }}';
        const SAVE_URL = '{{ route('admin.clients.prices.save', $client) }}';
        const CSRF     = '{{ csrf_token() }}';

        let state = {
            search:   '',
            perPage:  15,
            page:     1,
            lastPage: 1,
            prices:   {},
            loaded:   false,
        };

        const $   = id => document.getElementById(id);
        const fmt = n  => Number(n||0).toFixed(2);

        function showToast(msg, ok = true) {
            const t = $('pe-toast');
            t.textContent = msg;
            t.className = `fixed bottom-6 right-6 px-4 py-2 rounded-lg text-white text-sm shadow-lg z-50 ${ok ? 'bg-emerald-600' : 'bg-red-600'}`;
            t.classList.remove('hidden');
            clearTimeout(t._t);
            t._t = setTimeout(() => t.classList.add('hidden'), 3000);
        }

        async function load() {
            $('pe-tbody').innerHTML = `<tr><td colspan="5" class="px-3 py-8 text-center text-gray-400">Cargando...</td></tr>`;

            const params = new URLSearchParams({
                search:   state.search,
                per_page: state.perPage,
                page:     state.page,
            });

            try {
                const res  = await fetch(`${DATA_URL}?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                state.lastPage = data.last_page;

                if (!state.loaded) {
                    state.prices = {};
                    for (const [pid, precio] of Object.entries(data.overrides)) {
                        state.prices[String(pid)] = precio;
                    }
                    state.loaded = true;
                }

                renderTable(data.products, data.overrides, data.listPrices);
                renderPagination(data.total, data.page, data.last_page);
            } catch(e) {
                $('pe-tbody').innerHTML = `<tr><td colspan="5" class="px-3 py-8 text-center text-red-400">Error cargando datos.</td></tr>`;
            }
        }

        function renderTable(products, overrides, listPrices) {
            const tbody = $('pe-tbody');
            if (!products.length) {
                tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-8 text-center text-gray-400">Sin resultados.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            products.forEach(p => {
                const pid       = String(p.id);
                const override  = state.prices[pid] !== undefined ? state.prices[pid] : (overrides[pid] ?? '');
                const listPrice = listPrices[pid];
                const tr        = document.createElement('tr');
                tr.className    = 'hover:bg-gray-50';
                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-xs text-gray-500">${p.sku || '—'}</td>
                    <td class="px-3 py-2 font-medium text-gray-800">${p.nombre}</td>
                    <td class="px-3 py-2 text-right font-mono text-gray-600">$${fmt(p.precio_base)}</td>
                    <td class="px-3 py-2 text-right font-mono text-gray-500">
                        ${listPrice != null ? '$'+fmt(listPrice) : '—'}
                    </td>
                    <td class="px-3 py-2 text-right">
                        <input type="number" step="0.01" min="0"
                               class="w-32 rounded-md border-gray-300 text-right font-mono text-sm pe-price-input"
                               data-id="${pid}"
                               placeholder="0.00"
                               value="${override !== '' && override !== null ? fmt(override) : ''}">
                    </td>
                `;
                tr.querySelector('.pe-price-input').addEventListener('input', function() {
                    state.prices[this.dataset.id] = this.value;
                });
                tbody.appendChild(tr);
            });
        }

        function renderPagination(total, page, lastPage) {
            const wrap = $('pe-pagination');
            const from = total === 0 ? 0 : ((page - 1) * state.perPage) + 1;
            const to   = Math.min(page * state.perPage, total);
            wrap.innerHTML = `
                <span>${from}–${to} de ${total}</span>
                <div class="flex gap-1">
                    <button type="button" onclick="PE.goPage(${page - 1})"
                            class="px-3 py-1 rounded border text-xs ${page <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page <= 1 ? 'disabled' : ''}>← Ant</button>
                    <span class="px-3 py-1 text-xs">Pág ${page} / ${lastPage}</span>
                    <button type="button" onclick="PE.goPage(${page + 1})"
                            class="px-3 py-1 rounded border text-xs ${page >= lastPage ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}"
                            ${page >= lastPage ? 'disabled' : ''}>Sig →</button>
                </div>
            `;
        }

        async function save() {
            // Recoger inputs visibles
            document.querySelectorAll('.pe-price-input').forEach(inp => {
                state.prices[inp.dataset.id] = inp.value;
            });

            try {
                const res  = await fetch(SAVE_URL, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ prices: state.prices }),
                });
                const data = await res.json();
                if (data.ok) {
                    showToast('✓ ' + data.message);
                    state.loaded = false;
                    load();
                } else {
                    showToast('Error al guardar', false);
                }
            } catch(e) {
                showToast('Error: ' + e.message, false);
            }
        }

        function setZeroForEmpty() {
            document.querySelectorAll('.pe-price-input').forEach(inp => {
                if (inp.value === '' || inp.value === null) {
                    inp.value = '0.00';
                    state.prices[inp.dataset.id] = '0.00';
                }
            });
            showToast('Campos vacíos puestos en $0.00');
        }

        window.PE = {
            goPage(p) {
                if (p < 1 || p > state.lastPage) return;
                state.page = p;
                load();
            },
        };

        // Listeners con IDs directos — sin onclick en HTML
        $('btn-zero').addEventListener('click', setZeroForEmpty);
        $('btn-save').addEventListener('click', save);
        $('btn-save-bottom').addEventListener('click', save);

        $('pe-search').addEventListener('input', function() {
            clearTimeout(this._t);
            this._t = setTimeout(() => {
                state.search = this.value;
                state.page   = 1;
                load();
            }, 350);
        });

        $('pe-per-page').addEventListener('change', function() {
            state.perPage = parseInt(this.value);
            state.page    = 1;
            load();
        });

        load();
    })();
    </script>

</x-admin-layout>