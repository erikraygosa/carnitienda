<x-admin-layout
    title="Cotización {{ $quote->folio ?? '#'.$quote->id }}"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cotizaciones','url'=>route('admin.quotes.index')],
        ['name'=>$quote->folio ?? '#'.$quote->id],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.quotes.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <a href="{{ route('admin.quotes.send.form',$quote) }}" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-violet-600 text-white">Enviar</a>
        <a href="{{ route('admin.quotes.pdf',$quote) }}" target="_blank" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">Ver PDF</a>
        <a href="{{ route('admin.quotes.pdf.download',$quote) }}" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">Descargar PDF</a>
        @if($quote->status === 'BORRADOR')
            <button form="quote-edit-form" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
    </x-slot>

    @php
        $isLocked      = $quote->status !== 'BORRADOR';
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

        $itemsSeed = $quote->items->map(fn($i) => [
            'product_id'  => $i->product_id,
            'descripcion' => $i->descripcion,
            'cantidad'    => (float)$i->cantidad,
            'precio'      => (float)$i->precio,
            'descuento'   => (float)$i->descuento,
            'iva_pct'     => 0,
            'impuesto'    => (float)$i->impuesto,
            'total'       => (float)$i->total,
        ])->values()->toArray();
    @endphp

    {{-- ====== FORMULARIO ====== --}}
    <x-wire-card>
        <form id="quote-edit-form" method="POST" action="{{ route('admin.quotes.update',$quote) }}"
              class="space-y-6" x-data="qFormEdit()" x-init="init()">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id?'selected':'' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
                    <select name="price_list_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}" {{ $selPriceList===(string)$pl->id?'selected':'' }}>{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $valueFecha }}"
                                  :disabled="$isLocked" required />
                </div>
                <div>
                    <x-wire-input label="Moneda" name="moneda" value="{{ $valueMoneda }}"
                                  :disabled="$isLocked" required />
                </div>
                <div>
                    <x-wire-input label="Vigencia hasta" name="vigencia_hasta" type="date" value="{{ $valueVigencia }}"
                                  :disabled="$isLocked" />
                </div>
            </div>

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
                                            @change="syncDesc(i)" :disabled="locked">
                                        <option value="">—</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}"
                                                    data-nombre="{{ $p->nombre }}"
                                                    data-precio="{{ $p->precio_base }}">
                                                {{ $p->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input type="text" class="w-64 border rounded p-1 text-sm"
                                           x-bind:name="'items['+i+'][descripcion]'"
                                           x-model="it.descripcion" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0.001" step="0.001"
                                           class="w-24 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][cantidad]'"
                                           x-model.number="it.cantidad" @input="recalc(i)" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.0001"
                                           class="w-28 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][precio]'"
                                           x-model.number="it.precio" @input="recalc(i)" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01"
                                           class="w-24 border rounded p-1 text-right text-sm"
                                           x-model.number="it.descuento" @input="recalc(i)" :disabled="locked">
                                    <input type="hidden" x-bind:name="'items['+i+'][descuento]'" x-model="it.descuento">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01"
                                           class="w-20 border rounded p-1 text-right text-sm"
                                           x-model.number="it.iva_pct" @input="recalc(i)" :disabled="locked">
                                    <input type="hidden" x-bind:name="'items['+i+'][impuesto]'" x-model="it.impuesto">
                                </td>
                                <td class="p-2 text-right font-medium" x-text="fmt(it.total)"></td>
                                <td class="p-2" x-show="!locked">
                                    <button type="button" class="text-red-500 hover:text-red-700 text-xs"
                                            @click="remove(i)">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="mt-3" x-show="!locked">
                    <button type="button"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50"
                            @click="add()">+ Agregar partida</button>
                </div>
            </div>

            <div class="text-right space-y-1 border-t pt-3">
                <div class="text-sm text-gray-600">Subtotal: <span class="font-medium" x-text="fmt(subtotal)"></span></div>
                <div class="text-sm text-gray-600">Descuento: <span class="font-medium" x-text="fmt(desc_total)"></span></div>
                <div class="text-sm text-gray-600">Impuestos: <span class="font-medium" x-text="fmt(tax_total)"></span></div>
                <div class="text-lg font-bold text-gray-800">Total: $<span x-text="fmt(grand)"></span></div>
            </div>
        </form>
    </x-wire-card>

    {{-- ====== ACCIONES ====== --}}
    <x-wire-card class="mt-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                {{ $quote->status_label ?? $quote->status }}
            </span>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                @if(in_array($quote->status, ['BORRADOR','ENVIADA']))
                    <form method="POST" action="{{ route('admin.quotes.reject',$quote) }}">@csrf
                        <x-wire-button type="submit" amber xs>Rechazar</x-wire-button>
                    </form>
                @endif

                @if(!in_array($quote->status, ['CONVERTIDA','CANCELADA']))
                    <form method="POST" action="{{ route('admin.quotes.cancel',$quote) }}">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ====== CONVERTIR A PEDIDO ====== --}}
        @if(in_array($quote->status, ['BORRADOR','ENVIADA']))
        <div class="mt-4 border-t pt-4">
            <p class="text-sm font-semibold text-gray-700 mb-3">Aprobar y convertir en pedido</p>
            <form method="POST" action="{{ route('admin.quotes.approve',$quote) }}">
                @csrf
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Almacén <span class="text-red-500">*</span></label>
                        <select name="warehouse_id"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                            <option value="">-- seleccionar --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Método de pago <span class="text-red-500">*</span></label>
                        <select name="payment_method"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                x-data="{pm:'EFECTIVO'}" x-model="pm" required>
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia</option>
                            <option value="CONTRAENTREGA">Contraentrega</option>
                            <option value="CREDITO">Crédito</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Tipo entrega <span class="text-red-500">*</span></label>
                        <select name="delivery_type"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                            <option value="ENVIO">Envío a domicilio</option>
                            <option value="RECOGER">Recoger en almacén</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Programado para</label>
                        <input type="date" name="programado_para"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                        Aprobar y generar pedido
                    </button>
                </div>
            </form>
        </div>
        @endif

        {{-- Badge convertida --}}
        @if($quote->status === 'APROBADA' || $quote->status === 'CONVERTIDA')
        <div class="mt-4 border-t pt-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-emerald-100 text-emerald-700">
                Cotización aprobada — convertida en pedido
            </span>
        </div>
        @endif
    </x-wire-card>

    <script>
    function qFormEdit(){
        const seed   = @json($itemsSeed);
        const locked = @json($isLocked);
        return {
            items: (seed && seed.length) ? seed
                : [{product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}],
            locked,
            subtotal:0, desc_total:0, tax_total:0, grand:0,
            init(){ this.sum(); },
            add(){ if(this.locked) return; this.items.push({product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}); },
            remove(i){ if(this.locked) return; this.items.splice(i,1); this.sum(); },
            syncDesc(i){
                if(this.locked) return;
                const sel = event.target;
                const opt = sel.options[sel.selectedIndex];
                if(!this.items[i].descripcion) this.items[i].descripcion = opt?.dataset?.nombre || '';
                if(this.items[i].precio === 0)  this.items[i].precio = parseFloat(opt?.dataset?.precio || '0') || 0;
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