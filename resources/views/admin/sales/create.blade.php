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
        $seedItems = $seedItems ?? [];
        $initialItems = (is_array($seedItems) && count($seedItems)) ? $seedItems : [[
            'product_id'=>'','descripcion'=>'','cantidad'=>1,'precio'=>0,'descuento'=>0,'iva_pct'=>0,'impuesto'=>0,'total'=>0
        ]];

        $JS_OVERRIDES    = json_encode($overrides ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES   = json_encode($listItems ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_INITIALITEMS = json_encode($initialItems, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    <x-wire-card>
        <form id="sale-form" method="POST" action="{{ route('admin.sales.store') }}" class="space-y-6"
              x-data="snFormState()" x-init="init()">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Caja (POS) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Caja</label>
                    <select name="pos_register_id" class="w-full rounded-md border-gray-300" required>
                        @foreach($posRegisters as $pr)
                            <option value="{{ $pr->id }}">{{ $pr->nombre ?? ('Caja #'.$pr->id) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Almacén</label>
                    <select name="warehouse_id" class="w-full rounded-md border-gray-300" required>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Cliente</label>
                    <select name="client_id" id="client_id" class="w-full rounded-md border-gray-300" x-model="client_id" @change="onClientChange()">
                        <option value="">-- público general --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Lista de precios --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Lista de precios</label>
                    <select name="price_list_id" class="w-full rounded-md border-gray-300" x-model="selectedPriceList" @change="repriceAll()">
                        <option value="client" selected>Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}">{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha / Moneda --}}
                <div><x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" required/></div>
                <div><x-wire-input label="Moneda" name="moneda" value="MXN" required/></div>

                {{-- Entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo de entrega</label>
                    <select name="delivery_type" class="w-full rounded-md border-gray-300">
                        <option value="RECOGER">Recoger</option>
                        <option value="ENVIO">Envío</option>
                    </select>
                </div>

                {{-- Pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Método de pago</label>
                    <select name="tipo_venta" class="w-full rounded-md border-gray-300" x-on:change="$event.target.value==='CREDITO' ? $refs.cd.classList.remove('hidden') : $refs.cd.classList.add('hidden')">
                        <option value="CONTADO">Contado</option>
                        <option value="CONTRAENTREGA">Contraentrega</option>
                        <option value="CREDITO">Crédito</option>
                    </select>
                </div>
                <div x-ref="cd" class="hidden">
                    <x-wire-input label="Días de crédito" name="credit_days" type="number" min="0" value="0"/>
                </div>

                {{-- Ruta/Chofer (opcionales) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ruta</label>
                    <select name="shipping_route_id" class="w-full rounded-md border-gray-300">
                        <option value="">—</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Chofer</label>
                    <select name="driver_id" class="w-full rounded-md border-gray-300">
                        <option value="">—</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Alerta precios en 0 --}}
            <template x-if="client_id && anyZeroPrice">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800">
                    Hay productos sin precio personalizado para este cliente (quedaron en $0.00).
                </div>
            </template>

            {{-- Partidas --}}
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

    @php
        $JS_SELCLIENT    = json_encode('', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_OVERRIDES    = $JS_OVERRIDES;
        $JS_LISTPRICES   = $JS_LISTPRICES;
        $JS_INITIALITEMS = $JS_INITIALITEMS;
    @endphp

    <script>
    function snFormState(){
        const CLIENTS_OVERRIDES = {!! $JS_OVERRIDES !!};
        const LISTS_PRICES      = {!! $JS_LISTPRICES !!};
        const INITIAL_ITEMS     = {!! $JS_INITIALITEMS !!};
        const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};

        return {
            client_id: DEFAULT_CLIENT_ID || '',
            selectedPriceList: 'client',
            overridesMap: CLIENTS_OVERRIDES,
            listPricesMap: LISTS_PRICES,
            items: Array.isArray(INITIAL_ITEMS) && INITIAL_ITEMS.length ? INITIAL_ITEMS : [
                {product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}
            ],
            anyZeroPrice:false,
            subtotal:0, desc_total:0, tax_total:0, grand:0,

            init(){ this.sum(); },
            onClientChange(){ this.selectedPriceList='client'; this.repriceAll(); },

            getUnitPrice(productId){
                if(!productId) return 0;
                if(this.selectedPriceList==='client'){
                    const map = this.overridesMap[this.client_id] || {};
                    const val = map[productId];
                    return (val==null)?0:parseFloat(val)||0;
                }
                const listMap = this.listPricesMap[this.selectedPriceList] || {};
                const val = listMap[productId];
                return (val==null)?0:parseFloat(val)||0;
            },

            onProductChange(i, ev){
                const it = this.items[i];
                if(!it.descripcion && ev && ev.target){
                    const opt = ev.target.options[ev.target.selectedIndex];
                    it.descripcion = (opt?.text || '').trim();
                }
                it.precio = this.getUnitPrice(it.product_id);
                this.recalc(i);
            },

            repriceAll(){
                this.items.forEach((it,i)=>{
                    if(it.product_id){
                        it.precio = this.getUnitPrice(it.product_id);
                        this.recalc(i,true);
                    }
                });
                this.sum();
            },

            add(){ this.items.push({product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}); },
            remove(i){ this.items.splice(i,1); this.sum(); },

            recalc(i, skip=false){
                const it=this.items[i];
                const line=(+it.cantidad||0)*(+it.precio||0);
                const disc=+it.descuento||0;
                const base=Math.max(line-disc,0);
                const tax=((+it.iva_pct||0)*0.01)*base;
                it.impuesto=tax; it.total=base+tax;
                if(!skip) this.sum();
            },

            sum(){
                let s=0,d=0,t=0,g=0, hasZero=false;
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
