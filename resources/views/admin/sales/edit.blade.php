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
        $selPriceList = (string) old('price_list_id', $sale->price_list_id);
        $selRoute     = (string) old('shipping_route_id', $sale->shipping_route_id);
        $selDriver    = (string) old('driver_id', $sale->driver_id);

        $tipoVenta    = old('tipo_venta', $sale->tipo_venta);
        $creditDays   = old('credit_days', $sale->credit_days);

        $deliveryType = old('delivery_type', $sale->delivery_type);
        $entregaNombre   = old('entrega_nombre', $sale->entrega_nombre);
        $entregaTelefono = old('entrega_telefono', $sale->entrega_telefono);
        $entregaCalle    = old('entrega_calle', $sale->entrega_calle);
        $entregaNumero   = old('entrega_numero', $sale->entrega_numero);
        $entregaColonia  = old('entrega_colonia', $sale->entrega_colonia);
        $entregaCiudad   = old('entrega_ciudad', $sale->entrega_ciudad);
        $entregaEstado   = old('entrega_estado', $sale->entrega_estado);
        $entregaCp       = old('entrega_cp', $sale->entrega_cp);

        $valueFecha   = old('fecha', optional($sale->fecha)->format('Y-m-d\TH:i'));
        $moneda       = old('moneda', $sale->moneda ?? 'MXN');

        $statusClasses = [
            'BORRADOR'  => 'bg-gray-100 text-gray-700',
            'APROBADO'  => 'bg-blue-100 text-blue-700',
            'ABIERTA'   => 'bg-amber-100 text-amber-700',
            'PREPARANDO'=> 'bg-amber-100 text-amber-700',
            'PROCESADA' => 'bg-amber-100 text-amber-700',
            'EN_RUTA'   => 'bg-violet-100 text-violet-700',
            'ENTREGADA' => 'bg-emerald-100 text-emerald-700',
            'NO_ENTREGADA' => 'bg-slate-100 text-slate-700',
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

                {{-- Lista de precios --}}
                <div class="space-y-2 w-full">
                    <label class="block text-sm font-medium text-gray-700" for="price_list_id">Lista de precios</label>
                    <select name="price_list_id" id="price_list_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">— Personalizada del cliente —</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}" {{ $selPriceList===(string)$pl->id ? 'selected' : '' }}>
                                {{ $pl->nombre }}
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

                {{-- Tipo de entrega --}}
                <div class="space-y-2 w-full">
                    <label class="block text-sm font-medium text-gray-700" for="delivery_type">Tipo de entrega</label>
                    <select name="delivery_type" id="delivery_type" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="ENVIO"   {{ $deliveryType==='ENVIO' ? 'selected' : '' }}>Envío a domicilio</option>
                        <option value="RECOGER" {{ $deliveryType==='RECOGER' ? 'selected' : '' }}>Recoger en almacén</option>
                    </select>
                </div>

                {{-- Ruta / Chofer --}}
                <div class="space-y-2 w-full">
                    <label class="block text-sm font-medium text-gray-700" for="shipping_route_id">Ruta</label>
                    <select name="shipping_route_id" id="shipping_route_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- sin ruta --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selRoute===(string)$r->id ? 'selected' : '' }}>
                                {{ $r->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2 w-full">
                    <label class="block text-sm font-medium text-gray-700" for="driver_id">Chofer</label>
                    <select name="driver_id" id="driver_id" class="w-full rounded-md border-gray-300" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- sin chofer --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selDriver===(string)$d->id ? 'selected' : '' }}>
                                {{ $d->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Dirección de entrega (solo si ENVIO) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" @class(['hidden' => $deliveryType!=='ENVIO'])>
                <div><x-wire-input label="Nombre quien recibe" name="entrega_nombre"   value="{{ $entregaNombre }}"   :disabled="$isLocked" /></div>
                <div><x-wire-input label="Teléfono"             name="entrega_telefono" value="{{ $entregaTelefono }}" :disabled="$isLocked" /></div>
                <div><x-wire-input label="Calle"                name="entrega_calle"    value="{{ $entregaCalle }}"    :disabled="$isLocked" /></div>
                <div><x-wire-input label="Número"               name="entrega_numero"   value="{{ $entregaNumero }}"   :disabled="$isLocked" /></div>
                <div><x-wire-input label="Colonia"              name="entrega_colonia"  value="{{ $entregaColonia }}"  :disabled="$isLocked" /></div>
                <div><x-wire-input label="Ciudad"               name="entrega_ciudad"   value="{{ $entregaCiudad }}"   :disabled="$isLocked" /></div>
                <div><x-wire-input label="Estado"               name="entrega_estado"   value="{{ $entregaEstado }}"   :disabled="$isLocked" /></div>
                <div><x-wire-input label="CP"                   name="entrega_cp"       value="{{ $entregaCp }}"       :disabled="$isLocked" /></div>
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

    {{-- Cabecera de estado + Acciones --}}
    {{-- Cabecera de estado + Acciones --}}
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

            {{-- ==== Flujo logístico por ESTADO ==== --}}@if($sale->status === 'BORRADOR')
    <form method="POST" action="{{ route('admin.sales.approve',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" blue xs>Aprobar</x-wire-button>
    </form>
    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
    </form>

@elseif($sale->status === 'APROBADO')
    {{-- Aquí estaban faltando los botones --}}
    <form method="POST" action="{{ route('admin.sales.prepare',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" amber xs>Preparar</x-wire-button>
    </form>
    <form method="POST" action="{{ route('admin.sales.process',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" teal xs>Procesar</x-wire-button>
    </form>
    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
    </form>

@elseif($sale->status === 'ABIERTA')
    <form method="POST" action="{{ route('admin.sales.prepare',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" amber xs>Preparar</x-wire-button>
    </form>
    <form method="POST" action="{{ route('admin.sales.process',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" teal xs>Procesar</x-wire-button>
    </form>
    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
    </form>

@elseif($sale->status === 'PREPARANDO')
    <form method="POST" action="{{ route('admin.sales.process',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" teal xs>Procesar (descontar stock)</x-wire-button>
    </form>
    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
    </form>

@elseif($sale->status === 'PROCESADA')
    @if($sale->delivery_type === 'ENVIO')
        @if($sale->driver_id)
            <form method="POST" action="{{ route('admin.sales.en-ruta',$sale) }}" class="inline">@csrf
                <x-wire-button type="submit" violet xs>Enviar a ruta</x-wire-button>
            </form>
        @else
            <x-wire-badge class="bg-rose-100 text-rose-700">Asigna chofer para salir a ruta</x-wire-badge>
        @endif
    @else
        <form method="POST" action="{{ route('admin.sales.deliver',$sale) }}" class="inline">@csrf
            <x-wire-button type="submit" green xs>Marcar entregada</x-wire-button>
        </form>
    @endif

@elseif($sale->status === 'EN_RUTA')
    <form method="POST" action="{{ route('admin.sales.deliver',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" green xs>Entregada</x-wire-button>
    </form>
    <form method="POST" action="{{ route('admin.sales.not-delivered',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" gray xs>No entregada</x-wire-button>
    </form>
    @if($sale->tipo_venta === 'CONTRAENTREGA')
        <form method="POST" action="{{ route('admin.sales.cobrar',$sale) }}" class="inline">@csrf
            <x-wire-button type="submit" indigo xs>Cobrar contraentrega</x-wire-button>
        </form>
    @endif

@elseif($sale->status === 'ENTREGADA')
    @if($sale->tipo_venta === 'CONTRAENTREGA')
        <form method="POST" action="{{ route('admin.sales.liquidar',$sale) }}" class="inline">@csrf
            <x-wire-button type="submit" fuchsia xs>Liquidar chofer</x-wire-button>
        </form>
    @endif

@elseif($sale->status === 'NO_ENTREGADA')
    <form method="POST" action="{{ route('admin.sales.en-ruta',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" violet xs>Reprogramar a ruta</x-wire-button>
    </form>
    <form method="POST" action="{{ route('admin.sales.cancel',$sale) }}" class="inline">@csrf
        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
    </form>
@endif 
        </div>
    </div>
</x-wire-card>


    {{-- Alpine: cálculo de totales --}}
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
