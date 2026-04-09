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

    {{-- Toggle de vista --}}
    <div class="flex gap-2 mb-4">
        <a href="{{ route('admin.stock.index', array_merge(request()->query(), ['vista' => 'productos'])) }}"
           class="px-3 py-1.5 text-sm rounded-md border {{ $vista === 'productos' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            📦 Por producto
        </a>
        <a href="{{ route('admin.stock.index', array_merge(request()->query(), ['vista' => 'almacenes'])) }}"
           class="px-3 py-1.5 text-sm rounded-md border {{ $vista === 'almacenes' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            🏭 Por almacén
        </a>
    </div>

    @if($vista === 'productos')
        <x-wire-card>
            <form method="GET" action="{{ route('admin.stock.index') }}"
                  class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <input type="hidden" name="vista" value="productos">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id" class="w-full rounded-md border-gray-300 text-sm" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (string)$warehouseId === (string)$w->id ? 'selected' : '' }}>
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
                            <option value="{{ $p->id }}" {{ (string)$productId === (string)$p->id ? 'selected' : '' }}>
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

        <x-wire-card class="mt-4">
            @if(!$warehouseId)
                <p class="text-sm text-gray-500 py-4 text-center">Selecciona un almacén para ver existencias.</p>
            @elseif($stock->isEmpty())
                <p class="text-sm text-gray-500 py-4 text-center">No hay productos con inventario en este almacén.</p>
            @else
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
                                           class="px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50">Kardex</a>
                                        <a href="{{ route('admin.stock.adjustments.create', ['product_id' => $row->product_id, 'warehouse_id' => $warehouseId]) }}"
                                           class="px-2 py-1 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-50">Ajuste</a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div id="no-results" class="hidden py-8 text-center text-sm text-gray-400">No se encontraron productos.</div>
                    <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
                        <span id="pagination-info"></span>
                        <div class="flex gap-2">
                            <button id="btn-prev" class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">← Anterior</button>
                            <button id="btn-next" class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Siguiente →</button>
                        </div>
                    </div>
                </div>
                <script>
                (function () {
                    var tbody = document.getElementById('stock-tbody');
                    var noResults = document.getElementById('no-results');
                    var paginationInfo = document.getElementById('pagination-info');
                    var btnPrev = document.getElementById('btn-prev');
                    var btnNext = document.getElementById('btn-next');
                    var fSearch = document.getElementById('filter-search');
                    var PER_PAGE = 25;
                    var currentPage = 1, filteredRows = [];
                    function applyFilters() {
                        var search = fSearch.value.toLowerCase().trim();
                        filteredRows = Array.from(tbody.querySelectorAll('tr')).filter(function(r) {
                            return r.dataset.search.includes(search);
                        });
                        currentPage = 1;
                        renderPage();
                    }
                    function renderPage() {
                        Array.from(tbody.querySelectorAll('tr')).forEach(function(r) { r.classList.add('hidden'); });
                        var start = (currentPage - 1) * PER_PAGE;
                        var end = start + PER_PAGE;
                        filteredRows.slice(start, end).forEach(function(r) { r.classList.remove('hidden'); });
                        var total = filteredRows.length;
                        noResults.classList.toggle('hidden', total > 0);
                        paginationInfo.textContent = total > 0 ? ('Mostrando ' + (start+1) + '\u2013' + Math.min(end,total) + ' de ' + total + ' productos') : '';
                        btnPrev.disabled = currentPage <= 1;
                        btnNext.disabled = currentPage >= Math.ceil(total / PER_PAGE);
                    }
                    btnPrev.addEventListener('click', function() { currentPage--; renderPage(); });
                    btnNext.addEventListener('click', function() { currentPage++; renderPage(); });
                    fSearch.addEventListener('input', applyFilters);
                    applyFilters();
                })();
                </script>
            @endif
        </x-wire-card>

    @else
        {{-- ===== VISTA POR ALMACÉN ===== --}}
        <div class="mb-4">
            <input id="filter-almacen" type="text"
                placeholder="Buscar producto en todos los almacenes..."
                class="w-full md:w-96 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
        </div>

        @foreach($resumenAlmacenes as $w)
        <x-wire-card class="mt-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">🏭 {{ $w->nombre }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <span class="text-emerald-600 font-medium">{{ $w->con_stock }} con stock</span>
                        · <span class="text-red-500 font-medium">{{ $w->sin_stock }} sin stock</span>
                        · Valor total: <span class="font-mono font-medium">${{ number_format($w->total_valor, 2) }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.stock.index', ['vista' => 'productos', 'warehouse_id' => $w->id]) }}"
                       class="px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50">
                        Ver detalle
                    </a>
                 <a href="{{ route('admin.stock.print-warehouse', $w->id) }}"
                    target="_blank"
                    class="px-2 py-1 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                        🖨 Imprimir
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="almacen-{{ $w->id }}" class="min-w-full divide-y divide-gray-200 text-sm almacen-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">SKU</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Producto</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Unidad</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Existencia</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Stock mín.</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($w->productos as $row)
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
                        <tr class="almacen-row" data-search="{{ strtolower($row->sku . ' ' . $row->nombre) }}">
                            <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ $row->sku ?: '—' }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $row->nombre }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $row->unidad }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded font-mono text-sm {{ $existenciaClass }}">
                                    {{ number_format($qty, 3) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-mono text-sm text-gray-500">
                                {{ $stockMin > 0 ? number_format($stockMin, 3) : '—' }}
                            </td>
                            <td class="px-4 py-2 font-mono text-sm text-gray-700">
                                {{ $valor > 0 ? '$'.number_format($valor, 2) : '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-wire-card>
        @endforeach

   @endif

</x-admin-layout>

@push('js')
<script>
(function () {
    var filterAlmacen = document.getElementById('filter-almacen');
    if (!filterAlmacen) return;

    filterAlmacen.addEventListener('input', function () {
        var search = this.value.toLowerCase().trim();
        document.querySelectorAll('.almacen-row').forEach(function(row) {
            row.classList.toggle('hidden', !row.dataset.search.includes(search));
        });
    });

    document.querySelectorAll('.btn-imprimir').forEach(function(btn) {
        btn.addEventListener('click', function () {
            var tableId = this.dataset.printId;
            var nombre  = this.dataset.printNombre;
            var table   = document.getElementById(tableId);
            var fecha   = new Date().toLocaleDateString('es-MX', {
                day: '2-digit', month: 'long', year: 'numeric'
            });

            var filas = Array.from(table.querySelectorAll('tbody tr'))
                .filter(function(r) { return !r.classList.contains('hidden'); })
                .map(function(row) {
                    var cells = row.querySelectorAll('td');
                    return '<tr>'
                        + '<td>' + (cells[0] ? cells[0].textContent.trim() : '') + '</td>'
                        + '<td>' + (cells[1] ? cells[1].textContent.trim() : '') + '</td>'
                        + '<td>' + (cells[2] ? cells[2].textContent.trim() : '') + '</td>'
                        + '<td>' + (cells[3] ? cells[3].textContent.trim() : '') + '</td>'
                        + '<td>' + (cells[4] ? cells[4].textContent.trim() : '') + '</td>'
                        + '<td class="conteo"></td>'
                        + '</tr>';
                }).join('');

            var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                + '<title>Inventario - ' + nombre + '</title>'
                + '<style>'
                + 'body{font-family:Arial,sans-serif;font-size:12px;padding:20px}'
                + 'h2{margin-bottom:2px;font-size:16px}'
                + '.meta{color:#666;font-size:11px;margin-bottom:16px}'
                + 'table{width:100%;border-collapse:collapse}'
                + 'th{background:#f3f4f6;text-align:left;padding:6px 10px;font-size:11px;text-transform:uppercase;border-bottom:2px solid #d1d5db}'
                + 'td{padding:6px 10px;border-bottom:1px solid #e5e7eb}'
                + 'tr:nth-child(even) td{background:#f9fafb}'
                + 'td.conteo{min-width:100px;border-bottom:1px solid #9ca3af}'
                + '.firma{margin-top:48px;font-size:11px;color:#555}'
                + '</style></head><body>'
                + '<h2>Inventario fisico - ' + nombre + '</h2>'
                + '<p class="meta">Fecha: ' + fecha + '</p>'
                + '<table><thead><tr>'
                + '<th>SKU</th><th>Producto</th><th>Unidad</th>'
                + '<th>Existencia sistema</th><th>Stock min.</th><th>Conteo fisico</th>'
                + '</tr></thead><tbody>' + filas + '</tbody></table>'
                + '<div class="firma">Responsable: _____________________________ &nbsp;&nbsp; Fecha conteo: _____________</div>'
                + '</body></html>';

            var win = window.open('', '_blank');
            win.document.write(html);
            win.document.close();
            win.focus();
            setTimeout(function() { win.print(); }, 500);
        });
    });
})();
</script>
@endpush