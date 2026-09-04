<x-admin-layout
    title="Editar nota de venta"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Ventas','url'=>route('admin.sales.index')],
        ['name'=>'Editar'],
    ]"
>
    @php
        $puedeEditarCerrados = $puedeEditarCerrados ?? false;
        // Igual que en Pedidos: BORRADOR siempre editable; una nota ya
        // COMPLETADA solo se desbloquea viniendo de Gestión de notas con el
        // permiso 'editar pedidos cerrados'. Cualquier otro estatus (o
        // CANCELADA) se queda bloqueado.
        $editandoCerrado = $puedeEditarCerrados && $sale->status === \App\Models\Sale::S_COMPLETADA;
        $isLocked        = !($sale->status === 'BORRADOR' || $editandoCerrado);
    @endphp

    <x-slot name="action">
        <a href="{{ route('admin.sales.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        @if(!$isLocked)
            <button form="sale-edit-form" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
    </x-slot>

    @if($editandoCerrado)
    <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        ⚠️ Esta nota ya está <strong>COMPLETADA</strong> — la estás editando con permiso de Gestión de notas.
        Al guardar se ajusta automáticamente el inventario y, según el tipo de venta, la CxC del cliente o la caja
        (solo si sigue ABIERTA).
    </div>
    @endif

    @php
        $selClient    = (string) old('client_id',         $sale->client_id);
        $selWh        = (string) old('warehouse_id',       $sale->warehouse_id);
        $selCaja      = (string) old('cash_register_id',   $sale->cash_register_id);
        $selPayType   = (string) old('payment_type_id',    $sale->payment_type_id);
        $selPriceList = (string) old('price_list_id',      $sale->price_list_id);
        $tipoVenta    = old('tipo_venta',   $sale->tipo_venta);
        $creditDays   = old('credit_days',  $sale->credit_days);
        $valueFecha   = old('fecha', optional($sale->fecha)->format('Y-m-d\TH:i'));
        $moneda       = old('moneda', $sale->moneda ?? 'MXN');

        $statusClasses = [
            'BORRADOR'     => 'bg-gray-100 text-gray-700',
            'APROBADO'     => 'bg-blue-100 text-blue-700',
            'ABIERTA'      => 'bg-amber-100 text-amber-700',
            'PREPARANDO'   => 'bg-amber-100 text-amber-700',
            'PROCESADA'    => 'bg-amber-100 text-amber-700',
            'EN_RUTA'      => 'bg-violet-100 text-violet-700',
            'ENTREGADA'    => 'bg-emerald-100 text-emerald-700',
            'COMPLETADA'   => 'bg-emerald-100 text-emerald-700',
            'NO_ENTREGADA' => 'bg-slate-100 text-slate-700',
            'CERRADA'      => 'bg-emerald-100 text-emerald-700',
            'CANCELADA'    => 'bg-rose-100 text-rose-700',
        ];
        $statusClass = $statusClasses[$sale->status] ?? 'bg-slate-100 text-slate-700';

        $itemsSeed = $sale->items->map(fn($i) => [
            'product_id'      => $i->product_id,
            '_productoNombre' => $i->product?->nombre ?? $i->descripcion,
            'descripcion'     => $i->descripcion ?? ($i->product?->nombre ?? ''),
            'cantidad'        => (float) $i->cantidad,
            'num_cajas'       => $i->num_cajas,
            'precio'          => (float) $i->precio,
            'descuento'       => (float) $i->descuento,
            'iva_pct'         => 0,
            'impuesto'        => (float) $i->impuesto,
            'total'           => (float) $i->total,
        ])->values()->toArray();

        $JS_ITEMS           = json_encode($itemsSeed, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_OVERRIDES       = json_encode($overrides   ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES      = json_encode($listItems   ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT       = json_encode($selClient,          JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    <x-wire-card>
        <form id="sale-edit-form" method="POST"
              action="{{ route('admin.sales.update', $sale) }}" class="space-y-6">
            @csrf @method('PUT')
            @if(request('origen') === 'gestion-notas')
                <input type="hidden" name="origen" value="gestion-notas">
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p class="font-medium">No se guardó la nota:</p>
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Caja --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Caja</label>
                    <select name="cash_register_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="">-- seleccionar --</option>
                        @foreach($cashRegisters as $cr)
                            <option value="{{ $cr->id }}" {{ $selCaja===(string)$cr->id ? 'selected' : '' }}>
                                {{ $cr->warehouse?->nombre ?? ('Caja #'.$cr->id) }} — {{ $cr->user?->name }}
                                {{ $cr->estatus !== 'ABIERTO' ? ' (cerrada)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id" id="warehouse_id"
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
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- público general --</option>
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
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            onchange="SEF.onPriceListChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="client" {{ !$selPriceList ? 'selected' : '' }}>Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}" {{ $selPriceList===(string)$pl->id ? 'selected' : '' }}>{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="price_list_id" id="price_list_id" value="{{ $selPriceList }}">
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
                        <option value="CONTADO" {{ $tipoVenta==='CONTADO' ? 'selected' : '' }}>Contado</option>
                        <option value="CREDITO" {{ $tipoVenta==='CREDITO' ? 'selected' : '' }}>Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div id="credito-wrap" style="{{ $tipoVenta==='CREDITO' ? '' : 'display:none' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Días de crédito</label>
                    <input type="number" name="credit_days" id="credit_days" min="0" value="{{ $creditDays ?? 0 }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

            </div>

            {{-- Comentarios --}}
            <div class="border-t pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Comentarios</label>
                <textarea name="comentarios" rows="2" autocomplete="off"
                          class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                          {{ $isLocked ? 'readonly' : '' }}>{{ old('comentarios', $sale->comentarios) }}</textarea>
            </div>

            {{-- Alerta precio cero --}}
            <div id="zero-price-alert" class="hidden rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 flex items-center justify-between">
                <span>Algunos productos no tienen precio para este cliente — captúralo directo en la fila del producto.</span>
                @can('editar clientes')
                    <a id="zero-price-link" href="#" target="_blank"
                       class="ml-3 inline-flex px-3 py-1.5 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">
                        Ver/editar todos los precios
                    </a>
                @endcan
            </div>

            {{-- Partidas --}}
            <div class="overflow-auto border-t pt-4">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50">
                        <tr>
                            <th class="p-2 text-left">Producto</th>
                            <th class="p-2 text-left">Descripción</th>
                            <th class="p-2 text-right">Cantidad</th>
                            <th class="p-2 text-center" title="Número aproximado de cajas (referencia)">Cajas</th>
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
                <x-wire-button type="button" gray xs
                    onclick="preguntarFormatoPdf('{{ route('admin.sales.pdf.download',$sale) }}', '{{ route('admin.sales.ticket.pdf',$sale) }}')">
                    Descargar PDF
                </x-wire-button>
                <x-wire-button href="{{ route('admin.sales.ticket',$sale) }}" gray outline xs target="_blank">🧾 Imprimir ticket</x-wire-button>
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
                @elseif($sale->status === 'COMPLETADA')
                    <form id="form-cancel-sale-edit" method="POST" action="{{ route('admin.sales.cancel',$sale) }}">@csrf
                        <x-wire-button type="button" red xs
                            onclick="Swal.fire({title:'¿Cancelar esta nota?',text:'Revierte el inventario y, si aplica, la CxC o el efectivo de caja.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, cancelar',confirmButtonColor:'#dc2626',cancelButtonText:'Volver'}).then(r=>{if(r.isConfirmed) document.getElementById('form-cancel-sale-edit').submit();})">
                            Cancelar
                        </x-wire-button>
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
        const LOCKED            = {{ $isLocked ? 'true' : 'false' }};
        const INITIAL_ITEMS     = {!! $JS_ITEMS !!};
        const CLIENTS_OVERRIDES = {!! $JS_OVERRIDES !!};
        const LISTS_PRICES      = {!! $JS_LISTPRICES !!};
        const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};
        const PRODUCTS               = @json($productsJson);
        const CLIENTS_EDIT_BASE      = '{{ url('admin/clients') }}';
        const CLIENT_PRICES_BASE     = '{{ url('admin/sales-orders/client-prices') }}';
        const CAN_EDIT_CLIENT_PRICES = @json(auth()->user()->can('editar clientes'));

        let state = { items: [], clientId: DEFAULT_CLIENT_ID || '', priceList: '{{ $selPriceList ?: 'client' }}' };

        const fmt = n => Number(n||0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const $   = id => document.getElementById(id);
        const set = (id, val) => { const el = $(id); if(el) el.value = val; };

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

        // Mismo criterio que en Pedidos/Crear nota: el precio se bloquea
        // solo si el cliente/lista YA tiene un precio configurado (>0) para
        // ese producto. Si viene en $0, se deja editable para capturarlo.
        function aplicarEstadoPrecio(inputEl, precio) {
            if (!inputEl) return;
            inputEl.value = precio;
            if (LOCKED) { inputEl.readOnly = true; return; }
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
            const alertEl = $('zero-price-alert');
            if (alertEl) alertEl.classList.toggle('hidden', LOCKED || !hasZero || !state.clientId);
            const link = $('zero-price-link');
            if (link && state.clientId) link.href = `${CLIENTS_EDIT_BASE}/${state.clientId}/edit`;
        }

        // ── Portal global para el autocomplete de producto ──────────────
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
            if (LOCKED) return;
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
                    p.nombre.toLowerCase().includes(t) || (p.sku && p.sku.toLowerCase().includes(t))
                ).slice(0, 12);
                PROD_PORTAL.innerHTML = '';
                if (matches.length === 0) { hidePortal(); return; }
                matches.forEach(p => {
                    const li = document.createElement('li');
                    li.className = 'px-3 py-1.5 hover:bg-indigo-50 cursor-pointer flex justify-between items-center';
                    li.innerHTML = `<span class="truncate">${escHtml(p.nombre)}</span>`
                                 + (p.sku ? `<span class="text-xs text-gray-400 ml-2 shrink-0">${escHtml(p.sku)}</span>` : '');
                    li.addEventListener('mousedown', function(e) { e.preventDefault(); selectProduct(p); });
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
            input.addEventListener('focus', function() { if (this.value.trim()) showDropdown(this.value); });
            input.addEventListener('blur', function() { setTimeout(hidePortal, 150); });
            tr.querySelector('.btn-clear-product')?.addEventListener('click', clearProduct);
        }

        function renderRow(i) {
            const it = state.items[i];
            const dis = LOCKED ? 'disabled readonly' : '';
            const tr  = document.createElement('tr');
            tr.className   = 'border-b';
            tr.dataset.idx = i;
            tr.innerHTML = `
                <td class="p-2">
                    <input type="hidden" class="hid-product-id" name="items[${i}][product_id]" value="${escHtml(String(it.product_id || ''))}">
                    <div class="flex items-center gap-1">
                        <input type="text" class="w-48 border rounded p-1 text-sm inp-product-search"
                               placeholder="Buscar por nombre o SKU..." autocomplete="off"
                               value="${escHtml(it._productoNombre || '')}" ${dis}>
                        ${!LOCKED ? `<button type="button" class="btn-clear-product text-gray-400 hover:text-red-500 text-base leading-none px-1" title="Quitar producto">✕</button>` : ''}
                    </div>
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
                <td class="p-2 text-center">
                    <input type="number" min="1" step="1"
                           class="w-16 border rounded p-1 text-center text-sm inp-cajas"
                           name="items[${i}][num_cajas]" value="${it.num_cajas || ''}" placeholder="—" ${dis}>
                </td>
                <td class="p-2 text-right">
                    <div class="flex items-center justify-end gap-1">
                        <input type="number" min="0" step="0.0001"
                               class="w-24 border rounded p-1 text-right text-sm inp-precio"
                               name="items[${i}][precio]" value="${it.precio}" ${dis} required>
                        ${(CAN_EDIT_CLIENT_PRICES && !LOCKED) ? `
                        <a href="#" class="btn-editar-precio text-gray-400 hover:text-indigo-600 text-xs" target="_blank" title="Editar precio de este cliente">
                            <i class="fa-solid fa-pen"></i>
                        </a>` : ''}
                    </div>
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
                    <input type="hidden" class="hid-impuesto" name="items[${i}][impuesto]" value="${it.impuesto}">
                </td>
                <td class="p-2 text-right font-medium td-total">${fmt(it.total)}</td>
                ${!LOCKED ? `<td class="p-2 text-center"><button type="button" class="text-red-500 hover:text-red-700 text-xs btn-remove">✕</button></td>` : ''}
            `;

            attachProductSearch(tr, i);

            if (!LOCKED) {
                tr.querySelector('.inp-cantidad').addEventListener('input', function() {
                    state.items[i].cantidad = parseFloat(this.value)||0; recalcRow(i);
                });
                tr.querySelector('.inp-cajas').addEventListener('input', function() {
                    state.items[i].num_cajas = this.value === '' ? null : parseInt(this.value, 10) || null;
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
                tr.querySelector('.btn-remove')?.addEventListener('click', function() {
                    state.items.splice(i, 1); renderAll();
                });
            } else {
                aplicarEstadoPrecio(tr.querySelector('.inp-precio'), it.precio);
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
                state.items.push({product_id:'',_productoNombre:'',descripcion:'',cantidad:1,num_cajas:null,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0});
                renderAll();
            },
            onTipoVentaChange(val) {
                $('credito-wrap').style.display = val === 'CREDITO' ? '' : 'none';
            },
            onPriceListChange(val) {
                state.priceList = val;
                set('price_list_id', val === 'client' ? '' : val);
                SEF.repriceAll();
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

        if (!LOCKED) {
            document.getElementById('client_id')?.addEventListener('change', function () {
                state.clientId = this.value;
                SEF.repriceAll();
                updateTotals();
            });
        }

        // Init
        state.items = JSON.parse(JSON.stringify(INITIAL_ITEMS));
        if (!state.items.length) {
            state.items = [{product_id:'',_productoNombre:'',descripcion:'',cantidad:1,num_cajas:null,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}];
        }
        renderAll();
    })();
    </script>

    <script>
    if (typeof window.preguntarFormatoPdf !== 'function') {
        window.preguntarFormatoPdf = function (notaUrl, ticketUrl) {
            Swal.fire({
                title: '¿Qué formato quieres descargar?',
                text: 'Nota completa (tamaño carta) o ticket angosto (76mm).',
                icon: 'question',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Nota completa',
                denyButtonText: 'Ticket (76mm)',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4f46e5',
                denyButtonColor: '#6b7280',
            }).then(function (r) {
                if (r.isConfirmed) window.open(notaUrl, '_blank');
                else if (r.isDenied) window.open(ticketUrl, '_blank');
            });
        };
    }
    </script>

</x-admin-layout>
