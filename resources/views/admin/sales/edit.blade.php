<x-admin-layout
    title="Editar nota de venta"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Ventas','url'=>route('admin.sales.index')],
        ['name'=>'Editar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        @if($sale->status === 'ABIERTA')
            <button form="sale-edit-form" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
    </x-slot>

    @php
        $isLocked = $sale->status !== 'ABIERTA';

        $selClient    = (string) old('client_id',         $sale->client_id);
        $selWh        = (string) old('warehouse_id',       $sale->warehouse_id);
        $selPos       = (string) old('pos_register_id',    $sale->pos_register_id);
        $selPayType   = (string) old('payment_type_id',    $sale->payment_type_id);
        $selPriceList = (string) old('price_list_id',      $sale->price_list_id);
        $selRoute     = (string) old('shipping_route_id',  $sale->shipping_route_id);
        $selDriver    = (string) old('driver_id',          $sale->driver_id);
        $tipoVenta    = old('tipo_venta',   $sale->tipo_venta);
        $creditDays   = old('credit_days',  $sale->credit_days);
        $deliveryType = old('delivery_type',$sale->delivery_type);

        $entregaNombre   = old('entrega_nombre',   $sale->entrega_nombre);
        $entregaTelefono = old('entrega_telefono', $sale->entrega_telefono);
        $entregaCalle    = old('entrega_calle',    $sale->entrega_calle);
        $entregaNumero   = old('entrega_numero',   $sale->entrega_numero);
        $entregaColonia  = old('entrega_colonia',  $sale->entrega_colonia);
        $entregaCiudad   = old('entrega_ciudad',   $sale->entrega_ciudad);
        $entregaEstado   = old('entrega_estado',   $sale->entrega_estado);
        $entregaCp       = old('entrega_cp',       $sale->entrega_cp);
        $valueFecha      = old('fecha', optional($sale->fecha)->format('Y-m-d\TH:i'));
        $moneda          = old('moneda', $sale->moneda ?? 'MXN');

        $statusClasses = [
            'BORRADOR'     => 'bg-gray-100 text-gray-700',
            'APROBADO'     => 'bg-blue-100 text-blue-700',
            'ABIERTA'      => 'bg-amber-100 text-amber-700',
            'PREPARANDO'   => 'bg-amber-100 text-amber-700',
            'PROCESADA'    => 'bg-amber-100 text-amber-700',
            'EN_RUTA'      => 'bg-violet-100 text-violet-700',
            'ENTREGADA'    => 'bg-emerald-100 text-emerald-700',
            'NO_ENTREGADA' => 'bg-slate-100 text-slate-700',
            'CERRADA'      => 'bg-emerald-100 text-emerald-700',
            'CANCELADA'    => 'bg-rose-100 text-rose-700',
        ];
        $statusClass = $statusClasses[$sale->status] ?? 'bg-slate-100 text-slate-700';

        $itemsSeed = $sale->items->map(fn($i) => [
            'product_id'  => $i->product_id,
            'descripcion' => $i->descripcion ?? ($i->product?->nombre ?? ''),
            'cantidad'    => (float)$i->cantidad,
            'precio'      => (float)$i->precio,
            'descuento'   => (float)$i->descuento,
            'iva_pct'     => 0,
            'impuesto'    => (float)$i->impuesto,
            'total'       => (float)$i->total,
        ])->values()->toArray();

        $JS_ITEMS = json_encode($itemsSeed, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    <x-wire-card>
        <form id="sale-edit-form" method="POST"
              action="{{ route('admin.sales.update', $sale) }}" class="space-y-6">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Caja --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Caja</label>
                    <select name="pos_register_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="">-- seleccionar --</option>
                        @foreach($posRegisters as $pos)
                            <option value="{{ $pos->id }}" {{ $selPos===(string)$pos->id ? 'selected' : '' }}>
                                {{ $pos->nombre ?? ('Caja #'.$pos->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWh===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- mostrador / sin cliente --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Lista de precios --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
                    <select name="price_list_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">— Personalizada del cliente —</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}" {{ $selPriceList===(string)$pl->id ? 'selected' : '' }}>
                                {{ $pl->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="fecha" value="{{ $valueFecha }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="{{ $moneda }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                </div>

                {{-- Forma de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pago</label>
                    <select name="payment_type_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($payTypes as $pt)
                            <option value="{{ $pt->id }}" {{ $selPayType===(string)$pt->id ? 'selected' : '' }}>
                                {{ $pt->label ?? $pt->descripcion ?? $pt->clave }} ({{ $pt->clave }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo de venta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de venta</label>
                    <select name="tipo_venta" id="tipo_venta"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            onchange="SEF.onTipoVentaChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="CONTADO"       {{ $tipoVenta==='CONTADO'       ? 'selected' : '' }}>Contado</option>
                        <option value="CREDITO"       {{ $tipoVenta==='CREDITO'       ? 'selected' : '' }}>Crédito</option>
                        <option value="CONTRAENTREGA" {{ $tipoVenta==='CONTRAENTREGA' ? 'selected' : '' }}>Contraentrega</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div id="credito-wrap" style="{{ $tipoVenta==='CREDITO' ? '' : 'display:none' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Días de crédito</label>
                    <input type="number" name="credit_days" min="0" value="{{ $creditDays ?? 0 }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type" id="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            onchange="SEF.onDeliveryChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="ENVIO"   {{ $deliveryType==='ENVIO'   ? 'selected' : '' }}>Envío a domicilio</option>
                        <option value="RECOGER" {{ $deliveryType==='RECOGER' ? 'selected' : '' }}>Recoger en almacén</option>
                    </select>
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- sin ruta --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selRoute===(string)$r->id ? 'selected' : '' }}>
                                {{ $r->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Chofer --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chofer</label>
                    <select name="driver_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- sin chofer --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selDriver===(string)$d->id ? 'selected' : '' }}>
                                {{ $d->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- Dirección de entrega --}}
            <div id="entrega-section"
                 class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-4"
                 style="{{ $deliveryType==='ENVIO' ? '' : 'display:none' }}">
                @foreach([
                    ['entrega_nombre',   'Nombre quien recibe', $entregaNombre],
                    ['entrega_telefono', 'Teléfono',            $entregaTelefono],
                    ['entrega_calle',    'Calle',               $entregaCalle],
                    ['entrega_numero',   'Número',              $entregaNumero],
                    ['entrega_colonia',  'Colonia',             $entregaColonia],
                    ['entrega_ciudad',   'Ciudad',              $entregaCiudad],
                    ['entrega_estado',   'Estado',              $entregaEstado],
                    ['entrega_cp',       'CP',                  $entregaCp],
                ] as [$fname, $flabel, $fval])
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $flabel }}</label>
                    <input type="text" name="{{ $fname }}" value="{{ $fval }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>
                @endforeach
            </div>

            {{-- Partidas --}}
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
                    <button type="button" onclick="SEF.addRow()"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                        + Agregar partida
                    </button>
                </div>
                @endif
            </div>

            {{-- Totales --}}
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

    {{-- Acciones --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2">
            <x-wire-badge>Folio: {{ $sale->folio ?? ('Sale #'.$sale->id) }}</x-wire-badge>
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                Estatus: {{ $sale->status }}
            </span>
            <div class="ml-auto flex items-center space-x-2">
                <x-wire-button href="{{ route('admin.sales.pdf',$sale) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
                <x-wire-button href="{{ route('admin.sales.pdf.download',$sale) }}" gray xs>Descargar PDF</x-wire-button>
                <x-wire-button href="{{ route('admin.sales.send.form',$sale) }}" violet xs>Enviar</x-wire-button>

                @if($sale->status === 'BORRADOR')
                    <form method="POST" action="{{ route('admin.sales.approve',$sale) }}">@csrf
                        <x-wire-button type="submit" blue xs>Aprobar</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @elseif($sale->status === 'APROBADO')
                    <form method="POST" action="{{ route('admin.sales.prepare',$sale) }}">@csrf
                        <x-wire-button type="submit" amber xs>Preparar</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.process',$sale) }}">@csrf
                        <x-wire-button type="submit" teal xs>Procesar</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @elseif($sale->status === 'ABIERTA')
                    <form method="POST" action="{{ route('admin.sales.prepare',$sale) }}">@csrf
                        <x-wire-button type="submit" amber xs>Preparar</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.process',$sale) }}">@csrf
                        <x-wire-button type="submit" teal xs>Procesar</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @elseif($sale->status === 'PREPARANDO')
                    <form method="POST" action="{{ route('admin.sales.process',$sale) }}">@csrf
                        <x-wire-button type="submit" teal xs>Procesar (descontar stock)</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @elseif($sale->status === 'PROCESADA')
                    @if($sale->delivery_type === 'ENVIO')
                        @if($sale->driver_id)
                            <form method="POST" action="{{ route('admin.sales.en-ruta',$sale) }}">@csrf
                                <x-wire-button type="submit" violet xs>Enviar a ruta</x-wire-button>
                            </form>
                        @else
                            <x-wire-badge class="bg-rose-100 text-rose-700">Asigna chofer para salir a ruta</x-wire-badge>
                        @endif
                    @else
                        <form method="POST" action="{{ route('admin.sales.deliver',$sale) }}">@csrf
                            <x-wire-button type="submit" green xs>Marcar entregada</x-wire-button>
                        </form>
                    @endif
                @elseif($sale->status === 'EN_RUTA')
                    <form method="POST" action="{{ route('admin.sales.deliver',$sale) }}">@csrf
                        <x-wire-button type="submit" green xs>Entregada</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.not-delivered',$sale) }}">@csrf
                        <x-wire-button type="submit" gray xs>No entregada</x-wire-button>
                    </form>
                    @if($sale->tipo_venta === 'CONTRAENTREGA')
                        <form method="POST" action="{{ route('admin.sales.cobrar',$sale) }}">@csrf
                            <x-wire-button type="submit" indigo xs>Cobrar contraentrega</x-wire-button>
                        </form>
                    @endif
                @elseif($sale->status === 'ENTREGADA')
                    @if($sale->tipo_venta === 'CONTRAENTREGA')
                        <form method="POST" action="{{ route('admin.sales.liquidar',$sale) }}">@csrf
                            <x-wire-button type="submit" fuchsia xs>Liquidar chofer</x-wire-button>
                        </form>
                    @endif
                @elseif($sale->status === 'NO_ENTREGADA')
                    <form method="POST" action="{{ route('admin.sales.en-ruta',$sale) }}">@csrf
                        <x-wire-button type="submit" violet xs>Reprogramar a ruta</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @endif
            </div>
        </div>
    </x-wire-card>

    <script>
    (function(){
        const LOCKED        = {{ $isLocked ? 'true' : 'false' }};
        const INITIAL_ITEMS = {!! $JS_ITEMS !!};

        const PRODUCTS_OPTIONS = `@foreach($products as $p)<option value="{{ $p->id }}" data-precio="{{ $p->precio_base }}">{{ $p->nombre }}</option>@endforeach`;

        let state = { items: [] };

        const fmt = n => Number(n||0).toFixed(2);
        const $   = id => document.getElementById(id);
        const set = (id, val) => { const el = $(id); if(el) el.value = val; };

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
            let s=0, d=0, t=0, g=0;
            state.items.forEach(it => {
                const line = (+it.cantidad||0)*(+it.precio||0);
                const disc = +it.descuento||0;
                const base = Math.max(line-disc, 0);
                const tax  = ((+it.iva_pct||0)/100)*base;
                s += line; d += disc; t += tax; g += base+tax;
            });
            $('tot-subtotal').textContent = fmt(s);
            $('tot-desc').textContent     = fmt(d);
            $('tot-tax').textContent      = fmt(t);
            $('tot-grand').textContent    = fmt(g);
            set('h-subtotal',  fmt(s));
            set('h-descuento', fmt(d));
            set('h-impuestos', fmt(t));
            set('h-total',     fmt(g));
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
                    <select class="w-48 border rounded p-1 text-sm sel-product"
                            name="items[${i}][product_id]" ${dis}>
                        <option value="">—</option>
                        ${PRODUCTS_OPTIONS}
                    </select>
                </td>
                <td class="p-2">
                    <input type="text" class="w-64 border rounded p-1 text-sm inp-desc"
                           name="items[${i}][descripcion]" value="${escHtml(it.descripcion)}" ${dis} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0.001" step="0.001"
                           class="w-24 border rounded p-1 text-right text-sm inp-cantidad"
                           name="items[${i}][cantidad]" value="${it.cantidad}" ${dis} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.0001"
                           class="w-28 border rounded p-1 text-right text-sm inp-precio"
                           name="items[${i}][precio]" value="${it.precio}" ${dis} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01"
                           class="w-24 border rounded p-1 text-right text-sm inp-descuento"
                           name="items[${i}][descuento]" value="${it.descuento}" ${dis}>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01"
                           class="w-20 border rounded p-1 text-right text-sm inp-iva"
                           value="${it.iva_pct}" ${dis}>
                    <input type="hidden" class="hid-impuesto"
                           name="items[${i}][impuesto]" value="${it.impuesto}">
                </td>
                <td class="p-2 text-right font-medium td-total">${fmt(it.total)}</td>
                ${!LOCKED ? `<td class="p-2 text-center"><button type="button" class="text-red-500 hover:text-red-700 text-xs btn-remove">✕</button></td>` : ''}
            `;

            const sel = tr.querySelector('.sel-product');
            if (it.product_id) sel.value = it.product_id;

            if (!LOCKED) {
                sel.addEventListener('change', function() {
                    state.items[i].product_id = this.value;
                    const opt = this.options[this.selectedIndex];
                    if (!state.items[i].descripcion) {
                        state.items[i].descripcion = opt?.text || '';
                        tr.querySelector('.inp-desc').value = state.items[i].descripcion;
                    }
                    if (!state.items[i].precio) {
                        state.items[i].precio = parseFloat(opt?.dataset?.precio || 0) || 0;
                        tr.querySelector('.inp-precio').value = state.items[i].precio;
                    }
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
                tr.querySelector('.btn-remove')?.addEventListener('click', function() {
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

        window.SEF = {
            addRow() {
                if (LOCKED) return;
                state.items.push({product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0});
                renderAll();
            },
            onTipoVentaChange(val) {
                $('credito-wrap').style.display = val === 'CREDITO' ? '' : 'none';
            },
            onDeliveryChange(val) {
                $('entrega-section').style.display = val === 'ENVIO' ? '' : 'none';
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