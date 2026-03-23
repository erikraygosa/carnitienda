<x-admin-layout
    title="Cotización {{ $quote->folio ?? '#'.$quote->id }}"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cotizaciones','url'=>route('admin.quotes.index')],
        ['name'=>$quote->folio ?? '#'.$quote->id],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.quotes.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <a href="{{ route('admin.quotes.send.form',$quote) }}" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-violet-600 text-white">Enviar</a>
        <a href="{{ route('admin.quotes.pdf',$quote) }}" target="_blank" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">Ver PDF</a>
        <a href="{{ route('admin.quotes.pdf.download',$quote) }}" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">↓ PDF</a>
        @if($quote->status === 'BORRADOR')
            <button form="quote-edit-form" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
    </x-slot>

    @php
        $isLocked     = $quote->status !== 'BORRADOR';
        $locked       = $isLocked ? 'true' : 'false';
        $selClient    = (string) old('client_id',         $quote->client_id);
        $selPriceList = (string) old('price_list_id',     $quote->price_list_id ?? '');
        $selRoute     = (string) old('shipping_route_id', $quote->shipping_route_id ?? '');
        $valueFecha   = old('fecha',           optional($quote->fecha)->format('Y-m-d\TH:i'));
        $valueProg    = old('programado_para', optional($quote->programado_para ?? null)->toDateString());
        $valueMoneda  = old('moneda',          $quote->moneda);
        $valueVigencia= old('vigencia_hasta',  optional($quote->vigencia_hasta)->toDateString());
        $deliveryType = old('delivery_type',   $quote->delivery_type ?? 'ENVIO');
        $paymentMethod= old('payment_method',  $quote->payment_method ?? 'EFECTIVO');

        $statusClasses = [
            'BORRADOR'   => 'bg-gray-100 text-gray-700',
            'ENVIADA'    => 'bg-sky-100 text-sky-700',
            'APROBADA'   => 'bg-emerald-100 text-emerald-700',
            'RECHAZADA'  => 'bg-rose-100 text-rose-700',
            'CONVERTIDA' => 'bg-indigo-100 text-indigo-700',
            'CANCELADA'  => 'bg-amber-100 text-amber-700',
        ];
        $statusClass = $statusClasses[$quote->status] ?? 'bg-slate-100 text-slate-700';

        $itemsSeed = $quote->items->map(fn($i) => [
            'product_id'  => $i->product_id,
            'descripcion' => $i->descripcion,
            'cantidad'    => (float)$i->cantidad,
            'precio'      => (float)$i->precio,
            'descuento'   => (float)$i->descuento,
            'iva_pct'     => 0,
            'impuesto'    => (float)$i->impuesto,
            'total'       => (float)$i->total,
        ])->values()->toArray();

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

        $JS_OVERRIDES  = json_encode($overridesMap  ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES = json_encode($listPricesMap ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_CDEFAULTS  = json_encode($clientDefaults,     JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT  = json_encode($selClient,           JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_PRICELIST  = json_encode($selPriceList ?: 'client', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_ITEMS      = json_encode($itemsSeed,           JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        $entregaNombre  = old('entrega_nombre',   $quote->entrega_nombre   ?? '');
        $entregaTel     = old('entrega_telefono', $quote->entrega_telefono ?? '');
        $entregaCalle   = old('entrega_calle',    $quote->entrega_calle    ?? '');
        $entregaNumero  = old('entrega_numero',   $quote->entrega_numero   ?? '');
        $entregaColonia = old('entrega_colonia',  $quote->entrega_colonia  ?? '');
        $entregaCiudad  = old('entrega_ciudad',   $quote->entrega_ciudad   ?? '');
        $entregaEstado  = old('entrega_estado',   $quote->entrega_estado   ?? '');
        $entregaCp      = old('entrega_cp',       $quote->entrega_cp       ?? '');
    @endphp

    {{-- ====== FORMULARIO ====== --}}
    <x-wire-card>
        @if ($errors->any())
            <div class="mb-4 text-red-600 text-sm"><ul class="list-disc pl-5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul></div>
        @endif

        <form id="quote-edit-form" method="POST" action="{{ route('admin.quotes.update',$quote) }}" class="space-y-6">
            @csrf @method('PUT')

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            onchange="QE.onClientChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <p id="credito-info" class="mt-1 text-xs text-gray-500 hidden"></p>
                </div>

                {{-- Lista de precios --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
                    <select id="price_list_sel"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            onchange="QE.onPriceListChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="client" {{ (!$selPriceList || $selPriceList==='client') ? 'selected' : '' }}>Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}" {{ $selPriceList===(string)$pl->id ? 'selected' : '' }}>{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="price_list_id" id="price_list_id" value="{{ $selPriceList }}">
                </div>

                {{-- Fecha --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="fecha" value="{{ $valueFecha }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }} required>
                </div>

                {{-- Programado para --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Programado para</label>
                    <input type="date" name="programado_para" value="{{ $valueProg }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="{{ $valueMoneda }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                </div>

                {{-- Vigencia --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vigencia hasta</label>
                    <input type="date" name="vigencia_hasta" value="{{ $valueVigencia }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id" id="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- sin ruta --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selRoute===(string)$r->id ? 'selected' : '' }}>
                                {{ $r->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type" id="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            onchange="QE.onDeliveryChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="ENVIO"   {{ $deliveryType==='ENVIO'   ? 'selected' : '' }}>Envío a domicilio</option>
                        <option value="RECOGER" {{ $deliveryType==='RECOGER' ? 'selected' : '' }}>Recoger en almacén</option>
                    </select>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="payment_method" id="payment_method"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            onchange="QE.onPaymentChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="EFECTIVO"      {{ $paymentMethod==='EFECTIVO'      ? 'selected' : '' }}>Efectivo</option>
                        <option value="TRANSFERENCIA" {{ $paymentMethod==='TRANSFERENCIA' ? 'selected' : '' }}>Transferencia</option>
                        <option value="CONTRAENTREGA" {{ $paymentMethod==='CONTRAENTREGA' ? 'selected' : '' }}>Contraentrega</option>
                        <option value="CREDITO"       {{ $paymentMethod==='CREDITO'       ? 'selected' : '' }}>Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div id="credito-wrap" style="{{ $paymentMethod==='CREDITO' ? '' : 'display:none' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Días de crédito <span class="text-gray-400 font-normal text-xs">(del cliente)</span>
                    </label>
                    <input type="number" name="credit_days" id="credit_days"
                           value="{{ $quote->credit_days ?? 0 }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

            </div>

            {{-- ====== DIRECCIÓN DE ENTREGA ====== --}}
            <div id="entrega-section"
                 class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-4"
                 style="{{ $deliveryType==='ENVIO' ? '' : 'display:none' }}">
                <div class="md:col-span-3">
                    <p class="text-sm font-medium text-gray-700">Datos de entrega</p>
                </div>
                @foreach([
                    ['entrega_nombre',   'Nombre quien recibe', $entregaNombre],
                    ['entrega_telefono', 'Teléfono',            $entregaTel],
                    ['entrega_calle',    'Calle',               $entregaCalle],
                    ['entrega_numero',   'Número',              $entregaNumero],
                    ['entrega_colonia',  'Colonia',             $entregaColonia],
                    ['entrega_ciudad',   'Ciudad',              $entregaCiudad],
                    ['entrega_estado',   'Estado',              $entregaEstado],
                    ['entrega_cp',       'CP',                  $entregaCp],
                ] as [$fname, $flabel, $fval])
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $flabel }}</label>
                    <input type="text" name="{{ $fname }}" id="{{ $fname }}" value="{{ $fval }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>
                @endforeach
            </div>

            {{-- ====== ALERTA precio cero ====== --}}
            @if(!$isLocked)
            <div id="zero-price-alert" class="hidden rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 flex items-center justify-between">
                <span>Algunos productos no tienen precio para este cliente.</span>
                <a id="zero-price-link" href="#" target="_blank"
                   class="ml-3 inline-flex px-3 py-1.5 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">
                    Editar precios
                </a>
            </div>
            @endif

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
                            @if(!$isLocked)<th class="p-2 w-8"></th>@endif
                        </tr>
                    </thead>
                    <tbody id="items-body"></tbody>
                </table>
                @if(!$isLocked)
                <div class="mt-3">
                    <button type="button" onclick="QE.addRow()"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                        + Agregar partida
                    </button>
                </div>
                @endif
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

    {{-- ====== ACCIONES ====== --}}
    <x-wire-card class="mt-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                {{ $quote->status_label ?? $quote->status }}
            </span>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                @if(in_array($quote->status, ['BORRADOR','ENVIADA']))
                    <form method="POST" action="{{ route('admin.quotes.reject',$quote) }}">@csrf
                        <x-wire-button type="submit" amber xs>Rechazar</x-wire-button>
                    </form>
                @endif
                @if(!in_array($quote->status, ['CONVERTIDA','CANCELADA']))
                    <form method="POST" action="{{ route('admin.quotes.cancel',$quote) }}">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @endif
                @if(in_array($quote->status, ['BORRADOR','ENVIADA']))
                    <form method="POST" action="{{ route('admin.quotes.approve',$quote) }}">
                        @csrf
                        <input type="hidden" name="warehouse_id"      value="{{ $quote->warehouse_id      ?? $mainWarehouseId ?? '' }}">
                        <input type="hidden" name="payment_method"    value="{{ $quote->payment_method    ?? 'EFECTIVO' }}">
                        <input type="hidden" name="delivery_type"     value="{{ $quote->delivery_type     ?? 'ENVIO' }}">
                        <input type="hidden" name="credit_days"       value="{{ $quote->credit_days       ?? 0 }}">
                        <input type="hidden" name="programado_para"   value="{{ $valueProg }}">
                        <input type="hidden" name="shipping_route_id" value="{{ $quote->shipping_route_id ?? '' }}">
                        <input type="hidden" name="entrega_nombre"    value="{{ $entregaNombre }}">
                        <input type="hidden" name="entrega_telefono"  value="{{ $entregaTel }}">
                        <input type="hidden" name="entrega_calle"     value="{{ $entregaCalle }}">
                        <input type="hidden" name="entrega_numero"    value="{{ $entregaNumero }}">
                        <input type="hidden" name="entrega_colonia"   value="{{ $entregaColonia }}">
                        <input type="hidden" name="entrega_ciudad"    value="{{ $entregaCiudad }}">
                        <input type="hidden" name="entrega_estado"    value="{{ $entregaEstado }}">
                        <input type="hidden" name="entrega_cp"        value="{{ $entregaCp }}">
                        <x-wire-button type="submit" green xs>✓ Aprobar y generar pedido</x-wire-button>
                    </form>
                @endif
            </div>
        </div>
        @if(in_array($quote->status, ['APROBADA','CONVERTIDA']))
        <div class="mt-4 border-t pt-4">
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-emerald-100 text-emerald-700">
                ✓ Cotización aprobada — convertida en pedido
            </span>
        </div>
        @endif
    </x-wire-card>

    <script>
    (function(){
        const LOCKED         = {{ $isLocked ? 'true' : 'false' }};
        const CLIENTS_OVERRIDES = {!! $JS_OVERRIDES !!};
        const LISTS_PRICES      = {!! $JS_LISTPRICES !!};
        const CLIENT_DEFAULTS   = {!! $JS_CDEFAULTS !!};
        const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};
        const INITIAL_PRICE_LIST= {!! $JS_PRICELIST !!};
        const INITIAL_ITEMS     = {!! $JS_ITEMS !!};
        const CLIENTS_EDIT_BASE = '{{ url('admin/clients') }}';

        const PRODUCTS_OPTIONS = `@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach`;

        let state = {
            items: [],
            clientId: DEFAULT_CLIENT_ID || '',
            priceList: INITIAL_PRICE_LIST || 'client',
        };

        const fmt = n => Number(n||0).toFixed(2);
        const $  = id => document.getElementById(id);
        const set = (id, val) => { const el = $(id); if(el) el.value = val; };
        const setName = (name, val) => { const el = document.querySelector(`[name="${name}"]`); if(el && val !== undefined) el.value = val; };

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
                row.querySelector('.td-total').textContent   = fmt(it.total);
                row.querySelector('.hid-impuesto').value     = it.impuesto;
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

        function renderRow(i) {
            const it  = state.items[i];
            const dis = LOCKED ? 'disabled' : '';
            const tr  = document.createElement('tr');
            tr.className   = 'border-b';
            tr.dataset.idx = i;
            tr.innerHTML = `
                <td class="p-2">
                    <select class="w-48 border rounded p-1 text-sm sel-product" name="items[${i}][product_id]" ${dis}>
                        <option value="">—</option>
                        ${PRODUCTS_OPTIONS}
                    </select>
                </td>
                <td class="p-2">
                    <input type="text" class="w-64 border rounded p-1 text-sm inp-desc"
                           name="items[${i}][descripcion]" value="${escHtml(it.descripcion)}" ${dis} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0.001" step="0.001" class="w-24 border rounded p-1 text-right text-sm inp-cantidad"
                           name="items[${i}][cantidad]" value="${it.cantidad}" ${dis} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.0001" class="w-28 border rounded p-1 text-right text-sm inp-precio"
                           name="items[${i}][precio]" value="${it.precio}" ${dis} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01" class="w-24 border rounded p-1 text-right text-sm inp-descuento"
                           name="items[${i}][descuento]" value="${it.descuento}" ${dis}>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01" class="w-20 border rounded p-1 text-right text-sm inp-iva"
                           value="${it.iva_pct}" ${dis}>
                    <input type="hidden" class="hid-impuesto" name="items[${i}][impuesto]" value="${it.impuesto}">
                </td>
                <td class="p-2 text-right font-medium td-total">${fmt(it.total)}</td>
                ${!LOCKED ? `<td class="p-2 text-center"><button type="button" class="text-red-500 hover:text-red-700 text-xs btn-remove">✕</button></td>` : ''}
            `;

            const sel = tr.querySelector('.sel-product');
            if (it.product_id) sel.value = it.product_id;
            if (!LOCKED) {
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
            }
            return tr;
        }

        function renderAll() {
            const tbody = $('items-body');
            tbody.innerHTML = '';
            state.items.forEach((_, i) => tbody.appendChild(renderRow(i)));
            updateTotals();
        }

        window.QE = {
            addRow() {
                if (LOCKED) return;
                state.items.push({product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0});
                renderAll();
            },
            onClientChange(clientId) {
                if (LOCKED) return;
                state.clientId = clientId;
                const d = CLIENT_DEFAULTS[clientId];
                if (!d) return;
                if (d.shipping_route_id) set('shipping_route_id', d.shipping_route_id);
                if (d.price_list_id) { $('price_list_sel').value = d.price_list_id; QE.onPriceListChange(d.price_list_id); }
                set('credit_days', d.credito_dias || 0);
                if (d.credito_dias > 0) { set('payment_method', 'CREDITO'); QE.onPaymentChange('CREDITO'); }
                if (d.credito_limite > 0) {
                    const info = $('credito-info');
                    if(info) { info.textContent = `Límite: $${fmt(d.credito_limite)} · Días: ${d.credito_dias}d`; info.classList.remove('hidden'); }
                }
                ['telefono','calle','numero','colonia','ciudad','estado','cp'].forEach(f => {
                    const key = f === 'telefono' ? 'telefono' : `entrega_${f}`;
                    const el = $(`entrega_${f}`);
                    if (el && d[key]) el.value = d[key];
                });
                QE.repriceAll();
            },
            onPriceListChange(val) {
                state.priceList = val;
                set('price_list_id', val === 'client' ? '' : val);
                QE.repriceAll();
            },
            onDeliveryChange(val) {
                $('entrega-section').style.display = val === 'ENVIO' ? '' : 'none';
            },
            onPaymentChange(val) {
                $('credito-wrap').style.display = val === 'CREDITO' ? '' : 'none';
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
        // Init crédito
        if (DEFAULT_CLIENT_ID) {
            const d = CLIENT_DEFAULTS[DEFAULT_CLIENT_ID];
            if (d && d.credito_limite > 0) {
                const info = $('credito-info');
                if(info) { info.textContent = `Límite: $${fmt(d.credito_limite)} · Días: ${d.credito_dias}d`; info.classList.remove('hidden'); }
            }
        }
        QE.onDeliveryChange('{{ $deliveryType }}');
        QE.onPaymentChange('{{ $paymentMethod }}');
        renderAll();
    })();
    </script>

</x-admin-layout>