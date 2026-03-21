<x-admin-layout
    :title="'Kardex: '.$product->nombre"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Stock','url'=>route('admin.stock.index', ['warehouse_id'=>request('warehouse_id')])],
        ['name'=>'Kardex: '.$product->nombre],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.index', ['warehouse_id' => request('warehouse_id')]) }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Volver al stock
        </a>
    </x-slot>

    {{-- Info del producto --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <x-wire-card>
            <div class="text-xs text-gray-500">Producto</div>
            <div class="font-semibold text-gray-800 mt-0.5">{{ $product->nombre }}</div>
            @if($product->sku)
                <div class="text-xs text-gray-400 mt-0.5">SKU: {{ $product->sku }}</div>
            @endif
        </x-wire-card>
        <x-wire-card>
            <div class="text-xs text-gray-500">Almacén</div>
            <div class="font-semibold text-gray-800 mt-0.5">{{ $warehouse->nombre }}</div>
        </x-wire-card>
        <x-wire-card>
            <div class="text-xs text-gray-500">Existencia actual</div>
            <div class="font-semibold text-2xl mt-0.5
                {{ $existencia <= 0 ? 'text-red-600' : ($product->stock_min && $existencia <= $product->stock_min ? 'text-amber-600' : 'text-emerald-600') }}">
                {{ number_format($existencia, 3) }}
                <span class="text-sm font-normal text-gray-400">{{ $product->unidad }}</span>
            </div>
        </x-wire-card>
        <x-wire-card>
            <div class="text-xs text-gray-500">Costo promedio</div>
            <div class="font-semibold text-gray-800 mt-0.5">
                {{ $product->costo_promedio ? '$'.number_format($product->costo_promedio,2) : '—' }}
            </div>
            @if($product->stock_min)
                <div class="text-xs text-gray-400 mt-0.5">Mín: {{ number_format($product->stock_min,3) }}</div>
            @endif
        </x-wire-card>
    </div>

    {{-- Filtros del kardex --}}
    <x-wire-card class="mb-4">
        <form method="GET" class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
            <input type="hidden" name="product_id"   value="{{ $product->id }}">
            <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">

            <div>
                <label class="block text-xs text-gray-500 mb-1">Desde</label>
                <input type="date" name="desde"
                       value="{{ request('desde') }}"
                       class="w-full rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                <input type="date" name="hasta"
                       value="{{ request('hasta', now()->toDateString()) }}"
                       class="w-full rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                <select name="tipo" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Todos</option>
                    <option value="IN"  {{ request('tipo')==='IN'  ? 'selected':'' }}>Entradas</option>
                    <option value="OUT" {{ request('tipo')==='OUT' ? 'selected':'' }}>Salidas</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Filtrar
                </button>
                <a href="{{ route('admin.stock.kardex', ['product_id'=>$product->id,'warehouse_id'=>$warehouse->id]) }}"
                   class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </x-wire-card>

    {{-- Tabla de movimientos --}}
    <x-wire-card>
        <div class="overflow-auto rounded-lg border">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="p-3 text-left font-medium text-gray-600">Fecha</th>
                        <th class="p-3 text-center font-medium text-gray-600">Tipo</th>
                        <th class="p-3 text-right font-medium text-gray-600">Entrada</th>
                        <th class="p-3 text-right font-medium text-gray-600">Salida</th>
                        <th class="p-3 text-right font-medium text-gray-600">Saldo</th>
                        <th class="p-3 text-left font-medium text-gray-600">Motivo</th>
                        <th class="p-3 text-left font-medium text-gray-600">Referencia</th>
                        <th class="p-3 text-left font-medium text-gray-600">Usuario</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($movimientos as $mov)
                        <tr class="hover:bg-gray-50 {{ $mov->tipo === 'IN' ? 'bg-emerald-50/30' : '' }}">
                            <td class="p-3 text-gray-500 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($mov->created_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $mov->tipo === 'IN'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-red-100 text-red-700' }}">
                                    {{ $mov->tipo === 'IN' ? 'ENTRADA' : 'SALIDA' }}
                                </span>
                            </td>
                            <td class="p-3 text-right font-mono">
                                @if($mov->tipo === 'IN')
                                    <span class="text-emerald-700">+{{ number_format($mov->cantidad, 3) }}</span>
                                @else
                                    <span class="text-gray-200">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono">
                                @if($mov->tipo === 'OUT')
                                    <span class="text-red-700">-{{ number_format($mov->cantidad, 3) }}</span>
                                @else
                                    <span class="text-gray-200">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono font-semibold
                                {{ $mov->saldo_acumulado <= 0 ? 'text-red-600' : 'text-gray-800' }}">
                                {{ number_format($mov->saldo_acumulado, 3) }}
                            </td>
                            <td class="p-3 text-gray-600 text-xs">
                                {{ $mov->motivo ?? '—' }}
                            </td>
                            <td class="p-3 text-xs">
                                @if($mov->referencia_type && $mov->referencia_id)
                                    @php
                                        $label = match(class_basename($mov->referencia_type)) {
                                            'SalesOrder'      => 'Pedido',
                                            'Sale'            => 'Venta',
                                            'PurchaseOrder'   => 'Compra',
                                            'StockAdjustment' => 'Ajuste',
                                            'StockTransfer'   => 'Transferencia',
                                            default           => class_basename($mov->referencia_type),
                                        };
                                    @endphp
                                    <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">
                                        {{ $label }} #{{ $mov->referencia_id }}
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-gray-500 text-xs">
                                {{ $mov->user?->name ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-6 text-center text-gray-400">
                                Sin movimientos para este filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($movimientos->count())
                <tfoot class="border-t bg-gray-50">
                    <tr>
                        <td colspan="2" class="p-3 text-sm font-medium text-right text-gray-600">
                            Totales:
                        </td>
                        <td class="p-3 text-right font-semibold text-emerald-700 font-mono">
                            +{{ number_format($movimientos->where('tipo','IN')->sum('cantidad'), 3) }}
                        </td>
                        <td class="p-3 text-right font-semibold text-red-700 font-mono">
                            -{{ number_format($movimientos->where('tipo','OUT')->sum('cantidad'), 3) }}
                        </td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        <div class="mt-4">
            {{ $movimientos->links() }}
        </div>
    </x-wire-card>
</x-admin-layout>