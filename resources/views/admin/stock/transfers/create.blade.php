<x-admin-layout
    title="Crear transferencia"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Stock','url'=>route('admin.stock.index')],
        ['name'=>'Transferir'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">
            Regresar
        </a>
        <button form="transfer-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    @php
        $selFrom = (string) old('from_warehouse_id', (string)($prefill['from_warehouse_id'] ?? ''));
        $selTo   = (string) old('to_warehouse_id', '');
        $today   = old('fecha', now()->toDateString());

        $seedItems = old('items', []);
        if (empty($seedItems) && !empty($prefill['product_id'])) {
            $seedItems = [
                ['product_id' => (int)$prefill['product_id'], 'qty' => 1],
            ];
        }
    @endphp

    <x-wire-card>
        <form id="transfer-form" method="POST" action="{{ route('admin.stock.transfers.store') }}" class="space-y-6" x-data="transferForm()" x-init="init()">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Origen --}}
                <div class="space-y-2">
                    <label for="from_warehouse_id" class="block text-sm font-medium text-gray-700">Almacén origen</label>
                    <select name="from_warehouse_id" id="from_warehouse_id" class="w-full rounded-md border-gray-300" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selFrom===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Destino --}}
                <div class="space-y-2">
                    <label for="to_warehouse_id" class="block text-sm font-medium text-gray-700">Almacén destino</label>
                    <select name="to_warehouse_id" id="to_warehouse_id" class="w-full rounded-md border-gray-300" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selTo===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha --}}
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="date" :value="$today" required />
                </div>

                {{-- Notas --}}
                <div class="md:col-span-4">
                    <x-wire-textarea label="Notas" name="notas">{{ old('notas') }}</x-wire-textarea>
                </div>
            </div>

            {{-- Partidas --}}
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="p-2 text-left">Producto</th>
                            <th class="p-2 text-right">Cantidad</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(it, i) in items" :key="i">
                            <tr class="border-b">
                                <td class="p-2">
                                    <select class="w-full border rounded p-1"
                                            x-bind:name="'items['+i+'][product_id]'"
                                            x-model="it.product_id" required>
                                        <option value="">-- seleccionar --</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0.001" step="0.001" class="w-32 border rounded p-1 text-right"
                                           x-bind:name="'items['+i+'][qty]'"
                                           x-model.number="it.qty" required>
                                </td>
                                <td class="p-2">
                                    <button type="button" class="text-red-600" @click="remove(i)">Eliminar</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="mt-2">
                    <x-wire-button type="button" gray @click="add()">Agregar producto</x-wire-button>
                </div>
            </div>
        </form>
    </x-wire-card>

    <script>
        function transferForm(){
            const seed = @json($seedItems);
            return {
                items: (seed && seed.length) ? seed : [{product_id:'', qty:1}],
                init(){ /* no-op */ },
                add(){ this.items.push({product_id:'', qty:1}); },
                remove(i){ this.items.splice(i,1); },
            }
        }
    </script>
</x-admin-layout>
