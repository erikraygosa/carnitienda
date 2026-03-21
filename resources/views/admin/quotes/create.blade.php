<x-admin-layout
    title="Crear cotización"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cotizaciones','url'=>route('admin.quotes.index')],
        ['name'=>'Crear'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.quotes.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="quote-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    @php
        $selClient     = (string) old('client_id', '');
        $valueFecha    = old('fecha', now()->format('Y-m-d\TH:i'));
        $valueMoneda   = old('moneda', 'MXN');
        $valueVigencia = old('vigencia_hasta', '');

        $seedItems    = $seedItems ?? [];
        $initialItems = (is_array($seedItems) && count($seedItems)) ? $seedItems : [[
            'product_id'=>'','descripcion'=>'','cantidad'=>1,'precio'=>0,'descuento'=>0,'iva_pct'=>0,'impuesto'=>0,'total'=>0,
        ]];

        $JS_OVERRIDES    = json_encode($overridesMap   ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES   = json_encode($listPricesMap  ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_INITIALITEMS = json_encode($initialItems,          JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT    = json_encode($selClient,              JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_EDITBASE     = json_encode(url('admin/clients'),   JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_CDEFAULTS    = json_encode($clientDefaults ?? [],   JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    <x-wire-card>
        @if ($errors->any())
            <div class="mb-4 text-red-600 text-sm"><ul class="list-disc pl-5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul></div>
        @endif

        <form id="quote-form" method="POST" action="{{ route('admin.quotes.store') }}"
              class="space-y-6" x-data="qFormState()" x-init="init()">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="client_id" @change="onClientChange()">
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id?'selected':'' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    <template x-if="creditoLimite > 0">
                        <p class="mt-1 text-xs text-gray-500">
                            Límite: $<span x-text="creditoLimite.toFixed(2)"></span>
                            &nbsp;·&nbsp; Días: <span x-text="creditoDias"></span>d
                        </p>
                    </template>
                </div>

                {{-- Lista de precios --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
                    <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="selectedPriceList" @change="repriceAll()">
                        <option value="client">Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}">{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="price_list_id" :value="selectedPriceList === 'client' ? '' : selectedPriceList">
                </div>

                {{-- Fecha --}}
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $valueFecha }}" required />
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="{{ $valueMoneda }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                </div>

                {{-- Vigencia --}}
                <div>
                    <x-wire-input label="Vigencia hasta" name="vigencia_hasta" type="date" value="{{ $valueVigencia }}" />
                </div>
            </div>

            {{-- Alerta precio cero --}}
            <template x-if="client_id && anyZeroPrice">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 flex items-center justify-between">
                    <span>Algunos productos no tienen precio para este cliente. Se estableció $0.00.</span>
                    <a :href="clientEditUrl" target="_blank"
                       class="ml-3 inline-flex px-3 py-1.5 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">
                        Editar precios
                    </a>
                </div>
            </template>

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
                                    <input type="number" min="0.001" step="0.001" class="w-24 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][cantidad]'"
                                           x-model.number="it.cantidad" @input="recalc(i)" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.0001" class="w-28 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][precio]'"
                                           x-model.number="it.precio" @input="recalc(i)" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-24 border rounded p-1 text-right text-sm"
                                           x-model.number="it.descuento" @input="recalc(i)">
                                    <input type="hidden" x-bind:name="'items['+i+'][descuento]'" x-model="it.descuento">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-20 border rounded p-1 text-right text-sm"
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
                            @click.prevent="add()">+ Agregar partida</button>
                </div>
            </div>

            {{-- Totales --}}
            <div class="text-right space-y-1 border-t pt-3">
                <div class="text-sm text-gray-600">Subtotal: <span class="font-medium" x-text="fmt(subtotal)"></span></div>
                <div class="text-sm text-gray-600">Descuento: <span class="font-medium" x-text="fmt(desc_total)"></span></div>
                <div class="text-sm text-gray-600">Impuestos: <span class="font-medium" x-text="fmt(tax_total)"></span></div>
                <div class="text-lg font-bold text-gray-800">Total: $<span x-text="fmt(grand)"></span></div>
            </div>
        </form>
    </x-wire-card>

    <script>
    function qFormState(){
        const CLIENTS_OVERRIDES = {!! $JS_OVERRIDES !!};
        const LISTS_PRICES      = {!! $JS_LISTPRICES !!};
        const INITIAL_ITEMS     = {!! $JS_INITIALITEMS !!};
        const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};
        const CLIENTS_EDIT_BASE = {!! $JS_EDITBASE !!};
        const CLIENT_DEFAULTS   = {!! $JS_CDEFAULTS !!};

        return {
            client_id:         DEFAULT_CLIENT_ID || '',
            selectedPriceList: 'client',
            creditoDias:       0,
            creditoLimite:     0,
            overridesMap:      CLIENTS_OVERRIDES,
            listPricesMap:     LISTS_PRICES,
            items: Array.isArray(INITIAL_ITEMS) && INITIAL_ITEMS.length ? INITIAL_ITEMS
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
                if(!d){ this.creditoDias = 0; this.creditoLimite = 0; return; }
                if(d.price_list_id) this.selectedPriceList = d.price_list_id;
                this.creditoDias   = d.credito_dias   || 0;
                this.creditoLimite = d.credito_limite  || 0;
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
                    it.descripcion = (ev.target.options[ev.target.selectedIndex]?.text || '').trim();
                }
                it.precio = this.getUnitPrice(it.product_id);
                this.recalc(i);
            },

            repriceAll(){
                this.items.forEach((it,i) => {
                    if(it.product_id){ it.precio = this.getUnitPrice(it.product_id); this.recalc(i,true); }
                });
                this.sum();
            },

            add(){ this.items.push({product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}); },
            remove(i){ this.items.splice(i,1); this.sum(); },

            recalc(i, skipSum=false){
                const it=this.items[i];
                const line=(+it.cantidad||0)*(+it.precio||0);
                const disc=+it.descuento||0;
                const base=Math.max(line-disc,0);
                const tax=((+it.iva_pct||0)*0.01)*base;
                it.impuesto=tax; it.total=base+tax;
                if(!skipSum) this.sum();
            },

            sum(){
                let s=0,d=0,t=0,g=0,hasZero=false;
                this.items.forEach(it=>{
                    const line=(+it.cantidad||0)*(+it.precio||0);
                    const disc=+it.descuento||0;
                    const base=Math.max(line-disc,0);
                    const tax=((+it.iva_pct||0)*0.01)*base;
                    const tot=base+tax;
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