<x-admin-layout
    title="Punto de venta"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'POS'],
        ['name'=>'Venta'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.cash.show', $reg) }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Ver caja
        </a>
    </x-slot>

    <style>
        /* ── Orientación portrait (vertical) ── */
        @media (orientation: portrait) {
            .pos-grid { display: flex; flex-direction: column; gap: 1rem; }
            .pos-left  { order: 1; }
            .pos-right { order: 2; }
            .pos-cobrar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; padding: 12px 16px; background: white; border-top: 1px solid #e5e7eb; }
            .pos-cobrar-spacer { height: 80px; }
        }
        /* ── Orientación landscape (horizontal) ── */
        @media (orientation: landscape) {
            .pos-grid { display: grid; grid-template-columns: 1fr 320px; gap: 1rem; align-items: start; }
            .pos-left  { order: 1; }
            .pos-right { order: 2; }
            .pos-cobrar { display: block; }
            .pos-cobrar-spacer { display: none; }
        }
        /* Touch: botones más grandes */
        .btn-touch {
            min-height: 44px;
            min-width: 44px;
        }
        /* Inputs grandes para touch */
        .input-touch {
            font-size: 16px !important; /* evita zoom en iOS */
            padding: 10px 12px !important;
        }
        /* Resaltar fila al tocar */
        .item-row:active { background: #eff6ff; }
    </style>

    <form id="pos-form" action="{{ route('admin.pos.store') }}" method="POST">
        @csrf
        <input type="hidden" name="cash_register_id" value="{{ $reg->id }}">

        <div class="pos-grid">

            {{-- ===== IZQUIERDA: buscador + partidas ===== --}}
            <div class="pos-left space-y-3">

                {{-- Buscador --}}
                <x-wire-card>
                    <div class="relative">
                        <input type="text" id="product-search"
                            placeholder="🔍 Buscar por nombre o SKU..."
                            autocomplete="off"
                            inputmode="text"
                            class="input-touch w-full rounded-md border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <div id="search-results"
                             class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-xl max-h-72 overflow-y-auto">
                        </div>
                    </div>
                </x-wire-card>

                {{-- Partidas --}}
                <x-wire-card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Producto</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500 w-36">Cant.</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-500 w-28">Precio</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-500 w-24">Desc.</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-500 w-28">Importe</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="items-body">
                                <tr id="empty-row">
                                    <td colspan="6" class="px-3 py-10 text-center text-gray-400 text-sm">
                                        Busca un producto para agregarlo
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-wire-card>

            </div>

            {{-- ===== DERECHA: cliente + resumen + cobro ===== --}}
            <div class="pos-right space-y-3">

                {{-- Cliente + Fecha --}}
                <x-wire-card>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id"
                        class="input-touch w-full rounded-md border border-gray-300 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Público en general</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>

                    <label class="block text-sm font-medium text-gray-700 mb-1 mt-3">Fecha</label>
                    <input type="datetime-local" name="fecha"
                        value="{{ now()->format('Y-m-d\TH:i') }}"
                        class="input-touch w-full rounded-md border border-gray-300 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </x-wire-card>

                {{-- Resumen --}}
                <x-wire-card>
                    <h3 class="font-semibold mb-3 text-gray-700">Resumen</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span id="sum-subtotal" class="font-mono">$0.00</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Descuento</span>
                            <span id="sum-descuento" class="font-mono">$0.00</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Impuestos</span>
                            <span id="sum-impuestos" class="font-mono">$0.00</span>
                        </div>
                        <div class="flex justify-between text-xl font-bold pt-2 border-t">
                            <span>Total</span>
                            <span id="sum-total" class="text-indigo-600 font-mono">$0.00</span>
                        </div>
                    </div>
                </x-wire-card>

                {{-- Cobro --}}
                <x-wire-card>
                    <h3 class="font-semibold mb-3 text-gray-700">Cobro</h3>

                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="metodo_pago" id="metodo_pago"
                        class="input-touch w-full rounded-md border border-gray-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 mb-3">
                        @foreach(['EFECTIVO','TARJETA','TRANSFERENCIA','MIXTO','OTRO'] as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Efectivo recibido</label>
                            <input type="number" name="efectivo" id="efectivo"
                                step="0.01" value="0" inputmode="decimal"
                                class="input-touch w-full rounded-md border border-gray-300 text-right font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cambio</label>
                            <input type="number" name="cambio" id="cambio"
                                step="0.01" value="0" readonly
                                class="input-touch w-full rounded-md border border-gray-200 bg-gray-50 text-right font-mono"
                            />
                        </div>
                    </div>

                    <label class="block text-sm font-medium text-gray-700 mb-1 mt-3">Referencia</label>
                    <input type="text" name="referencia"
                        class="input-touch w-full rounded-md border border-gray-300 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />

                    {{-- Botones rápidos de billete --}}
                    <div class="grid grid-cols-3 gap-2 mt-3">
                        @foreach([50, 100, 200, 500, 1000] as $bill)
                        <button type="button" data-bill="{{ $bill }}"
                            class="btn-bill btn-touch px-2 py-2 text-sm font-medium rounded-md border border-gray-300 hover:bg-indigo-50 hover:border-indigo-400 hover:text-indigo-700 active:bg-indigo-100 transition-colors">
                            ${{ number_format($bill) }}
                        </button>
                        @endforeach
                        <button type="button" id="btn-exact"
                            class="btn-touch px-2 py-2 text-sm font-medium rounded-md border border-gray-300 hover:bg-gray-50 active:bg-gray-100">
                            Exacto
                        </button>
                    </div>
                </x-wire-card>

                {{-- Botón cobrar --}}
                <div class="pos-cobrar">
                    <button type="submit" id="btn-cobrar" disabled
                        class="btn-touch w-full py-4 text-lg font-bold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 active:bg-indigo-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        💳 Cobrar
                    </button>
                </div>
                <div class="pos-cobrar-spacer"></div>

            </div>
        </div>
    </form>

    @push('js')
    <script>
    (function () {
        var PRODUCTS = {!! json_encode($productsJson) !!};
        var rowCount  = 0;

        // ── Utilidades ────────────────────────────────────────────────────
        function money(n) {
            return '$' + Number(n || 0).toLocaleString('es-MX', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        }
        function toNum(v) { return parseFloat(v) || 0; }

        function fmtMono(n) {
            return Number(n || 0).toLocaleString('es-MX', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        }

        // ── Buscador ──────────────────────────────────────────────────────
        var searchInput   = document.getElementById('product-search');
        var searchResults = document.getElementById('search-results');

        searchInput.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            if (q.length < 1) { searchResults.classList.add('hidden'); return; }

            var matches = PRODUCTS.filter(function(p) {
                return p.nombre.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q);
            }).slice(0, 20);

            if (!matches.length) {
                searchResults.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">Sin resultados</div>';
                searchResults.classList.remove('hidden');
                return;
            }

            searchResults.innerHTML = matches.map(function(p) {
                return '<div class="search-item px-4 py-4 cursor-pointer hover:bg-indigo-50 active:bg-indigo-100 border-b last:border-0 flex justify-between items-center"'
                    + ' data-id="' + p.id + '">'
                    + '<div>'
                    + '<div class="font-medium text-sm text-gray-900">' + p.nombre + '</div>'
                    + '<div class="text-xs text-gray-400">' + (p.sku || '—') + ' · ' + p.unidad + '</div>'
                    + '</div>'
                    + '<div class="text-sm font-mono font-semibold text-indigo-600">$' + fmtMono(p.precio) + '</div>'
                    + '</div>';
            }).join('');

            searchResults.classList.remove('hidden');

            searchResults.querySelectorAll('.search-item').forEach(function(el) {
                el.addEventListener('click', function() {
                    var product = PRODUCTS.find(function(p) { return p.id == el.dataset.id; });
                    if (product) addRow(product);
                    searchInput.value = '';
                    searchResults.classList.add('hidden');
                    searchInput.focus();
                });
            });
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });

        // ── Agregar fila ──────────────────────────────────────────────────
        function addRow(product) {
            var emptyRow = document.getElementById('empty-row');
            if (emptyRow) emptyRow.remove();

            // Si ya existe, incrementar cantidad
            var existing = document.querySelector('[data-product-id="' + product.id + '"]');
            if (existing) {
                var cantInput = existing.querySelector('.item-cant');
                cantInput.value = (toNum(cantInput.value) + 1).toFixed(3);
                recalcRow(existing);
                recalcTotals();
                return;
            }

            var idx = rowCount++;
            var tr  = document.createElement('tr');
            tr.className = 'border-b item-row';
            tr.dataset.productId = product.id;

            tr.innerHTML =
                '<input type="hidden" name="items[' + idx + '][product_id]" value="' + product.id + '">'
                + '<input type="hidden" name="items[' + idx + '][impuestos]" class="item-imp" value="' + (product.tasa_iva || 0) + '">'
                + '<td class="px-3 py-2">'
                +   '<div class="font-medium text-sm leading-tight">' + product.nombre + '</div>'
                +   '<div class="text-xs text-gray-400">' + (product.sku || '—') + ' · ' + product.unidad + '</div>'
                + '</td>'
                + '<td class="px-2 py-2">'
                +   '<div class="flex items-center gap-1 justify-center">'
                +     '<button type="button" class="btn-minus btn-touch w-9 h-9 rounded-full border border-gray-300 text-xl font-bold leading-none hover:bg-gray-100 active:bg-gray-200 flex items-center justify-center">−</button>'
                +     '<input name="items[' + idx + '][cantidad]" type="number" step="0.001" min="0.001" value="1.000"'
                +       ' inputmode="decimal"'
                +       ' class="item-cant w-16 text-center rounded-md border border-gray-300 py-1 text-sm font-mono">'
                +     '<button type="button" class="btn-plus btn-touch w-9 h-9 rounded-full border border-gray-300 text-xl font-bold leading-none hover:bg-gray-100 active:bg-gray-200 flex items-center justify-center">+</button>'
                +   '</div>'
                + '</td>'
                + '<td class="px-2 py-2">'
                +   '<input name="items[' + idx + '][precio_unitario]" type="number" step="0.01" value="' + product.precio.toFixed(2) + '"'
                +     ' inputmode="decimal"'
                +     ' class="item-precio w-full text-right rounded-md border border-gray-300 py-1 text-sm font-mono">'
                + '</td>'
                + '<td class="px-2 py-2">'
                +   '<input name="items[' + idx + '][descuento]" type="number" step="0.01" value="0"'
                +     ' inputmode="decimal"'
                +     ' class="item-desc w-full text-right rounded-md border border-gray-300 py-1 text-sm font-mono">'
                + '</td>'
                + '<td class="px-3 py-2 text-right font-mono text-sm font-semibold">'
                +   '<span class="item-importe">0.00</span>'
                + '</td>'
                + '<td class="px-2 py-2 text-center">'
                +   '<button type="button" class="btn-del btn-touch w-9 h-9 rounded-full text-red-500 hover:bg-red-50 active:bg-red-100 text-xl flex items-center justify-center">×</button>'
                + '</td>';

            document.getElementById('items-body').appendChild(tr);
            bindRow(tr);
            recalcRow(tr);
            recalcTotals();
        }

        // ── Bind eventos de fila ──────────────────────────────────────────
        function bindRow(tr) {
            var cantInp  = tr.querySelector('.item-cant');
            var precioInp = tr.querySelector('.item-precio');

            // Botones +/−
            tr.querySelector('.btn-minus').addEventListener('click', function() {
                cantInp.value = Math.max(0.001, toNum(cantInp.value) - 1).toFixed(3);
                recalcRow(tr); recalcTotals();
            });
            tr.querySelector('.btn-plus').addEventListener('click', function() {
                cantInp.value = (toNum(cantInp.value) + 1).toFixed(3);
                recalcRow(tr); recalcTotals();
            });

            // Cantidad: al perder foco interpretar entero >= 100 como gramos
            cantInp.addEventListener('blur', function() {
                var val = this.value.trim();
                if (val && !val.includes('.') && parseInt(val) >= 100) {
                    this.value = (parseInt(val) / 1000).toFixed(3);
                } else {
                    this.value = parseFloat(val || 0).toFixed(3);
                }
                recalcRow(tr); recalcTotals();
            });
            cantInp.addEventListener('focus', function() { this.select(); });
            cantInp.addEventListener('input', function() { recalcRow(tr); recalcTotals(); });

            // Precio: seleccionar al foco
            precioInp.addEventListener('focus', function() { this.select(); });
            precioInp.addEventListener('input', function() { recalcRow(tr); recalcTotals(); });

            // Descuento
            tr.querySelector('.item-desc').addEventListener('input', function() { recalcRow(tr); recalcTotals(); });

            // Eliminar
            tr.querySelector('.btn-del').addEventListener('click', function() {
                tr.remove();
                if (!document.querySelector('.item-row')) {
                    document.getElementById('items-body').innerHTML =
                        '<tr id="empty-row"><td colspan="6" class="px-3 py-10 text-center text-gray-400 text-sm">Busca un producto para agregarlo</td></tr>';
                }
                recalcTotals();
            });
        }

        // ── Recalcular fila ───────────────────────────────────────────────
        function recalcRow(tr) {
            var cant    = toNum(tr.querySelector('.item-cant').value);
            var precio  = toNum(tr.querySelector('.item-precio').value);
            var desc    = toNum(tr.querySelector('.item-desc').value);
            var importe = (cant * precio) - desc;
            tr.querySelector('.item-importe').textContent = fmtMono(importe);
        }

        // ── Recalcular totales ────────────────────────────────────────────
        function recalcTotals() {
            var sub = 0, des = 0, imp = 0;
            document.querySelectorAll('.item-row').forEach(function(tr) {
                var cant     = toNum(tr.querySelector('.item-cant').value);
                var precio   = toNum(tr.querySelector('.item-precio').value);
                var desc     = toNum(tr.querySelector('.item-desc').value);
                var tasaIva  = toNum(tr.querySelector('.item-imp').value);
                var lineaSub = cant * precio;
                sub += lineaSub;
                des += desc;
                imp += lineaSub * (tasaIva / 100);
            });
            var total = sub - des + imp;
            document.getElementById('sum-subtotal').textContent  = money(sub);
            document.getElementById('sum-descuento').textContent = money(des);
            document.getElementById('sum-impuestos').textContent = money(imp);
            document.getElementById('sum-total').textContent     = money(total);

            var efectivo = toNum(document.getElementById('efectivo').value);
            document.getElementById('cambio').value = Math.max(0, efectivo - total).toFixed(2);
            document.getElementById('btn-cobrar').disabled = !document.querySelector('.item-row');
        }

        // ── Botones de billete ────────────────────────────────────────────
        document.querySelectorAll('.btn-bill').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('efectivo').value = this.dataset.bill;
                recalcTotals();
            });
        });
        document.getElementById('btn-exact').addEventListener('click', function() {
            var totalText = document.getElementById('sum-total').textContent.replace(/[$,]/g, '');
            document.getElementById('efectivo').value = parseFloat(totalText).toFixed(2);
            recalcTotals();
        });
        document.getElementById('efectivo').addEventListener('input', recalcTotals);
        document.getElementById('efectivo').addEventListener('focus', function() { this.select(); });

        recalcTotals();
    })();
    </script>
    @endpush

</x-admin-layout>