<x-admin-layout
    title="Crear pedido"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Pedidos','url'=>route('admin.sales-orders.index')],
        ['name'=>'Crear'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales-orders.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="so-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    @php
        $selClient     = (string) old('client_id', '');
        $selWarehouse  = (string) old('warehouse_id', $mainWarehouseId ?? '');
        $selRoute      = (string) old('shipping_route_id', '');
        $valueFecha    = old('fecha', now()->format('Y-m-d\TH:i'));
        $valueProg     = old('programado_para', now()->addDay()->format('Y-m-d'));
        $valueMoneda   = old('moneda', 'MXN');
        $deliveryType  = old('delivery_type', 'ENVIO');
        $paymentMethod = old('payment_method', 'CREDITO');

        $seedItems    = $seedItems ?? [];
        $initialItems = (is_array($seedItems) && count($seedItems))
            ? $seedItems
            : [['product_id'=>'','_productoNombre'=>'','descripcion'=>'','cantidad'=>1,'num_cajas'=>null,'precio'=>0,'descuento'=>0,'iva_pct'=>0,'impuesto'=>0,'total'=>0]];

        $JS_OVERRIDES       = json_encode($overrides   ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES      = json_encode($listItems   ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_INITIALITEMS    = json_encode($initialItems,      JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT       = json_encode($selClient,         JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_EXISTENCIAS     = json_encode($existencias ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        $clientDefaults = $clients->mapWithKeys(fn($c) => [(string)$c->id => [
            'shipping_route_id' => (string) ($c->shipping_route_id ?? ''),
            'price_list_id'     => (string) ($c->price_list_id ?? ''),
            'credito_dias'      => (int)    ($c->credito_dias  ?? 0),
            'credito_limite'    => (float)  ($c->credito_limite ?? 0),
            'telefono'          => (string) ($c->telefono ?? ''),
            'entrega_calle'    => $c->entrega_igual_fiscal ? ($c->fiscal_calle   ?? '') : ($c->entrega_calle   ?? ''),
            'entrega_numero'   => $c->entrega_igual_fiscal ? ($c->fiscal_numero  ?? '') : ($c->entrega_numero  ?? ''),
            'entrega_colonia'  => $c->entrega_igual_fiscal ? ($c->fiscal_colonia ?? '') : ($c->entrega_colonia ?? ''),
            'entrega_ciudad'   => $c->entrega_igual_fiscal ? ($c->fiscal_ciudad  ?? '') : ($c->entrega_ciudad  ?? ''),
            'entrega_estado'   => $c->entrega_igual_fiscal ? ($c->fiscal_estado  ?? '') : ($c->entrega_estado  ?? ''),
            'entrega_cp'       => $c->entrega_igual_fiscal ? ($c->fiscal_cp      ?? '') : ($c->entrega_cp      ?? ''),
        ]])->toArray();

        $JS_CLIENT_DEFAULTS = json_encode($clientDefaults, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    <x-wire-card>
        <form id="so-form" method="POST" action="{{ route('admin.sales-orders.store') }}" class="space-y-6">
            @csrf

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <p id="credito-info" class="mt-1 text-xs text-gray-500 hidden"></p>
                </div>

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Lista de precios --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
                    <select id="price_list_sel"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="SOF.onPriceListChange(this.value)">
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
                    <input type="datetime-local" name="fecha" value="{{ $valueFecha }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                {{-- Programado para --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Programado para</label>
                    <input type="date" name="programado_para" value="{{ $valueProg }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="{{ $valueMoneda }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                </div>

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type" id="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="SOF.onDeliveryChange(this.value)">
                        <option value="ENVIO"   {{ $deliveryType==='ENVIO'   ? 'selected' : '' }}>Envío a domicilio</option>
                        <option value="RECOGER" {{ $deliveryType==='RECOGER' ? 'selected' : '' }}>Recoger en almacén</option>
                    </select>
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id" id="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- sin ruta --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selRoute===(string)$r->id ? 'selected' : '' }}>
                                {{ $r->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Ruta del día (1ra/2da) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta del día</label>
                    @php $selRonda = (string) old('ronda', '1'); @endphp
                    <select name="ronda" id="ronda"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1" {{ $selRonda==='1' ? 'selected' : '' }}>1ra ruta</option>
                        <option value="2" {{ $selRonda==='2' ? 'selected' : '' }}>2da ruta</option>
                    </select>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="payment_method" id="payment_method"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="SOF.onPaymentChange(this.value)">
                        <option value="EFECTIVO"      {{ $paymentMethod==='EFECTIVO'      ? 'selected' : '' }}>Efectivo</option>
                        <option value="TRANSFERENCIA" {{ $paymentMethod==='TRANSFERENCIA' ? 'selected' : '' }}>Transferencia</option>
                        <option value="CONTRAENTREGA" {{ $paymentMethod==='CONTRAENTREGA' ? 'selected' : '' }}>Contraentrega</option>
                        <option value="CREDITO"       {{ $paymentMethod==='CREDITO'       ? 'selected' : '' }}>Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div id="credito-wrap" style="display:none">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Días de crédito <span class="text-gray-400 font-normal text-xs">(del cliente)</span>
                    </label>
                    <input type="number" name="credit_days" id="credit_days" value="0"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                </div>

            </div>

            {{-- ====== DIRECCIÓN DE ENTREGA ====== --}}
            <div id="entrega-section" class="border-t pt-4" style="{{ $deliveryType==='ENVIO' ? '' : 'display:none' }}">
                {{-- Toggle colapsable --}}
                <button type="button" id="entrega-toggle"
                        onclick="toggleEntrega()"
                        class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-indigo-600 w-full text-left mb-2">
                    <svg id="entrega-chevron" class="w-4 h-4 transition-transform duration-200"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    Datos de entrega
                </button>
                <div id="entrega-fields" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach([
                        ['entrega_nombre',   'Nombre quien recibe'],
                        ['entrega_telefono', 'Teléfono'],
                        ['entrega_calle',    'Calle'],
                        ['entrega_numero',   'Número'],
                        ['entrega_colonia',  'Colonia'],
                        ['entrega_ciudad',   'Ciudad'],
                        ['entrega_estado',   'Estado'],
                        ['entrega_cp',       'CP'],
                    ] as [$fname, $flabel])
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $flabel }}</label>
                        <input type="text" name="{{ $fname }}" id="{{ $fname }}"
                               value="{{ old($fname) }}"
                               autocomplete="off"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- ====== COMENTARIOS ====== --}}
            <div class="border-t pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Comentarios</label>
                <textarea name="comentarios" rows="2" autocomplete="off"
                          placeholder="Notas u observaciones del pedido..."
                          class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('comentarios') }}</textarea>
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
                            <th class="p-2 text-left">Unidad</th>
                            <th class="p-2 text-center" title="Número aproximado de cajas (referencia)">Cajas</th>
                            <th class="p-2 text-right">Precio</th>
                            <th class="p-2 text-right">Desc.</th>
                            @if($mostrarIva)<th class="p-2 text-right">% IVA</th>@endif
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body"></tbody>
                </table>
                <div class="mt-3">
                    <button type="button" onclick="SOF.addRow()"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                        + Agregar partida
                    </button>
                </div>
            </div>

            {{-- ====== TOTALES ====== --}}
            <div class="text-right space-y-1 border-t pt-3">
                <div class="text-sm text-gray-600">Subtotal: <span id="tot-subtotal" class="font-medium">0.00</span></div>
                <div class="text-sm text-gray-600">Descuento: <span id="tot-desc" class="font-medium">0.00</span></div>
                @if($mostrarIva)<div class="text-sm text-gray-600">Impuestos: <span id="tot-tax" class="font-medium">0.00</span></div>@endif
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
        const CLIENTS_OVERRIDES = {!! $JS_OVERRIDES !!};
        const LISTS_PRICES      = {!! $JS_LISTPRICES !!};
        const INITIAL_ITEMS     = {!! $JS_INITIALITEMS !!};
        const CLIENT_DEFAULTS   = {!! $JS_CLIENT_DEFAULTS !!};
        const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};
        const EXISTENCIAS       = {!! $JS_EXISTENCIAS !!}; // { [warehouse_id]: { [product_id]: existencia } }
        const CLIENTS_EDIT_BASE    = '{{ url('admin/clients') }}';
        const CLIENT_PRICES_BASE   = '{{ url('admin/sales-orders/client-prices') }}';
        const PRODUCTS             = @json($productsJson);
        const MOSTRAR_IVA          = @json($mostrarIva);
        const CAN_EDIT_CLIENT_PRICES = @json(auth()->user()->can('editar clientes'));

        let state = {
            items: [],
            clientId: DEFAULT_CLIENT_ID || '',
            priceList: 'client',
        };

        const fmt = n => Number(n||0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const $   = id => document.getElementById(id);
        const set = (id, val) => { const el = $(id); if(el) el.value = val; };
        const soForm = document.getElementById('so-form');

        function escHtml(str) {
            return String(str||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function getPrice(productId) {
            if (!productId) return 0;
            const pid = String(productId);
            if (state.priceList === 'client') {
                return parseFloat((CLIENTS_OVERRIDES[state.clientId]||{})[pid] ?? 0) || 0;
            }
            return parseFloat((LISTS_PRICES[state.priceList]||{})[pid] ?? 0) || 0;
        }

        // El precio se bloquea SOLO si el cliente/lista ya tiene un precio
        // configurado para ese producto (>0) — ese si requiere el permiso de
        // editar clientes para cambiarse (lápiz de al lado). Si todavía no
        // existe (viene en $0), cualquiera puede capturarlo aquí mismo, sin
        // permiso: no es "editar" un precio del cliente, es agregar uno que
        // no existe, y al guardar el pedido queda registrado como el precio
        // oficial del cliente para ese producto (lo hace el servidor).
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
            if ($('tot-tax')) $('tot-tax').textContent = fmt(t);
            $('tot-grand').textContent    = fmt(g);
            set('h-subtotal',  fmt(s));
            set('h-descuento', fmt(d));
            set('h-impuestos', fmt(t));
            set('h-total',     fmt(g));
            const alertEl = $('zero-price-alert');
            if (alertEl) alertEl.classList.toggle('hidden', !hasZero || !state.clientId);
            const link = $('zero-price-link');
            if (link && state.clientId) link.href = `${CLIENTS_EDIT_BASE}/${state.clientId}/edit`;
        }

        // ── Portal global para el autocomplete (escapa overflow-auto) ───
        const PROD_PORTAL = document.createElement('ul');
        PROD_PORTAL.id = 'product-dropdown-portal';
        PROD_PORTAL.className = 'fixed z-[9999] bg-white border border-gray-200 rounded shadow-md hidden max-h-48 overflow-y-auto text-sm list-none py-1';
        document.body.appendChild(PROD_PORTAL);

        let portalSelectFn = null;

        function hidePortal() { PROD_PORTAL.classList.add('hidden'); portalSelectFn = null; }

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

        // ── Autocomplete de producto ─────────────────────────────────────
        function attachProductSearch(tr, i) {
            const input  = tr.querySelector('.inp-product-search');
            const hidden = tr.querySelector('.hid-product-id');

            function selectProduct(p) {
                hidden.value = p.id;
                input.value  = p.nombre;

                state.items[i].product_id      = p.id;
                state.items[i]._productoNombre = p.nombre;
                state.items[i].unidad          = p.unidad || '';

                // Siempre actualiza la descripción al cambiar de producto
                state.items[i].descripcion = p.nombre;
                tr.querySelector('.inp-desc').value = p.nombre;

                state.items[i].precio = getPrice(p.id);
                aplicarEstadoPrecio(tr.querySelector('.inp-precio'), state.items[i].precio);

                const unidadEl = tr.querySelector('.td-unidad');
                if (unidadEl) unidadEl.textContent = p.unidad || '—';

                recalcRow(i);
                hidePortal();
                input.focus();
            }

            function clearProduct() {
                hidden.value = '';
                input.value  = '';
                state.items[i].product_id      = '';
                state.items[i]._productoNombre = '';
                state.items[i].descripcion     = '';
                state.items[i].precio          = 0;
                state.items[i].unidad          = '';
                tr.querySelector('.inp-desc').value   = '';
                aplicarEstadoPrecio(tr.querySelector('.inp-precio'), 0);
                const unidadEl = tr.querySelector('.td-unidad');
                if (unidadEl) unidadEl.textContent = '—';
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

                portalSelectFn = selectProduct;

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

        // ── Render de fila ───────────────────────────────────────────────
        function renderRow(i) {
            const it = state.items[i];
            if (!it.unidad && it.product_id) {
                const prod = PRODUCTS.find(p => p.id == it.product_id);
                if (prod) it.unidad = prod.unidad || '';
            }
            const tr = document.createElement('tr');
            tr.className   = 'border-b';
            tr.dataset.idx = i;
            tr.innerHTML = `
                <td class="p-2">
                    <input type="hidden" class="hid-product-id" name="items[${i}][product_id]" value="${escHtml(String(it.product_id || ''))}">
                    <div class="flex items-center gap-1">
                        <input type="text"
                               class="w-52 border rounded p-1 text-sm inp-product-search"
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
                    <div class="flex items-center justify-end gap-0.5">
                        <button type="button" class="btn-qty-step w-5 h-6 shrink-0 border rounded text-xs text-gray-500 hover:bg-gray-100" data-step="-0.5" tabindex="-1">−</button>
                        <input type="number" min="0.001" step="0.001"
                               class="w-16 border rounded p-1 text-right text-sm inp-cantidad"
                               name="items[${i}][cantidad]" value="${it.cantidad}" required>
                        <button type="button" class="btn-qty-step w-5 h-6 shrink-0 border rounded text-xs text-gray-500 hover:bg-gray-100" data-step="0.5" tabindex="-1">+</button>
                    </div>
                </td>
                <td class="p-2 text-xs text-gray-500 td-unidad">${escHtml(it.unidad || '—')}</td>
                <td class="p-2 text-center">
                    <input type="number" min="1" step="1"
                           class="w-16 border rounded p-1 text-center text-sm inp-cajas"
                           name="items[${i}][num_cajas]" value="${it.num_cajas || ''}"
                           placeholder="—" title="Cajas aprox.">
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
                ${MOSTRAR_IVA ? `
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01"
                           class="w-20 border rounded p-1 text-right text-sm inp-iva"
                           value="${it.iva_pct}">
                </td>` : ''}
                <td class="p-2 text-right font-medium">
                    <span class="td-total">${fmt(it.total)}</span>
                    <input type="hidden" class="hid-impuesto" name="items[${i}][impuesto]" value="${it.impuesto}">
                </td>
                <td class="p-2 text-center">
                    <button type="button" class="text-red-500 hover:text-red-700 text-xs btn-remove">✕</button>
                </td>
            `;

            // Adjuntar autocomplete (reemplaza el sel.addEventListener anterior)
            attachProductSearch(tr, i);

            tr.querySelector('.inp-cantidad').addEventListener('input', function() {
                state.items[i].cantidad = parseFloat(this.value)||0; recalcRow(i);
            });
            tr.querySelectorAll('.btn-qty-step').forEach(btn => {
                btn.addEventListener('click', function() {
                    const inp = tr.querySelector('.inp-cantidad');
                    const nuevo = Math.max(0, (parseFloat(inp.value)||0) + parseFloat(this.dataset.step));
                    inp.value = nuevo.toFixed(3).replace(/\.?0+$/, '') || '0';
                    state.items[i].cantidad = parseFloat(inp.value)||0;
                    recalcRow(i);
                });
            });
            // Sin este listener, lo que se escribía en "Cajas" nunca llegaba
            // a state.items — al agregar otra partida, renderAll() reconstruye
            // toda la tabla desde state.items y esa cantidad de cajas se perdía.
            tr.querySelector('.inp-cajas').addEventListener('input', function() {
                state.items[i].num_cajas = this.value === '' ? null : parseInt(this.value, 10) || null;
            });
            // El precio se bloquea solo cuando el cliente/lista YA tiene un
            // precio configurado (>0) para este producto. Si viene en $0
            // (no configurado), se deja editable para poder capturarlo en
            // esta línea. Para cambiar un precio que ya existe, hay que
            // hacerlo en el registro del cliente — el lápiz de al lado lleva
            // directo a esa pantalla (solo visible con permiso de editar
            // clientes).
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
            if (MOSTRAR_IVA) {
                tr.querySelector('.inp-iva').addEventListener('input', function() {
                    state.items[i].iva_pct = parseFloat(this.value)||0; recalcRow(i);
                });
            }
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

        // ── API pública ──────────────────────────────────────────────────
        window.SOF = {
            addRow() {
                state.items.push({
                    product_id: '', _productoNombre: '',
                    descripcion: '', cantidad: 1,
                    precio: 0, descuento: 0, iva_pct: 0, impuesto: 0, total: 0
                });
                renderAll();
            },

            onClientChange(clientId) {
                state.clientId  = clientId;
                state.priceList = 'client';

                const d = CLIENT_DEFAULTS[clientId];
                if (d) {
                    if (d.shipping_route_id) set('shipping_route_id', d.shipping_route_id);
                    if (d.credito_dias > 0) {
                        set('payment_method', 'CREDITO');
                        set('credit_days', d.credito_dias);
                        SOF.onPaymentChange('CREDITO');
                    }
                    if (d.credito_limite > 0) {
                        const info = $('credito-info');
                        if (info) {
                            info.textContent = `Límite: $${fmt(d.credito_limite)} · Días: ${d.credito_dias}d`;
                            info.classList.remove('hidden');
                        }
                    }
                    const fields = {
                        entrega_telefono: d.telefono,
                        entrega_calle:    d.entrega_calle,
                        entrega_numero:   d.entrega_numero,
                        entrega_colonia:  d.entrega_colonia,
                        entrega_ciudad:   d.entrega_ciudad,
                        entrega_estado:   d.entrega_estado,
                        entrega_cp:       d.entrega_cp,
                    };
                    Object.entries(fields).forEach(([id, val]) => { if(val) set(id, val); });
                } else {
                    const info = $('credito-info');
                    if (info) info.classList.add('hidden');
                }
                SOF.repriceAll();
            },

            onPriceListChange(val) {
                state.priceList = val;
                set('price_list_id', val === 'client' ? '' : val);
                SOF.repriceAll();
            },

            onDeliveryChange(val) {
                $('entrega-section').style.display = val === 'ENVIO' ? '' : 'none';
            },

            onPaymentChange(val) {
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

        // ── Init ─────────────────────────────────────────────────────────
        state.items = JSON.parse(JSON.stringify(INITIAL_ITEMS));
        if (!state.items.length) {
            state.items = [{
                product_id: '', _productoNombre: '',
                descripcion: '', cantidad: 1,
                precio: 0, descuento: 0, iva_pct: 0, impuesto: 0, total: 0
            }];
        }
        if (DEFAULT_CLIENT_ID) SOF.onClientChange(DEFAULT_CLIENT_ID);
        SOF.onDeliveryChange('{{ $deliveryType }}');
        SOF.onPaymentChange('{{ $paymentMethod }}');
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
                    SOF.repriceAll();
                })
                .catch(() => {});
        });

        // ── Validación antes de guardar ─────────────────────────────────
        // No se bloquea el guardado — solo se avisa y se pide confirmar,
        // para que un Enter accidental (o guardar de prisa) no deje pasar
        // un precio en $0 o un producto casi sin existencia sin que nadie
        // se dé cuenta. Se engancha al evento "submit" del formulario, así
        // que aplica tanto al botón Guardar como a Enter en cualquier campo.
        soForm.addEventListener('submit', function (e) {
            const warehouseSel = document.querySelector('select[name="warehouse_id"]');
            const stockAlmacen = EXISTENCIAS[String(warehouseSel ? warehouseSel.value : '')] || {};
            const avisos = [];

            state.items.forEach(function (it) {
                if (!it.product_id) return;
                const nombre = it._productoNombre || it.descripcion || ('#' + it.product_id);

                if ((+it.precio || 0) <= 0) {
                    avisos.push('• ' + escHtml(nombre) + ': precio en $0.00');
                }

                const pid = String(it.product_id);
                if (Object.prototype.hasOwnProperty.call(stockAlmacen, pid)) {
                    const existencia = +stockAlmacen[pid];
                    if (existencia <= 1) {
                        avisos.push('• ' + escHtml(nombre) + ': solo queda' + (existencia === 1 ? '' : 'n') + ' ' + existencia + ' en existencia');
                    }
                }
            });

            if (!avisos.length) return; // nada que avisar, se guarda normal

            e.preventDefault();
            Swal.fire({
                title: 'Revisa antes de guardar',
                html: 'Hay partidas con posibles problemas:<br><br>' + avisos.join('<br>'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Continuar de todos modos',
                cancelButtonText: 'Corregir',
                confirmButtonColor: '#4f46e5',
                cancelButtonColor: '#6b7280',
            }).then(function (r) {
                // form.submit() (nativo) no vuelve a disparar "submit", así
                // que no hay riesgo de loop ni necesidad de una bandera.
                if (r.isConfirmed) soForm.submit();
            });
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
        placeholder: '-- seleccionar cliente --',
        allowClear: true,
        width: '100%',
        language: { searching: function() { return 'Buscando...'; }, noResults: function() { return 'Sin resultados'; } },
    }).on('change', function () {
        SOF.onClientChange(this.value);
    });

    @if($selClient)
    $('#client_id').val('{{ $selClient }}').trigger('change.select2');
    @endif

    // Bug conocido de select2: al dar clic en la "x" de limpiar, el mismo clic
    // se propaga y vuelve a abrir el dropdown (se ve como "abre y cierra sin borrar").
    $(document).on('select2:unselecting', function(e) {
        $(e.target).data('unselecting', true);
    }).on('select2:opening', function(e) {
        if ($(e.target).data('unselecting')) {
            $(e.target).removeData('unselecting');
            e.preventDefault();
        }
    });
});

function toggleEntrega() {
    const fields  = document.getElementById('entrega-fields');
    const chevron = document.getElementById('entrega-chevron');
    const open    = !fields.classList.contains('hidden');
    fields.classList.toggle('hidden', open);
    chevron.style.transform = open ? '' : 'rotate(90deg)';
}

// Abrir automáticamente si hay datos de entrega (por old() en caso de error)
(function(){
    const vals = ['entrega_nombre','entrega_telefono','entrega_calle','entrega_numero','entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp'];
    const hasData = vals.some(id => { const el = document.getElementById(id); return el && el.value.trim(); });
    if (hasData) toggleEntrega();
})();
</script>
@endpush

</x-admin-layout>