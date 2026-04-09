<x-admin-layout
    title="Editar compra"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Compras','url'=>route('admin.purchases.index')],
        ['name'=>'Editar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.purchases.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">
            Regresar
        </a>
        @if($purchase->status === 'draft')
            <button form="purchase-edit-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
    </x-slot>

    @php
        $selProvider   = (string) old('provider_id',  $purchase->provider_id);
        $selWarehouse  = (string) old('warehouse_id', $purchase->warehouse_id);
        $isLocked      = $purchase->status !== 'draft';

        $valueFecha    = old('fecha', optional($purchase->fecha)->toDateString());
        $valueCurrency = old('currency', $purchase->currency);
        $valueNotas    = old('notas', $purchase->notas);
        $valuePayment  = old('payment_method', $purchase->payment_method ?? 'EFECTIVO');

        $statusMap = [
            'draft'     => 'Borrador',
            'received'  => 'Recibida',
            'cancelled' => 'Cancelada',
        ];
        $statusLabel = $statusMap[$purchase->status] ?? strtoupper($purchase->status);

        $statusClasses = [
            'draft'     => 'bg-gray-100 text-gray-700',
            'received'  => 'bg-emerald-100 text-emerald-700',
            'cancelled' => 'bg-rose-100 text-rose-700',
        ];
        $statusClass = $statusClasses[$purchase->status] ?? 'bg-slate-100 text-slate-700';

        $itemsSeed = $purchase->items->map(function($i){
            return [
                'product_id'   => $i->product_id,
                'qty_received' => (float) $i->qty_received,
                'price'        => (float) $i->price,
                'discount'     => 0.0,
                'tax_rate'     => 0.0,
                'total'        => (float) $i->total,
            ];
        })->values()->toArray();
    @endphp

    <x-wire-card>
        <form id="purchase-edit-form"
              method="POST"
              action="{{ route('admin.purchases.update',$purchase) }}"
              class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Proveedor --}}
                <div class="md:col-span-2 space-y-1">
                    <label for="provider_id" class="block text-sm font-medium text-gray-700">
                        Proveedor @if(!$isLocked)<span class="text-red-500">*</span>@endif
                    </label>
                    <select name="provider_id" id="provider_id" required
                        {{ $isLocked ? 'disabled' : '' }}
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $isLocked ? 'bg-gray-100 cursor-not-allowed' : '' }}">
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
                        Almacén @if(!$isLocked)<span class="text-red-500">*</span>@endif
                    </label>
                    <select name="warehouse_id" id="warehouse_id" required
                        {{ $isLocked ? 'disabled' : '' }}
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $isLocked ? 'bg-gray-100 cursor-not-allowed' : '' }}">
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
                    <x-wire-input
                        label="Fecha"
                        name="fecha"
                        type="date"
                        :value="$valueFecha"
                        :disabled="$isLocked"
                        required
                    />
                </div>

                {{-- Moneda --}}
                <div>
                    <x-wire-input
                        label="Moneda"
                        name="currency"
                        :value="$valueCurrency"
                        :disabled="$isLocked"
                        required
                    />
                </div>

                {{-- Método de pago --}}
                <div class="space-y-1">
                    <label for="payment_method" class="block text-sm font-medium text-gray-700">Método de pago</label>
                    <select name="payment_method" id="payment_method"
                        {{ $isLocked ? 'disabled' : '' }}
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $isLocked ? 'bg-gray-100 cursor-not-allowed' : '' }}">
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
                    <x-wire-textarea label="Notas" name="notas" :disabled="$isLocked">
                        {{ $valueNotas }}
                    </x-wire-textarea>
                </div>
            </div>

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

                @if(!$isLocked)
                    <div class="mt-2">
                        <x-wire-button type="button" gray id="btn-add-item">Agregar partida</x-wire-button>
                    </div>
                @endif
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

    {{-- Cabecera de estado y acciones --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2">
            <x-wire-badge>Folio: {{ $purchase->folio }}</x-wire-badge>
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                Estatus: {{ $statusLabel }}
            </span>

            <div class="ml-auto flex items-center gap-2">
                @if($purchase->status === 'draft')
                    <form method="POST" action="{{ route('admin.purchases.receive',$purchase) }}">
                        @csrf
                        <x-wire-button type="submit" green>Recibir</x-wire-button>
                    </form>
                    <form method="POST" action="{{ route('admin.purchases.cancel',$purchase) }}">
                        @csrf
                        <x-wire-button type="submit" red>Cancelar</x-wire-button>
                    </form>
                @endif
            </div>
        </div>
    </x-wire-card>

    {{-- Datos para JS --}}
    <script>
        const PRODUCTS   = @json($products->map(fn($p) => ['id' => $p->id, 'nombre' => $p->nombre]));
        const ITEMS_SEED = @json($itemsSeed);
        const IS_LOCKED  = @json($isLocked);
    </script>

    <script>
        (() => {
            /* ─── Estado ─── */
            let items = (ITEMS_SEED && ITEMS_SEED.length)
                ? ITEMS_SEED.map(s => ({ ...s }))
                : [{ product_id: '', qty_received: 1, price: 0, discount: 0, tax_rate: 0, total: 0 }];

            const locked = IS_LOCKED;

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

                const disabledAttr = locked ? 'disabled' : '';
                const disabledCls  = locked ? 'bg-gray-100 cursor-not-allowed' : '';

                tr.innerHTML = `
                    <td class="p-2">
                        <select class="w-full border rounded p-1 ${disabledCls}"
                                name="items[${i}][product_id]"
                                ${disabledAttr} required>
                            <option value="">-- seleccionar --</option>
                            ${buildProductOptions(item.product_id)}
                        </select>
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0.001" step="0.001"
                               class="w-28 border rounded p-1 text-right ${disabledCls}"
                               name="items[${i}][qty_received]"
                               value="${item.qty_received}"
                               ${disabledAttr} required>
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0" step="0.01"
                               class="w-28 border rounded p-1 text-right ${disabledCls}"
                               name="items[${i}][price]"
                               value="${item.price}"
                               ${disabledAttr} required>
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0" step="0.01"
                               class="w-24 border rounded p-1 text-right ${disabledCls}"
                               name="items[${i}][discount]"
                               value="${item.discount}"
                               ${disabledAttr}>
                    </td>
                    <td class="p-2 text-right">
                        <input type="number" min="0" step="0.01"
                               class="w-20 border rounded p-1 text-right ${disabledCls}"
                               name="items[${i}][tax_rate]"
                               value="${item.tax_rate}"
                               ${disabledAttr}>
                    </td>
                    <td class="p-2 text-right item-total">${fmt(item.total)}</td>
                    <td class="p-2">
                        ${!locked ? `<button type="button" class="text-red-600 btn-remove">Eliminar</button>` : ''}
                    </td>
                `;

                if (!locked) {
                    tr.querySelectorAll('input').forEach(input => {
                        input.addEventListener('input', () => syncRow(tr));
                    });

                    tr.querySelector('.btn-remove')?.addEventListener('click', () => {
                        removeRow(parseInt(tr.dataset.idx));
                    });
                }

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
                if (locked) return;
                snapshotDOM();
                items.push({ product_id: '', qty_received: 1, price: 0, discount: 0, tax_rate: 0, total: 0 });
                renderAll();
            }

            function removeRow(i) {
                if (locked) return;
                snapshotDOM();
                items.splice(i, 1);
                renderAll();
            }

            /* ─── Init ─── */
            if (btnAdd) btnAdd.addEventListener('click', addItem);
            renderAll();
        })();
    </script>
</x-admin-layout>