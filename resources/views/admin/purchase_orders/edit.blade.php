<x-admin-layout
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Órdenes de compra','url'=>route('admin.purchase-orders.index')],
        ['name'=>'Editar'],
    ]"
    title="Editar orden de compra"
>
    <x-slot name="action">
        <a href="{{ route('admin.purchase-orders.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">
            Regresar
        </a>
        @if($order->status === 'draft')
            <button form="po-edit-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
        @if($order->status === 'approved')
            <x-wire-button href="{{ route('admin.purchases.create', ['purchase_order_id'=>$order->id]) }}" violet>
                Crear compra
            </x-wire-button>
        @endif
    </x-slot>

    @php
        // Selects (nativos) con old()
        $selProvider   = (string) old('provider_id',  $order->provider_id);
        $selWarehouse  = (string) old('warehouse_id', $order->warehouse_id);
        $isLocked      = $order->status !== 'draft';

        $valueFecha     = old('fecha',      optional($order->fecha)->toDateString());
        $valueExpected  = old('expected_at', optional($order->expected_at)->toDateString());
        $valueCurrency  = old('currency',   $order->currency);
        $valueObs       = old('observaciones', $order->observaciones);

        // Mapeo de estatus a español
        $statusMap = [
            'draft'               => 'Borrador',
            'approved'            => 'Aprobada',
            'partially_received'  => 'Parcialmente recibida',
            'closed'              => 'Cerrada',
            'cancelled'           => 'Cancelada',
        ];
        $statusLabel = $statusMap[$order->status] ?? strtoupper($order->status);

        $statusClasses = [
            'draft'               => 'bg-gray-100 text-gray-700',
            'approved'            => 'bg-emerald-100 text-emerald-700',
            'partially_received'  => 'bg-amber-100 text-amber-700',
            'closed'              => 'bg-indigo-100 text-indigo-700',
            'cancelled'           => 'bg-rose-100 text-rose-700',
        ];
        $statusClass = $statusClasses[$order->status] ?? 'bg-slate-100 text-slate-700';

        // Seed para Alpine (partidas)
        $itemsSeed = $order->items->map(function ($i) {
            return [
                'product_id' => $i->product_id,
                'qty'        => (float) $i->qty_ordered,
                'price'      => (float) $i->price,
                'discount'   => (float) $i->discount,
                'tax_rate'   => (float) $i->tax_rate,
                'total'      => (float) $i->total,
            ];
        })->values()->toArray();
        $lockedFlag = $isLocked;
    @endphp

    <x-wire-card>
        {{-- 🔹 x-init="init()" para calcular totales al montar --}}
        <form id="po-edit-form"
              method="POST"
              action="{{ route('admin.purchase-orders.update',$order) }}"
              class="space-y-6"
              x-data="poFormEdit()"
              x-init="init()">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Proveedor --}}
                <div class="md:col-span-2 space-y-2 w-full">
                    <label for="provider_id" class="block text-sm font-medium text-gray-700">Proveedor</label>
                    <select
                        name="provider_id"
                        id="provider_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        {{ $isLocked ? 'disabled' : '' }}
                        required
                    >
                        <option value="">-- seleccionar --</option>
                        @foreach($providers as $p)
                            <option value="{{ $p->id }}" {{ $selProvider === (string)$p->id ? 'selected' : '' }}>
                                {{ $p->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Almacén --}}
                <div class="space-y-2 w-full">
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Almacén</label>
                    <select
                        name="warehouse_id"
                        id="warehouse_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        {{ $isLocked ? 'disabled' : '' }}
                        required
                    >
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse === (string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha --}}
                <div>
                    <x-wire-input
                        label="Fecha"
                        name="fecha"
                        type="date"
                        :value="$valueFecha"
                        :disabled="$isLocked"
                        required
                    />
                </div>

                {{-- Entrega estimada --}}
                <div>
                    <x-wire-input
                        label="Entrega estimada"
                        name="expected_at"
                        type="date"
                        :value="$valueExpected"
                        :disabled="$isLocked"
                    />
                </div>

                {{-- Moneda --}}
                <div>
                    <x-wire-input
                        label="Moneda"
                        name="currency"
                        :value="$valueCurrency"
                        :disabled="$isLocked"
                        required
                    />
                </div>

                {{-- Observaciones --}}
                <div class="md:col-span-4">
                    <x-wire-textarea label="Observaciones" name="observaciones" :disabled="$isLocked">{{ $valueObs }}</x-wire-textarea>
                </div>
            </div>

            {{-- Partidas --}}
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="text-left p-2">Producto</th>
                            <th class="text-right p-2">Cant.</th>
                            <th class="text-right p-2">Precio</th>
                            <th class="text-right p-2">Desc.</th>
                            <th class="text-right p-2">% IVA</th>
                            <th class="text-right p-2">Total</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(it, i) in items" :key="i">
                            <tr class="border-b">
                                <td class="p-2">
                                    <select class="w-full border rounded p-1"
                                            x-bind:name="'items[' + i + '][product_id]'"
                                            x-model="it.product_id"
                                            :disabled="locked" required>
                                        <option value="">-- seleccionar --</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="p-2 text-right">
                                    <input type="number" min="0.001" step="0.001" class="w-28 border rounded p-1 text-right"
                                           x-bind:name="'items[' + i + '][qty_ordered]'"
                                           x-model.number="it.qty"
                                           @input="recalc(i)" :disabled="locked" required>
                                </td>

                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-28 border rounded p-1 text-right"
                                           x-bind:name="'items[' + i + '][price]'"
                                           x-model.number="it.price"
                                           @input="recalc(i)" :disabled="locked" required>
                                </td>

                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-24 border rounded p-1 text-right"
                                           x-bind:name="'items[' + i + '][discount]'"
                                           x-model.number="it.discount"
                                           @input="recalc(i)" :disabled="locked">
                                </td>

                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01" class="w-20 border rounded p-1 text-right"
                                           x-bind:name="'items[' + i + '][tax_rate]'"
                                           x-model.number="it.tax_rate"
                                           @input="recalc(i)" :disabled="locked">
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
                <div>Descuento: <span x-text="fmt(discount_total)"></span></div>
                <div>Impuestos: <span x-text="fmt(tax_total)"></span></div>
                <div class="font-semibold text-lg">Total: <span x-text="fmt(grand)"></span></div>
            </div>
        </form>
    </x-wire-card>

    {{-- Cabecera de estado y acciones --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2">
            <x-wire-badge>Folio: {{ $order->folio }}</x-wire-badge>
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                Estatus: {{ $statusLabel }}
            </span>

            <div class="ml-auto flex items-center gap-2">
                @if($order->status === 'draft')
                    <form method="POST" action="{{ route('admin.purchase-orders.approve',$order) }}">
                        @csrf
                        <x-wire-button type="submit" green> Aprobar </x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.purchase-orders.cancel',$order) }}">
                        @csrf
                        <x-wire-button type="submit" red> Cancelar </x-wire-button>
                    </form>
                @endif
                @if($order->status === 'approved')
                    <x-wire-button href="{{ route('admin.purchases.create', ['purchase_order_id'=>$order->id]) }}" violet>
                        Crear compra
                    </x-wire-button>
                @endif
            </div>
        </div>
    </x-wire-card>

    <script>
        function poFormEdit(){
            const seed   = @json($itemsSeed);
            const locked = @json($lockedFlag);

            return {
                items: (seed && seed.length) ? seed : [{product_id:'',qty:1,price:0,discount:0,tax_rate:0,total:0}],
                locked,
                subtotal: 0,
                discount_total: 0,
                tax_total: 0,
                grand: 0,

                // 🔹 Se ejecuta al montar el componente
                init(){ this.sum(); },

                add(){
                    if(this.locked) return;
                    this.items.push({product_id:'',qty:1,price:0,discount:0,tax_rate:0,total:0});
                },
                remove(i){
                    if(this.locked) return;
                    this.items.splice(i,1);
                    this.sum();
                },
                recalc(i){
                    const it = this.items[i];
                    const line_sub = (+it.qty || 0) * (+it.price || 0);
                    const disc     = +it.discount || 0;
                    const base     = Math.max(line_sub - disc, 0);
                    const tax      = ((+it.tax_rate || 0) * 0.01) * base;
                    it.total = base + tax;
                    this.sum();
                },
                sum(){
                    let s=0,d=0,t=0,g=0;
                    this.items.forEach(it=>{
                        const line_sub=(+it.qty||0)*(+it.price||0);
                        const disc=+it.discount||0;
                        const base=Math.max(line_sub-disc,0);
                        const tax=((+it.tax_rate||0)*0.01)*base;
                        const tot=base+tax;
                        s+=line_sub; d+=disc; t+=tax; g+=tot;
                    });
                    this.subtotal=s; this.discount_total=d; this.tax_total=t; this.grand=g;
                },
                fmt(n){ return Number(n||0).toFixed(2); }
            }
        }
    </script>
</x-admin-layout>
