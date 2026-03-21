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
        $valueProg     = old('programado_para', '');
        $valueMoneda   = old('moneda', 'MXN');
        $deliveryType  = old('delivery_type', 'ENVIO');
        $paymentMethod = old('payment_method', 'EFECTIVO');
        $creditDays    = old('credit_days', 0);

        $seedItems    = $seedItems ?? [];
        $initialItems = (is_array($seedItems) && count($seedItems))
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

        $JS_OVERRIDES    = json_encode($overrides    ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES   = json_encode($listItems    ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_INITIALITEMS = json_encode($initialItems,       JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT    = json_encode($selClient,           JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_EDITBASE     = json_encode(url('admin/clients'), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        // Defaults por cliente: ruta, crédito Y dirección de entrega efectiva
        $clientDefaults = $clients->mapWithKeys(fn($c) => [(string)$c->id => [
            'shipping_route_id' => (string) ($c->shipping_route_id ?? ''),
            'price_list_id'     => (string) ($c->price_list_id ?? ''),
            'credito_dias'      => (int)    ($c->credito_dias  ?? 0),
            'credito_limite'    => (float)  ($c->credito_limite ?? 0),
            'telefono'          => (string) ($c->telefono ?? ''),
            // Si entrega_igual_fiscal usa la fiscal, si no la de entrega
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
        <form id="so-form"
              method="POST"
              action="{{ route('admin.sales-orders.store') }}"
              class="space-y-6"
              x-data="soFormState()"
              x-init="init()">
            @csrf

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
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
                    <template x-if="creditoLimite > 0">
                        <p class="mt-1 text-xs text-gray-500">
                            Límite: $<span x-text="creditoLimite.toFixed(2)"></span>
                            &nbsp;·&nbsp; Días: <span x-text="creditoDias"></span>d
                        </p>
                    </template>
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
                    <select name="price_list_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="selectedPriceList" @change="repriceAll()">
                        <option value="client">Personalizada del cliente</option>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="{{ $valueMoneda }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm"
                           readonly>
                </div>

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="delivery_type">
                        <option value="ENVIO">Envío a domicilio</option>
                        <option value="RECOGER">Recoger en almacén</option>
                    </select>
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="shipping_route_id">
                        <option value="">-- sin ruta --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selRoute===(string)$r->id ? 'selected' : '' }}>
                                {{ $r->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="payment_method"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="payment_method">
                        <option value="EFECTIVO">Efectivo</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="CONTRAENTREGA">Contraentrega</option>
                        <option value="CREDITO">Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div x-show="payment_method === 'CREDITO'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Días de crédito
                        <span class="text-gray-400 font-normal text-xs">(del cliente)</span>
                    </label>
                    <input type="number" name="credit_days"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm"
                           x-model="creditoDias"
                           readonly>
                </div>

            </div>

            {{-- ====== DIRECCIÓN DE ENTREGA (solo si ENVIO) ====== --}}
            <div x-show="delivery_type === 'ENVIO'"
                 x-transition
                 class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-4">
                <div class="md:col-span-3">
                    <p class="text-sm font-medium text-gray-700">Datos de entrega</p>
                </div>
                <div>
                    <x-wire-input label="Nombre quien recibe" name="entrega_nombre"
                                  value="{{ old('entrega_nombre') }}" />
                </div>
                <div>
                    <x-wire-input label="Teléfono" name="entrega_telefono"
                                  value="{{ old('entrega_telefono') }}" />
                </div>
                <div>
                    <x-wire-input label="Calle" name="entrega_calle"
                                  value="{{ old('entrega_calle') }}" />
                </div>
                <div>
                    <x-wire-input label="Número" name="entrega_numero"
                                  value="{{ old('entrega_numero') }}" />
                </div>
                <div>
                    <x-wire-input label="Colonia" name="entrega_colonia"
                                  value="{{ old('entrega_colonia') }}" />
                </div>
                <div>
                    <x-wire-input label="Ciudad" name="entrega_ciudad"
                                  value="{{ old('entrega_ciudad') }}" />
                </div>
                <div>
                    <x-wire-input label="Estado" name="entrega_estado"
                                  value="{{ old('entrega_estado') }}" />
                </div>
                <div>
                    <x-wire-input label="CP" name="entrega_cp"
                                  value="{{ old('entrega_cp') }}" />
                </div>
            </div>

            {{-- ====== ALERTA: precio cero ====== --}}
            <template x-if="client_id && anyZeroPrice">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 flex items-center justify-between">
                    <span>Algunos productos no tienen precio para este cliente. Se estableció $0.00.</span>
                    <a :href="clientEditUrl" target="_blank"
                       class="ml-3 inline-flex px-3 py-1.5 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">
                        Editar precios
                    </a>
                </div>
            </template>

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
                    <tbody>
                        <template x-for="(it, i) in items" :key="i">
                            <tr class="border-b">
                                <td class="p-2">
                                    <select class="w-48 border rounded p-1 text-sm"
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
                                    <input type="text" class="w-64 border rounded p-1 text-sm"
                                           x-bind:name="'items['+i+'][descripcion]'"
                                           x-model="it.descripcion" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0.001" step="0.001"
                                           class="w-24 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][cantidad]'"
                                           x-model.number="it.cantidad" @input="recalc(i)" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.0001"
                                           class="w-28 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][precio]'"
                                           x-model.number="it.precio" @input="recalc(i)" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01"
                                           class="w-24 border rounded p-1 text-right text-sm"
                                           x-model.number="it.descuento" @input="recalc(i)">
                                    <input type="hidden" x-bind:name="'items['+i+'][descuento]'" x-model="it.descuento">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01"
                                           class="w-20 border rounded p-1 text-right text-sm"
                                           x-model.number="it.iva_pct" @input="recalc(i)">
                                    <input type="hidden" x-bind:name="'items['+i+'][impuesto]'" x-model="it.impuesto">
                                </td>
                                <td class="p-2 text-right font-medium" x-text="fmt(it.total)"></td>
                                <td class="p-2">
                                    <button type="button" class="text-red-500 hover:text-red-700 text-xs"
                                            @click.prevent="remove(i)">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="mt-3">
                    <button type="button"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50"
                            @click.prevent="add()">
                        + Agregar partida
                    </button>
                </div>
            </div>

            {{-- ====== TOTALES ====== --}}
            <div class="text-right space-y-1 border-t pt-3">
                <div class="text-sm text-gray-600">Subtotal: <span class="font-medium" x-text="fmt(subtotal)"></span></div>
                <div class="text-sm text-gray-600">Descuento: <span class="font-medium" x-text="fmt(desc_total)"></span></div>
                <div class="text-sm text-gray-600">Impuestos: <span class="font-medium" x-text="fmt(tax_total)"></span></div>
                <div class="text-lg font-bold text-gray-800">Total: $<span x-text="fmt(grand)"></span></div>
            </div>

        </form>
    </x-wire-card>

    <script>
    function soFormState(){
        const CLIENTS_OVERRIDES  = {!! $JS_OVERRIDES !!};
        const LISTS_PRICES       = {!! $JS_LISTPRICES !!};
        const INITIAL_ITEMS      = {!! $JS_INITIALITEMS !!};
        const DEFAULT_CLIENT_ID  = {!! $JS_SELCLIENT !!};
        const CLIENTS_EDIT_BASE  = {!! $JS_EDITBASE !!};
        const CLIENT_DEFAULTS    = {!! $JS_CLIENT_DEFAULTS !!};

        return {
            client_id:         DEFAULT_CLIENT_ID || '',
            selectedPriceList: 'client',
            shipping_route_id: '',
            payment_method:    @json($paymentMethod),
            delivery_type:     @json($deliveryType),
            creditoDias:       0,
            creditoLimite:     0,

            overridesMap:  CLIENTS_OVERRIDES,
            listPricesMap: LISTS_PRICES,

            items: Array.isArray(INITIAL_ITEMS) && INITIAL_ITEMS.length
                ? INITIAL_ITEMS
                : [{product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}],

            anyZeroPrice: false,
            subtotal:0, desc_total:0, tax_total:0, grand:0,

            init(){
                if(!this.client_id){
                    const el = document.getElementById('client_id');
                    if(el) this.client_id = el.value || '';
                }
                if(this.client_id) this._applyClientDefaults(this.client_id);
                this.sum();
            },

            get clientEditUrl(){
                return this.client_id ? `${CLIENTS_EDIT_BASE}/${this.client_id}/edit` : '#';
            },

            onClientChange(){
                this._applyClientDefaults(this.client_id);
                this.selectedPriceList = 'client';
                this.repriceAll();
            },

            _applyClientDefaults(clientId){
                const d = CLIENT_DEFAULTS[clientId];
                if(!d){
                    this.creditoDias      = 0;
                    this.creditoLimite    = 0;
                    this.shipping_route_id = '';
                    return;
                }

                // Ruta y lista de precios
                if(d.shipping_route_id) this.shipping_route_id = d.shipping_route_id;
                if(d.price_list_id)     this.selectedPriceList = d.price_list_id;

                // Crédito
                this.creditoDias   = d.credito_dias   || 0;
                this.creditoLimite = d.credito_limite  || 0;
                if(d.credito_dias > 0) this.payment_method = 'CREDITO';

                // Precargar dirección de entrega en los inputs del DOM
                const set = (name, val) => {
                    const el = document.querySelector(`[name="${name}"]`);
                    if(el && val) el.value = val;
                };
                set('entrega_telefono', d.telefono         || '');
                set('entrega_calle',    d.entrega_calle    || '');
                set('entrega_numero',   d.entrega_numero   || '');
                set('entrega_colonia',  d.entrega_colonia  || '');
                set('entrega_ciudad',   d.entrega_ciudad   || '');
                set('entrega_estado',   d.entrega_estado   || '');
                set('entrega_cp',       d.entrega_cp       || '');
            },

            getUnitPrice(productId){
                if(!productId) return 0;
                if(this.selectedPriceList === 'client'){
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
                if(!it.descripcion && ev?.target){
                    const opt = ev.target.options[ev.target.selectedIndex];
                    it.descripcion = (opt?.text || '').trim();
                }
                it.precio = this.getUnitPrice(it.product_id);
                this.recalc(i);
            },

            repriceAll(){
                this.items.forEach((it, i) => {
                    if(it.product_id){
                        it.precio = this.getUnitPrice(it.product_id);
                        this.recalc(i, true);
                    }
                });
                this.sum();
            },

            add(){
                this.items.push({product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0});
            },
            remove(i){ this.items.splice(i,1); this.sum(); },

            recalc(i, skipSum=false){
                const it   = this.items[i];
                const line = (+it.cantidad||0) * (+it.precio||0);
                const disc = +it.descuento||0;
                const base = Math.max(line - disc, 0);
                const tax  = ((+it.iva_pct||0) * 0.01) * base;
                it.impuesto = tax;
                it.total    = base + tax;
                if(!skipSum) this.sum();
            },

            sum(){
                let s=0,d=0,t=0,g=0,hasZero=false;
                this.items.forEach(it=>{
                    const line = (+it.cantidad||0)*(+it.precio||0);
                    const disc = +it.descuento||0;
                    const base = Math.max(line-disc,0);
                    const tax  = ((+it.iva_pct||0)*0.01)*base;
                    const tot  = base+tax;
                    s+=line; d+=disc; t+=tax; g+=tot;
                    if((+it.precio||0)===0 && it.product_id) hasZero=true;
                    it.impuesto=tax; it.total=tot;
                });
                this.subtotal=s; this.desc_total=d; this.tax_total=t; this.grand=g;
                this.anyZeroPrice=hasZero;
            },

            fmt(n){ return Number(n||0).toFixed(2); }
        }
    }
    </script>
</x-admin-layout>