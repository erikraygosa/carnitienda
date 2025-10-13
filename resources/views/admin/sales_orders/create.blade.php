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
        // Defaults/old
        $selClient     = (string) old('client_id', '');
        $selWarehouse  = (string) old('warehouse_id', '');
        $selRoute      = (string) old('shipping_route_id', '');
        $selDriver     = (string) old('driver_id', '');
        $valueFecha    = old('fecha', now()->format('Y-m-d\TH:i'));
        $valueProg     = old('programado_para', '');
        $valueMoneda   = old('moneda', 'MXN');
        $deliveryType  = old('delivery_type', 'ENVIO'); // RECOGER | ENVIO
        $paymentMethod = old('payment_method', 'EFECTIVO'); // CREDITO | TRANSFERENCIA | CONTRAENTREGA | EFECTIVO
        $creditDays    = old('credit_days');

        // Partidas iniciales
        $seedItems     = $seedItems ?? [];
        $initialItems  = (is_array($seedItems) && count($seedItems))
            ? $seedItems
            : [[
                'product_id'  => '',
                'descripcion' => '',
                'cantidad'    => 1,
                'precio'      => 0,
                'descuento'   => 0,
                'iva_pct'     => 0,
                'impuesto'    => 0,
                'total'       => 0,
            ]];

        // Serializamos mapas para el <script> (evitamos @json directo en atributos)
        $JS_OVERRIDES    = json_encode($overrides ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES   = json_encode($listItems ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_INITIALITEMS = json_encode($initialItems ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT    = json_encode($selClient ?? '', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_EDITBASE     = json_encode(url('admin/clients'), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    <x-wire-card>
        <form id="so-form"
              method="POST"
              action="{{ route('admin.sales-orders.store') }}"
              class="space-y-6"
              x-data="soFormState()"
              x-init="init()"
        >
            @csrf

            {{-- ====== Encabezado ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Cliente --}}
                <div class="md:col-span-2 space-y-2 w-full">
                    <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                    <select name="client_id" id="client_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        x-model="client_id" @change="onClientChange()">
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Almacén --}}
                <div class="space-y-2 w-full">
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Almacén</label>
                    <select name="warehouse_id" id="warehouse_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Lista de precios --}}
                <div class="space-y-2 w-full">
                    <label for="price_list_id" class="block text-sm font-medium text-gray-700">Lista de precios</label>
                    <select name="price_list_id" id="price_list_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        x-model="selectedPriceList" @change="repriceAll()">
                        <option value="client" selected>Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}">{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha --}}
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $valueFecha }}" required />
                </div>

                {{-- Programado para --}}
                <div>
                    <x-wire-input label="Programado para" name="programado_para" type="date" value="{{ $valueProg }}" />
                </div>

                {{-- Moneda --}}
                <div>
                    <x-wire-input label="Moneda" name="moneda" value="{{ $valueMoneda }}" required />
                </div>

                {{-- Tipo de entrega --}}
                <div class="space-y-2 w-full">
                    <label for="delivery_type" class="block text-sm font-medium text-gray-700">Tipo de entrega</label>
                    <select name="delivery_type" id="delivery_type"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        x-model="delivery_type">
                        <option value="ENVIO" {{ $deliveryType==='ENVIO' ? 'selected' : '' }}>Envío a domicilio</option>
                        <option value="RECOGER" {{ $deliveryType==='RECOGER' ? 'selected' : '' }}>Recoger en almacén</option>
                    </select>
                </div>

                {{-- Ruta / Chofer (opcionales) --}}
                <div class="space-y-2 w-full">
                    <label for="shipping_route_id" class="block text-sm font-medium text-gray-700">Ruta</label>
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

                <div class="space-y-2 w-full">
                    <label for="driver_id" class="block text-sm font-medium text-gray-700">Chofer</label>
                    <select name="driver_id" id="driver_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- sin chofer --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selDriver===(string)$d->id ? 'selected' : '' }}>
                                {{ $d->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de pago + días crédito --}}
                <div class="space-y-2 w-full">
                    <label for="payment_method" class="block text-sm font-medium text-gray-700">Método de pago</label>
                    <select name="payment_method" id="payment_method"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        x-model="payment_method">
                        <option value="EFECTIVO" {{ $paymentMethod==='EFECTIVO' ? 'selected' : '' }}>Efectivo</option>
                        <option value="TRANSFERENCIA" {{ $paymentMethod==='TRANSFERENCIA' ? 'selected' : '' }}>Transferencia</option>
                        <option value="CONTRAENTREGA" {{ $paymentMethod==='CONTRAENTREGA' ? 'selected' : '' }}>Contraentrega</option>
                        <option value="CREDITO" {{ $paymentMethod==='CREDITO' ? 'selected' : '' }}>Crédito</option>
                    </select>
                </div>

                <div class="space-y-2 w-full" x-show="payment_method==='CREDITO'">
                    <x-wire-input label="Días de crédito" name="credit_days" type="number" min="0" value="{{ $creditDays ?? 0 }}" />
                </div>
            </div>

            {{-- Dirección de entrega (solo si ENVIO) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" x-show="delivery_type==='ENVIO'">
                <div>
                    <x-wire-input label="Nombre quien recibe" name="entrega_nombre" value="{{ old('entrega_nombre') }}" />
                </div>
                <div>
                    <x-wire-input label="Teléfono" name="entrega_telefono" value="{{ old('entrega_telefono') }}" />
                </div>
                <div>
                    <x-wire-input label="Calle" name="entrega_calle" value="{{ old('entrega_calle') }}" />
                </div>
                <div>
                    <x-wire-input label="Número" name="entrega_numero" value="{{ old('entrega_numero') }}" />
                </div>
                <div>
                    <x-wire-input label="Colonia" name="entrega_colonia" value="{{ old('entrega_colonia') }}" />
                </div>
                <div>
                    <x-wire-input label="Ciudad" name="entrega_ciudad" value="{{ old('entrega_ciudad') }}" />
                </div>
                <div>
                    <x-wire-input label="Estado" name="entrega_estado" value="{{ old('entrega_estado') }}" />
                </div>
                <div>
                    <x-wire-input label="CP" name="entrega_cp" value="{{ old('entrega_cp') }}" />
                </div>
            </div>

            {{-- ALERTA: sin precio personalizado --}}
            <template x-if="client_id && anyZeroPrice">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 flex items-center justify-between">
                    <div>
                        Algunos productos no tienen precio personalizado para este cliente. Se estableció <b>$0.00</b>.
                    </div>
                    <a :href="clientEditUrl" target="_blank"
                       class="inline-flex px-3 py-1.5 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">
                       Editar precios del cliente
                    </a>
                </div>
            </template>

            {{-- ====== Partidas ====== --}}
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="p-2 text-left">Producto</th>
                            <th class="p-2 text-left">Descripción</th>
                            <th class="p-2 text-right">Cantidad</th>
                            <th class="p-2 text-right">Precio</th>
                            <th class="p-2 text-right">Desc.</th>
                            <th class="p-2 text-right">% IVA</th>
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(it, i) in items" :key="i">
                            <tr class="border-b">
                                <td class="p-2">
                                    <select class="w-48 border rounded p-1"
                                        x-bind:name="'items['+i+'][product_id]'"
                                        x-model="it.product_id"
                                        @change="onProductChange(i, $event)">
                                        <option value="">—</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input type="text" class="w-64 border rounded p-1"
                                        x-bind:name="'items['+i+'][descripcion]'"
                                        x-model="it.descripcion" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0.001" step="0.001" class="w-24 border rounded p-1 text-right"
                                        x-bind:name="'items['+i+'][cantidad]'"
                                        x-model.number="it.cantidad" @input="recalc(i)" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.0001" class="w-28 border rounded p-1 text-right"
                                        x-bind:name="'items['+i+'][precio]'"
                                        x-model.number="it.precio" @input="recalc(i)" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-24 border rounded p-1 text-right"
                                        x-model.number="it.descuento" @input="recalc(i)">
                                    <input type="hidden" x-bind:name="'items['+i+'][descuento]'" x-model="it.descuento">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-20 border rounded p-1 text-right"
                                        x-model.number="it.iva_pct" @input="recalc(i)">
                                    <input type="hidden" x-bind:name="'items['+i+'][impuesto]'" x-model="it.impuesto">
                                </td>
                                <td class="p-2 text-right" x-text="fmt(it.total)"></td>
                                <td class="p-2">
                                    <button type="button" class="text-red-600" @click.prevent="remove(i)">Eliminar</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="mt-2">
                    <x-wire-button type="button" gray @click.prevent="add()">Agregar partida</x-wire-button>
                </div>
            </div>

            {{-- Totales --}}
            <div class="text-right space-y-1">
                <div>Subtotal: <span x-text="fmt(subtotal)"></span></div>
                <div>Descuento: <span x-text="fmt(desc_total)"></span></div>
                <div>Impuestos: <span x-text="fmt(tax_total)"></span></div>
                <div class="font-semibold text-lg">Total: <span x-text="fmt(grand)"></span></div>
            </div>
        </form>
    </x-wire-card>

    {{-- ====== Alpine state ====== --}}
    <script>
    function soFormState(){
        const CLIENTS_OVERRIDES = {!! $JS_OVERRIDES !!};   // {clientId:{productId:precio}}
        const LISTS_PRICES      = {!! $JS_LISTPRICES !!};  // {listId:{productId:precio}}
        const INITIAL_ITEMS     = {!! $JS_INITIALITEMS !!};
        const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};
        const CLIENTS_EDIT_BASE = {!! $JS_EDITBASE !!};

        return {
            // estado
            client_id: DEFAULT_CLIENT_ID || '',
            selectedPriceList: 'client', // siempre personalizada por defecto
            overridesMap: CLIENTS_OVERRIDES,
            listPricesMap: LISTS_PRICES,
            items: Array.isArray(INITIAL_ITEMS) && INITIAL_ITEMS.length ? INITIAL_ITEMS : [
                {product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}
            ],
            anyZeroPrice: false,

            // extra
            delivery_type: @json($deliveryType),
            payment_method: @json($paymentMethod),

            // totales
            subtotal:0, desc_total:0, tax_total:0, grand:0,

            init(){
                if(!this.client_id){
                    const el = document.getElementById('client_id');
                    if (el) this.client_id = el.value || '';
                }
                this.sum();
            },

            get clientEditUrl(){
                return this.client_id ? `${CLIENTS_EDIT_BASE}/${this.client_id}/edit` : '#';
            },

            onClientChange(){
                this.selectedPriceList = 'client';
                this.repriceAll();
            },

            getUnitPrice(productId){
                if(!productId) return 0;
                if (this.selectedPriceList === 'client') {
                    const map = this.overridesMap[this.client_id] || {};
                    const val = map[productId];
                    return (val == null) ? 0 : parseFloat(val) || 0;
                }
                const listMap = this.listPricesMap[this.selectedPriceList] || {};
                const val = listMap[productId];
                return (val == null) ? 0 : parseFloat(val) || 0;
            },

            onProductChange(i, ev){
                const it = this.items[i];
                if (!it.descripcion && ev && ev.target) {
                    const opt = ev.target.options[ev.target.selectedIndex];
                    it.descripcion = (opt?.text || '').trim();
                }
                it.precio = this.getUnitPrice(it.product_id);
                this.recalc(i);
            },

            repriceAll(){
                this.items.forEach((it, i) => {
                    if (it.product_id) {
                        it.precio = this.getUnitPrice(it.product_id);
                        this.recalc(i, true);
                    }
                });
                this.sum();
            },

            add(){
                this.items.push({product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0});
            },
            remove(i){
                this.items.splice(i,1);
                this.sum();
            },

            recalc(i, skipSum=false){
                const it   = this.items[i];
                const line = (+it.cantidad || 0) * (+it.precio || 0);
                const disc = +it.descuento || 0;
                const base = Math.max(line - disc, 0);
                const tax  = ((+it.iva_pct || 0) * 0.01) * base;
                it.impuesto = tax;
                it.total    = base + tax;
                if(!skipSum) this.sum();
            },

            sum(){
                let s=0, d=0, t=0, g=0, hasZero=false;
                this.items.forEach(it=>{
                    const line = (+it.cantidad || 0) * (+it.precio || 0);
                    const disc = +it.descuento || 0;
                    const base = Math.max(line - disc, 0);
                    const tax  = ((+it.iva_pct || 0) * 0.01) * base;
                    const tot  = base + tax;
                    s += line; d += disc; t += tax; g += tot;
                    if ((+it.precio || 0) === 0 && it.product_id) hasZero = true;
                    it.impuesto = tax; it.total = tot;
                });
                this.subtotal = s; this.desc_total = d; this.tax_total = t; this.grand = g;
                this.anyZeroPrice = hasZero;
            },

            fmt(n){ return Number(n||0).toFixed(2); }
        }
    }
    </script>
</x-admin-layout>
