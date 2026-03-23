<x-admin-layout
    title="Nueva factura"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas','url'=>route('admin.invoices.index')],
        ['name'=>'Nueva'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button type="button" onclick="submitForm()"
                class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    @php
        $valueFecha  = old('fecha', now()->format('Y-m-d\TH:i'));
        $fromOrder   = isset($prefill) && $prefill;
        $lockedItems = $fromOrder; // si viene de pedido, items bloqueados

        $itemsSeed = old('items', $prefill['items'] ?? [[
            'product_id'      => '',
            'clave_prod_serv' => '01010101',
            'clave_unidad'    => 'H87',
            'unidad'          => 'PZA',
            'descripcion'     => '',
            'cantidad'        => 1,
            'valor_unitario'  => 0,
            'descuento'       => 0,
            'iva_pct'         => 16,
            'objeto_imp'      => '02',
            'importe'         => 0,
            'iva_importe'     => 0,
            'ieps_pct'        => 0,
            'ieps_importe'    => 0,
        ]]);

        $regimenes = \App\Models\CompanyFiscalData::REGIMENES_FISCALES;

        $usoCfdiCatalogo = [
            'G01'  => 'G01 — Adquisición de mercancias',
            'G02'  => 'G02 — Devoluciones, descuentos o bonificaciones',
            'G03'  => 'G03 — Gastos en general',
            'I01'  => 'I01 — Construcciones',
            'I03'  => 'I03 — Equipo de transporte',
            'I04'  => 'I04 — Equipo de computo y accesorios',
            'S01'  => 'S01 — Sin efectos fiscales',
            'CP01' => 'CP01 — Pagos',
        ];

        $formasPago = [
            '01' => '01 — Efectivo',
            '02' => '02 — Cheque nominativo',
            '03' => '03 — Transferencia electrónica',
            '04' => '04 — Tarjeta de crédito',
            '28' => '28 — Tarjeta de débito',
            '99' => '99 — Por definir',
        ];

        $selRegRec = old('regimen_fiscal_receptor', $prefill['regimen_fiscal_receptor'] ?? '');
        $selUso    = old('uso_cfdi', $prefill['uso_cfdi'] ?? 'G03');
        $cid       = old('client_id', $prefill['client_id'] ?? '');
    @endphp

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
            <strong class="block mb-2">Revisa los siguientes errores:</strong>
            <ul class="list-disc ml-5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Aviso si viene de pedido --}}
    @if($fromOrder)
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-800 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
        Las partidas provienen del pedido y no pueden modificarse. Solo ajusta los datos fiscales.
    </div>
    @endif

    <x-wire-card>
        <form id="inv-form" method="POST" action="{{ route('admin.invoices.store') }}" class="space-y-6">
            @csrf

            {{-- Campos ocultos del emisor --}}
            <input type="hidden" name="lugar_expedicion"      value="{{ old('lugar_expedicion', $emisorDefaults['lugar_expedicion']) }}">
            <input type="hidden" name="regimen_fiscal_emisor" value="{{ old('regimen_fiscal_emisor', $emisorDefaults['regimen_fiscal_emisor']) }}">
            <input type="hidden" name="serie"       value="A">
            <input type="hidden" name="folio"       value="">
            <input type="hidden" name="exportacion" value="01">
            <input type="hidden" name="subtotal"    id="hidden-subtotal">
            <input type="hidden" name="impuestos"   id="hidden-impuestos">
            <input type="hidden" name="total"       id="hidden-total">

            {{-- EMISOR --}}
            @if($empresa)
            <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                
                <div class="text-sm text-blue-800 flex flex-wrap gap-x-3 gap-y-1">
                    <span><strong>Emisor:</strong> {{ $empresa->razon_social }}</span>
                    <span class="text-blue-300">|</span>
                    <span class="font-mono">{{ $empresa->rfc }}</span>
                    <span class="text-blue-300">|</span>
                    <span>{{ $emisorDefaults['regimen_fiscal_emisor'] }} — {{ $regimenes[$emisorDefaults['regimen_fiscal_emisor']] ?? '' }}</span>
                    <span class="text-blue-300">|</span>
                    <span>CP: {{ $emisorDefaults['lugar_expedicion'] }}</span>
                </div>
            </div>
            {{-- Serie y Folio --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
        <input type="text" value="{{ $nextSerie }}" readonly
               class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm cursor-not-allowed">
        <input type="hidden" name="serie" value="{{ $nextSerie }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Folio</label>
        <input type="text" value="{{ $nextFolio }}" readonly
               class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm cursor-not-allowed">
        <input type="hidden" name="folio" value="{{ $nextFolio }}">
    </div>
</div>
            
            @else
            
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                ⚠ No hay empresa activa configurada.
                <a href="{{ route('admin.parametros.companies.index') }}" class="underline font-medium ml-1">Configúrala aquí</a>
            </div>
            @endif

            {{-- ENCABEZADO --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2 space-y-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Cliente <span class="text-red-500">*</span>
                    </label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="onClientChange(this.value)"
                            {{ $lockedItems ? 'disabled' : '' }}
                            required>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ (string)$cid === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @if($lockedItems)
                        <input type="hidden" name="client_id" value="{{ $cid }}">
                    @endif
                    <div id="client-meta" class="text-xs text-gray-500 flex gap-3 pt-1 hidden">
                        <span>RFC: <strong id="client-rfc"></strong></span>
                        <span id="client-razon"></span>
                    </div>
                </div>

                {{-- Fecha --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="fecha" value="{{ $valueFecha }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    @php $selMoneda = old('moneda', $prefill['moneda'] ?? 'MXN'); @endphp
                    <select name="moneda"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="MXN" {{ $selMoneda === 'MXN' ? 'selected' : '' }}>MXN — Peso mexicano</option>
                        <option value="USD" {{ $selMoneda === 'USD' ? 'selected' : '' }}>USD — Dólar americano</option>
                    </select>
                </div>

                {{-- Tipo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo <span class="text-red-500">*</span></label>
                    @php $tc = old('tipo_comprobante', 'I'); @endphp
                    <select name="tipo_comprobante"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="I" {{ $tc === 'I' ? 'selected' : '' }}>Ingreso</option>
                        <option value="E" {{ $tc === 'E' ? 'selected' : '' }}>Egreso</option>
                    </select>
                </div>

                {{-- Forma de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pago</label>
                    @php $selForma = old('forma_pago', '03'); @endphp
                    <select name="forma_pago"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($formasPago as $k => $lbl)
                            <option value="{{ $k }}" {{ $selForma === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    @php $selMetodo = old('metodo_pago', 'PUE'); @endphp
                    <select name="metodo_pago"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="PUE" {{ $selMetodo === 'PUE' ? 'selected' : '' }}>PUE — Una sola exhibición</option>
                        <option value="PPD" {{ $selMetodo === 'PPD' ? 'selected' : '' }}>PPD — Parcialidades o diferido</option>
                    </select>
                </div>

            </div>

            {{-- RECEPTOR --}}
            <div class="border-t pt-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Datos del receptor</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Régimen fiscal <span class="text-red-500">*</span>
                        </label>
                        <select name="regimen_fiscal_receptor" id="regimen_fiscal_receptor" required
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona régimen</option>
                            @foreach($regimenes as $clave => $desc)
                                <option value="{{ $clave }}" {{ $selRegRec === $clave ? 'selected' : '' }}>
                                    {{ $clave }} — {{ $desc }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Uso CFDI <span class="text-red-500">*</span>
                        </label>
                        <select name="uso_cfdi" id="uso_cfdi" required
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($usoCfdiCatalogo as $clave => $desc)
                                <option value="{{ $clave }}" {{ $selUso === $clave ? 'selected' : '' }}>
                                    {{ $desc }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- PARTIDAS --}}
            <div class="border-t pt-5">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-gray-700">Partidas</h4>
                    @if($lockedItems)
                        <span class="text-xs text-amber-600 flex items-center gap-1">
                            🔒 Bloqueadas — provienen del pedido
                        </span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm" id="items-table">
                        <thead class="border-b bg-gray-50">
                            <tr>
                                <th class="p-2 text-left" style="min-width:200px">Producto / Concepto</th>
                                <th class="p-2 text-left" style="min-width:100px">Clave SAT</th>
                                <th class="p-2 text-left" style="min-width:90px">Unidad</th>
                                <th class="p-2 text-right" style="min-width:90px">Cantidad</th>
                                <th class="p-2 text-right" style="min-width:110px">V. Unitario</th>
                                <th class="p-2 text-right" style="min-width:90px">Desc.</th>
                                <th class="p-2 text-center" style="min-width:80px">% IVA</th>
                                <th class="p-2 text-center" style="min-width:60px">Obj.</th>
                                <th class="p-2 text-right" style="min-width:100px">Importe</th>
                                @if(!$lockedItems)<th class="p-2 w-8"></th>@endif
                            </tr>
                        </thead>
                        <tbody id="items-body"></tbody>
                    </table>

                    @if(!$lockedItems)
                    <div class="mt-3">
                        <button type="button" onclick="addItem()"
                                class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                            + Agregar partida
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            {{-- TOTALES --}}
            <div class="flex justify-end border-t pt-4">
                <div class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span id="display-subtotal">$0.00</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>IVA</span>
                        <span id="display-iva">$0.00</span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-900 text-base border-t pt-2">
                        <span>Total</span>
                        <span id="display-total">$0.00</span>
                    </div>
                </div>
            </div>

        </form>
    </x-wire-card>

    <script>
    const CLIENTS_MAP   = @json($clientsMap);
    const PRODUCTS_MAP  = @json($productsMap);
    const ITEMS_SEED    = @json($itemsSeed);
    const PREFILL_CID   = @json((string) old('client_id', $prefill['client_id'] ?? ''));
    const LOCKED_ITEMS  = {{ $lockedItems ? 'true' : 'false' }};

    let items = JSON.parse(JSON.stringify(ITEMS_SEED));

    document.addEventListener('DOMContentLoaded', function () {
        renderAllRows();

        if (PREFILL_CID) {
            const sel = document.getElementById('client_id');
            if (sel) sel.value = PREFILL_CID;
            applyClient(PREFILL_CID);
        }

        items.forEach(function(_, i) { recalc(i); });
    });

    function onClientChange(clientId) { applyClient(clientId); }

    function applyClient(clientId) {
        var d    = CLIENTS_MAP[String(clientId)];
        var meta = document.getElementById('client-meta');
        if (!d) { if (meta) meta.classList.add('hidden'); return; }

        document.getElementById('client-rfc').textContent   = d.rfc         || '';
        document.getElementById('client-razon').textContent = d.razon_social || '';
        if (meta) meta.classList.remove('hidden');

        var regEl = document.getElementById('regimen_fiscal_receptor');
        if (regEl && d.regimen_fiscal) regEl.value = d.regimen_fiscal;

        var usoEl = document.getElementById('uso_cfdi');
        if (usoEl && d.uso_cfdi) usoEl.value = d.uso_cfdi;
    }

    function onProductChange(i, productId) {
        if (LOCKED_ITEMS) return;
        items[i].product_id = productId;
        if (!productId) {
            items[i].descripcion = ''; items[i].clave_prod_serv = '01010101';
            items[i].clave_unidad = 'H87'; items[i].unidad = 'PZA'; items[i].valor_unitario = 0;
            updateRowFields(i); recalc(i); return;
        }
        var p = PRODUCTS_MAP[String(productId)];
        if (!p) return;
        items[i].descripcion     = p.nombre;
        items[i].clave_prod_serv = p.clave_prod_serv || '01010101';
        items[i].clave_unidad    = p.clave_unidad    || 'H87';
        items[i].unidad          = p.unidad          || 'PZA';
        items[i].valor_unitario  = p.precio_base     || 0;
        updateRowFields(i); recalc(i);
    }

    function updateRowFields(i) {
        var row = document.getElementById('item-row-' + i);
        if (!row) return;
        row.querySelector('[data-field="descripcion"]').value     = items[i].descripcion     || '';
        row.querySelector('[data-field="clave_prod_serv"]').value = items[i].clave_prod_serv || '';
        row.querySelector('[data-field="clave_unidad"]').value    = items[i].clave_unidad    || '';
        row.querySelector('[data-field="unidad"]').value          = items[i].unidad          || '';
        row.querySelector('[data-field="valor_unitario"]').value  = items[i].valor_unitario  || 0;
    }

    function addItem() {
        if (LOCKED_ITEMS) return;
        items.push({
            product_id:'', clave_prod_serv:'01010101', clave_unidad:'H87',
            unidad:'PZA', descripcion:'', cantidad:1, valor_unitario:0,
            descuento:0, iva_pct:16, objeto_imp:'02',
            importe:0, iva_importe:0, ieps_pct:0, ieps_importe:0,
        });
        appendRow(items.length - 1);
        recalc(items.length - 1);
    }

    function removeItem(i) {
        if (LOCKED_ITEMS) return;
        items.splice(i, 1);
        renderAllRows();
        updateTotals();
    }

    function renderAllRows() {
        document.getElementById('items-body').innerHTML = '';
        items.forEach(function(_, i) { appendRow(i); });
        updateTotals();
    }

    function appendRow(i) {
        var it    = items[i];
        var tbody = document.getElementById('items-body');
        var dis   = LOCKED_ITEMS ? 'disabled' : '';
        var ro    = LOCKED_ITEMS ? 'readonly' : '';

        var productOptions = '<option value="">— seleccionar —</option>';
        if (!LOCKED_ITEMS) {
            Object.entries(PRODUCTS_MAP).forEach(function(entry) {
                var id = entry[0], p = entry[1];
                var sel = String(it.product_id) === String(id) ? 'selected' : '';
                productOptions += '<option value="' + id + '" ' + sel + '>' + escHtml(p.nombre) + '</option>';
            });
        }

        var tr = document.createElement('tr');
        tr.id        = 'item-row-' + i;
        tr.className = 'border-b hover:bg-gray-50';

        // Si está bloqueado mostramos texto, si no mostramos inputs editables
        if (LOCKED_ITEMS) {
            tr.innerHTML = `
                <td class="p-2">
                    <input type="hidden" name="items[${i}][product_id]" value="${escHtml(it.product_id)}">
                    <div class="font-medium text-gray-800 text-sm">${escHtml(it.descripcion)}</div>
                    <input type="hidden" name="items[${i}][descripcion]" value="${escHtml(it.descripcion)}" data-field="descripcion">
                </td>
                <td class="p-2">
                    <div class="text-xs text-gray-600 font-mono">${escHtml(it.clave_prod_serv)}</div>
                    <input type="hidden" name="items[${i}][clave_prod_serv]" value="${escHtml(it.clave_prod_serv)}" data-field="clave_prod_serv">
                </td>
                <td class="p-2">
                    <div class="text-xs text-gray-600">${escHtml(it.clave_unidad)} / ${escHtml(it.unidad)}</div>
                    <input type="hidden" name="items[${i}][clave_unidad]" value="${escHtml(it.clave_unidad)}" data-field="clave_unidad">
                    <input type="hidden" name="items[${i}][unidad]" value="${escHtml(it.unidad)}" data-field="unidad">
                </td>
                <td class="p-2 text-right font-mono text-sm">${it.cantidad}</td>
                <td class="p-2 text-right font-mono text-sm">$${fmt(it.valor_unitario)}</td>
                <td class="p-2 text-right font-mono text-sm">$${fmt(it.descuento)}</td>
                <td class="p-2 text-center">
                    <span class="text-xs">${it.iva_pct}%</span>
                    <input type="hidden" name="items[${i}][iva_pct]"      id="hid-iva-pct-${i}"  value="${it.iva_pct}">
                    <input type="hidden" name="items[${i}][iva_importe]"  id="hid-iva-imp-${i}"  value="${it.iva_importe}">
                    <input type="hidden" name="items[${i}][ieps_pct]"     id="hid-ieps-pct-${i}" value="${it.ieps_pct}">
                    <input type="hidden" name="items[${i}][ieps_importe]" id="hid-ieps-imp-${i}" value="${it.ieps_importe}">
                    <input type="hidden" name="items[${i}][importe]"      id="hid-importe-${i}"  value="${it.importe}">
                </td>
                <td class="p-2 text-center">
                    <select name="items[${i}][objeto_imp]" class="w-full border rounded p-1 text-sm">
                        <option value="01" ${it.objeto_imp === '01' ? 'selected' : ''}>01</option>
                        <option value="02" ${it.objeto_imp === '02' ? 'selected' : ''}>02</option>
                        <option value="03" ${it.objeto_imp === '03' ? 'selected' : ''}>03</option>
                    </select>
                </td>
                <td class="p-2 text-right font-medium" id="display-importe-${i}">$${fmt(it.importe)}</td>
                <input type="hidden" name="items[${i}][cantidad]"       value="${it.cantidad}" data-field="cantidad">
                <input type="hidden" name="items[${i}][valor_unitario]" value="${it.valor_unitario}" data-field="valor_unitario">
                <input type="hidden" name="items[${i}][descuento]"      value="${it.descuento}" data-field="descuento">
            `;
        } else {
            tr.innerHTML = `
                <td class="p-2">
                    <select class="w-full border rounded p-1 text-sm mb-1" onchange="onProductChange(${i}, this.value)">
                        ${productOptions}
                    </select>
                    <input type="hidden" name="items[${i}][product_id]" value="${escHtml(it.product_id)}">
                    <input class="w-full border rounded p-1 text-xs text-gray-600"
                           data-field="descripcion" name="items[${i}][descripcion]"
                           value="${escHtml(it.descripcion)}" placeholder="Descripción"
                           oninput="items[${i}].descripcion = this.value" required>
                </td>
                <td class="p-2">
                    <input class="w-full border rounded p-1 text-sm" data-field="clave_prod_serv"
                           name="items[${i}][clave_prod_serv]" value="${escHtml(it.clave_prod_serv)}"
                           placeholder="01010101" oninput="items[${i}].clave_prod_serv = this.value">
                </td>
                <td class="p-2">
                    <input class="w-16 border rounded p-1 text-sm mb-1" data-field="clave_unidad"
                           name="items[${i}][clave_unidad]" value="${escHtml(it.clave_unidad)}"
                           placeholder="H87" oninput="items[${i}].clave_unidad = this.value">
                    <input class="w-16 border rounded p-1 text-sm" data-field="unidad"
                           name="items[${i}][unidad]" value="${escHtml(it.unidad)}"
                           placeholder="PZA" oninput="items[${i}].unidad = this.value">
                </td>
                <td class="p-2">
                    <input type="number" step="0.001" min="0" class="w-full border rounded p-1 text-right text-sm"
                           data-field="cantidad" name="items[${i}][cantidad]" value="${it.cantidad}"
                           oninput="items[${i}].cantidad = parseFloat(this.value)||0; recalc(${i})" required>
                </td>
                <td class="p-2">
                    <input type="number" step="0.0001" min="0" class="w-full border rounded p-1 text-right text-sm"
                           data-field="valor_unitario" name="items[${i}][valor_unitario]" value="${it.valor_unitario}"
                           oninput="items[${i}].valor_unitario = parseFloat(this.value)||0; recalc(${i})" required>
                </td>
                <td class="p-2">
                    <input type="number" step="0.01" min="0" class="w-full border rounded p-1 text-right text-sm"
                           data-field="descuento" name="items[${i}][descuento]" value="${it.descuento}"
                           oninput="items[${i}].descuento = parseFloat(this.value)||0; recalc(${i})">
                </td>
                <td class="p-2 text-center">
                    <select class="w-full border rounded p-1 text-sm"
                            onchange="items[${i}].iva_pct = parseInt(this.value); recalc(${i})">
                        <option value="0"  ${it.iva_pct == 0  ? 'selected' : ''}>0%</option>
                        <option value="8"  ${it.iva_pct == 8  ? 'selected' : ''}>8%</option>
                        <option value="16" ${it.iva_pct == 16 ? 'selected' : ''}>16%</option>
                    </select>
                    <input type="hidden" name="items[${i}][iva_pct]"      id="hid-iva-pct-${i}"  value="${it.iva_pct}">
                    <input type="hidden" name="items[${i}][iva_importe]"  id="hid-iva-imp-${i}"  value="${it.iva_importe}">
                    <input type="hidden" name="items[${i}][ieps_pct]"     id="hid-ieps-pct-${i}" value="${it.ieps_pct}">
                    <input type="hidden" name="items[${i}][ieps_importe]" id="hid-ieps-imp-${i}" value="${it.ieps_importe}">
                    <input type="hidden" name="items[${i}][importe]"      id="hid-importe-${i}"  value="${it.importe}">
                </td>
                <td class="p-2 text-center">
                    <select class="w-full border rounded p-1 text-sm" name="items[${i}][objeto_imp]"
                            onchange="items[${i}].objeto_imp = this.value">
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
        }

        tbody.appendChild(tr);
    }

    function recalc(i) {
        var it   = items[i];
        var base = Math.max(
            (parseFloat(it.cantidad)||0) * (parseFloat(it.valor_unitario)||0) - (parseFloat(it.descuento)||0),
            0
        );
        var iva  = base * ((parseFloat(it.iva_pct)||0)  / 100);
        var ieps = base * ((parseFloat(it.ieps_pct)||0) / 100);
        it.iva_importe  = iva;
        it.ieps_importe = ieps;
        it.importe      = base + iva + ieps;

        var h = function(id, val) { var el = document.getElementById(id); if(el) el.value = val; };
        h('hid-iva-pct-'+i,  it.iva_pct);
        h('hid-iva-imp-'+i,  it.iva_importe);
        h('hid-ieps-pct-'+i, it.ieps_pct);
        h('hid-ieps-imp-'+i, it.ieps_importe);
        h('hid-importe-'+i,  it.importe);

        var cell = document.getElementById('display-importe-' + i);
        if (cell) cell.textContent = '$' + fmt(it.importe);

        updateTotals();
    }

    function updateTotals() {
        var sub=0, tax=0, grand=0;
        items.forEach(function(it) {
            var base = Math.max(
                (parseFloat(it.cantidad)||0)*(parseFloat(it.valor_unitario)||0)-(parseFloat(it.descuento)||0), 0
            );
            var iva  = base * ((parseFloat(it.iva_pct)||0)/100);
            var ieps = base * ((parseFloat(it.ieps_pct)||0)/100);
            sub += base; tax += iva+ieps; grand += base+iva+ieps;
        });
        document.getElementById('display-subtotal').textContent = '$'+fmt(sub);
        document.getElementById('display-iva').textContent      = '$'+fmt(tax);
        document.getElementById('display-total').textContent    = '$'+fmt(grand);
        document.getElementById('hidden-subtotal').value  = sub;
        document.getElementById('hidden-impuestos').value = tax;
        document.getElementById('hidden-total').value     = grand;
    }

    function submitForm() { updateTotals(); document.getElementById('inv-form').submit(); }

    function fmt(n) { return Number(n||0).toFixed(2); }
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    </script>

</x-admin-layout>