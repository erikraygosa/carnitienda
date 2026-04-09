<x-admin-layout
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Órdenes de compra','url'=>route('admin.purchase-orders.index')],
        ['name'=>'Crear'],
    ]"
    title="Crear orden de compra"
>
    <x-slot name="action">
        <a href="{{ route('admin.purchase-orders.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Cancelar</a>
        <button form="po-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    <x-wire-card>
        <form id="po-form" method="POST" action="{{ route('admin.purchase-orders.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2 space-y-1">
                    <label for="provider_id" class="block text-sm font-medium text-gray-700">Proveedor <span class="text-red-500">*</span></label>
                    <select name="provider_id" id="provider_id" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- seleccionar --</option>
                        @foreach($providers as $p)
                            <option value="{{ $p->id }}" {{ old('provider_id') == $p->id ? 'selected' : '' }}>
                                {{ $p->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('provider_id')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div class="space-y-1">
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Almacén <span class="text-red-500">*</span></label>
                    <select name="warehouse_id" id="warehouse_id" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="date" :value="now()->toDateString()" required />
                </div>
                <div>
                    <x-wire-input label="Entrega estimada" name="expected_at" type="date" />
                </div>
                <div>
                    <x-wire-input label="Moneda" name="currency" :value="old('currency','MXN')" />
                </div>
                <div class="space-y-2 w-full">
                    <label for="payment_method" class="block text-sm font-medium text-gray-700">Método de pago</label>
                    <select name="payment_method" id="payment_method"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="EFECTIVO">Efectivo</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="CONTRAENTREGA">Contraentrega</option>
                        <option value="CREDITO">Crédito</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <x-wire-textarea label="Observaciones" name="observaciones">{{ old('observaciones') }}</x-wire-textarea>
                </div>
            </div>

            <div class="overflow-auto">
                <table class="min-w-full text-sm" id="items-table">
                    <thead class="border-b">
                        <tr>
                            <th class="text-left p-2">Producto</th>
                            <th class="text-right p-2">Cant.</th>
                            <th class="text-right p-2">Precio</th>
                            <th class="text-right p-2">Desc.</th>
                            <th class="text-right p-2">% IVA</th>
                            <th class="text-right p-2">Total</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        {{-- Las filas se generan con JS --}}
                    </tbody>
                </table>
                <div class="mt-2">
                    <x-wire-button type="button" gray id="btn-add-item">Agregar partida</x-wire-button>
                </div>
            </div>

            <div class="text-right space-y-1">
                <div>Subtotal: <span id="lbl-subtotal">0.00</span></div>
                <div>Descuento: <span id="lbl-discount">0.00</span></div>
                <div>Impuestos: <span id="lbl-tax">0.00</span></div>
                <div class="font-semibold text-lg">Total: <span id="lbl-grand">0.00</span></div>
            </div>
        </form>
    </x-wire-card>

    {{-- Productos disponibles para el select --}}
    <script>
        const PRODUCTS = @json($products->map(fn($p) => ['id' => $p->id, 'nombre' => $p->nombre]));
    </script>

    <script>
        (() => {
            /* ─── Estado ─── */
            let items = [];

            /* ─── Referencias DOM ─── */
            const tbody      = document.getElementById('items-body');
            const btnAdd     = document.getElementById('btn-add-item');
            const lblSub     = document.getElementById('lbl-subtotal');
            const lblDisc    = document.getElementById('lbl-discount');
            const lblTax     = document.getElementById('lbl-tax');
            const lblGrand   = document.getElementById('lbl-grand');

            /* ─── Helpers ─── */
            const fmt  = n  => Number(n || 0).toFixed(2);
            const num  = v  => parseFloat(v) || 0;

            function calcLine(item) {
                const lineSub = num(item.qty)   * num(item.price);
                const disc    = num(item.discount);
                const base    = Math.max(lineSub - disc, 0);
                const tax     = num(item.tax_rate) * 0.01 * base;
                item.total    = base + tax;
            }

            function updateTotals() {
                let subtotal = 0, discountTotal = 0, taxTotal = 0, grand = 0;
                items.forEach(it => {
                    const lineSub = num(it.qty) * num(it.price);
                    const disc    = num(it.discount);
                    const base    = Math.max(lineSub - disc, 0);
                    const tax     = num(it.tax_rate) * 0.01 * base;
                    subtotal      += lineSub;
                    discountTotal += disc;
                    taxTotal      += tax;
                    grand         += base + tax;
                });
                lblSub.textContent   = fmt(subtotal);
                lblDisc.textContent  = fmt(discountTotal);
                lblTax.textContent   = fmt(taxTotal);
                lblGrand.textContent = fmt(grand);
            }

            /* ─── Opciones de productos ─── */
            function buildProductOptions(selectedId) {
                return PRODUCTS.map(p => {
                    const sel = String(p.id) === String(selectedId) ? 'selected' : '';
                    return `<option value="${p.id}" ${sel}>${p.nombre}</option>`;
                }).join('');
            }

            /* ─── Render de una fila ─── */
            function renderRow(i, item) {
                const tr = document.createElement('tr');
                tr.className   = 'border-b';
                tr.dataset.idx = i;

                tr.innerHTML = `
                    <td class="p-2">
                        <select class="w-full border rounded p-1" name="items[${i}][product_id]" required>
                            <option value="">-- seleccionar --</option>
                            ${buildProductOptions(item.product_id)}
                        </select>
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0.001" step="0.001"
                            class="w-28 border rounded p-1 text-right"
                            name="items[${i}][qty_ordered]"
                            value="${item.qty}" required>
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0" step="0.01"
                            class="w-28 border rounded p-1 text-right"
                            name="items[${i}][price]"
                            value="${item.price}" required>
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0" step="0.01"
                            class="w-24 border rounded p-1 text-right"
                            name="items[${i}][discount]"
                            value="${item.discount}">
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0" step="0.01"
                            class="w-20 border rounded p-1 text-right"
                            name="items[${i}][tax_rate]"
                            value="${item.tax_rate}">
                    </td>
                    <td class="p-2 text-right item-total">${fmt(item.total)}</td>
                    <td class="p-2">
                        <button type="button" class="text-red-600 btn-remove">Eliminar</button>
                    </td>
                `;

                /* Guardar product_id al cambiar el select */
                tr.querySelector('select').addEventListener('change', function() {
                    items[parseInt(tr.dataset.idx)].product_id = this.value;
                });

                /* Eventos de inputs numéricos */
                tr.querySelectorAll('input').forEach(input => {
                    input.addEventListener('input', () => syncRow(tr));
                });

                /* Evento eliminar */
                tr.querySelector('.btn-remove').addEventListener('click', () => {
                    removeRow(parseInt(tr.dataset.idx));
                });

                return tr;
            }

            /* Sincroniza inputs numéricos DOM → items[] y recalcula */
            function syncRow(tr) {
                const i    = parseInt(tr.dataset.idx);
                const item = items[i];
                if (!item) return;

                const inputs  = tr.querySelectorAll('input');
                item.qty      = num(inputs[0].value);
                item.price    = num(inputs[1].value);
                item.discount = num(inputs[2].value);
                item.tax_rate = num(inputs[3].value);

                calcLine(item);
                tr.querySelector('.item-total').textContent = fmt(item.total);
                updateTotals();
            }

            /* Guarda el estado actual del DOM en items[] antes de re-renderizar */
            function snapshotDOM() {
                tbody.querySelectorAll('tr[data-idx]').forEach(tr => {
                    const i    = parseInt(tr.dataset.idx);
                    const item = items[i];
                    if (!item) return;
                    item.product_id = tr.querySelector('select').value;
                    const inputs    = tr.querySelectorAll('input');
                    item.qty        = num(inputs[0].value);
                    item.price      = num(inputs[1].value);
                    item.discount   = num(inputs[2].value);
                    item.tax_rate   = num(inputs[3].value);
                    calcLine(item);
                });
            }

            /* ─── Re-render completo del tbody ─── */
            function renderAll() {
                snapshotDOM();
                tbody.innerHTML = '';
                items.forEach((item, i) => {
                    tbody.appendChild(renderRow(i, item));
                });
                updateTotals();
            }

            /* ─── Acciones ─── */
            function addItem() {
                snapshotDOM();
                items.push({ product_id: '', qty: 1, price: 0, discount: 0, tax_rate: 0, total: 0 });
                renderAll();
            }

            function removeRow(i) {
                snapshotDOM();
                items.splice(i, 1);
                renderAll();
            }

            /* ─── Init ─── */
            btnAdd.addEventListener('click', addItem);
            addItem(); // Fila inicial
        })();
    </script>
</x-admin-layout>