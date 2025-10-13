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
                                @change="onProductChange(i, $event)"
                                :disabled="locked">
                            <option value="">—</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre }}</option>
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
