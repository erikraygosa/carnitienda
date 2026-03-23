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

    <x-wire-card>
        <form id="sale-form" method="POST" action="{{ route('admin.sales.store') }}" class="space-y-6">
            @csrf

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Caja --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Caja</label>
                    <select name="pos_register_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                        @foreach($posRegisters as $pr)
                            <option value="{{ $pr->id }}">{{ $pr->nombre ?? ('Caja #'.$pr->id) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            onchange="SNF.onClientChange(this.value)">
                        <option value="">-- público general --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
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

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type" id="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            onchange="SNF.onDeliveryChange(this.value)">
                        <option value="RECOGER">Recoger</option>
                        <option value="ENVIO">Envío</option>
                    </select>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="tipo_venta" id="tipo_venta"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            onchange="SNF.onTipoVentaChange(this.value)">
                        <option value="CONTADO">Contado</option>
                        <option value="CONTRAENTREGA">Contraentrega</option>
                        <option value="CREDITO">Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div id="credito-wrap" style="display:none">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Días de crédito</label>
                    <input type="number" name="credit_days" id="credit_days" min="0" value="0"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id" id="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">—</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Chofer --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chofer</label>
                    <select name="driver_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">—</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- ====== DIRECCIÓN DE ENTREGA ====== --}}
            <div id="entrega-section"
                 class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-4"
                 style="display:none">
                <div class="md:col-span-3">
                    <p class="text-sm font-medium text-gray-700">Datos de entrega</p>
                </div>
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
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                @endforeach
            </div>

            {{-- ====== ALERTA precio cero ====== --}}
            <div id="zero-price-alert" class="hidden rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 text-sm">
                Hay productos sin precio personalizado para este cliente (quedaron en $0.00).
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

        const PRODUCTS_OPTIONS = `@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach`;

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
        }

        function escHtml(str) {
            return String(str||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function renderRow(i) {
            const it = state.items[i];
            const tr = document.createElement('tr');
            tr.className   = 'border-b';
            tr.dataset.idx = i;
            tr.innerHTML = `
                <td class="p-2">
                    <select class="w-48 border rounded p-1 text-sm sel-product" name="items[${i}][product_id]">
                        <option value="">—</option>
                        ${PRODUCTS_OPTIONS}
                    </select>
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
                    <input type="number" min="0" step="0.0001"
                           class="w-28 border rounded p-1 text-right text-sm inp-precio"
                           name="items[${i}][precio]" value="${it.precio}" required>
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

            const sel = tr.querySelector('.sel-product');
            if (it.product_id) sel.value = it.product_id;

            sel.addEventListener('change', function() {
                state.items[i].product_id = this.value;
                const txt = this.options[this.selectedIndex]?.text || '';
                if (!state.items[i].descripcion) {
                    state.items[i].descripcion = txt;
                    tr.querySelector('.inp-desc').value = txt;
                }
                state.items[i].precio = getPrice(this.value);
                tr.querySelector('.inp-precio').value = state.items[i].precio;
                recalcRow(i);
            });
            tr.querySelector('.inp-cantidad').addEventListener('input', function() {
                state.items[i].cantidad = parseFloat(this.value)||0; recalcRow(i);
            });
            tr.querySelector('.inp-precio').addEventListener('input', function() {
                state.items[i].precio = parseFloat(this.value)||0; recalcRow(i);
            });
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
                    if (d.shipping_route_id) set('shipping_route_id', d.shipping_route_id);
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

            onDeliveryChange(val) {
                $('entrega-section').style.display = val === 'ENVIO' ? '' : 'none';
            },

            repriceAll() {
                state.items.forEach((it, i) => {
                    if (it.product_id) it.precio = getPrice(it.product_id);
                    recalcRow(i);
                });
            },
        };

        // Init
        state.items = JSON.parse(JSON.stringify(INITIAL_ITEMS));
        if (!state.items.length) {
            state.items = [{product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}];
        }
        renderAll();
    })();
    </script>

</x-admin-layout>