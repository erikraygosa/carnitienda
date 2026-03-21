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
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            + Nuevo traspaso
        </a>
    </x-slot>

    <x-wire-card>
        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="p-2 text-left">Folio</th>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Origen</th>
                        <th class="p-2 text-left">Destino</th>
                        <th class="p-2 text-left">Estatus</th>
                        <th class="p-2 text-left">Despacho</th>
                        <th class="p-2 w-24"></th>
                    </tr>
                </thead>
                <tbody>
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
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-2 font-mono text-indigo-600 font-medium">
                                <a href="{{ route('admin.stock.transfers.show', $t) }}">{{ $t->folio }}</a>
                            </td>
                            <td class="p-2 text-gray-600">{{ $t->fecha->format('d/m/Y') }}</td>
                            <td class="p-2">{{ $t->fromWarehouse?->nombre ?? '—' }}</td>
                            <td class="p-2">{{ $t->toWarehouse?->nombre ?? '—' }}</td>
                            <td class="p-2">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $sc }}">
                                    {{ $t->status_label }}
                                </span>
                            </td>
                            <td class="p-2 text-gray-500 text-xs">
                                @if($t->dispatch_id)
                                    <a href="{{ route('admin.dispatches.edit', $t->dispatch_id) }}"
                                       class="text-indigo-600 hover:underline">
                                        #{{ $t->dispatch_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="p-2 flex gap-2">
                                <a href="{{ route('admin.stock.transfers.show', $t) }}"
                                   class="text-xs text-indigo-600 hover:underline">Ver</a>
                                <a href="{{ route('admin.stock.transfers.print', $t) }}"
                                   target="_blank"
                                   class="text-xs text-gray-500 hover:underline">🖨</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-gray-400">No hay traspasos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transfers->links() }}</div>
    </x-wire-card>
</x-admin-layout>