<x-admin-layout
    title="Cajas"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'POS'],
        ['name'=>'Cajas'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.cash.create') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">
            Abrir caja
        </a>
    </x-slot>

    <x-wire-card>
       <form method="GET" action="{{ route('admin.cash.index') }}" class="flex gap-2 mb-4">
    <input type="text" name="search" value="{{ request('search') }}"
        placeholder="Buscar por fecha (YYYY-MM-DD)..."
        class="w-full md:w-80 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
    />
    <button type="submit"
        class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
        Buscar
    </button>
    @if(request('search'))
        <a href="{{ route('admin.cash.index') }}"
           class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Limpiar
        </a>
    @endif
</form>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Fecha</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Almacén</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Usuario</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Cierre</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Estatus</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($registers as $r)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-700">{{ $r->fecha->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $r->warehouse?->nombre ?? 'N/D' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $r->user?->name ?? 'N/D' }}</td>
                        <td class="px-4 py-3 text-right font-mono text-gray-700">${{ number_format($r->monto_cierre, 2) }}</td>
                        <td class="px-4 py-3">
                            @if($r->estatus === 'ABIERTO')
                                <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Abierto</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Cerrado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.cash.show', $r) }}"
                               class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                Ver
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">Sin datos</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación Laravel --}}
        @if($registers->hasPages())
            <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
                <span>
                    Mostrando {{ $registers->firstItem() }}–{{ $registers->lastItem() }}
                    de {{ $registers->total() }} cajas
                </span>
                {{ $registers->links() }}
            </div>
        @endif
    </x-wire-card>

</x-admin-layout>