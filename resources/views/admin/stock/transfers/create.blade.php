<x-admin-layout
    title="Crear transferencia"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Stock','url'=>route('admin.stock.index')],
        ['name'=>'Transferir'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">
            Regresar
        </a>
        <button type="button"
                onclick="TF.validate() && document.getElementById('transfer-form').submit()"
                class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    @php
        $selFrom   = (string) old('from_warehouse_id', (string)($prefill['from_warehouse_id'] ?? ''));
        $selTo     = (string) old('to_warehouse_id', '');
        $today     = old('fecha', now()->toDateString());
        $seedItems = old('items', []);
        if (empty($seedItems) && !empty($prefill['product_id'])) {
            $seedItems = [['product_id' => (int)$prefill['product_id'], 'qty' => 1]];
        }
        $JS_SEED     = json_encode($seedItems, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_PRODUCTS = json_encode($products->map(fn($p) => [
            'id'     => $p->id,
            'nombre' => $p->nombre,
            'sku'    => $p->sku ?? '',
            'codigo' => $p->codigo ?? $p->barcode ?? '',
        ])->values(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @endphp

    @if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
        <strong class="block mb-1">Revisa los siguientes errores:</strong>
        <ul class="list-disc ml-5 text-sm">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <x-wire-card>
        <form id="transfer-form" method="POST"
              action="{{ route('admin.stock.transfers.store') }}"
              class="space-y-6">
            @csrf

            {{-- Encabezado --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Almacén origen <span class="text-red-500">*</span>
                    </label>
                    <select name="from_warehouse_id" id="from_warehouse_id"
                            class="w-full rounded-md border-gray-300 text-sm" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selFrom===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Almacén destino <span class="text-red-500">*</span>
                    </label>
                    <select name="to_warehouse_id" id="to_warehouse_id"
                            class="w-full rounded-md border-gray-300 text-sm" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selTo===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Fecha <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="fecha" value="{{ $today }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea name="notas" rows="2"
                              class="w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('notas') }}</textarea>
                </div>

            </div>

            {{-- Buscador / Scanner --}}
            <div class="border rounded-lg p-4 bg-gray-50">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fa-solid fa-barcode text-gray-400"></i>
                    <label class="text-sm font-medium text-gray-700">
                        Buscar producto o escanear código
                    </label>
                    <span class="text-xs text-gray-400">(escribe nombre, SKU o escanea con lector)</span>
                </div>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input type="text" id="product-search"
                               placeholder="Buscar por nombre o código de barras..."
                               autocomplete="off"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm pr-8">
                        <button type="button" onclick="TF.clearSearch()"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs">
                            ✕
                        </button>
                    </div>
                    <button type="button" onclick="TF.addFromSearch()"
                            class="inline-flex items-center gap-1 px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        <i class="fa-solid fa-plus"></i> Agregar
                    </button>
                </div>

                <div id="search-results"
                     class="hidden mt-2 border rounded-md bg-white shadow-sm max-h-48 overflow-y-auto">
                </div>
            </div>

            {{-- Tabla partidas --}}
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50">
                        <tr>
                            <th class="p-2 text-left">Producto</th>
                            <th class="p-2 text-left text-gray-400 text-xs">Código</th>
                            <th class="p-2 text-right w-36">Cantidad</th>
                            <th class="p-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        <tr id="empty-row">
                            <td colspan="4" class="p-4 text-center text-gray-400 text-sm">
                                Usa el buscador o scanner para agregar productos
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="text-right text-xs text-gray-400">
                <span id="items-count">0</span> producto(s) en la transferencia
            </div>

        </form>
    </x-wire-card>

    <script>
    (function(){
        const PRODUCTS   = {!! $JS_PRODUCTS !!};
        const SEED_ITEMS = {!! $JS_SEED !!};

        const byCode = {};
        PRODUCTS.forEach(p => {
            if (p.codigo) byCode[p.codigo.toLowerCase()] = p;
            if (p.sku)    byCode[p.sku.toLowerCase()]    = p;
        });

        let items = [];
        let searchTimeout = null;
        let selectedSearchProduct = null;

        const $ = id => document.getElementById(id);

        // ── Render ──────────────────────────────────────────────────────
        function renderAll() {
            const tbody = $('items-body');
            tbody.innerHTML = '';

            if (!items.length) {
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-gray-400 text-sm">
                    Usa el buscador o scanner para agregar productos</td></tr>`;
                $('items-count').textContent = 0;
                return;
            }

            items.forEach((it, i) => {
                const p  = PRODUCTS.find(p => p.id == it.product_id);
                const tr = document.createElement('tr');
                tr.className   = 'border-b hover:bg-gray-50';
                tr.dataset.idx = i;
                tr.innerHTML = `
                    <input type="hidden" name="items[${i}][product_id]" value="${it.product_id}">
                    <td class="p-2 font-medium text-gray-800">${escHtml(p?.nombre || it.nombre || '—')}</td>
                    <td class="p-2 text-xs text-gray-400 font-mono">${escHtml(p?.codigo || p?.sku || '')}</td>
                    <td class="p-2 text-right">
                        <input type="number" min="0.001" step="0.001"
                               name="items[${i}][qty]"
                               value="${it.qty}"
                               class="w-28 border rounded p-1 text-right text-sm inp-qty" required>
                    </td>
                    <td class="p-2 text-center">
                        <button type="button" class="text-red-400 hover:text-red-600 text-xs btn-remove">✕</button>
                    </td>
                `;
                tr.querySelector('.inp-qty').addEventListener('input', function() {
                    items[i].qty = parseFloat(this.value) || 0;
                });
                tr.querySelector('.btn-remove').addEventListener('click', function() {
                    items.splice(i, 1);
                    renderAll();
                });
                tbody.appendChild(tr);
            });

            $('items-count').textContent = items.length;
        }

        function escHtml(str) {
            return String(str||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        // ── Agregar producto ────────────────────────────────────────────
        function addProduct(product, qty = 1) {
            const existing = items.find(it => it.product_id == product.id);
            if (existing) {
                existing.qty += qty;
                renderAll();
                showToast(`+${qty} a ${product.nombre}`);
                return;
            }
            items.push({ product_id: product.id, nombre: product.nombre, qty });
            renderAll();
            showToast(`✓ ${product.nombre} agregado`);
        }

        // ── Toast ───────────────────────────────────────────────────────
        function showToast(msg, color = 'bg-gray-800') {
            let toast = document.getElementById('tf-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'tf-toast';
                toast.className = `fixed bottom-6 right-6 text-white text-sm px-4 py-2 rounded-lg shadow-lg z-50`;
                document.body.appendChild(toast);
            }
            toast.className = `fixed bottom-6 right-6 ${color} text-white text-sm px-4 py-2 rounded-lg shadow-lg z-50`;
            toast.textContent = msg;
            toast.style.opacity = '1';
            clearTimeout(toast._t);
            toast._t = setTimeout(() => toast.style.opacity = '0', 2500);
        }

        // ── Resultados búsqueda ─────────────────────────────────────────
        function renderSearchResults(results) {
            const box = $('search-results');
            if (!results.length) {
                box.classList.add('hidden');
                return;
            }
            box.classList.remove('hidden');
            box.innerHTML = results.map(p => `
                <div class="flex items-center justify-between px-3 py-2 hover:bg-indigo-50 cursor-pointer border-b last:border-0 search-item"
                     data-id="${p.id}">
                    <div>
                        <span class="font-medium text-sm text-gray-800">${escHtml(p.nombre)}</span>
                        ${p.codigo ? `<span class="ml-2 text-xs text-gray-400 font-mono">${escHtml(p.codigo)}</span>` : ''}
                    </div>
                    <span class="text-xs text-indigo-600 ml-3">+ Agregar</span>
                </div>
            `).join('');

            box.querySelectorAll('.search-item').forEach(el => {
                el.addEventListener('click', function() {
                    const id = parseInt(this.dataset.id);
                    const p  = PRODUCTS.find(p => p.id === id);
                    if (p) {
                        addProduct(p);
                        $('product-search').value = '';
                        box.classList.add('hidden');
                        selectedSearchProduct = null;
                    }
                });
            });
        }

        // ── Scanner ─────────────────────────────────────────────────────
        let scanBuffer  = '';
        let scanTimeout = null;

        document.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('inp-qty')) return;
            if (e.target.id === 'product-search') return; // La búsqueda maneja su propio input

            if (e.key === 'Enter' && scanBuffer.length > 2) {
                e.preventDefault();
                const code = scanBuffer.trim().toLowerCase();
                const p    = byCode[code];
                if (p) {
                    addProduct(p);
                } else {
                    showToast('⚠ Código no encontrado: ' + scanBuffer.trim(), 'bg-amber-600');
                }
                scanBuffer = '';
                return;
            }

            if (e.key.length === 1) {
                scanBuffer += e.key;
                clearTimeout(scanTimeout);
                scanTimeout = setTimeout(() => { scanBuffer = ''; }, 200);
            }
        });

        // ── API pública ─────────────────────────────────────────────────
        window.TF = {
            validate() {
                const from = document.querySelector('[name="from_warehouse_id"]').value;
                const to   = document.querySelector('[name="to_warehouse_id"]').value;

                if (!from || !to) {
                    showToast('⚠ Selecciona almacén origen y destino', 'bg-red-600');
                    return false;
                }
                if (from === to) {
                    showToast('⚠ El origen y destino no pueden ser el mismo almacén', 'bg-red-600');
                    document.querySelector('[name="to_warehouse_id"]').classList.add('border-red-500', 'ring-1', 'ring-red-500');
                    setTimeout(() => {
                        document.querySelector('[name="to_warehouse_id"]').classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                    }, 3000);
                    return false;
                }
                if (!items.length) {
                    showToast('⚠ Agrega al menos un producto', 'bg-red-600');
                    return false;
                }
                return true;
            },

            addFromSearch() {
                if (selectedSearchProduct) {
                    addProduct(selectedSearchProduct);
                    $('product-search').value = '';
                    $('search-results').classList.add('hidden');
                    selectedSearchProduct = null;
                }
            },

            clearSearch() {
                $('product-search').value = '';
                $('search-results').classList.add('hidden');
                selectedSearchProduct = null;
            },
        };

        // ── Input búsqueda ──────────────────────────────────────────────
        $('product-search').addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            clearTimeout(searchTimeout);
            selectedSearchProduct = null;

            if (!q) {
                $('search-results').classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                const results = PRODUCTS.filter(p =>
                    p.nombre.toLowerCase().includes(q) ||
                    (p.codigo && p.codigo.toLowerCase().includes(q)) ||
                    (p.sku    && p.sku.toLowerCase().includes(q))
                ).slice(0, 10);
                renderSearchResults(results);
                // Si hay un resultado exacto por código, pre-seleccionarlo
                const exact = PRODUCTS.find(p =>
                    (p.codigo && p.codigo.toLowerCase() === q) ||
                    (p.sku    && p.sku.toLowerCase() === q)
                );
                if (exact) selectedSearchProduct = exact;
            }, 150);
        });

        // Enter en buscador = agregar primer resultado
        $('product-search').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                TF.addFromSearch();
            }
        });

        // Cerrar resultados al click fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#search-results') && e.target.id !== 'product-search') {
                $('search-results').classList.add('hidden');
            }
        });

        // ── Init ────────────────────────────────────────────────────────
        if (SEED_ITEMS && SEED_ITEMS.length) {
            SEED_ITEMS.forEach(it => {
                const p = PRODUCTS.find(p => p.id == it.product_id);
                if (p) items.push({ product_id: p.id, nombre: p.nombre, qty: it.qty || 1 });
            });
        }
        renderAll();

    })();
    </script>

</x-admin-layout>