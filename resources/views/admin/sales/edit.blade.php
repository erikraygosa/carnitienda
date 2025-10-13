{{-- resources/views/admin/sales/edit.blade.php --}}
<x-admin-layout
    title="Editar nota de venta"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Ventas','url'=>route('admin.sales.index')],
        ['name'=>'Editar'],
    ]"
>
    {{-- BARRA DE ACCIONES SUPERIOR --}}
    <x-slot name="action">
        <a href="{{ route('admin.sales.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>

        @if($sale->status === 'ABIERTA')
            <button form="sale-edit-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
    </x-slot>

    @php
        $isLocked     = $sale->status !== 'ABIERTA';

        $selClient    = (string) old('client_id', $sale->client_id);
        $selWh        = (string) old('warehouse_id', $sale->warehouse_id);
        $selPos       = (string) old('pos_register_id', $sale->pos_register_id);
        $selPayType   = (string) old('payment_type_id', $sale->payment_type_id);
        $tipoVenta    = old('tipo_venta', $sale->tipo_venta);
        $creditDays   = old('credit_days', $sale->credit_days);

        $valueFecha   = old('fecha', optional($sale->fecha)->format('Y-m-d\TH:i'));
        $moneda       = old('moneda', $sale->moneda ?? 'MXN');

        $statusClasses = [
            'ABIERTA'   => 'bg-amber-100 text-amber-700',
            'CERRADA'   => 'bg-emerald-100 text-emerald-700',
            'CANCELADA' => 'bg-rose-100 text-rose-700',
        ];
        $statusClass = $statusClasses[$sale->status] ?? 'bg-slate-100 text-slate-700';

        // Semilla de partidas para Alpine
        $itemsSeed = $sale->items->map(function($i){
            return [
                'product_id'  => $i->product_id,
                'descripcion' => $i->descripcion ?? ($i->product?->nombre ?? ''),
                'cantidad'    => (float)$i->cantidad,
                'precio'      => (float)$i->precio,
                'descuento'   => (float)$i->descuento,
                'iva_pct'     => 0,
                'impuesto'    => (float)$i->impuesto,
                'total'       => (float)$i->total,
            ];
        })->values()->toArray();
    @endphp

    {{-- FORMULARIO --}}
    <x-wire-card>
        <form id="sale-edit-form"
              method="POST"
              action="{{ route('admin.sales.update', $sale) }}"
              class="space-y-6"
              x-data="saleFormEdit()"
              x-init="init()"
        >
            @csrf @method('PUT')

            {{-- Encabezado --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Caja / POS --}}
                <div class="space-y-2 w-full">
                    <label class="block text-sm font-medium text-gray-700" for="pos_register_id">Caja</label>
                    <select name="pos_register_id" id="pos_register_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="">-- seleccionar --</option>
                        @foreach($posRegisters as $pos)
                            <option value="{{ $pos->id }}" {{ $selPos===(string)$pos->id ? 'selected' : '' }}>
                                {{ $pos->nombre ?? ('Caja #'.$pos->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Almacén --}}
                <div class="space-y-2 w-full">
                    <label class="block text-sm font-medium text-gray-700" for="warehouse_id">Almacén</label>
                    <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }} required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWh===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Cliente --}}
                <div class="space-y-2 w-full md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700" for="client_id">Cliente</label>
                    <select name="client_id" id="client_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- mostrador / sin cliente --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha / Moneda --}}
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $valueFecha }}" :disabled="$isLocked" required/>
                </div>
                <div>
                    <x-wire-input label="Moneda" name="moneda" value="{{ $moneda }}" :disabled="$isLocked" required/>
                </div>

                {{-- Forma de pago (catálogo) --}}
                <div class="space-y-2 w-full">
                    <label class="block text-sm font-medium text-gray-700" for="payment_type_id">Forma de pago</label>
                    <select name="payment_type_id" id="payment_type_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($payTypes as $pt)
                            <option value="{{ $pt->id }}" {{ $selPayType===(string)$pt->id ? 'selected' : '' }}>
                                {{ $pt->label ?? $pt->descripcion ?? $pt->clave }} ({{ $pt->clave }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo de venta --}}
                <div class="space-y-2 w-full">
                    <label class="block text-sm font-medium text-gray-700" for="tipo_venta">Tipo de venta</label>
                    <select name="tipo_venta" id="tipo_venta" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="CONTADO"        {{ $tipoVenta==='CONTADO' ? 'selected' : '' }}>Contado</option>
                        <option value="CREDITO"        {{ $tipoVenta==='CREDITO' ? 'selected' : '' }}>Crédito</option>
                        <option value="CONTRAENTREGA"  {{ $tipoVenta==='CONTRAENTREGA' ? 'selected' : '' }}>Contraentrega</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div class="space-y-2 w-full" @class(['hidden' => $tipoVenta!=='CREDITO'])>
                    <x-wire-input label="Días de crédito" name="credit_days" type="number" min="0" value="{{ $creditDays ?? 0 }}" :disabled="$isLocked" />
                </div>
            </div>

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

    {{-- Cabecera de estado + Acciones como en cotizaciones --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2">
            <x-wire-badge>Folio: {{ $sale->folio ?? ('Sale #'.$sale->id) }}</x-wire-badge>
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                Estatus: {{ $sale->status }}
            </span>

            <div class="ml-auto flex items-center space-x-2">
                {{-- PDF --}}
                <x-wire-button href="{{ route('admin.sales.pdf',$sale) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
                <x-wire-button href="{{ route('admin.sales.pdf.download',$sale) }}" gray xs>Descargar PDF</x-wire-button>

                {{-- Enviar --}}
                <x-wire-button href="{{ route('admin.sales.send.form',$sale) }}" violet xs>Enviar</x-wire-button>

                @if($sale->status === 'ABIERTA')
                    <form method="POST" action="{{ route('admin.sales.close',$sale) }}"> @csrf
                        <x-wire-button type="submit" green xs>Cerrar</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}"> @csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @endif
            </div>
        </div>
    </x-wire-card>

    {{-- Alpine: mismo patrón de cálculo que ya usamos --}}
    <script>
    function saleFormEdit(){
        const seed   = @json($itemsSeed);
        const locked = @json($isLocked);

        return {
            items: (seed && seed.length) ? seed : [
                {product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}
            ],
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
