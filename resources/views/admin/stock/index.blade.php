<x-admin-layout
    title="Stock por almacén"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Stock'],
    ]"
>
    <x-wire-card>
        <form method="GET" action="{{ route('admin.stock.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="space-y-2 w-full md:col-span-2">
                <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Almacén</label>
                @php $selWarehouse = (string) request('warehouse_id'); @endphp
                <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300" required>
                    <option value="">-- seleccionar --</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>
                            {{ $w->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-2 w-full">
                <label for="product_id" class="block text-sm font-medium text-gray-700">Producto</label>
                @php $selProduct = (string) request('product_id'); @endphp
                <select name="product_id" id="product_id" class="w-full rounded-md border-gray-300">
                    <option value="">-- todos --</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ $selProduct===(string)$p->id ? 'selected' : '' }}>
                            {{ $p->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Filtrar</button>
            </div>
        </form>
    </x-wire-card>

    <x-wire-card class="mt-4">
        @php
            $warehouseId = request('warehouse_id');
            $productId   = request('product_id');
        @endphp

        @if($warehouseId)
            @livewire('admin.datatables.stock-table', [
                'warehouseId' => (int)$warehouseId,
                'productId'   => $productId ? (int)$productId : null
            ], key('stock-'.$warehouseId.'-'.$productId))
        @else
            <p class="text-sm text-gray-600">Selecciona un almacén para ver existencias.</p>
        @endif
    </x-wire-card>
</x-admin-layout>
