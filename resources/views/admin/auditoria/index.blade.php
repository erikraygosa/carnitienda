<x-admin-layout
    title="Auditoría"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Auditoría'],
    ]"
>
    {{-- Filtros --}}
    <x-wire-card class="mb-4">
        <form method="GET" action="{{ route('admin.auditoria.index') }}"
              class="flex flex-wrap gap-3 items-end">

            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                <select name="tipo"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="producto"   @selected(request('tipo')=='producto')>Producto</option>
                    <option value="pedido"     @selected(request('tipo')=='pedido')>Pedido</option>
                    <option value="factura"    @selected(request('tipo')=='factura')>Factura</option>
                    <option value="movimiento" @selected(request('tipo')=='movimiento')>Movimiento stock</option>
                    <option value="traspaso"   @selected(request('tipo')=='traspaso')>Traspaso</option>
                    <option value="inventario" @selected(request('tipo')=='inventario')>Inventario</option>
                </select>
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
                <label class="block text-xs text-gray-500 mb-1">Acción</label>
                <select name="action"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    <option value="CREATED"       @selected(request('action')=='CREATED')>Creado</option>
                    <option value="UPDATED"       @selected(request('action')=='UPDATED')>Editado</option>
                    <option value="STATUS_CHANGED" @selected(request('action')=='STATUS_CHANGED')>Cambio de estatus</option>
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
                <a href="{{ route('admin.auditoria.index') }}"
                   class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </x-wire-card>

    <x-wire-card>
        @if($logs->isEmpty())
            <p class="py-10 text-center text-gray-400 text-sm">No hay registros de auditoría.</p>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha/hora</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estatus</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Campos modificados</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($logs as $log)
                    @php
                        $tipoLabel = $tiposInvertido[$log->document_type] ?? class_basename($log->document_type);
                    @endphp
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-3 py-2 text-gray-500 whitespace-nowrap text-xs">
                            {{ $log->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-3 py-2 text-gray-700 whitespace-nowrap">
                            {{ $log->user?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 capitalize">
                                {{ $tipoLabel }}
                            </span>
                        </td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">
                            {{ $log->document_id }}
                            @if($log->nota)
                                <p class="text-gray-400 text-xs mt-0.5 italic">{{ $log->nota }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if($log->action === 'CREATED')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                    Creado
                                </span>
                            @elseif($log->action === 'UPDATED')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                    Editado
                                </span>
                            @elseif($log->action === 'STATUS_CHANGED')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                    Cambio estatus
                                </span>
                            @else
                                <span class="text-xs text-gray-500">{{ $log->action }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">
                            @if($log->old_status || $log->new_status)
                                <span class="text-gray-400">{{ $log->old_status ?? '—' }}</span>
                                <span class="mx-1 text-gray-400">→</span>
                                <span class="font-medium">{{ $log->new_status ?? '—' }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if($log->changes)
                                <details class="text-xs">
                                    <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 select-none">
                                        {{ count($log->changes) }} campo(s)
                                    </summary>
                                    <table class="mt-1 min-w-[300px] divide-y divide-gray-100 border border-gray-200 rounded">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-2 py-1 text-left text-xs text-gray-500">Campo</th>
                                                <th class="px-2 py-1 text-left text-xs text-gray-500">Antes</th>
                                                <th class="px-2 py-1 text-left text-xs text-gray-500">Después</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach($log->changes as $campo => $diff)
                                            <tr>
                                                <td class="px-2 py-1 font-mono text-gray-600">{{ $campo }}</td>
                                                <td class="px-2 py-1 text-rose-600 max-w-[150px] truncate" title="{{ $diff['old'] }}">
                                                    {{ $diff['old'] ?? '—' }}
                                                </td>
                                                <td class="px-2 py-1 text-emerald-600 max-w-[150px] truncate" title="{{ $diff['new'] }}">
                                                    {{ $diff['new'] ?? '—' }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </details>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
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
