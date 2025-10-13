<x-admin-layout
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Órdenes de compra','url'=>route('admin.purchase-orders.index')],
        ['name'=>'Crear'],
    ]"
    title="Crear orden de compra"
>
    <x-slot name="action">
        <a href="{{ route('admin.purchase-orders.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Cancelar</a>
        <button form="po-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    <x-wire-card>
        <form id="po-form" method="POST" action="{{ route('admin.purchase-orders.store') }}" class="space-y-6" x-data="poForm()">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <x-wire-select label="Proveedor" name="provider_id"
                        :options="$providers->map(fn($p)=>['id'=>$p->id,'name'=>$p->nombre])"
                        :option-label="'name'" :option-value="'id'" required />
                </div>
                <div>
                    <x-wire-select label="Almacén" name="warehouse_id"
                        :options="$warehouses->map(fn($w)=>['id'=>$w->id,'name'=>$w->nombre])"
                        :option-label="'name'" :option-value="'id'" required />
                </div>
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="date" :value="now()->toDateString()" required />
                </div>
                <div>
                    <x-wire-input label="Entrega estimada" name="expected_at" type="date" />
                </div>
                <div>
                    <x-wire-input label="Moneda" name="currency" :value="old('currency','MXN')" />
                </div>
                <div class="md:col-span-4">
                    <x-wire-textarea label="Observaciones" name="observaciones">{{ old('observaciones') }}</x-wire-textarea>
                </div>
            </div>

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
                                    <select class="w-full border rounded p-1" :name="`items[${i}][product_id]`" x-model="it.product_id" required>
                                        <option value="">-- seleccionar --</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2 text-right"><input type="number" min="0.001" step="0.001" class="w-28 border rounded p-1 text-right" :name="`items[${i}][qty_ordered]`" x-model.number="it.qty" @input="recalc(i)" required></td>
                                <td class="p-2 text-right"><input type="number" min="0" step="0.01" class="w-28 border rounded p-1 text-right" :name="`items[${i}][price]`" x-model.number="it.price" @input="recalc(i)" required></td>
                                <td class="p-2 text-right"><input type="number" min="0" step="0.01" class="w-24 border rounded p-1 text-right" :name="`items[${i}][discount]`" x-model.number="it.discount" @input="recalc(i)"></td>
                                <td class="p-2 text-right"><input type="number" min="0" step="0.01" class="w-20 border rounded p-1 text-right" :name="`items[${i}][tax_rate]`" x-model.number="it.tax_rate" @input="recalc(i)"></td>
                                <td class="p-2 text-right" x-text="fmt(it.total)"></td>
                                <td class="p-2"><button type="button" class="text-red-600" @click="remove(i)">Eliminar</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="mt-2">
                    <x-wire-button type="button" gray @click="add()">Agregar partida</x-wire-button>
                </div>
            </div>

            <div class="text-right space-y-1">
                <div>Subtotal: <span x-text="fmt(subtotal)"></span></div>
                <div>Descuento: <span x-text="fmt(discount_total)"></span></div>
                <div>Impuestos: <span x-text="fmt(tax_total)"></span></div>
                <div class="font-semibold text-lg">Total: <span x-text="fmt(grand)"></span></div>
            </div>
        </form>
    </x-wire-card>

    <script>
        function poForm(){
            return {
                items: [{product_id:'', qty:1, price:0, discount:0, tax_rate:0, total:0}],
                subtotal:0, discount_total:0, tax_total:0, grand:0,
                add(){ this.items.push({product_id:'', qty:1, price:0, discount:0, tax_rate:0, total:0}); },
                remove(i){ this.items.splice(i,1); this.sum(); },
                recalc(i){
                    const it = this.items[i];
                    const line_sub = (it.qty||0)*(it.price||0);
                    const disc = it.discount||0;
                    const base = Math.max(line_sub - disc, 0);
                    const tax = (it.tax_rate||0)*0.01*base;
                    it.total = base + tax;
                    this.sum();
                },
                sum(){
                    let s=0,d=0,t=0,g=0;
                    this.items.forEach(it=>{
                        const line_sub=(it.qty||0)*(it.price||0);
                        const disc=it.discount||0;
                        const base=Math.max(line_sub-disc,0);
                        const tax=(it.tax_rate||0)*0.01*base;
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
