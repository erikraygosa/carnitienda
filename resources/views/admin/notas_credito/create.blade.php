<x-admin-layout
    title="Nueva Nota de Crédito"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturación'],
        ['name'=>'Notas de Crédito','url'=>route('admin.notas-credito.index')],
        ['name'=>'Nueva'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.notas-credito.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button type="button" onclick="submitForm()"
                class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    @php
        $regimenes = \App\Models\CompanyFiscalData::REGIMENES_FISCALES;
        $selRegRec = old('regimen_fiscal_receptor', '');
        $tieneRfc  = !empty($invoice->client?->rfc);
        $serieDefault = $series->firstWhere('es_default', true) ?? $series->first();
        $nextSerie = $serieDefault?->serie ?? 'E';
    @endphp

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
            <strong class="block mb-2">Revisa los siguientes errores:</strong>
            <ul class="list-disc ml-5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800 flex flex-wrap gap-x-4 gap-y-1">
        <span><strong>Factura original:</strong> {{ $invoice->serie }}{{ $invoice->folio }}</span>
        <span><strong>Cliente:</strong> {{ $invoice->client?->nombre }}</span>
        <span><strong>Total factura:</strong> ${{ number_format($invoice->total, 2) }}</span>
        <span><strong>Disponible para nota:</strong> ${{ number_format($disponible, 2) }}</span>
    </div>

    <x-wire-card>
        <form id="nc-form" method="POST" action="{{ route('admin.notas-credito.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            <input type="hidden" name="lugar_expedicion"      value="{{ old('lugar_expedicion', $emisorDefaults['lugar_expedicion']) }}">
            <input type="hidden" name="regimen_fiscal_emisor" value="{{ old('regimen_fiscal_emisor', $emisorDefaults['regimen_fiscal_emisor']) }}">
            <input type="hidden" name="subtotal"  id="hidden-subtotal">
            <input type="hidden" name="impuestos" id="hidden-impuestos">
            <input type="hidden" name="total"     id="hidden-total">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
                    <select name="serie" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @forelse($series as $s)
                            <option value="{{ $s->serie }}" {{ $s->serie === $nextSerie ? 'selected' : '' }}>
                                {{ $s->serie }} — siguiente folio: {{ $s->folio_actual + 1 }}
                            </option>
                        @empty
                            <option value="">Sin serie configurada para Egreso</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha <span class="text-red-500">*</span></label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo (opcional)</label>
                    <input type="text" name="motivo" maxlength="255" value="{{ old('motivo') }}"
                           placeholder="Devolución, descuento, bonificación..."
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
            </div>

            <div class="border-t pt-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Datos del receptor</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Régimen fiscal <span class="text-red-500">*</span></label>
                        <select name="regimen_fiscal_receptor" id="regimen_fiscal_receptor" required
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Selecciona régimen</option>
                            @foreach($regimenes as $clave => $desc)
                                <option value="{{ $clave }}" {{ (string)$selRegRec === (string)$clave ? 'selected' : '' }}>
                                    {{ $clave }} — {{ $desc }}
                                </option>
                            @endforeach
                        </select>
                        <p id="no-rfc-warning" class="{{ $tieneRfc ? 'hidden' : '' }} mt-1 text-xs text-amber-600">
                            ⚠ Este cliente no tiene RFC registrado — el SAT exige Régimen 616.
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pago</label>
                        <select name="forma_pago" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="99" {{ old('forma_pago', $invoice->forma_pago) === '99' ? 'selected' : '' }}>99 — Por definir</option>
                            <option value="01" {{ old('forma_pago', $invoice->forma_pago) === '01' ? 'selected' : '' }}>01 — Efectivo</option>
                            <option value="03" {{ old('forma_pago', $invoice->forma_pago) === '03' ? 'selected' : '' }}>03 — Transferencia electrónica</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="border-t pt-5">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-gray-700">Partidas de la nota</h4>
                    <span class="text-xs text-gray-500">Máximo disponible: ${{ number_format($disponible, 2) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm" id="items-table">
                        <thead class="border-b bg-gray-50">
                            <tr>
                                <th class="p-2 text-left" style="min-width:200px">Producto / Concepto</th>
                                <th class="p-2 text-right" style="min-width:90px">Cantidad</th>
                                <th class="p-2 text-right" style="min-width:110px">V. Unitario</th>
                                <th class="p-2 text-center" style="min-width:80px">% IVA</th>
                                <th class="p-2 text-center" style="min-width:60px">Obj.</th>
                                <th class="p-2 text-right" style="min-width:100px">Importe</th>
                                <th class="p-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body"></tbody>
                    </table>
                    <div class="mt-3">
                        <button type="button" onclick="addItem()"
                                class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                            + Agregar partida
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t pt-4">
                <div class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-600"><span>Subtotal</span><span id="display-subtotal">$0.00</span></div>
                    <div class="flex justify-between text-gray-600"><span>IVA</span><span id="display-iva">$0.00</span></div>
                    <div class="flex justify-between font-bold text-gray-900 text-base border-t pt-2">
                        <span>Total</span><span id="display-total">$0.00</span>
                    </div>
                    <div id="excede-warning" class="hidden text-xs text-red-600 pt-1">
                        ⚠ El total excede el saldo disponible de la factura.
                    </div>
                </div>
            </div>
        </form>
    </x-wire-card>

    <script>
    const PRODUCTS_MAP = @json($productsMap);
    const DISPONIBLE    = {{ (float) $disponible }};
    let items = [{
        product_id:'', clave_prod_serv:'01010101', clave_unidad:'H87', unidad:'PZA',
        descripcion:'', cantidad:1, valor_unitario:0, descuento:0, iva_pct:16, objeto_imp:'02',
        importe:0, iva_importe:0, ieps_pct:0, ieps_importe:0,
    }];

    document.addEventListener('DOMContentLoaded', function () {
        renderAllRows();
    });

    function onProductChange(i, productId) {
        items[i].product_id = productId;
        var p = PRODUCTS_MAP[String(productId)];
        if (p) {
            items[i].descripcion    = p.nombre;
            items[i].clave_prod_serv= p.clave_prod_serv || '01010101';
            items[i].clave_unidad   = p.clave_unidad || 'H87';
            items[i].unidad         = p.unidad || 'PZA';
            items[i].valor_unitario = p.precio_base || 0;
        }
        updateRowFields(i);
        recalc(i);
    }

    function updateRowFields(i) {
        var row = document.getElementById('item-row-' + i);
        if (!row) return;
        var prodField = row.querySelector('input[name="items[' + i + '][product_id]"]');
        if (prodField) prodField.value = items[i].product_id || '';
        row.querySelector('[data-field="descripcion"]').value    = items[i].descripcion || '';
        row.querySelector('[data-field="valor_unitario"]').value = items[i].valor_unitario || 0;
    }

    function addItem() {
        items.push({
            product_id:'', clave_prod_serv:'01010101', clave_unidad:'H87', unidad:'PZA',
            descripcion:'', cantidad:1, valor_unitario:0, descuento:0, iva_pct:16, objeto_imp:'02',
            importe:0, iva_importe:0, ieps_pct:0, ieps_importe:0,
        });
        appendRow(items.length - 1);
        initRowSelect2(items.length - 1);
        recalc(items.length - 1);
    }

    function removeItem(i) {
        items.splice(i, 1);
        renderAllRows();
        updateTotals();
    }

    function initRowSelect2(i) {
        if (typeof $ === 'undefined') return;
        var $sel = $('#item-row-' + i).find('.sel-product');
        if (!$sel.length) return;
        $sel.select2({
            placeholder: '-- seleccionar producto --', allowClear: true, width: '100%',
            dropdownParent: $('#nc-form'),
            language: { searching: function(){return 'Buscando...';}, noResults: function(){return 'Sin resultados';} },
        });
    }

    function renderAllRows() {
        var tbody = document.getElementById('items-body');
        if (typeof $ !== 'undefined') {
            tbody.querySelectorAll('.sel-product').forEach(function(sel) {
                if ($(sel).data('select2')) $(sel).select2('destroy');
            });
        }
        tbody.innerHTML = '';
        items.forEach(function(_, i) { appendRow(i); initRowSelect2(i); });
        updateTotals();
    }

    function appendRow(i) {
        var it = items[i];
        var tbody = document.getElementById('items-body');
        var productOptions = '<option value="">— seleccionar —</option>';
        Object.entries(PRODUCTS_MAP).forEach(function(entry) {
            var id = entry[0], p = entry[1];
            var sel = String(it.product_id) === String(id) ? 'selected' : '';
            productOptions += '<option value="' + id + '" ' + sel + '>' + escHtml(p.nombre) + '</option>';
        });

        var tr = document.createElement('tr');
        tr.id = 'item-row-' + i;
        tr.className = 'border-b hover:bg-gray-50';
        tr.innerHTML = `
            <td class="p-2">
                <select class="w-full border rounded p-1 text-sm mb-1 sel-product" onchange="onProductChange(${i}, this.value)">
                    ${productOptions}
                </select>
                <input type="hidden" name="items[${i}][product_id]" value="${escHtml(it.product_id)}">
                <input class="w-full border rounded p-1 text-xs text-gray-600" data-field="descripcion"
                       name="items[${i}][descripcion]" value="${escHtml(it.descripcion)}" placeholder="Descripción"
                       oninput="items[${i}].descripcion = this.value" required>
                <input type="hidden" name="items[${i}][clave_prod_serv]" value="${escHtml(it.clave_prod_serv)}">
                <input type="hidden" name="items[${i}][clave_unidad]" value="${escHtml(it.clave_unidad)}">
                <input type="hidden" name="items[${i}][unidad]" value="${escHtml(it.unidad)}">
            </td>
            <td class="p-2">
                <input type="number" step="0.001" min="0.001" class="w-full border rounded p-1 text-right text-sm"
                       name="items[${i}][cantidad]" value="${it.cantidad}"
                       oninput="items[${i}].cantidad = parseFloat(this.value)||0; recalc(${i})" required>
            </td>
            <td class="p-2">
                <input type="number" step="0.0001" min="0" class="w-full border rounded p-1 text-right text-sm"
                       data-field="valor_unitario" name="items[${i}][valor_unitario]" value="${it.valor_unitario}"
                       oninput="items[${i}].valor_unitario = parseFloat(this.value)||0; recalc(${i})" required>
            </td>
            <td class="p-2 text-center">
                <select class="w-full border rounded p-1 text-sm" onchange="items[${i}].iva_pct = parseInt(this.value); recalc(${i})">
                    <option value="0"  ${it.iva_pct == 0  ? 'selected' : ''}>0%</option>
                    <option value="8"  ${it.iva_pct == 8  ? 'selected' : ''}>8%</option>
                    <option value="16" ${it.iva_pct == 16 ? 'selected' : ''}>16%</option>
                </select>
                <input type="hidden" name="items[${i}][iva_pct]" value="${it.iva_pct}">
            </td>
            <td class="p-2 text-center">
                <select class="w-full border rounded p-1 text-sm" name="items[${i}][objeto_imp]">
                    <option value="01" ${it.objeto_imp === '01' ? 'selected' : ''}>01</option>
                    <option value="02" ${it.objeto_imp === '02' ? 'selected' : ''}>02</option>
                    <option value="03" ${it.objeto_imp === '03' ? 'selected' : ''}>03</option>
                </select>
            </td>
            <td class="p-2 text-right font-medium" id="display-importe-${i}">$${fmt(it.importe)}</td>
            <td class="p-2 text-center">
                <button type="button" class="text-red-400 hover:text-red-600" onclick="removeItem(${i})">✕</button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    function recalc(i) {
        var it = items[i];
        var base = Math.max((parseFloat(it.cantidad)||0) * (parseFloat(it.valor_unitario)||0), 0);
        var iva  = base * ((parseFloat(it.iva_pct)||0) / 100);
        it.iva_importe = iva;
        it.importe = base + iva;
        var cell = document.getElementById('display-importe-' + i);
        if (cell) cell.textContent = '$' + fmt(it.importe);
        updateTotals();
    }

    function updateTotals() {
        var sub=0, tax=0, grand=0;
        items.forEach(function(it) {
            var base = Math.max((parseFloat(it.cantidad)||0)*(parseFloat(it.valor_unitario)||0), 0);
            var iva  = base * ((parseFloat(it.iva_pct)||0)/100);
            sub += base; tax += iva; grand += base+iva;
        });
        document.getElementById('display-subtotal').textContent = '$'+fmt(sub);
        document.getElementById('display-iva').textContent      = '$'+fmt(tax);
        document.getElementById('display-total').textContent    = '$'+fmt(grand);
        document.getElementById('hidden-subtotal').value  = sub;
        document.getElementById('hidden-impuestos').value = tax;
        document.getElementById('hidden-total').value     = grand;

        var warn = document.getElementById('excede-warning');
        if (warn) warn.classList.toggle('hidden', grand <= DISPONIBLE + 0.01);
    }

    function submitForm() { updateTotals(); document.getElementById('nc-form').submit(); }

    function fmt(n) { return Number(n||0).toFixed(2); }
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    </script>

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
.select2-container .select2-selection--single { height: 38px !important; border-color: #d1d5db !important; border-radius: 6px !important; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 0.875rem; color: #374151; padding-left: 10px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
.select2-dropdown { border-color: #d1d5db; border-radius: 6px; font-size: 0.875rem; }
.select2-container--default .select2-search--dropdown .select2-search__field { border-color: #d1d5db; border-radius: 4px; padding: 4px 8px; }
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush

</x-admin-layout>
