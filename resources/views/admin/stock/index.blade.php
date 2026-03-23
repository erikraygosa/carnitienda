<x-admin-layout
    title="Stock por almacén"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Inventario'],
        ['name'=>'Stock'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.adjustments.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Ajuste manual
        </a>
        <a href="{{ route('admin.stock.transfers.create') }}"
           class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Transferencia
        </a>
    </x-slot>

    {{-- Filtros --}}
    <x-wire-card>
        <form method="GET" action="{{ route('admin.stock.index') }}"
              class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                @php $selWarehouse = (string) request('warehouse_id', $mainWarehouseId ?? ''); @endphp
                <select name="warehouse_id" class="w-full rounded-md border-gray-300 text-sm" required>
                    <option value="">-- seleccionar --</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>
                            {{ $w->nombre }}{{ isset($w->is_primary) && $w->is_primary ? ' (principal)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Producto</label>
                @php $selProduct = (string) request('product_id'); @endphp
                <select name="product_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">-- todos --</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ $selProduct===(string)$p->id ? 'selected' : '' }}>
                            {{ $p->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 inline-flex justify-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Filtrar
                </button>
                <a href="{{ route('admin.stock.index') }}"
                   class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </x-wire-card>

    {{-- Tabla --}}
    <x-wire-card class="mt-4">
        @php
            $warehouseId = request('warehouse_id', $mainWarehouseId ?? null);
            $productId   = request('product_id');
        @endphp

        @if($warehouseId)
            @livewire('admin.datatables.stock-table', [
                'warehouseId' => (int) $warehouseId,
                'productId'   => $productId ? (int) $productId : null,
            ], key('stock-'.$warehouseId.'-'.$productId))
        @else
            <p class="text-sm text-gray-500 py-4 text-center">
                Selecciona un almacén para ver existencias.
            </p>
        @endif
    </x-wire-card>

</x-admin-layout>
