<x-admin-layout
    title="Traspasos de almacén"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Stock','url'=>route('admin.stock.index')],
        ['name'=>'Traspasos'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.transfers.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            + Nuevo traspaso
        </a>
    </x-slot>

    {{-- Filtros --}}
    <x-wire-card>
        <form method="GET" action="{{ route('admin.stock.transfers.index') }}"
              class="flex flex-wrap gap-3 items-end">

            <input type="text" name="search"
                value="{{ request('search') }}"
                placeholder="Buscar folio..."
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            />

            <select name="status"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Todos los estatus</option>
                @foreach(['PENDIENTE','ASIGNADO','EN_RUTA','COMPLETADO','CANCELADO'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                        {{ ucfirst(strtolower(str_replace('_',' ',$s))) }}
                    </option>
                @endforeach
            </select>

            <select name="from_warehouse"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Origen (todos)</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}" {{ request('from_warehouse') == $w->id ? 'selected' : '' }}>
                        {{ $w->nombre }}
                    </option>
                @endforeach
            </select>

            <select name="to_warehouse"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Destino (todos)</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}" {{ request('to_warehouse') == $w->id ? 'selected' : '' }}>
                        {{ $w->nombre }}
                    </option>
                @endforeach
            </select>

            <button type="submit"
                class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('admin.stock.transfers.index') }}"
               class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                Limpiar
            </a>
        </form>
    </x-wire-card>

    {{-- Tabla --}}
    <x-wire-card class="mt-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Folio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Fecha</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Origen</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Destino</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Estatus</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Despacho</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($transfers as $t)
                        @php
                            $sc = [
                                'PENDIENTE'  => 'bg-gray-100 text-gray-700',
                                'ASIGNADO'   => 'bg-sky-100 text-sky-700',
                                'EN_RUTA'    => 'bg-violet-100 text-violet-700',
                                'COMPLETADO' => 'bg-emerald-100 text-emerald-700',
                                'CANCELADO'  => 'bg-rose-100 text-rose-700',
                            ][$t->status] ?? 'bg-slate-100 text-slate-700';
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-3 font-mono text-indigo-600 font-medium">
                                <a href="{{ route('admin.stock.transfers.show', $t) }}" class="hover:underline">
                                    {{ $t->folio }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ $t->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $t->fromWarehouse?->nombre ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $t->toWarehouse?->nombre ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $sc }}">
                                    {{ $t->status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                @if($t->dispatch_id)
                                    <a href="{{ route('admin.dispatches.edit', $t->dispatch_id) }}"
                                       class="text-indigo-600 hover:underline">
                                        #{{ $t->dispatch_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.stock.transfers.show', $t) }}"
                                       class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                                        Ver
                                    </a>
                                    <a href="{{ route('admin.stock.transfers.print', $t) }}"
                                       target="_blank"
                                       class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50">
                                        🖨 Imprimir
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                No hay traspasos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación Laravel --}}
        @if($transfers->hasPages())
            <div class="mt-4 flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>
                    Mostrando {{ $transfers->firstItem() }}–{{ $transfers->lastItem() }}
                    de {{ $transfers->total() }} traspasos
                </span>
                {{ $transfers->links() }}
            </div>
        @endif
    </x-wire-card>

</x-admin-layout>