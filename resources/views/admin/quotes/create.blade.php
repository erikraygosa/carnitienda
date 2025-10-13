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

        // Semilla de partidas segura
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

        // Serializamos a JSON para JS (fuera de atributos)
        $JS_OVERRIDES    = json_encode($overridesMap ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES   = json_encode($listPricesMap ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_INITIALITEMS = json_encode($initialItems ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT    = json_encode($selClient ?? '', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_EDITBASE     = json_encode(url('admin/clients'), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    <x-wire-card>
        {{-- Errores de validación (útil para detectar por qué “no guarda”) --}}
        @if ($errors->any())
            <div class="mb-4 text-red-600">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="quote-form"
              method="POST"
              action="{{ route('admin.quotes.store') }}"
              class="space-y-6"
              x-data="qFormState()"
              x-init="init()"
        >
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Cliente --}}
                <div class="md:col-span-2 space-y-2 w-full">
                    <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="client_id"
                            @change="onClientChange()">
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Lista de precios (la que se selecciona visualmente) --}}
                <div class="space-y-2 w-full">
                    <label for="price_list_ui" class="block text-sm font-medium text-gray-700">Lista de precios</label>
                    <select id="price_list_ui"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="selectedPriceList"
                            @change="repriceAll()">
                        <option value="client" selected>Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}">{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                    {{-- Lo que se envía realmente al backend (null si "client") --}}
                    <input type="hidden" name="price_list_id" :value="selectedPriceList === 'client' ? '' : selectedPriceList">
                </div>

                {{-- Fecha --}}
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $valueFecha }}" required />
                </div>

                {{-- Moneda --}}
                <div>
                    <x-wire-input label="Moneda" name="moneda" value="{{ $valueMoneda }}" required />
                </div>

                {{-- Vigencia --}}
                <div>
                    <x-wire-input label="Vigencia hasta" name="vigencia_hasta" type="date" value="{{ $valueVigencia }}" />
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
    // serializamos fuera de atributos para evitar errores de comillas/corchetes
    $JS_OVERRIDES    = json_encode($overridesMap ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $JS_LISTPRICES   = json_encode($listPricesMap ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $JS_INITIALITEMS = json_encode($initialItems ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $JS_SELCLIENT    = json_encode((string)($selClient ?? ''), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $JS_EDITBASE     = json_encode(url('admin/clients'), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
@endphp

<script>
function qFormState(){
    const CLIENTS_OVERRIDES = {!! $JS_OVERRIDES !!};
    const LISTS_PRICES      = {!! $JS_LISTPRICES !!};
    const INITIAL_ITEMS     = {!! $JS_INITIALITEMS !!};
    const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};
    const CLIENTS_EDIT_BASE = {!! $JS_EDITBASE !!};

    return {
        client_id: DEFAULT_CLIENT_ID || '',
        selectedPriceList: 'client', // por defecto: personalizada del cliente
        overridesMap: CLIENTS_OVERRIDES,
        listPricesMap: LISTS_PRICES,
        items: Array.isArray(INITIAL_ITEMS) && INITIAL_ITEMS.length ? INITIAL_ITEMS : [
            {product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}
        ],
        anyZeroPrice: false,

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
