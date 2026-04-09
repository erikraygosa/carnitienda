<x-admin-layout
    title="Crear compra"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Compras','url'=>route('admin.purchases.index')],
        ['name'=>'Crear'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.purchases.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">
            Regresar
        </a>
        <button form="purchase-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    @php
        $selProvider  = (string) old('provider_id',  isset($order) ? $order->provider_id  : '');
        $selWarehouse = (string) old('warehouse_id', isset($order) ? $order->warehouse_id : '');
        $valueFecha   = old('fecha', now()->toDateString());
        $valueMoneda  = old('currency', 'MXN');
        $valueNotas   = old('notas', '');
        $valuePayment = old('payment_method', 'EFECTIVO');

        if (!isset($seedItems)) {
            if (isset($order)) {
                $seedItems = $order->items->map(function ($i) {
                    return [
                        'product_id'   => $i->product_id,
                        'qty_received' => (float) $i->qty_ordered,
                        'price'        => (float) $i->price,
                        'discount'     => (float) ($i->discount ?? 0),
                        'tax_rate'     => (float) ($i->tax_rate ?? 0),
                        'total'        => 0,
                    ];
                })->values()->toArray();
            } else {
                $seedItems = [];
            }
        }
    @endphp

    <x-wire-card>
        <form id="purchase-form"
              method="POST"
              action="{{ route('admin.purchases.store') }}"
              class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Proveedor --}}
                <div class="md:col-span-2 space-y-1">
                    <label for="provider_id" class="block text-sm font-medium text-gray-700">
                        Proveedor <span class="text-red-500">*</span>
                    </label>
                    <select name="provider_id" id="provider_id" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- seleccionar --</option>
                        @foreach($providers as $p)
                            <option value="{{ $p->id }}" {{ $selProvider === (string)$p->id ? 'selected' : '' }}>
                                {{ $p->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('provider_id')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Almacén --}}
                <div class="space-y-1">
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">
                        Almacén <span class="text-red-500">*</span>
                    </label>
                    <select name="warehouse_id" id="warehouse_id" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse === (string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Fecha --}}
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="date" :value="$valueFecha" required />
                </div>

                {{-- Moneda --}}
                <div>
                    <x-wire-input label="Moneda" name="currency" :value="$valueMoneda" required />
                </div>

                {{-- Método de pago --}}
                <div class="space-y-1">
                    <label for="payment_method" class="block text-sm font-medium text-gray-700">Método de pago</label>
                    <select name="payment_method" id="payment_method"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="EFECTIVO"      {{ $valuePayment === 'EFECTIVO'      ? 'selected' : '' }}>Efectivo</option>
                        <option value="TRANSFERENCIA" {{ $valuePayment === 'TRANSFERENCIA' ? 'selected' : '' }}>Transferencia</option>
                        <option value="CONTRAENTREGA" {{ $valuePayment === 'CONTRAENTREGA' ? 'selected' : '' }}>Contraentrega</option>
                        <option value="CREDITO"       {{ $valuePayment === 'CREDITO'       ? 'selected' : '' }}>Crédito</option>
                    </select>
                    @error('payment_method')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Notas --}}
                <div class="md:col-span-4">
                    <x-wire-textarea label="Notas" name="notas">{{ $valueNotas }}</x-wire-textarea>
                </div>
            </div>

            {{-- ID de OC si viene de una --}}
            @if(isset($order))
                <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">
            @endif

            {{-- Partidas --}}
            <div class="overflow-auto">
                <table class="min-w-full text-sm" id="items-table">
                    <thead class="border-b">
                        <tr>
                            <th class="text-left p-2">Producto</th>
                            <th class="text-right p-2">Recibido</th>
                            <th class="text-right p-2">Precio</th>
                            <th class="text-right p-2">Desc.</th>
                            <th class="text-right p-2">% IVA</th>
                            <th class="text-right p-2">Total</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        {{-- Filas generadas por JS --}}
                    </tbody>
                </table>

                <div class="mt-2">
                    <x-wire-button type="button" gray id="btn-add-item">Agregar partida</x-wire-button>
                </div>
            </div>

            {{-- Totales --}}
            <div class="text-right space-y-1">
                <div>Subtotal: <span id="lbl-subtotal">0.00</span></div>
                <div>Descuento: <span id="lbl-discount">0.00</span></div>
                <div>Impuestos: <span id="lbl-tax">0.00</span></div>
                <div class="font-semibold text-lg">Total: <span id="lbl-grand">0.00</span></div>
            </div>
        </form>
    </x-wire-card>

    {{-- Datos para JS --}}
    <script>
        const PRODUCTS   = @json($products->map(fn($p) => ['id' => $p->id, 'nombre' => $p->nombre]));
        const ITEMS_SEED = @json($seedItems);
    </script>

    <script>
        (() => {
            /* ─── Estado ─── */
            let items = (ITEMS_SEED && ITEMS_SEED.length)
                ? ITEMS_SEED.map(s => ({ ...s }))
                : [{ product_id: '', qty_received: 1, price: 0, discount: 0, tax_rate: 0, total: 0 }];

            /* ─── Referencias DOM ─── */
            const tbody    = document.getElementById('items-body');
            const btnAdd   = document.getElementById('btn-add-item');
            const lblSub   = document.getElementById('lbl-subtotal');
            const lblDisc  = document.getElementById('lbl-discount');
            const lblTax   = document.getElementById('lbl-tax');
            const lblGrand = document.getElementById('lbl-grand');

            /* ─── Helpers ─── */
            const fmt = n => Number(n || 0).toFixed(2);
            const num = v => parseFloat(v) || 0;

            function calcLine(item) {
                const line = num(item.qty_received) * num(item.price);
                const disc = num(item.discount);
                const base = Math.max(line - disc, 0);
                const tax  = num(item.tax_rate) * 0.01 * base;
                item.total = base + tax;
            }

            function updateTotals() {
                let subtotal = 0, discountTotal = 0, taxTotal = 0, grand = 0;
                items.forEach(it => {
                    const line = num(it.qty_received) * num(it.price);
                    const disc = num(it.discount);
                    const base = Math.max(line - disc, 0);
                    const tax  = num(it.tax_rate) * 0.01 * base;
                    subtotal      += line;
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
                        <select class="w-full border rounded p-1"
                                name="items[${i}][product_id]" required>
                            <option value="">-- seleccionar --</option>
                            ${buildProductOptions(item.product_id)}
                        </select>
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0.001" step="0.001"
                               class="w-28 border rounded p-1 text-right"
                               name="items[${i}][qty_received]"
                               value="${item.qty_received}" required>
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

                tr.querySelectorAll('input').forEach(input => {
                    input.addEventListener('input', () => syncRow(tr));
                });

                tr.querySelector('.btn-remove').addEventListener('click', () => {
                    removeRow(parseInt(tr.dataset.idx));
                });

                return tr;
            }

            /* ─── Sincroniza DOM → items[] ─── */
            function syncRow(tr) {
                const i    = parseInt(tr.dataset.idx);
                const item = items[i];
                if (!item) return;

                const inputs      = tr.querySelectorAll('input');
                item.qty_received = num(inputs[0].value);
                item.price        = num(inputs[1].value);
                item.discount     = num(inputs[2].value);
                item.tax_rate     = num(inputs[3].value);

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
                    item.qty_received = num(inputs[0].value);
                    item.price        = num(inputs[1].value);
                    item.discount     = num(inputs[2].value);
                    item.tax_rate     = num(inputs[3].value);
                    calcLine(item);
                });
            }

            /* ─── Re-render completo ─── */
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
                items.push({ product_id: '', qty_received: 1, price: 0, discount: 0, tax_rate: 0, total: 0 });
                renderAll();
            }

            function removeRow(i) {
                snapshotDOM();
                items.splice(i, 1);
                renderAll();
            }

            /* ─── Init ─── */
            btnAdd.addEventListener('click', addItem);
            renderAll(); // carga inicial con seed (o fila vacía)
        })();
    </script>
</x-admin-layout>