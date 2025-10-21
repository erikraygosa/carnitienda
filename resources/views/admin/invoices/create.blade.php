<x-admin-layout
    title="Nueva factura"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas','url'=>route('admin.invoices.index')],
        ['name'=>'Nueva'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="inv-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    @php
        $valueFecha = old('fecha', now()->format('Y-m-d\TH:i'));
        $itemsSeed = old('items', [
            ['product_id'=>null,'clave_prod_serv'=>'01010101','clave_unidad'=>'H87','unidad'=>'PZA','descripcion'=>'','cantidad'=>1,'valor_unitario'=>0,'descuento'=>0,'iva_pct'=>0,'objeto_imp'=>'02','importe'=>0,'iva_importe'=>0,'ieps_pct'=>0,'ieps_importe'=>0],
        ]);
    @endphp

    <x-wire-card>
        <form id="inv-form"
              method="POST"
              action="{{ route('admin.invoices.store') }}"
              class="space-y-6"
              x-data="invForm()"
              x-init="init()">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Cliente (select clásico como pediste) --}}
                <div class="md:col-span-2 space-y-2 w-full">
                    <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                    @php $cid = old('client_id'); @endphp
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ (string)$cid === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <x-wire-input label="Serie" name="serie" value="{{ old('serie','A') }}" />
                <x-wire-input label="Folio" name="folio" value="{{ old('folio') }}" />

                <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $valueFecha }}" required />
                <x-wire-input label="Moneda" name="moneda" value="{{ old('moneda','MXN') }}" />
                <x-wire-input label="Lugar expedición (CP)" name="lugar_expedicion" value="{{ old('lugar_expedicion', '97000') }}" />

                {{-- Tipo comprobante (select clásico) --}}
                <div class="space-y-2 w-full">
                    <label for="tipo_comprobante" class="block text-sm font-medium text-gray-700">Tipo comprobante</label>
                    @php $tc = old('tipo_comprobante','I'); @endphp
                    <select name="tipo_comprobante" id="tipo_comprobante"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(['I'=>'Ingreso','E'=>'Egreso','P'=>'Pago','N'=>'Nómina'] as $k=>$lbl)
                            <option value="{{ $k }}" {{ $tc===$k ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Forma de pago</label>
                    <input name="forma_pago" class="w-full rounded-md border-gray-300" value="{{ old('forma_pago','01') }}" placeholder="01, 03, 99...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Método de pago</label>
                    <input name="metodo_pago" class="w-full rounded-md border-gray-300" value="{{ old('metodo_pago','PUE') }}" placeholder="PUE/PPD">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Uso CFDI</label>
                    <input name="uso_cfdi" class="w-full rounded-md border-gray-300" value="{{ old('uso_cfdi','G01') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Exportación</label>
                    <input name="exportacion" class="w-full rounded-md border-gray-300" value="{{ old('exportacion','01') }}" maxlength="2">
                </div>
            </div>

            {{-- Partidas --}}
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b">
                    <tr>
                        <th class="p-2">ClaveProdServ</th>
                        <th class="p-2">Descripción</th>
                        <th class="p-2">Unidad</th>
                        <th class="p-2 text-right">Cantidad</th>
                        <th class="p-2 text-right">V. Unitario</th>
                        <th class="p-2 text-right">Desc.</th>
                        <th class="p-2 text-right">% IVA</th>
                        <th class="p-2 text-right">Importe</th>
                        <th class="p-2"></th>
                    </tr>
                    </thead>
                    <tbody>
                        <template x-for="(it, i) in items" :key="i">
                            <tr class="border-b">
                                <td class="p-2">
                                    <input class="w-28 border rounded p-1" x-model="it.clave_prod_serv" :name="'items['+i+'][clave_prod_serv]'">
                                </td>
                                <td class="p-2">
                                    <input class="w-64 border rounded p-1" x-model="it.descripcion" :name="'items['+i+'][descripcion]'">
                                </td>
                                <td class="p-2">
                                    <div class="flex gap-1">
                                        <input class="w-16 border rounded p-1" x-model="it.clave_unidad" :name="'items['+i+'][clave_unidad]'" placeholder="H87">
                                        <input class="w-16 border rounded p-1" x-model="it.unidad" :name="'items['+i+'][unidad]'" placeholder="PZA">
                                    </div>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" step="0.000001" min="0" class="w-24 border rounded p-1 text-right"
                                           x-model.number="it.cantidad" :name="'items['+i+'][cantidad]'" @input="recalc(i)">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" step="0.000001" min="0" class="w-28 border rounded p-1 text-right"
                                           x-model.number="it.valor_unitario" :name="'items['+i+'][valor_unitario]'" @input="recalc(i)">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" step="0.000001" min="0" class="w-24 border rounded p-1 text-right"
                                           x-model.number="it.descuento" :name="'items['+i+'][descuento]'" @input="recalc(i)">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" step="0.01" min="0" class="w-20 border rounded p-1 text-right"
                                           x-model.number="it.iva_pct" @input="recalc(i)">
                                    <input type="hidden" :name="'items['+i+'][iva_pct]'" x-model="it.iva_pct">
                                    <input type="hidden" :name="'items['+i+'][iva_importe]'" x-model="it.iva_importe">
                                </td>
                                <td class="p-2 text-right" x-text="fmt(it.importe)"></td>
                                <td class="p-2">
                                    <button class="text-red-600" type="button" @click="remove(i)">Eliminar</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="mt-2">
                    <x-wire-button type="button" gray @click="add()">Agregar partida</x-wire-button>
                </div>
            </div>

            {{-- Totales --}}
            <div class="text-right space-y-1">
                <div>Subtotal: <span x-text="fmt(subtotal)"></span></div>
                <div>Impuestos: <span x-text="fmt(tax_total)"></span></div>
                <div class="font-semibold text-lg">Total: <span x-text="fmt(grand)"></span></div>
                <input type="hidden" name="subtotal" x-model="subtotal">
                <input type="hidden" name="impuestos" x-model="tax_total">
                <input type="hidden" name="total" x-model="grand">
            </div>
        </form>
    </x-wire-card>

    <script>
    function invForm(){
        const seed = @json($itemsSeed);
        return {
            items: seed,
            subtotal:0, tax_total:0, grand:0,
            init(){ this.sum(); },
            add(){ this.items.push({clave_prod_serv:'01010101', clave_unidad:'H87', unidad:'PZA', descripcion:'', cantidad:1, valor_unitario:0, descuento:0, iva_pct:0, objeto_imp:'02', importe:0, iva_importe:0}); },
            remove(i){ this.items.splice(i,1); this.sum(); },
            recalc(i){
                const it=this.items[i];
                const base = Math.max((+it.cantidad||0)*(+it.valor_unitario||0) - (+it.descuento||0), 0);
                const iva = base * ((+it.iva_pct||0)/100);
                it.iva_importe = iva; it.importe = base + iva;
                this.sum();
            },
            sum(){
                let s=0, t=0, g=0;
                this.items.forEach(it=>{
                    const base = Math.max((+it.cantidad||0)*(+it.valor_unitario||0) - (+it.descuento||0), 0);
                    const iva  = base * ((+it.iva_pct||0)/100);
                    it.iva_importe=iva; it.importe=base+iva;
                    s+=base; t+=iva; g+=base+iva;
                });
                this.subtotal=s; this.tax_total=t; this.grand=g;
            },
            fmt(n){ return Number(n||0).toFixed(2); }
        }
    }
    </script>
</x-admin-layout>
