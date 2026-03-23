<x-admin-layout
    title="Editar pedido"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Pedidos','url'=>route('admin.sales-orders.index')],
        ['name'=>'Editar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales-orders.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        @if($order->status === 'BORRADOR')
            <button form="so-edit-form" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @elseif($order->status === 'PREPARANDO')
            <button form="so-edit-form" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Guardar cantidades
            </button>
        @endif
    </x-slot>

    @php
        $isLocked   = !in_array($order->status, ['BORRADOR','PREPARANDO']);
        $canEditQty = in_array($order->status, ['BORRADOR','PREPARANDO']);

        $selClient    = (string) old('client_id',         $order->client_id);
        $selWarehouse = (string) old('warehouse_id',       $order->warehouse_id);
        $selRoute     = (string) old('shipping_route_id',  $order->shipping_route_id);
        $selDriver    = (string) old('driver_id',          $order->driver_id);

        $valueFecha    = old('fecha',           optional($order->fecha)->format('Y-m-d\TH:i'));
        $valueProg     = old('programado_para', optional($order->programado_para)->toDateString());
        $valueMoneda   = old('moneda',          $order->moneda);
        $deliveryType  = old('delivery_type',   $order->delivery_type);
        $paymentMethod = old('payment_method',  $order->payment_method);

        $statusClasses = [
            'BORRADOR'     => 'bg-gray-100 text-gray-700',
            'APROBADO'     => 'bg-blue-100 text-blue-700',
            'PREPARANDO'   => 'bg-sky-100 text-sky-700',
            'PROCESADO'    => 'bg-amber-100 text-amber-700',
            'EN_RUTA'      => 'bg-violet-100 text-violet-700',
            'ENTREGADO'    => 'bg-emerald-100 text-emerald-700',
            'NO_ENTREGADO' => 'bg-orange-100 text-orange-700',
            'CANCELADO'    => 'bg-rose-100 text-rose-700',
        ];
        $statusClass = $statusClasses[$order->status] ?? 'bg-slate-100 text-slate-700';

        $itemsSeed = $order->items->map(fn($i) => [
            'id'          => $i->id,
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

        $JS_CLIENT_DEFAULTS = json_encode($clientDefaults, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT       = json_encode($selClient,      JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_ITEMS           = json_encode($itemsSeed,      JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        $entregaNombre  = old('entrega_nombre',   $order->entrega_nombre   ?? '');
        $entregaTel     = old('entrega_telefono', $order->entrega_telefono ?? '');
        $entregaCalle   = old('entrega_calle',    $order->entrega_calle    ?? '');
        $entregaNumero  = old('entrega_numero',   $order->entrega_numero   ?? '');
        $entregaColonia = old('entrega_colonia',  $order->entrega_colonia  ?? '');
        $entregaCiudad  = old('entrega_ciudad',   $order->entrega_ciudad   ?? '');
        $entregaEstado  = old('entrega_estado',   $order->entrega_estado   ?? '');
        $entregaCp      = old('entrega_cp',       $order->entrega_cp       ?? '');
    @endphp

    {{-- ====== FORMULARIO ====== --}}
    <x-wire-card>
        <form id="so-edit-form" method="POST"
              action="{{ route('admin.sales-orders.update',$order) }}"
              class="space-y-6">
            @csrf @method('PUT')

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="SOE.onClientChange(this.value)"
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

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
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
                    <select name="price_list_id"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="client">Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}"
                                {{ old('price_list_id', $order->price_list_id) == $pl->id ? 'selected' : '' }}>
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

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type" id="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="SOE.onDeliveryChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="ENVIO"   {{ $deliveryType==='ENVIO'   ? 'selected' : '' }}>Envío a domicilio</option>
                        <option value="RECOGER" {{ $deliveryType==='RECOGER' ? 'selected' : '' }}>Recoger en almacén</option>
                    </select>
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id" id="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm"
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
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- sin chofer --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selDriver===(string)$d->id ? 'selected' : '' }}>
                                {{ $d->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="payment_method" id="payment_method"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            onchange="SOE.onPaymentChange(this.value)"
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
                           value="{{ $order->credit_days ?? 0 }}"
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
                    <button type="button" onclick="SOE.addRow()"
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
        <div class="flex items-center space-x-2">
            <x-wire-button href="{{ route('admin.sales-orders.edit',$order) }}" blue xs>Editar</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.pdf',$order) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.pdf.download',$order) }}" gray xs>Descargar PDF</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.send.form',$order) }}" violet xs>Enviar</x-wire-button>

            <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                Estatus: {{ $order->status_label }}
            </span>

            <div class="ml-auto flex items-center space-x-2">
                @if($order->status === 'BORRADOR')
                    <form action="{{ route('admin.sales-orders.approve',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" green xs>Aprobar</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.cancel',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @elseif($order->status === 'APROBADO')
                    <form action="{{ route('admin.sales-orders.prepare',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" blue xs>Preparar</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.process',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" amber xs>Procesar</x-wire-button>
                    </form>
                @elseif($order->status === 'PREPARANDO')
                    <form action="{{ route('admin.sales-orders.process',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" amber xs>Procesar</x-wire-button>
                    </form>
                @elseif($order->status === 'PROCESADO')
                    <form action="{{ route('admin.sales-orders.en-ruta',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" violet xs>Enviar a ruta</x-wire-button>
                    </form>
                @elseif($order->status === 'EN_RUTA')
                    <form action="{{ route('admin.sales-orders.deliver',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" emerald xs>Entregar</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.not-delivered',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" gray xs>No entregado</x-wire-button>
                    </form>
                    @if($order->payment_method === 'CONTRAENTREGA')
                    <form action="{{ route('admin.sales-orders.cobrar',$order) }}" method="POST" class="inline-flex items-center space-x-1">
                        @csrf
                        <input type="number" name="monto" min="0" step="0.01" placeholder="Monto"
                               class="w-24 border rounded px-2 py-1 text-sm">
                        <x-wire-button type="submit" gray xs>Cobrar</x-wire-button>
                    </form>
                    @endif
                @elseif($order->status === 'NO_ENTREGADO')
                    <form action="{{ route('admin.sales-orders.en-ruta',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" violet xs>Reintentar ruta</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.cancel',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Liquidación chofer --}}
        @if(in_array($order->status, ['ENTREGADO','NO_ENTREGADO']) && ($order->driver_settlement_status ?? '') !== 'LIQUIDADO')
        <div class="mt-4 border-t pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">Liquidar cobro del chofer</p>
            <form method="POST" action="{{ route('admin.sales-orders.liquidar',$order) }}">
                @csrf
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Monto entregado</label>
                        <input type="number" name="monto_entregado" min="0" step="0.01"
                               value="{{ $order->total }}"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Forma de pago</label>
                        <select name="payment_type_id"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                            <option value="">-- seleccionar --</option>
                            @foreach(\App\Models\PaymentType::orderBy('descripcion')->get() as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->descripcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Caja del día (opcional)</label>
                        <select name="pos_register_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">-- sin caja --</option>
                            @foreach(\App\Models\CashRegister::where('estatus','ABIERTO')->latest()->get() as $cr)
                                <option value="{{ $cr->id }}">
                                    {{ $cr->warehouse->nombre ?? 'Sin almacén' }} — {{ $cr->fecha }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Referencia (opcional)</label>
                        <input type="text" name="referencia" maxlength="255"
                               placeholder="Folio, transferencia, etc."
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit"
                            class="inline-flex px-4 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-700">
                        Confirmar liquidación
                    </button>
                </div>
            </form>
        </div>
        @endif

        @if(($order->driver_settlement_status ?? '') === 'LIQUIDADO')
        <div class="mt-4 border-t pt-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-emerald-100 text-emerald-700">
                Chofer liquidado — {{ optional($order->driver_settlement_at)->format('d/m/Y H:i') }}
            </span>
        </div>
        @endif
    </x-wire-card>

    <script>
    (function(){
        const LOCKED         = {{ $isLocked ? 'true' : 'false' }};
        const CAN_EDIT_QTY   = {{ $canEditQty ? 'true' : 'false' }};
        const CLIENT_DEFAULTS= {!! $JS_CLIENT_DEFAULTS !!};
        const DEFAULT_CLIENT = {!! $JS_SELCLIENT !!};
        const INITIAL_ITEMS  = {!! $JS_ITEMS !!};
        const CLIENTS_EDIT_BASE = '{{ url('admin/clients') }}';

        const PRODUCTS_OPTIONS = `@foreach($products as $p)<option value="{{ $p->id }}" data-precio="{{ $p->precio_base }}">{{ $p->nombre }}</option>@endforeach`;

        let state = {
            items: [],
            clientId: DEFAULT_CLIENT || '',
        };

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
                row.querySelector('.td-total').textContent  = fmt(it.total);
                row.querySelector('.hid-impuesto').value    = it.impuesto;
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
            const disQty = CAN_EDIT_QTY ? '' : 'disabled';
            const tr  = document.createElement('tr');
            tr.className   = 'border-b';
            tr.dataset.idx = i;
            tr.innerHTML = `
                <input type="hidden" name="items[${i}][id]" value="${it.id || ''}">
                <td class="p-2">
                    <select class="w-48 border rounded p-1 text-sm sel-product"
                            name="items[${i}][product_id]" ${dis}>
                        <option value="">—</option>
                        ${PRODUCTS_OPTIONS}
                    </select>
                </td>
                <td class="p-2">
                    <input type="text" class="w-64 border rounded p-1 text-sm inp-desc"
                           name="items[${i}][descripcion]" value="${escHtml(it.descripcion)}"
                           ${dis} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0.001" step="0.001"
                           class="w-24 border rounded p-1 text-right text-sm inp-cantidad"
                           name="items[${i}][cantidad]" value="${it.cantidad}"
                           ${disQty} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.0001"
                           class="w-28 border rounded p-1 text-right text-sm inp-precio"
                           name="items[${i}][precio]" value="${it.precio}"
                           ${dis} required>
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

            if (CAN_EDIT_QTY) {
                tr.querySelector('.inp-cantidad').addEventListener('input', function() {
                    state.items[i].cantidad = parseFloat(this.value)||0; recalcRow(i);
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

        window.SOE = {
            addRow() {
                if (LOCKED) return;
                state.items.push({id:null,product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0});
                renderAll();
            },
            onClientChange(clientId) {
                if (LOCKED) return;
                state.clientId = clientId;
                const d = CLIENT_DEFAULTS[clientId];
                if (!d) return;
                if (d.shipping_route_id) set('shipping_route_id', d.shipping_route_id);
                set('credit_days', d.credito_dias || 0);
                if (d.credito_dias > 0) { set('payment_method', 'CREDITO'); SOE.onPaymentChange('CREDITO'); }
                if (d.credito_limite > 0) {
                    const info = $('credito-info');
                    if(info) { info.textContent = `Límite: $${fmt(d.credito_limite)} · Días: ${d.credito_dias}d`; info.classList.remove('hidden'); }
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
            },
            onDeliveryChange(val) {
                $('entrega-section').style.display = val === 'ENVIO' ? '' : 'none';
            },
            onPaymentChange(val) {
                $('credito-wrap').style.display = val === 'CREDITO' ? '' : 'none';
            },
        };

        // Init
        state.items = JSON.parse(JSON.stringify(INITIAL_ITEMS));
        if (!state.items.length) {
            state.items = [{id:null,product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}];
        }
        // Init crédito
        if (DEFAULT_CLIENT) {
            const d = CLIENT_DEFAULTS[DEFAULT_CLIENT];
            if (d && d.credito_limite > 0) {
                const info = $('credito-info');
                if(info) { info.textContent = `Límite: $${fmt(d.credito_limite)} · Días: ${d.credito_dias}d`; info.classList.remove('hidden'); }
            }
        }
        SOE.onDeliveryChange('{{ $deliveryType }}');
        SOE.onPaymentChange('{{ $paymentMethod }}');
        renderAll();
    })();
    </script>

</x-admin-layout>