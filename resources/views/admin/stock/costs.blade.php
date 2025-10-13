<x-admin-layout
    title="Costos de producto"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Stock','url'=>route('admin.stock.index')],
        ['name'=>'Costos'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.index', ['warehouse_id'=>optional($warehouse)->id, 'product_id'=>optional($product)->id]) }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">
            Regresar
        </a>
    </x-slot>

    <x-wire-card>
        <div class="mb-4 space-y-1">
            <div><span class="font-semibold">Almacén:</span> {{ $warehouse->nombre }}</div>
            <div><span class="font-semibold">Producto:</span> {{ $product->nombre }}</div>
        </div>

        @if($purchases->isEmpty())
            <p class="text-sm text-gray-600">Sin compras recibidas para este producto en este almacén.</p>
        @else
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="p-2 text-left">Fecha</th>
                            <th class="p-2 text-left">Proveedor</th>
                            <th class="p-2 text-right">Recibido</th>
                            <th class="p-2 text-right">Precio</th>
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2 text-left">Folio compra</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchases as $pi)
                            <tr class="border-b">
                                <td class="p-2">{{ optional($pi->purchase->fecha)->toDateString() }}</td>
                                <td class="p-2">{{ optional($pi->purchase->provider)->nombre ?? '—' }}</td>
                                <td class="p-2 text-right">{{ number_format((float)$pi->qty_received,3) }}</td>
                                <td class="p-2 text-right">${{ number_format((float)$pi->price,2) }}</td>
                                <td class="p-2 text-right">${{ number_format((float)$pi->total,2) }}</td>
                                <td class="p-2">{{ $pi->purchase->folio ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @php
                $last = $purchases->first();
                $lastCost = $last ? (float)$last->price : 0;
                $avgCost = (float) $purchases->avg('price');
            @endphp

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-3 rounded-lg bg-gray-50">
                    <div class="text-xs text-gray-500">Último costo</div>
                    <div class="text-lg font-semibold">${{ number_format($lastCost,2) }}</div>
                </div>
                <div class="p-3 rounded-lg bg-gray-50">
                    <div class="text-xs text-gray-500">Costo promedio (últ. {{ $purchases->count() }})</div>
                    <div class="text-lg font-semibold">${{ number_format($avgCost,2) }}</div>
                </div>
                <div class="p-3 rounded-lg bg-gray-50">
                    <div class="text-xs text-gray-500">Proveedor último</div>
                    <div class="text-sm">{{ optional(optional($last)->purchase->provider)->nombre ?? '—' }}</div>
                </div>
            </div>
        @endif
    </x-wire-card>
</x-admin-layout>
