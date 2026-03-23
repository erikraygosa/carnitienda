<div>
    {{-- Filtros --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
        <div class="md:col-span-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Buscar folio, cliente..."
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <select wire:model.live="status"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los estatus</option>
                <option value="BORRADOR">Borrador</option>
                <option value="APROBADO">Aprobado</option>
                <option value="PREPARANDO">Preparando</option>
                <option value="PROCESADO">Procesado</option>
                <option value="EN_RUTA">En ruta</option>
                <option value="ENTREGADO">Entregado</option>
                <option value="NO_ENTREGADO">No entregado</option>
                <option value="CANCELADO">Cancelado</option>
            </select>
        </div>
        <div>
            <input type="date" wire:model.live="fechaDesde"
                   placeholder="Desde"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <input type="date" wire:model.live="fechaHasta"
                   placeholder="Hasta"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    {{-- Fila inferior filtros --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <select wire:model.live="perPage"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <span class="text-xs text-gray-400">por página</span>
        </div>
        <button type="button"
                wire:click="$set('search',''); $set('status',''); $set('fechaDesde',''); $set('fechaHasta','')"
                class="text-xs text-indigo-600 hover:underline">
            Limpiar filtros
        </button>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @php
                        $th = fn($col,$label) => '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100"
                            wire:click="sort(\''.$col.'\')">'.$label.($this->sortBy===$col?($this->sortDir==='asc'?' ↑':' ↓'):'').'</th>';
                    @endphp
                    {!! $th('folio','Folio') !!}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Almacén</th>
                    {!! $th('fecha','Fecha') !!}
                    {!! $th('status','Estatus') !!}
                    {!! $th('total','Total') !!}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($orders as $order)
                @php
                    $statusClasses = [
                        'BORRADOR'     => 'bg-gray-100 text-gray-700',
                        'APROBADO'     => 'bg-blue-100 text-blue-700',
                        'PREPARANDO'   => 'bg-sky-100 text-sky-700',
                        'PROCESADO'    => 'bg-amber-100 text-amber-700',
                        'EN_RUTA'      => 'bg-violet-100 text-violet-700',
                        'ENTREGADO'    => 'bg-emerald-100 text-emerald-700',
                        'NO_ENTREGADO' => 'bg-orange-100 text-orange-700',
                        'CANCELADO'    => 'bg-rose-100 text-rose-700',
                    ];
                    $sc = $statusClasses[$order->status] ?? 'bg-gray-100 text-gray-700';
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 font-mono text-indigo-700 font-medium">{{ $order->folio }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $order->client?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $order->warehouse?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        {{ optional($order->fecha)->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full {{ $sc }}">
                            {{ $order->status_label ?? $order->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-gray-700">
                        ${{ number_format((float)$order->total, 2) }}
                    </td>
                    <td class="px-4 py-3">
                        @include('admin.sales_orders.partials.actions', ['order' => $order])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                        No se encontraron pedidos.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $orders->links() }}
    </div>
</div>