<x-admin-layout
    title="Resumen de liquidaciones por chofer"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Logística'],
        ['name'=>'Cortes de choferes'],
    ]"
>
    {{-- Filtro de fechas --}}
    <x-wire-card class="mb-4">
        <form method="GET" action="{{ route('admin.driver-cash.index') }}"
              class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                <input type="date" name="desde" value="{{ $desde }}"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ $hasta }}"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>
            <button type="submit"
                class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('admin.driver-cash.index') }}"
               class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                Este mes
            </a>
        </form>
    </x-wire-card>

    @if($resumen->isEmpty())
        <x-wire-card>
            <p class="text-center text-gray-400 py-8 text-sm">No hay despachos en el período seleccionado.</p>
        </x-wire-card>
    @endif

    @foreach($resumen as $driver)
    <x-wire-card class="mt-4">
        {{-- Header chofer --}}
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-semibold text-gray-800">🚗 {{ $driver->nombre }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    <span class="font-medium text-gray-700">{{ $driver->total_despachos }}</span> despachos
                    · <span class="text-blue-600 font-medium">{{ $driver->cerrados }} cerrados</span>
                    · <span class="text-emerald-600 font-medium">${{ number_format($driver->total_pedidos, 2) }}</span> pedidos entregados
                    · <span class="text-indigo-600 font-medium">${{ number_format($driver->total_cxc, 2) }}</span> CxC cobrada
                    · <span class="text-violet-600 font-medium">${{ number_format($driver->total_liquidado, 2) }}</span> liquidado
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Fecha</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Estatus</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Pedidos</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Total pedidos</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">CxC cobrada</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Liquidado</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-{{ $driver->id }}" class="divide-y divide-gray-200">
                    @foreach($driver->despachos as $dispatch)
                    @php
                        $statusMap = [
                            'EN_RUTA'   => 'bg-violet-100 text-violet-700',
                            'ENTREGADO' => 'bg-emerald-100 text-emerald-700',
                            'CERRADO'   => 'bg-blue-100 text-blue-700',
                        ];
                        $statusClass       = $statusMap[$dispatch->status] ?? 'bg-gray-100 text-gray-700';
                        $pedidosEntregados = $dispatch->items->filter(fn($i) => $i->salesOrder?->status === 'ENTREGADO')->count();
                        $totalItems        = $dispatch->items->count();
                        $totalPedidosDisp  = $dispatch->items->filter(fn($i) => $i->salesOrder?->status === 'ENTREGADO')->sum(fn($i) => $i->salesOrder?->total ?? 0);
                        $cxcCobrada        = $dispatch->arAssignments->where('status', 'COBRADO')->sum('monto_cobrado');
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-gray-600">
                            {{ \Carbon\Carbon::parse($dispatch->fecha)->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                {{ $dispatch->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $pedidosEntregados }} / {{ $totalItems }}</td>
                        <td class="px-4 py-2 font-mono text-gray-700">${{ number_format($totalPedidosDisp, 2) }}</td>
                        <td class="px-4 py-2 font-mono text-gray-700">${{ number_format($cxcCobrada, 2) }}</td>
                        <td class="px-4 py-2 font-mono text-gray-700">${{ number_format($dispatch->monto_liquidado ?? 0, 2) }}</td>
                        <td class="px-4 py-2">
                            <div class="flex gap-1">
                                <a href="{{ route('admin.dispatches.edit', $dispatch) }}"
                                   class="px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50">
                                    Ver
                                </a>
                                @if($dispatch->status === 'CERRADO')
                                    <a href="{{ route('admin.dispatches.print.liquidacion', $dispatch) }}"
                                       target="_blank"
                                       class="px-2 py-1 text-xs rounded border border-blue-300 text-blue-600 hover:bg-blue-50">
                                        Liquidación
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-medium text-sm">
                        <td colspan="3" class="px-4 py-2 text-right text-gray-600">Totales:</td>
                        <td class="px-4 py-2 font-mono text-gray-800">${{ number_format($driver->total_pedidos, 2) }}</td>
                        <td class="px-4 py-2 font-mono text-gray-800">${{ number_format($driver->total_cxc, 2) }}</td>
                        <td class="px-4 py-2 font-mono text-gray-800">${{ number_format($driver->total_liquidado, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            {{-- Paginación vanilla JS por chofer --}}
            <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
                <span id="info-{{ $driver->id }}"></span>
                <div class="flex gap-2">
                    <button data-driver="{{ $driver->id }}" data-dir="prev"
                        class="btn-page px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        ← Anterior
                    </button>
                    <button data-driver="{{ $driver->id }}" data-dir="next"
                        class="btn-page px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>
    </x-wire-card>
    @endforeach

    @push('js')
    <script>
    (function () {
        var PER_PAGE = 10;
        var pages = {};

        document.querySelectorAll('[id^="tbody-"]').forEach(function(tbody) {
            var driverId = tbody.id.replace('tbody-', '');
            pages[driverId] = 1;
            paginate(driverId);
        });

        document.querySelectorAll('.btn-page').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var driverId = this.dataset.driver;
                var dir      = this.dataset.dir;
                var tbody    = document.getElementById('tbody-' + driverId);
                var total    = tbody.querySelectorAll('tr').length;
                var maxPage  = Math.ceil(total / PER_PAGE);

                if (dir === 'prev' && pages[driverId] > 1) pages[driverId]--;
                if (dir === 'next' && pages[driverId] < maxPage) pages[driverId]++;

                paginate(driverId);
            });
        });

        function paginate(driverId) {
            var tbody    = document.getElementById('tbody-' + driverId);
            var info     = document.getElementById('info-' + driverId);
            var btnPrev  = document.querySelector('[data-driver="' + driverId + '"][data-dir="prev"]');
            var btnNext  = document.querySelector('[data-driver="' + driverId + '"][data-dir="next"]');
            var rows     = Array.from(tbody.querySelectorAll('tr'));
            var total    = rows.length;
            var maxPage  = Math.ceil(total / PER_PAGE);
            var page     = pages[driverId];
            var start    = (page - 1) * PER_PAGE;
            var end      = start + PER_PAGE;

            rows.forEach(function(r, i) {
                r.classList.toggle('hidden', i < start || i >= end);
            });

            info.textContent = total > 0
                ? 'Mostrando ' + (start + 1) + '\u2013' + Math.min(end, total) + ' de ' + total
                : '';

            btnPrev.disabled = page <= 1;
            btnNext.disabled = page >= maxPage;
        }
    })();
    </script>
    @endpush

</x-admin-layout>