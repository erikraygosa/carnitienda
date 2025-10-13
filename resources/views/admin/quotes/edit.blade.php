<x-admin-layout
    title="Editar cotización"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cotizaciones','url'=>route('admin.quotes.index')],
        ['name'=>'Editar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.quotes.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>

        {{-- Enviar (abre formulario), Ver/Descargar PDF --}}
        <a href="{{ route('admin.quotes.send.form',$quote) }}" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-violet-600 text-white">Enviar</a>
        <a href="{{ route('admin.quotes.pdf',$quote) }}" target="_blank" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">Ver PDF</a>
        <a href="{{ route('admin.quotes.pdf.download',$quote) }}" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">Descargar PDF</a>

        @if($quote->status === 'BORRADOR')
            <button form="quote-edit-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Actualizar</button>
        @endif
    </x-slot>

    @php
        $isLocked = $quote->status !== 'BORRADOR';
        $selClient     = (string) old('client_id', $quote->client_id);
        $selPriceList  = (string) old('price_list_id', $quote->price_list_id);
        $valueFecha    = old('fecha', optional($quote->fecha)->format('Y-m-d\TH:i'));
        $valueMoneda   = old('moneda', $quote->moneda);
        $valueVigencia = old('vigencia_hasta', optional($quote->vigencia_hasta)->toDateString());

        $statusClasses = [
            'BORRADOR'   => 'bg-gray-100 text-gray-700',
            'ENVIADA'    => 'bg-sky-100 text-sky-700',
            'APROBADA'   => 'bg-emerald-100 text-emerald-700',
            'RECHAZADA'  => 'bg-rose-100 text-rose-700',
            'CONVERTIDA' => 'bg-indigo-100 text-indigo-700',
            'CANCELADA'  => 'bg-amber-100 text-amber-700',
        ];
        $statusClass = $statusClasses[$quote->status] ?? 'bg-slate-100 text-slate-700';

        $itemsSeed = $quote->items->map(function($i){
            return [
                'product_id'  => $i->product_id,
                'descripcion' => $i->descripcion,
                'cantidad'    => (float)$i->cantidad,
                'precio'      => (float)$i->precio,
                'descuento'   => (float)$i->descuento,
                'iva_pct'     => 0, // desconocido; el importe ya está en impuesto
                'impuesto'    => (float)$i->impuesto,
                'total'       => (float)$i->total,
            ];
        })->values()->toArray();
    @endphp

    <x-wire-card>
        <form id="quote-edit-form" method="POST" action="{{ route('admin.quotes.update',$quote) }}"
              class="space-y-6" x-data="qFormEdit()" x-init="init()">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2 space-y-2 w-full">
                    <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                    <select name="client_id" id="client_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 w-full">
                    <label for="price_list_id" class="block text-sm font-medium text-gray-700">Lista de precios</label>
                    <select name="price_list_id" id="price_list_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}" {{ $selPriceList===(string)$pl->id ? 'selected' : '' }}>{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $valueFecha }}" :disabled="$isLocked" required/>
                </div>

                <div>
                    <x-wire-input label="Moneda" name="moneda" value="{{ $valueMoneda }}" :disabled="$isLocked" required/>
                </div>

                <div>
                    <x-wire-input label="Vigencia hasta" name="vigencia_hasta" type="date" value="{{ $valueVigencia }}" :disabled="$isLocked"/>
                </div>
            </div>

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

            <div class="text-right space-y-1">
                <div>Subtotal: <span x-text="fmt(subtotal)"></span></div>
                <div>Descuento: <span x-text="fmt(desc_total)"></span></div>
                <div>Impuestos: <span x-text="fmt(tax_total)"></span></div>
                <div class="font-semibold text-lg">Total: <span x-text="fmt(grand)"></span></div>
            </div>
        </form>
    </x-wire-card>

    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2">
            <x-wire-badge>ID: {{ $quote->id }}</x-wire-badge>
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                Estatus: {{ $quote->status_label ?? $quote->status }}
            </span>
            <div class="ml-auto flex items-center gap-2">
                {{-- Acciones rápidas también aquí si lo prefieres --}}
                <a href="{{ route('admin.quotes.send.form',$quote) }}" class="inline-flex px-3 py-1.5 text-sm rounded-md bg-violet-600 text-white">Enviar</a>
                <a href="{{ route('admin.quotes.pdf',$quote) }}" target="_blank" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Ver PDF</a>
                <a href="{{ route('admin.quotes.pdf.download',$quote) }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Descargar PDF</a>

                @if($quote->status === 'BORRADOR')
                    <form method="POST" action="{{ route('admin.quotes.approve',$quote) }}"> @csrf
                        <x-wire-button type="submit" green>Aprobar</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.quotes.reject',$quote) }}"> @csrf
                        <x-wire-button type="submit" amber>Rechazar</x-wire-button>
                    </form>
                @endif
                @if($quote->status !== 'CONVERTIDA')
                    <form method="POST" action="{{ route('admin.quotes.cancel',$quote) }}"> @csrf
                        <x-wire-button type="submit" red>Cancelar</x-wire-button>
                    </form>
                @endif
            </div>
        </div>
    </x-wire-card>

    <script>
        function qFormEdit(){
            const seed   = @json($itemsSeed);
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
                    it.impuesto=tax;
                    it.total=base+tax;
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
