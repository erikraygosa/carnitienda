<x-admin-layout
    title="Gestión de notas"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Gestión de notas'],
    ]"
>
    <x-wire-card>
        <form method="GET" action="{{ route('admin.gestion-notas.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-4">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Folio / Cliente</label>
                <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Buscar..."
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                <select name="tipo" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="todos"  {{ $filtros['tipo'] === 'todos'  ? 'selected' : '' }}>Todos</option>
                    <option value="pedido" {{ $filtros['tipo'] === 'pedido' ? 'selected' : '' }}>Solo pedidos</option>
                    <option value="pos"    {{ $filtros['tipo'] === 'pos'    ? 'selected' : '' }}>Solo notas POS</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Almacén</label>
                <select name="warehouse_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ (string)$filtros['warehouseId'] === (string)$w->id ? 'selected' : '' }}>{{ $w->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Desde</label>
                <input type="date" name="fecha_desde" value="{{ $filtros['fechaDesde'] }}" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            </div>
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ $filtros['fechaHasta'] }}" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <button type="submit" class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700 whitespace-nowrap">Buscar</button>
            </div>
        </form>

        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="p-2 text-left">Tipo</th>
                        <th class="p-2 text-left">Folio</th>
                        <th class="p-2 text-left">Cliente</th>
                        <th class="p-2 text-left">Almacén</th>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-right">Total</th>
                        <th class="p-2 text-center">Estatus</th>
                        <th class="p-2 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultados as $r)
                        @php
                            $estatusClass = match(true) {
                                $r['estatus'] === 'CANCELADO' || $r['estatus'] === 'CANCELADA' => 'bg-rose-100 text-rose-700',
                                $r['estatus'] === 'ENTREGADO' || $r['estatus'] === 'COMPLETADA' => 'bg-emerald-100 text-emerald-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-2">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $r['tipo'] === 'pedido' ? 'bg-indigo-100 text-indigo-700' : 'bg-violet-100 text-violet-700' }}">
                                    {{ $r['tipo'] === 'pedido' ? 'Pedido' : 'POS' }}
                                </span>
                            </td>
                            <td class="p-2 font-mono text-xs">
                                <a href="{{ $r['url_ver'] }}" target="_blank" class="text-indigo-600 hover:underline">{{ $r['folio'] }}</a>
                            </td>
                            <td class="p-2">{{ $r['cliente'] }}</td>
                            <td class="p-2 text-gray-500 text-xs">{{ $r['almacen'] }}</td>
                            <td class="p-2 text-gray-500 text-xs">{{ $r['fecha'] }}</td>
                            <td class="p-2 text-right font-mono">${{ number_format($r['total'], 2) }}</td>
                            <td class="p-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $estatusClass }}">{{ $r['estatus'] }}</span>
                            </td>
                            <td class="p-2 text-center whitespace-nowrap">
                                <a href="{{ $r['url_ver'] }}" target="_blank" class="text-xs text-indigo-600 hover:underline mr-2">Ver{{ $r['tipo'] === 'pedido' ? ' / Editar' : '' }}</a>
                                @if($r['cancelable'] && (($r['tipo'] === 'pedido' && auth()->user()->can('editar pedidos cerrados')) || ($r['tipo'] === 'pos' && auth()->user()->can('cancelar notas pos'))))
                                    <form action="{{ $r['url_cancelar'] }}" method="POST" class="inline form-cancelar-nota">
                                        @csrf
                                        <input type="hidden" name="motivo" class="inp-motivo">
                                        <button type="button" onclick="confirmarCancelacion(this)"
                                                class="text-xs text-rose-600 hover:underline">Cancelar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-6 text-center text-gray-400">Sin resultados para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-wire-card>

    <script>
    function confirmarCancelacion(btn) {
        var form = btn.closest('form');
        Swal.fire({
            title: '¿Cancelar esta nota?',
            text: 'Se revertirá el stock y, si aplica, el cargo a CxC o el efectivo de caja. Esta acción queda registrada en Auditoría.',
            icon: 'warning',
            input: 'text',
            inputPlaceholder: 'Motivo (opcional)',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            confirmButtonColor: '#e11d48',
            cancelButtonText: 'Volver',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            form.querySelector('.inp-motivo').value = result.value || '';
            form.submit();
        });
    }
    </script>
</x-admin-layout>
