<x-admin-layout
    title="Precios por almacén"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Inventario'],
        ['name'=>'Precios por almacén'],
    ]"
>
    @if(! $modoAlmacen)
    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>El sistema está en modo "precios globales".</strong> Los precios que guardes aquí quedan
        listos, pero el Punto de Venta seguirá usando el precio de la ficha del producto (igual en todos
        los almacenes) hasta que un superadmin active "Precios por almacén" en Configuración.
    </div>
    @endif

    <x-wire-card class="mb-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('admin.precios.index') }}" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id" id="warehouse_id" class="w-56 rounded-md border-gray-300 text-sm">
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (string)$warehouseId === (string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}{{ $w->is_primary ? ' (Matriz)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="SKU o producto..."
                           class="w-56 rounded-md border-gray-300 text-sm">
                </div>
                <button type="submit" class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Filtrar
                </button>
                <a href="{{ route('admin.precios.index', ['warehouse_id' => $warehouseId]) }}"
                   class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                    Limpiar
                </a>
            </form>

            @can('aplicar precios matriz')
            <form method="POST" action="{{ route('admin.precios.aplicar-matriz') }}"
                  onsubmit="return confirm('Esto copia los precios de {{ $matriz?->nombre ?? 'Matriz' }} a los demás almacenes, SOLO donde ese almacén no tenga ya un precio propio configurado. ¿Continuar?');">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md border border-indigo-300 text-indigo-700 hover:bg-indigo-50">
                    <i class="fa-solid fa-arrows-turn-right"></i>
                    Aplicar precios de {{ $matriz?->nombre ?? 'Matriz' }} a los demás almacenes
                </button>
            </form>
            @endcan
        </div>
    </x-wire-card>

    @can('editar precios almacen')
    <form method="POST" action="{{ route('admin.precios.store') }}">
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
    @endcan

        <x-wire-card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">SKU</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Producto</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase text-xs">Precio ficha (respaldo)</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase text-xs">Precio en este almacén</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($products as $p)
                        @php $override = $overrides[$p->id] ?? null; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $p->sku ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-800 font-medium">{{ $p->nombre }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500">
                                ${{ number_format($p->precio_base, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('editar precios almacen')
                                <input type="number" step="0.01" min="0"
                                       name="precios[{{ $p->id }}]"
                                       value="{{ $override !== null ? number_format((float)$override, 2, '.', '') : '' }}"
                                       placeholder="{{ number_format($p->precio_base, 2) }}"
                                       class="w-32 text-right rounded-md border-gray-300 text-sm {{ $override !== null ? 'border-indigo-300 bg-indigo-50 font-semibold' : '' }}">
                                @else
                                    <span class="{{ $override !== null ? 'font-semibold text-indigo-700' : 'text-gray-400' }}">
                                        {{ $override !== null ? '$'.number_format((float)$override, 2) : 'usa precio de ficha' }}
                                    </span>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No hay productos que coincidan.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-wire-card>

    @can('editar precios almacen')
        <div class="mt-4 flex justify-end">
            <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                Guardar precios de este almacén
            </button>
        </div>
    </form>
    @endcan

<script>
document.getElementById('warehouse_id').addEventListener('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('warehouse_id', this.value);
    window.location.href = url.toString();
});
</script>
</x-admin-layout>
