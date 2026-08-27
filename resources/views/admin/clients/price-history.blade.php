<x-admin-layout
    title="Historial de precios de clientes"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Clientes','url'=>route('admin.clients.index')],
        ['name'=>'Historial de precios'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.clients.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">Volver</a>
    </x-slot>

    {{-- Filtros --}}
    <x-wire-card class="mb-4">
        <form method="GET" action="{{ route('admin.clients.price-history') }}"
              class="flex flex-wrap gap-3 items-end">

            <div>
                <label class="block text-xs text-gray-500 mb-1">Cliente</label>
                <input type="text" name="cliente" value="{{ request('cliente') }}" placeholder="Nombre del cliente..."
                       class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Producto</label>
                <input type="text" name="producto" value="{{ request('producto') }}" placeholder="Nombre del producto..."
                       class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Usuario</label>
                <select name="user_id"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}"
                       class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}"
                       class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Filtrar
                </button>
                <a href="{{ route('admin.clients.price-history') }}"
                   class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </x-wire-card>

    <x-wire-card>
        @if($logs->isEmpty())
            <p class="py-10 text-center text-gray-400 text-sm">No hay modificaciones de precios registradas.</p>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha/hora</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Precio anterior</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Precio nuevo</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($logs as $log)
                    @php
                        $producto  = $log->changes['producto'] ?? '—';
                        $clienteNombre = $clientes[$log->document_id] ?? "Cliente #{$log->document_id}";
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-gray-500 whitespace-nowrap text-xs">
                            {{ $log->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-3 py-2 text-gray-800 whitespace-nowrap">
                            <a href="{{ route('admin.clients.edit', $log->document_id) }}" class="text-indigo-600 hover:text-indigo-800">
                                {{ $clienteNombre }}
                            </a>
                        </td>
                        <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $producto }}</td>
                        <td class="px-3 py-2 text-right text-rose-600 whitespace-nowrap">
                            {{ $log->old_status !== null ? '$'.$log->old_status : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right text-emerald-600 font-medium whitespace-nowrap">
                            {{ $log->new_status !== null ? '$'.$log->new_status : '—' }}
                        </td>
                        <td class="px-3 py-2 text-gray-700 whitespace-nowrap">
                            {{ $log->user?->name ?? '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
        @endif
    </x-wire-card>
</x-admin-layout>
