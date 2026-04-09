<x-admin-layout
    title="Stock por almacén"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Inventario'],
        ['name'=>'Stock'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.adjustments.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Ajuste manual
        </a>
        <a href="{{ route('admin.stock.transfers.create') }}"
           class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Transferencia
        </a>
    </x-slot>

    {{-- Filtros --}}
    <x-wire-card>
        <form method="GET" action="{{ route('admin.stock.index') }}"
              class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                <select name="warehouse_id" class="w-full rounded-md border-gray-300 text-sm" required>
                    <option value="">-- seleccionar --</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}"
                            {{ (string)$warehouseId === (string)$w->id ? 'selected' : '' }}>
                            {{ $w->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Producto</label>
                <select name="product_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">-- todos --</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}"
                            {{ (string)$productId === (string)$p->id ? 'selected' : '' }}>
                            {{ $p->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 inline-flex justify-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Filtrar
                </button>
                <a href="{{ route('admin.stock.index') }}"
                   class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </x-wire-card>

    {{-- Tabla --}}
    <x-wire-card class="mt-4">
        @if(!$warehouseId)
            <p class="text-sm text-gray-500 py-4 text-center">
                Selecciona un almacén para ver existencias.
            </p>
        @elseif($stock->isEmpty())
            <p class="text-sm text-gray-500 py-4 text-center">
                No hay productos con inventario en este almacén.
            </p>
        @else
            {{-- Búsqueda JS --}}
            <div class="mb-4">
                <input id="filter-search" type="text"
                    placeholder="Buscar SKU o producto..."
                    class="w-full md:w-80 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">SKU</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Producto</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Unidad</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Existencia</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Stock mín.</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Valor inv.</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="stock-tbody" class="divide-y divide-gray-200">
                        @foreach($stock as $row)
                        @php
                            $qty      = (float) $row->existencia;
                            $stockMin = (float) ($row->stock_min ?? 0);
                            $valor    = (float) ($row->costo_promedio ?? 0) * $qty;

                            $existenciaClass = $qty <= 0
                                ? 'text-red-700 bg-red-50'
                                : ($stockMin > 0 && $qty <= $stockMin
                                    ? 'text-amber-700 bg-amber-50'
                                    : 'text-emerald-700 bg-emerald-50');
                        @endphp
                        <tr data-search="{{ strtolower($row->sku . ' ' . $row->nombre) }}">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $row->sku ?: '—' }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $row->nombre }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $row->unidad }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded font-mono text-sm {{ $existenciaClass }}">
                                    {{ number_format($qty, 3) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-sm text-gray-500">
                                {{ $stockMin > 0 ? number_format($stockMin, 3) : '—' }}
                            </td>
                            <td class="px-4 py-3 font-mono text-sm text-gray-700">
                                {{ $valor > 0 ? '$'.number_format($valor, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('admin.stock.kardex', ['product_id' => $row->product_id, 'warehouse_id' => $warehouseId]) }}"
                                       class="px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50">
                                        Kardex
                                    </a>
                                    <a href="{{ route('admin.stock.adjustments.create', ['product_id' => $row->product_id, 'warehouse_id' => $warehouseId]) }}"
                                       class="px-2 py-1 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                                        Ajuste
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div id="no-results" class="hidden py-8 text-center text-sm text-gray-400">
                    No se encontraron productos.
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

            <script>
            (function () {
                const tbody          = document.getElementById('stock-tbody');
                const noResults      = document.getElementById('no-results');
                const paginationInfo = document.getElementById('pagination-info');
                const btnPrev        = document.getElementById('btn-prev');
                const btnNext        = document.getElementById('btn-next');
                const fSearch        = document.getElementById('filter-search');

                const PER_PAGE = 25;
                let currentPage  = 1;
                let filteredRows = [];

                function applyFilters() {
                    const search = fSearch.value.toLowerCase().trim();
                    filteredRows = Array.from(tbody.querySelectorAll('tr')).filter(row =>
                        row.dataset.search.includes(search)
                    );
                    currentPage = 1;
                    renderPage();
                }

                function renderPage() {
                    Array.from(tbody.querySelectorAll('tr')).forEach(r => r.classList.add('hidden'));

                    const start    = (currentPage - 1) * PER_PAGE;
                    const end      = start + PER_PAGE;
                    filteredRows.slice(start, end).forEach(r => r.classList.remove('hidden'));

                    const total      = filteredRows.length;
                    const totalPages = Math.ceil(total / PER_PAGE);

                    noResults.classList.toggle('hidden', total > 0);
                    paginationInfo.textContent = total > 0
                        ? `Mostrando ${start + 1}–${Math.min(end, total)} de ${total} productos`
                        : '';

                    btnPrev.disabled = currentPage <= 1;
                    btnNext.disabled = currentPage >= totalPages;
                }

                btnPrev.addEventListener('click', () => { currentPage--; renderPage(); });
                btnNext.addEventListener('click', () => { currentPage++; renderPage(); });
                fSearch.addEventListener('input', applyFilters);

                applyFilters();
            })();
            </script>
        @endif
    </x-wire-card>

</x-admin-layout>