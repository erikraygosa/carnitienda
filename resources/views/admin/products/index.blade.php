<x-admin-layout
    title="Listado de productos"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Productos'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.products.create') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">
            Nuevo
        </a>
    </x-slot>

    <x-wire-card>
        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 mb-4">
            <input
                id="filter-search"
                type="text"
                placeholder="Buscar por nombre o SKU..."
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            />
            <select
                id="filter-categoria"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            >
                <option value="">Todas las categorías</option>
                @foreach($categories as $cat)
                    <option value="{{ strtolower($cat->nombre) }}">{{ $cat->nombre }}</option>
                @endforeach
            </select>
            <select
                id="filter-activo"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            >
                <option value="">Todos los estatus</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
            <select
                id="filter-compuesto"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            >
                <option value="">Compuesto (todos)</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
            </select>
            <select
                id="filter-subproducto"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            >
                <option value="">Subproducto (todos)</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
            </select>
            <input
                id="filter-stock"
                type="number"
                placeholder="Stock mín. máximo..."
                class="w-40 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
            />
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">SKU</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Categoría</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Precio base</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Stock mín.</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Compuesto</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Subproducto</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Estatus</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Acciones</th>
                    </tr>
                </thead>
                <tbody id="product-tbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($products as $product)
                    <tr
                        data-search="{{ strtolower($product->nombre . ' ' . $product->sku) }}"
                        data-categoria="{{ strtolower($product->category?->nombre ?? '') }}"
                        data-activo="{{ $product->activo ? '1' : '0' }}"
                        data-compuesto="{{ $product->es_compuesto ? '1' : '0' }}"
                        data-subproducto="{{ $product->es_subproducto ? '1' : '0' }}"
                        data-stock="{{ $product->stock_min }}"
                    >
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $product->id }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $product->sku ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $product->nombre }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $product->category?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">${{ number_format($product->precio_base, 2) }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ number_format($product->stock_min, 3) }}</td>
                        <td class="px-4 py-3">
                            @if($product->es_compuesto)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">Sí</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($product->es_subproducto)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700">Sí</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($product->activo)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Activo</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.products.edit', $product) }}"
                                   class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                    Editar
                                </a>
                                <form action="{{ route('admin.products.destroy', $product) }}"
                                      method="POST"
                                      class="delete-form inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-red-600 text-red-600 hover:bg-red-50">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Sin resultados --}}
            <div id="no-results" class="hidden py-8 text-center text-sm text-gray-400">
                No se encontraron productos.
            </div>

            {{-- Paginación --}}
            <div class="flex items-center justify-between mt-4 text-sm text-gray-600 dark:text-gray-400">
                <span id="pagination-info"></span>
                <div class="flex gap-2">
                    <button id="btn-prev"
                        class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed dark:border-gray-600 dark:hover:bg-gray-700">
                        ← Anterior
                    </button>
                    <button id="btn-next"
                        class="px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed dark:border-gray-600 dark:hover:bg-gray-700">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>
    </x-wire-card>

    <script>
    (function () {
        const tbody          = document.getElementById('product-tbody');
        const noResults      = document.getElementById('no-results');
        const paginationInfo = document.getElementById('pagination-info');
        const btnPrev        = document.getElementById('btn-prev');
        const btnNext        = document.getElementById('btn-next');
        const fSearch        = document.getElementById('filter-search');
        const fCategoria     = document.getElementById('filter-categoria');
        const fActivo        = document.getElementById('filter-activo');
        const fCompuesto     = document.getElementById('filter-compuesto');
        const fSubproducto   = document.getElementById('filter-subproducto');
        const fStock         = document.getElementById('filter-stock');

        const PER_PAGE = 15;
        let currentPage  = 1;
        let filteredRows = [];

        function applyFilters() {
            const search      = fSearch.value.toLowerCase().trim();
            const categoria   = fCategoria.value.toLowerCase();
            const activo      = fActivo.value;
            const compuesto   = fCompuesto.value;
            const subproducto = fSubproducto.value;
            const stock       = fStock.value !== '' ? parseFloat(fStock.value) : null;

            const allRows = Array.from(tbody.querySelectorAll('tr'));

            filteredRows = allRows.filter(row => {
                const matchSearch      = row.dataset.search.includes(search);
                const matchCategoria   = categoria === '' || row.dataset.categoria.includes(categoria);
                const matchActivo      = activo === '' || row.dataset.activo === activo;
                const matchCompuesto   = compuesto === '' || row.dataset.compuesto === compuesto;
                const matchSubproducto = subproducto === '' || row.dataset.subproducto === subproducto;
                const matchStock       = stock === null || parseFloat(row.dataset.stock) <= stock;
                return matchSearch && matchCategoria && matchActivo && matchCompuesto && matchSubproducto && matchStock;
            });

            currentPage = 1;
            renderPage();
        }

        function renderPage() {
            const allRows  = Array.from(tbody.querySelectorAll('tr'));
            allRows.forEach(row => row.classList.add('hidden'));

            const start    = (currentPage - 1) * PER_PAGE;
            const end      = start + PER_PAGE;
            const pageRows = filteredRows.slice(start, end);
            pageRows.forEach(row => row.classList.remove('hidden'));

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
        fCategoria.addEventListener('change', applyFilters);
        fActivo.addEventListener('change', applyFilters);
        fCompuesto.addEventListener('change', applyFilters);
        fSubproducto.addEventListener('change', applyFilters);
        fStock.addEventListener('input', applyFilters);

        // SweetAlert para eliminar
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: '¡No podrás revertir esto!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(result => {
                    if (result.isConfirmed) form.submit();
                });
            });
        });

        // Inicializar
        applyFilters();
    })();
    </script>

</x-admin-layout>