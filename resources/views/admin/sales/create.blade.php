<x-admin-layout
    title="Crear nota de venta"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Notas de venta','url'=>route('admin.sales.index')],
        ['name'=>'Crear'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="sale-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    @php
        $seedItems    = $seedItems ?? [];
        $initialItems = (is_array($seedItems) && count($seedItems)) ? $seedItems : [[
            'product_id'=>'','descripcion'=>'','cantidad'=>1,'precio'=>0,'descuento'=>0,'iva_pct'=>0,'impuesto'=>0,'total'=>0
        ]];

        $JS_OVERRIDES       = json_encode($overrides       ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES      = json_encode($listItems       ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_INITIALITEMS    = json_encode($initialItems,          JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_CLIENT_DEFAULTS = json_encode($clientDefaults  ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
        Venta directa de mostrador — al guardar, el inventario se descuenta de inmediato y la nota queda completa
        (no pasa por aprobación ni logística).
    </div>

    <x-wire-card>
        <form id="sale-form" method="POST" action="{{ route('admin.sales.store') }}" class="space-y-6">
            @csrf

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Caja --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Caja</label>
                    @if($cashRegisters->isEmpty())
                        <p class="text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-md p-2">
                            No hay ninguna caja abierta — abre una en
                            <a href="{{ route('admin.cash.create') }}" class="underline">Cajas</a> antes de vender.
                        </p>
                        <input type="hidden" name="cash_register_id" value="">
                    @else
                        <select name="cash_register_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                            @foreach($cashRegisters as $cr)
                                <option value="{{ $cr->id }}">{{ $cr->warehouse?->nombre ?? ('Caja #'.$cr->id) }} — {{ $cr->user?->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (string) old('warehouse_id', $mainWarehouseId ?? '') === (string) $w->id ? 'selected' : '' }}>{{ $w->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">-- público general --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ (string) old('client_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    <p id="credito-info" class="mt-1 text-xs text-gray-500 hidden"></p>
                </div>

                {{-- Lista de precios --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
                    <select id="price_list_sel"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            onchange="SNF.onPriceListChange(this.value)">
                        <option value="client">Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}">{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="price_list_id" id="price_list_id" value="">
                </div>

                {{-- Fecha --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="fecha" value="{{ now()->format('Y-m-d\TH:i') }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="MXN"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="tipo_venta" id="tipo_venta"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            onchange="SNF.onTipoVentaChange(this.value)">
                        <option value="CONTADO">Contado</option>
                        <option value="CREDITO">Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div id="credito-wrap" style="display:none">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Días de crédito</label>
                    <input type="number" name="credit_days" id="credit_days" min="0" value="0"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

            </div>

            {{-- ====== ALERTA precio cero ====== --}}
            <div id="zero-price-alert" class="hidden rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 flex items-center justify-between">
                <span>Algunos productos no tienen precio para este cliente — captúralo directo en la fila del producto.</span>
                @can('editar clientes')
                    <a id="zero-price-link" href="#" target="_blank"
                       class="ml-3 inline-flex px-3 py-1.5 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">
                        Ver/editar todos los precios
                    </a>
                @endcan
            </div>

            {{-- ====== PARTIDAS ====== --}}
            <div class="overflow-auto border-t pt-4">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50">
                        <tr>
                            <th class="p-2 text-left">Producto</th>
                            <th class="p-2 text-left">Descripción</th>
                            <th class="p-2 text-right">Cantidad</th>
                            <th class="p-2 text-right">Precio</th>
                            <th class="p-2 text-right">Desc.</th>
                            <th class="p-2 text-right">% IVA</th>
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body"></tbody>
                </table>
                <div class="mt-3">
                    <button type="button" onclick="SNF.addRow()"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                        + Agregar partida
                    </button>
                </div>
            </div>

            {{-- ====== TOTALES ====== --}}
            <div class="text-right space-y-1 border-t pt-3">
                <div class="text-sm text-gray-600">Subtotal: <span id="tot-subtotal" class="font-medium">0.00</span></div>
                <div class="text-sm text-gray-600">Descuento: <span id="tot-desc" class="font-medium">0.00</span></div>
                <div class="text-sm text-gray-600">Impuestos: <span id="tot-tax" class="font-medium">0.00</span></div>
                <div class="text-lg font-bold text-gray-800">Total: $<span id="tot-grand">0.00</span></div>
            </div>

            <input type="hidden" name="subtotal"  id="h-subtotal">
            <input type="hidden" name="descuento" id="h-descuento">
            <input type="hidden" name="impuestos" id="h-impuestos">
            <input type="hidden" name="total"     id="h-total">

        </form>
    </x-wire-card>

    <script>
    (function(){
        const CLIENTS_OVERRIDES  = {!! $JS_OVERRIDES !!};
        const LISTS_PRICES       = {!! $JS_LISTPRICES !!};
        const INITIAL_ITEMS      = {!! $JS_INITIALITEMS !!};
        const CLIENT_DEFAULTS    = {!! $JS_CLIENT_DEFAULTS !!};

        const PRODUCTS = @json($productsJson);
        const CLIENTS_EDIT_BASE      = '{{ url('admin/clients') }}';
        const CLIENT_PRICES_BASE     = '{{ url('admin/sales-orders/client-prices') }}';
        const CAN_EDIT_CLIENT_PRICES = @json(auth()->user()->can('editar clientes'));

        let state = {
            items: [],
            clientId: '',
            priceList: 'client',
        };

        const fmt = n => Number(n||0).toFixed(2);
        const $   = id => document.getElementById(id);
        const set = (id, val) => { const el = $(id); if(el) el.value = val; };

        function getPrice(productId) {
            if (!productId) return 0;
            if (state.priceList === 'client') {
                return parseFloat((CLIENTS_OVERRIDES[state.clientId]||{})[productId] ?? 0) || 0;
            }
            return parseFloat((LISTS_PRICES[state.priceList]||{})[productId] ?? 0) || 0;
        }

        // El precio se bloquea SOLO si el cliente/lista ya tiene un precio
        // configurado (>0) para ese producto — para cambiarlo hay que ir a
        // su registro (permiso "editar clientes"). Si viene en $0 (no
        // configurado), se deja editable para capturarlo aquí mismo; al
        // guardar la nota, el servidor lo registra como precio oficial.
        function aplicarEstadoPrecio(inputEl, precio) {
            if (!inputEl) return;
            inputEl.value = precio;
            const bloqueado = (+precio || 0) > 0;
            inputEl.readOnly = bloqueado;
            inputEl.classList.toggle('bg-gray-100', bloqueado);
            inputEl.classList.toggle('text-gray-600', bloqueado);
            inputEl.classList.toggle('cursor-not-allowed', bloqueado);
            inputEl.title = bloqueado
                ? 'Precio del cliente — para cambiarlo, edítalo en su registro'
                : 'Este producto no tiene precio configurado para el cliente — captúralo aquí, quedará registrado como su precio oficial';
        }

        function recalcRow(i) {
            const it   = state.items[i];
            const line = (+it.cantidad||0) * (+it.precio||0);
            const disc = +it.descuento||0;
            const base = Math.max(line - disc, 0);
            const tax  = ((+it.iva_pct||0) / 100) * base;
            it.impuesto = tax;
            it.total    = base + tax;
            const row = document.querySelector(`#items-body tr[data-idx="${i}"]`);
            if (row) {
                row.querySelector('.td-total').textContent = fmt(it.total);
                row.querySelector('.hid-impuesto').value   = it.impuesto;
            }
            updateTotals();
        }

        function updateTotals() {
            let s=0, d=0, t=0, g=0, hasZero=false;
            state.items.forEach(it => {
                const line = (+it.cantidad||0)*(+it.precio||0);
                const disc = +it.descuento||0;
                const base = Math.max(line-disc, 0);
                const tax  = ((+it.iva_pct||0)/100)*base;
                s += line; d += disc; t += tax; g += base+tax;
                if ((+it.precio||0)===0 && it.product_id) hasZero = true;
            });
            $('tot-subtotal').textContent = fmt(s);
            $('tot-desc').textContent     = fmt(d);
            $('tot-tax').textContent      = fmt(t);
            $('tot-grand').textContent    = fmt(g);
            set('h-subtotal',  fmt(s));
            set('h-descuento', fmt(d));
            set('h-impuestos', fmt(t));
            set('h-total',     fmt(g));
            const alert = $('zero-price-alert');
            if (alert) alert.classList.toggle('hidden', !hasZero || !state.clientId);
            const link = $('zero-price-link');
            if (link && state.clientId) link.href = `${CLIENTS_EDIT_BASE}/${state.clientId}/edit`;
        }

        function escHtml(str) {
            return String(str||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        // ── Portal global para el autocomplete de producto (igual que Pedidos) ──
        const PROD_PORTAL = document.createElement('ul');
        PROD_PORTAL.id = 'product-dropdown-portal';
        PROD_PORTAL.className = 'fixed z-[9999] bg-white border border-gray-200 rounded shadow-md hidden max-h-48 overflow-y-auto text-sm list-none py-1';
        document.body.appendChild(PROD_PORTAL);

        function hidePortal() { PROD_PORTAL.classList.add('hidden'); }

        function positionPortal(input) {
            const r = input.getBoundingClientRect();
            PROD_PORTAL.style.left  = r.left + 'px';
            PROD_PORTAL.style.top   = (r.bottom + 2) + 'px';
            PROD_PORTAL.style.width = Math.max(r.width, 240) + 'px';
        }

        document.addEventListener('scroll', hidePortal, true);
        document.addEventListener('click', function(e) {
            if (!PROD_PORTAL.contains(e.target)) hidePortal();
        });

        function attachProductSearch(tr, i) {
            const input  = tr.querySelector('.inp-product-search');
            const hidden = tr.querySelector('.hid-product-id');

            function selectProduct(p) {
                hidden.value = p.id;
                input.value  = p.nombre;

                state.items[i].product_id      = p.id;
                state.items[i]._productoNombre = p.nombre;

                if (!state.items[i].descripcion) {
                    state.items[i].descripcion = p.nombre;
                    tr.querySelector('.inp-desc').value = p.nombre;
                }

                state.items[i].precio = getPrice(p.id);
                aplicarEstadoPrecio(tr.querySelector('.inp-precio'), state.items[i].precio);

                recalcRow(i);
                hidePortal();
                input.focus();
            }

            function clearProduct() {
                hidden.value = '';
                input.value  = '';
                state.items[i].product_id      = '';
                state.items[i]._productoNombre = '';
                state.items[i].precio          = 0;
                aplicarEstadoPrecio(tr.querySelector('.inp-precio'), 0);
                recalcRow(i);
                input.focus();
            }

            function showDropdown(term) {
                const t = term.toLowerCase().trim();
                const matches = t.length === 0 ? [] : PRODUCTS.filter(p =>
                    p.nombre.toLowerCase().includes(t) ||
                    (p.sku && p.sku.toLowerCase().includes(t))
                ).slice(0, 12);

                PROD_PORTAL.innerHTML = '';
                if (matches.length === 0) { hidePortal(); return; }

                matches.forEach(p => {
                    const li = document.createElement('li');
                    li.className = 'px-3 py-1.5 hover:bg-indigo-50 cursor-pointer flex justify-between items-center';
                    li.innerHTML = `<span class="truncate">${escHtml(p.nombre)}</span>`
                                 + (p.sku ? `<span class="text-xs text-gray-400 ml-2 shrink-0">${escHtml(p.sku)}</span>` : '');
                    li.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        selectProduct(p);
                    });
                    PROD_PORTAL.appendChild(li);
                });

                positionPortal(input);
                PROD_PORTAL.classList.remove('hidden');
            }

            input.addEventListener('input', function() {
                if (!this.value.trim()) {
                    hidden.value = '';
                    state.items[i].product_id      = '';
                    state.items[i]._productoNombre = '';
                }
                showDropdown(this.value);
            });

            input.addEventListener('focus', function() {
                if (this.value.trim()) showDropdown(this.value);
            });

            input.addEventListener('blur', function() {
                setTimeout(hidePortal, 150);
            });

            tr.querySelector('.btn-clear-product').addEventListener('click', clearProduct);
        }

        function renderRow(i) {
            const it = state.items[i];
            if (!it._productoNombre && it.product_id) {
                const prod = PRODUCTS.find(p => p.id == it.product_id);
                if (prod) it._productoNombre = prod.nombre;
            }
            const tr = document.createElement('tr');
            tr.className   = 'border-b';
            tr.dataset.idx = i;
            tr.innerHTML = `
                <td class="p-2">
                    <input type="hidden" class="hid-product-id" name="items[${i}][product_id]" value="${escHtml(String(it.product_id || ''))}">
                    <div class="flex items-center gap-1">
                        <input type="text"
                               class="w-48 border rounded p-1 text-sm inp-product-search"
                               placeholder="Buscar por nombre o SKU..."
                               autocomplete="off"
                               value="${escHtml(it._productoNombre || '')}">
                        <button type="button" class="btn-clear-product text-gray-400 hover:text-red-500 text-base leading-none px-1" title="Quitar producto">✕</button>
                    </div>
                </td>
                <td class="p-2">
                    <input type="text" class="w-64 border rounded p-1 text-sm inp-desc"
                           name="items[${i}][descripcion]" value="${escHtml(it.descripcion)}" required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0.001" step="0.001"
                           class="w-24 border rounded p-1 text-right text-sm inp-cantidad"
                           name="items[${i}][cantidad]" value="${it.cantidad}" required>
                </td>
                <td class="p-2 text-right">
                    <div class="flex items-center justify-end gap-1">
                        <input type="number" min="0" step="0.0001"
                               class="w-24 border rounded p-1 text-right text-sm inp-precio"
                               name="items[${i}][precio]" value="${it.precio}" required>
                        ${CAN_EDIT_CLIENT_PRICES ? `
                        <a href="#" class="btn-editar-precio text-gray-400 hover:text-indigo-600 text-xs" target="_blank"
                           title="Editar precio de este cliente">
                            <i class="fa-solid fa-pen"></i>
                        </a>` : ''}
                    </div>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01"
                           class="w-24 border rounded p-1 text-right text-sm inp-descuento"
                           name="items[${i}][descuento]" value="${it.descuento}">
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01"
                           class="w-20 border rounded p-1 text-right text-sm inp-iva"
                           value="${it.iva_pct}">
                    <input type="hidden" class="hid-impuesto" name="items[${i}][impuesto]" value="${it.impuesto}">
                </td>
                <td class="p-2 text-right font-medium td-total">${fmt(it.total)}</td>
                <td class="p-2 text-center">
                    <button type="button" class="text-red-500 hover:text-red-700 text-xs btn-remove">✕</button>
                </td>
            `;

            attachProductSearch(tr, i);

            tr.querySelector('.inp-cantidad').addEventListener('input', function() {
                state.items[i].cantidad = parseFloat(this.value)||0; recalcRow(i);
            });
            aplicarEstadoPrecio(tr.querySelector('.inp-precio'), it.precio);
            tr.querySelector('.inp-precio').addEventListener('input', function() {
                if (this.readOnly) return;
                state.items[i].precio = parseFloat(this.value)||0; recalcRow(i);
            });
            const btnEditarPrecio = tr.querySelector('.btn-editar-precio');
            if (btnEditarPrecio) {
                btnEditarPrecio.addEventListener('click', function(e) {
                    if (!state.clientId) { e.preventDefault(); return; }
                    this.href = `${CLIENTS_EDIT_BASE}/${state.clientId}/edit`;
                });
            }
            tr.querySelector('.inp-descuento').addEventListener('input', function() {
                state.items[i].descuento = parseFloat(this.value)||0; recalcRow(i);
            });
            tr.querySelector('.inp-iva').addEventListener('input', function() {
                state.items[i].iva_pct = parseFloat(this.value)||0; recalcRow(i);
            });
            tr.querySelector('.inp-desc').addEventListener('input', function() {
                state.items[i].descripcion = this.value;
            });
            tr.querySelector('.btn-remove').addEventListener('click', function() {
                state.items.splice(i, 1); renderAll();
            });

            return tr;
        }

        function renderAll() {
            const tbody = $('items-body');
            tbody.innerHTML = '';
            state.items.forEach((_, i) => tbody.appendChild(renderRow(i)));
            updateTotals();
        }

        window.SNF = {
            addRow() {
                state.items.push({product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0});
                renderAll();
            },

            onClientChange(clientId) {
                state.clientId = clientId;
                state.priceList = 'client';

                const d = CLIENT_DEFAULTS[clientId];
                if (d) {
                    if (d.credito_dias > 0) {
                        set('tipo_venta', 'CREDITO');
                        set('credit_days', d.credito_dias);
                        SNF.onTipoVentaChange('CREDITO');
                    }
                    if (d.credito_limite > 0) {
                        const info = $('credito-info');
                        if (info) {
                            info.textContent = `Límite: $${fmt(d.credito_limite)} · Días: ${d.credito_dias}d`;
                            info.classList.remove('hidden');
                        }
                    }
                } else {
                    const info = $('credito-info');
                    if (info) info.classList.add('hidden');
                }

                SNF.repriceAll();
            },

            onPriceListChange(val) {
                state.priceList = val;
                set('price_list_id', val === 'client' ? '' : val);
                SNF.repriceAll();
            },

            onTipoVentaChange(val) {
                $('credito-wrap').style.display = val === 'CREDITO' ? '' : 'none';
            },

            repriceAll() {
                state.items.forEach((it, i) => {
                    if (it.product_id) {
                        it.precio = getPrice(it.product_id);
                        const row = document.querySelector(`#items-body tr[data-idx="${i}"]`);
                        if (row) aplicarEstadoPrecio(row.querySelector('.inp-precio'), it.precio);
                        recalcRow(i);
                    }
                });
            },
        };

        // Init
        state.items = JSON.parse(JSON.stringify(INITIAL_ITEMS));
        if (!state.items.length) {
            state.items = [{product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}];
        }
        renderAll();

        // Cuando el usuario regresa a esta pestaña (ej. después de editar precios),
        // refresca los overrides del cliente y vuelve a calcular precios.
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState !== 'visible' || !state.clientId) return;
            fetch(`${CLIENT_PRICES_BASE}/${state.clientId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.ok ? r.json() : null)
                .then(prices => {
                    if (!prices) return;
                    const cid = String(state.clientId);
                    CLIENTS_OVERRIDES[cid] = {};
                    Object.entries(prices).forEach(([pid, p]) => {
                        CLIENTS_OVERRIDES[cid][String(pid)] = parseFloat(p) || 0;
                    });
                    SNF.repriceAll();
                })
                .catch(() => {});
        });
    })();
    </script>

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
.select2-container .select2-selection--single { height: 38px !important; border-color: #d1d5db !important; border-radius: 6px !important; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 0.875rem; color: #374151; padding-left: 10px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
.select2-dropdown { border-color: #d1d5db; border-radius: 6px; font-size: 0.875rem; }
.select2-container--default .select2-search--dropdown .select2-search__field { border-color: #d1d5db; border-radius: 4px; padding: 4px 8px; }
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function () {
    $('#client_id').select2({
        placeholder: '-- público general --',
        allowClear: true,
        width: '100%',
        language: { searching: function() { return 'Buscando...'; }, noResults: function() { return 'Sin resultados'; } },
    }).on('change', function () {
        window.SNF && SNF.onClientChange(this.value);
    });

    // Bug conocido de select2: al dar clic en la "x" de limpiar, el mismo clic
    // se propaga y vuelve a abrir el dropdown.
    $(document).on('select2:unselecting', function(e) {
        $(e.target).data('unselecting', true);
    }).on('select2:opening', function(e) {
        if ($(e.target).data('unselecting')) {
            $(e.target).removeData('unselecting');
            e.preventDefault();
        }
    });
});
</script>
@endpush

</x-admin-layout>