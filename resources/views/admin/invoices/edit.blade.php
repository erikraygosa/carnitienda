<x-admin-layout
    title="Editar factura"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas','url'=>route('admin.invoices.index')],
        ['name'=>'Editar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        @if($invoice->status === 'BORRADOR')
            <button form="invoice-edit-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Actualizar</button>
        @endif
    </x-slot>

    @php
        $isEdit  = true;
        $isLocked = $invoice->status !== 'BORRADOR';

        $selClient = old('client_id', $invoice->client_id);
        $selMoneda = old('moneda', $invoice->moneda ?? 'MXN');
        $selMetodo = old('metodo_pago', $invoice->metodo_pago ?? 'PPD');
        $selForma  = old('forma_pago', $invoice->forma_pago ?? '03'); // 03 Transferencia, etc.
        $selUso    = old('uso_cfdi', $invoice->uso_cfdi ?? 'G01');
        $selTipo   = old('tipo_comprobante', $invoice->tipo_comprobante ?? 'I'); // I, E, P, N
        $fechaVal  = old('fecha', optional($invoice->fecha)->format('Y-m-d\TH:i'));
    @endphp

    <x-wire-card>
        <form id="invoice-edit-form"
              action="{{ route('admin.invoices.update', $invoice) }}"
              method="POST"
              class="space-y-6"
              x-data="invoiceFormEdit()"
              x-init="init()"
        >
            @csrf @method('PUT')

            {{-- Encabezado --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Cliente (select clásico) --}}
                <div class="md:col-span-2 space-y-2 w-full">
                    <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                    @php $clientSel = (string) $selClient; @endphp
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $clientSel === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local"
                                  value="{{ $fechaVal }}" :disabled="$isLocked" required/>
                </div>

                <div>
                    <x-wire-input label="Serie" name="serie" value="{{ old('serie',$invoice->serie) }}" :disabled="$isLocked"/>
                </div>

                <div>
                    <x-wire-input label="Folio" name="folio" type="number" value="{{ old('folio',$invoice->folio) }}" :disabled="$isLocked"/>
                </div>

                {{-- Moneda (select clásico) --}}
                <div class="space-y-2 w-full">
                    <label for="moneda" class="block text-sm font-medium text-gray-700">Moneda</label>
                    @php $mon = $selMoneda; @endphp
                    <select name="moneda" id="moneda"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="MXN" {{ $mon==='MXN' ? 'selected' : '' }}>MXN</option>
                        <option value="USD" {{ $mon==='USD' ? 'selected' : '' }}>USD</option>
                    </select>
                </div>

                {{-- Tipo comprobante --}}
                <div class="space-y-2 w-full">
                    <label for="tipo_comprobante" class="block text-sm font-medium text-gray-700">Tipo</label>
                    @php $tipo = $selTipo; @endphp
                    <select name="tipo_comprobante" id="tipo_comprobante"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="I" {{ $tipo==='I' ? 'selected' : '' }}>Ingreso</option>
                        <option value="E" {{ $tipo==='E' ? 'selected' : '' }}>Egreso</option>
                        <option value="P" {{ $tipo==='P' ? 'selected' : '' }}>Pago</option>
                        <option value="N" {{ $tipo==='N' ? 'selected' : '' }}>Nómina</option>
                    </select>
                </div>

                {{-- Método/forma/uso CFDI (select clásico como pediste) --}}
                <div class="space-y-2 w-full">
                    <label for="metodo_pago" class="block text-sm font-medium text-gray-700">Método de pago</label>
                    @php $met = $selMetodo; @endphp
                    <select name="metodo_pago" id="metodo_pago"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="PUE" {{ $met==='PUE' ? 'selected' : '' }}>PUE</option>
                        <option value="PPD" {{ $met==='PPD' ? 'selected' : '' }}>PPD</option>
                    </select>
                </div>

                <div class="space-y-2 w-full">
                    <label for="forma_pago" class="block text-sm font-medium text-gray-700">Forma de pago</label>
                    @php $fp = $selForma; @endphp
                    <select name="forma_pago" id="forma_pago"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="01" {{ $fp==='01' ? 'selected':'' }}>Efectivo</option>
                        <option value="02" {{ $fp==='02' ? 'selected':'' }}>Cheque nominativo</option>
                        <option value="03" {{ $fp==='03' ? 'selected':'' }}>Transferencia</option>
                        <option value="28" {{ $fp==='28' ? 'selected':'' }}>Tarjeta débito</option>
                        <option value="04" {{ $fp==='04' ? 'selected':'' }}>Tarjeta crédito</option>
                    </select>
                </div>

                <div class="space-y-2 w-full">
                    <label for="uso_cfdi" class="block text-sm font-medium text-gray-700">Uso CFDI</label>
                    @php $uso = $selUso; @endphp
                    <select name="uso_cfdi" id="uso_cfdi"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="G01" {{ $uso==='G01' ? 'selected':'' }}>Adquisición de mercancías</option>
                        <option value="G03" {{ $uso==='G03' ? 'selected':'' }}>Gastos en general</option>
                        <option value="P01" {{ $uso==='P01' ? 'selected':'' }}>Por definir</option>
                    </select>
                </div>
            </div>

            {{-- Partidas (idéntico a Pedidos, con IVA/desc/total) --}}
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
                                        @change="syncDescFromProduct(i)"
                                        :disabled="locked">
                                        <option value="">—</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}" data-nombre="{{ $p->nombre }}" data-precio="{{ $p->precio_base }}">
                                                {{ $p->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input type="text" class="w-64 border rounded p-1"
                                        x-bind:name="'items['+i+'][descripcion]'"
                                        x-model="it.descripcion" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0.001" step="0.001" class="w-24 border rounded p-1 text-right"
                                        x-bind:name="'items['+i+'][cantidad]'"
                                        x-model.number="it.cantidad" @input="recalc(i)" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.0001" class="w-28 border rounded p-1 text-right"
                                        x-bind:name="'items['+i+'][precio]'"
                                        x-model.number="it.precio" @input="recalc(i)" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-24 border rounded p-1 text-right"
                                        x-model.number="it.descuento" @input="recalc(i)" :disabled="locked">
                                    <input type="hidden" x-bind:name="'items['+i+'][descuento]'" x-model="it.descuento">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-20 border rounded p-1 text-right"
                                        x-model.number="it.iva_pct" @input="recalc(i)" :disabled="locked">
                                    <input type="hidden" x-bind:name="'items['+i+'][impuesto]'" x-model="it.impuesto">
                                </td>
                                <td class="p-2 text-right" x-text="fmt(it.total)"></td>
                                <td class="p-2" x-show="!locked">
                                    <button type="button" class="text-red-600" @click="remove(i)">Eliminar</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="mt-2" x-show="!locked">
                    <x-wire-button type="button" gray @click="add()">Agregar partida</x-wire-button>
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

    {{-- Acciones rápidas --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center space-x-2">
            <x-wire-button href="{{ route('admin.invoices.edit', $invoice) }}" blue xs>Editar</x-wire-button>

            @if($invoice->status === 'BORRADOR')
                <x-wire-button href="{{ route('admin.invoices.preview',$invoice) }}" gray outline xs target="_blank">Previsualizar</x-wire-button>
                <form action="{{ route('admin.invoices.timbrar',$invoice) }}" method="POST">@csrf
                    <x-wire-button type="submit" emerald xs>Timbrar</x-wire-button>
                </form>
            @elseif($invoice->status === 'TIMBRADA')
                <x-wire-button href="{{ route('admin.invoices.pdf',$invoice) }}" gray outline xs target="_blank">PDF</x-wire-button>
                <x-wire-button href="{{ route('admin.invoices.xml',$invoice) }}" gray xs>XML</x-wire-button>
                <form action="{{ route('admin.invoices.cancel',$invoice) }}" method="POST">@csrf
                    <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                </form>
            @endif

            <span class="ml-auto px-2 py-1 text-xs rounded-full
                {{ $invoice->status === 'TIMBRADA' ? 'bg-emerald-100 text-emerald-700' :
                   ($invoice->status === 'CANCELADA' ? 'bg-rose-100 text-rose-700' :
                   'bg-slate-100 text-slate-700') }}">
                Estatus: {{ $invoice->status }}
            </span>
        </div>
    </x-wire-card>

    <script>
    function invoiceFormEdit(){
        const seed = @json(
            $invoice->items->map(fn($i)=>[
                'product_id'  => $i->product_id,
                'descripcion' => $i->descripcion,
                'cantidad'    => (float)$i->cantidad,
                'precio'      => (float)$i->precio,
                'descuento'   => (float)$i->descuento,
                'iva_pct'     => (float)$i->iva_pct,
                'impuesto'    => (float)$i->impuesto,
                'total'       => (float)$i->total,
            ])->values()
        );
        const locked = @json($isLocked);

        return {
            items: (seed && seed.length) ? seed : [{product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}],
            locked,
            subtotal:0, desc_total:0, tax_total:0, grand:0,

            init(){ this.sum(); },
            add(){ if(this.locked) return; this.items.push({product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}); },
            remove(i){ if(this.locked) return; this.items.splice(i,1); this.sum(); },

            syncDescFromProduct(i){
                if(this.locked) return;
                const select = event.target;
                const nombre = select.options[select.selectedIndex]?.dataset?.nombre || '';
                const precio = parseFloat(select.options[select.selectedIndex]?.dataset?.precio || '0') || 0;
                if(!this.items[i].descripcion){ this.items[i].descripcion = nombre; }
                if(this.items[i].precio === 0){ this.items[i].precio = precio; }
                this.recalc(i);
            },

            recalc(i){
                const it=this.items[i];
                const line=(+it.cantidad||0)*(+it.precio||0);
                const disc=+it.descuento||0;
                const base=Math.max(line-disc,0);
                const tax=((+it.iva_pct||0)*0.01)*base;
                it.impuesto=tax; it.total=base+tax;
                this.sum();
            },

            sum(){
                let s=0,d=0,t=0,g=0;
                this.items.forEach(it=>{
                    const line=(+it.cantidad||0)*(+it.precio||0);
                    const disc=+it.descuento||0;
                    const base=Math.max(line-disc,0);
                    const tax=((+it.iva_pct||0)*0.01)*base;
                    const tot=base+tax;
                    s+=line; d+=disc; t+=tax; g+=tot;
                    it.impuesto=tax; it.total=tot;
                });
                this.subtotal=s; this.desc_total=d; this.tax_total=t; this.grand=g;
            },

            fmt(n){ return Number(n||0).toFixed(2); }
        }
    }
    </script>
</x-admin-layout>
