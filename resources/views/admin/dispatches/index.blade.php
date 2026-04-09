<x-admin-layout
    title="Despachos"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Despachos'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.dispatches.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Nuevo despacho
        </a>
    </x-slot>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        @php
            $kpis = [
                ['label'=>'En ruta hoy',  'color'=>'text-violet-700',
                 'count'=> \App\Models\Dispatch::where('status','EN_RUTA')->whereDate('fecha', today())->count()],
                ['label'=>'Planeados',    'color'=>'text-gray-600',
                 'count'=> \App\Models\Dispatch::where('status','PLANEADO')->count()],
                ['label'=>'Cerrados hoy', 'color'=>'text-blue-700',
                 'count'=> \App\Models\Dispatch::where('status','CERRADO')->whereDate('cerrado_at', today())->count()],
                ['label'=>'Cancelados',   'color'=>'text-rose-600',
                 'count'=> \App\Models\Dispatch::where('status','CANCELADO')->whereDate('fecha', today())->count()],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <x-wire-card>
                <div class="text-xs text-gray-500">{{ $kpi['label'] }}</div>
                <div class="text-2xl font-bold mt-1 {{ $kpi['color'] }}">{{ $kpi['count'] }}</div>
            </x-wire-card>
        @endforeach
    </div>

    <x-wire-card>
        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 mb-4">
            <input id="filter-search" type="text"
                placeholder="Buscar chofer / ruta / almacén..."
                class="flex-1 min-w-[200px] rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <select id="filter-status"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos los estados</option>
                @foreach(['PLANEADO','PREPARANDO','CARGADO','EN_RUTA','ENTREGADO','CERRADO','CANCELADO'] as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Fecha</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Chofer</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Ruta</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Almacén</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Pedidos</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody id="dispatch-tbody" class="divide-y divide-gray-200">
                    @foreach($dispatches as $dispatch)
                    @php
                        $statusMap = [
                            'PLANEADO'   => 'bg-gray-100 text-gray-700',
                            'PREPARANDO' => 'bg-sky-100 text-sky-700',
                            'CARGADO'    => 'bg-amber-100 text-amber-700',
                            'EN_RUTA'    => 'bg-violet-100 text-violet-700',
                            'ENTREGADO'  => 'bg-emerald-100 text-emerald-700',
                            'CERRADO'    => 'bg-blue-100 text-blue-700',
                            'CANCELADO'  => 'bg-rose-100 text-rose-700',
                        ];
                        $statusClass = $statusMap[$dispatch->status] ?? 'bg-slate-100 text-slate-700';

                        $total        = (int) $dispatch->items_count;
                        $entregados   = (int) $dispatch->items_entregados;
                        $noEntregados = (int) $dispatch->items_no_entregados;
                        $pendientes   = $total - $entregados - $noEntregados;
                    @endphp
                    <tr
                        data-search="{{ strtolower(($dispatch->driver?->nombre ?? '') . ' ' . ($dispatch->route?->nombre ?? '') . ' ' . ($dispatch->warehouse?->nombre ?? '')) }}"
                        data-status="{{ $dispatch->status }}"
                    >
                        <td class="px-4 py-3 text-gray-600">
                            {{ $dispatch->fecha ? \Carbon\Carbon::parse($dispatch->fecha)->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $dispatch->driver?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $dispatch->route?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $dispatch->warehouse?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-sm">{{ $total }}</span>
                            @if($entregados > 0)
                                <span class="text-xs text-emerald-600">✓{{ $entregados }}</span>
                            @endif
                            @if($noEntregados > 0)
                                <span class="text-xs text-red-500">✗{{ $noEntregados }}</span>
                            @endif
                            @if($pendientes > 0 && $dispatch->status === 'EN_RUTA')
                                <span class="text-xs text-violet-500">⏳{{ $pendientes }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                {{ $dispatch->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 flex-wrap">
                                <a href="{{ route('admin.dispatches.edit', $dispatch) }}"
                                   class="px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50">
                                    Ver
                                </a>
                                @if(in_array($dispatch->status, ['PLANEADO','PREPARANDO','CARGADO','EN_RUTA']))
                                    <a href="{{ route('admin.dispatches.print.ruta', $dispatch) }}"
                                       target="_blank"
                                       class="px-2 py-1 text-xs rounded border border-teal-300 text-teal-600 hover:bg-teal-50">
                                        Ruta
                                    </a>
                                @endif
                                @if($dispatch->status === 'CERRADO')
                                    <a href="{{ route('admin.dispatches.print.liquidacion', $dispatch) }}"
                                       target="_blank"
                                       class="px-2 py-1 text-xs rounded border border-blue-300 text-blue-600 hover:bg-blue-50">
                                        Liquidación
                                    </a>
                                @endif
                                @if(!in_array($dispatch->status, ['EN_RUTA','ENTREGADO','CERRADO']))
                                    <form method="POST"
                                          action="{{ route('admin.dispatches.destroy', $dispatch) }}"
                                          class="delete-form inline">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="px-2 py-1 text-xs rounded border border-red-200 text-red-500 hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div id="no-results" class="hidden py-8 text-center text-sm text-gray-400">
                No se encontraron despachos.
            </div>

            {{-- Paginación --}}
            <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
                <span id="pagination-info"></span>
                <div class="flex gap-2">
                    <button id="btn-prev"
                        class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        ← Anterior
                    </button>
                    <button id="btn-next"
                        class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>
    </x-wire-card>

    <script>
    (function () {
        var tbody          = document.getElementById('dispatch-tbody');
        var noResults      = document.getElementById('no-results');
        var paginationInfo = document.getElementById('pagination-info');
        var btnPrev        = document.getElementById('btn-prev');
        var btnNext        = document.getElementById('btn-next');
        var fSearch        = document.getElementById('filter-search');
        var fStatus        = document.getElementById('filter-status');

        var PER_PAGE = 15;
        var currentPage = 1, filteredRows = [];

        function applyFilters() {
            var search = fSearch.value.toLowerCase().trim();
            var status = fStatus.value;

            filteredRows = Array.from(tbody.querySelectorAll('tr')).filter(function(row) {
                var matchSearch = row.dataset.search.includes(search);
                var matchStatus = status === '' || row.dataset.status === status;
                return matchSearch && matchStatus;
            });

            currentPage = 1;
            renderPage();
        }

        function renderPage() {
            Array.from(tbody.querySelectorAll('tr')).forEach(function(r) { r.classList.add('hidden'); });

            var start = (currentPage - 1) * PER_PAGE;
            var end   = start + PER_PAGE;
            filteredRows.slice(start, end).forEach(function(r) { r.classList.remove('hidden'); });

            var total      = filteredRows.length;
            var totalPages = Math.ceil(total / PER_PAGE);

            noResults.classList.toggle('hidden', total > 0);
            paginationInfo.textContent = total > 0
                ? ('Mostrando ' + (start+1) + '–' + Math.min(end,total) + ' de ' + total + ' despachos')
                : '';

            btnPrev.disabled = currentPage <= 1;
            btnNext.disabled = currentPage >= totalPages;
        }

        btnPrev.addEventListener('click', function() { currentPage--; renderPage(); });
        btnNext.addEventListener('click', function() { currentPage++; renderPage(); });
        fSearch.addEventListener('input', applyFilters);
        fStatus.addEventListener('change', applyFilters);

        document.querySelectorAll('.delete-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Eliminar despacho?',
                    text: 'No podrás revertirlo.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                }).then(function(r) { if (r.isConfirmed) form.submit(); });
            });
        });

        applyFilters();
    })();
    </script>

</x-admin-layout>