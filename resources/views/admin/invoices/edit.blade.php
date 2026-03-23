<x-admin-layout
    title="Editar factura"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas','url'=>route('admin.invoices.index')],
        ['name'=>'Editar #'.$invoice->id],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        @if($invoice->estatus === 'BORRADOR')
            <button type="button" onclick="submitForm()"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
    </x-slot>

    @php
        $isLocked  = $invoice->estatus !== 'BORRADOR';
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

        $selClient = old('client_id', $invoice->client_id);
        $selMoneda = old('moneda', $invoice->moneda ?? 'MXN');
        $selMetodo = old('metodo_pago', $invoice->metodo_pago ?? 'PUE');
        $selForma  = old('forma_pago', $invoice->forma_pago ?? '03');
        $selUso    = old('uso_cfdi', $invoice->uso_cfdi ?? 'G03');
        $selTipo   = old('tipo_comprobante', $invoice->tipo_comprobante ?? 'I');
        $selRegRec = old('regimen_fiscal_receptor', $invoice->regimen_fiscal_receptor ?? '');
        $fechaVal  = old('fecha', optional($invoice->fecha)->format('Y-m-d\TH:i'));

        $clientsMap = $clients->keyBy('id')->map(fn($c) => [
            'rfc'            => $c->rfc ?? '',
            'razon_social'   => $c->razon_social ?? $c->nombre ?? '',
            'regimen_fiscal' => $c->regimen_fiscal ?? '',
            'uso_cfdi'       => $c->uso_cfdi_default ?? 'G03',
        ]);

        $productsMap = $products->keyBy('id')->map(fn($p) => [
            'nombre'          => $p->nombre,
            'precio_base'     => (float)($p->precio_base ?? 0),
            'clave_prod_serv' => $p->clave_prod_serv ?? '01010101',
            'clave_unidad'    => $p->clave_unidad ?? 'H87',
            'unidad'          => $p->unidad ?? 'PZA',
        ]);

        $itemsSeed = $invoice->items->map(fn($i) => [
            'id'              => $i->id,
            'product_id'      => (string)($i->product_id ?? ''),
            'descripcion'     => $i->descripcion,
            'clave_prod_serv' => $i->clave_prod_serv ?? '01010101',
            'clave_unidad'    => $i->clave_unidad ?? 'H87',
            'unidad'          => $i->unidad ?? 'PZA',
            'cantidad'        => (float)$i->cantidad,
            'valor_unitario'  => (float)($i->valor_unitario ?? $i->precio_unitario ?? 0),
            'descuento'       => (float)$i->descuento,
            'objeto_imp'      => $i->objeto_imp ?? '02',
            'iva_pct'         => (float)($i->iva_pct ?? 0),
            'iva_importe'     => (float)($i->iva_importe ?? 0),
            'ieps_pct'        => (float)($i->ieps_pct ?? 0),
            'ieps_importe'    => (float)($i->ieps_importe ?? 0),
            'importe'         => (float)($i->importe ?? $i->total ?? 0),
        ])->values()->toArray();
    @endphp

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
            <strong class="block mb-2">Revisa los siguientes errores:</strong>
            <ul class="list-disc ml-5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-wire-card>
        <form id="invoice-edit-form"
              action="{{ route('admin.invoices.update', $invoice) }}"
              method="POST"
              class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Campos ocultos del emisor --}}
            <input type="hidden" name="lugar_expedicion"
                   value="{{ old('lugar_expedicion', $invoice->lugar_expedicion ?? $emisorDefaults['lugar_expedicion']) }}">
            <input type="hidden" name="regimen_fiscal_emisor"
                   value="{{ old('regimen_fiscal_emisor', $invoice->regimen_fiscal_emisor ?? $emisorDefaults['regimen_fiscal_emisor']) }}">
            <input type="hidden" name="exportacion" value="{{ $invoice->exportacion ?? '01' }}">
            <input type="hidden" name="subtotal"    id="hidden-subtotal">
            <input type="hidden" name="impuestos"   id="hidden-impuestos">
            <input type="hidden" name="total"       id="hidden-total">

            {{-- ====== EMISOR ====== --}}
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
            @endif

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2 space-y-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Cliente <span class="text-red-500">*</span>
                    </label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="onClientChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}
                            required>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}"
                                    {{ (string)$selClient === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <div id="client-meta" class="text-xs text-gray-500 flex gap-3 pt-1" style="display:none">
                        <span>RFC: <strong id="client-rfc"></strong></span>
                        <span id="client-razon"></span>
                    </div>
                </div>

                {{-- Fecha --}}
                <x-wire-input label="Fecha" name="fecha" type="datetime-local"
                              value="{{ $fechaVal }}" :disabled="$isLocked" required/>

                {{-- Moneda --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Moneda</label>
                    <select name="moneda"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="MXN" {{ $selMoneda === 'MXN' ? 'selected' : '' }}>MXN — Peso mexicano</option>
                        <option value="USD" {{ $selMoneda === 'USD' ? 'selected' : '' }}>USD — Dólar americano</option>
                    </select>
                </div>

                {{-- Tipo --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select name="tipo_comprobante"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="I" {{ $selTipo === 'I' ? 'selected' : '' }}>Ingreso</option>
                        <option value="E" {{ $selTipo === 'E' ? 'selected' : '' }}>Egreso</option>
                    </select>
                </div>

                {{-- Forma de pago --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Forma de pago</label>
                    <select name="forma_pago"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ $isLocked ? 'disabled' : '' }}>
                        @foreach($formasPago as $k => $lbl)
                            <option value="{{ $k }}" {{ $selForma === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de pago --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Método de pago</label>
                    <select name="metodo_pago"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="PUE" {{ $selMetodo === 'PUE' ? 'selected' : '' }}>PUE — Una sola exhibición</option>
                        <option value="PPD" {{ $selMetodo === 'PPD' ? 'selected' : '' }}>PPD — Parcialidades o diferido</option>
                    </select>
                </div>

            </div>

            {{-- ====== RECEPTOR ====== --}}
            <div class="border-t pt-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Datos del receptor</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Régimen fiscal <span class="text-red-500">*</span>
                        </label>
                        <select name="regimen_fiscal_receptor"
                                id="regimen_fiscal_receptor"
                                required
                                {{ $isLocked ? 'disabled' : '' }}
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona régimen</option>
                            @foreach($regimenes as $clave => $desc)
                                <option value="{{ $clave }}"
                                        {{ $selRegRec === $clave ? 'selected' : '' }}>
                                    {{ $clave }} — {{ $desc }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Uso CFDI <span class="text-red-500">*</span>
                        </label>
                        <select name="uso_cfdi" id="uso_cfdi" required
                                {{ $isLocked ? 'disabled' : '' }}
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($usoCfdiCatalogo as $clave => $desc)
                                <option value="{{ $clave }}" {{ $selUso === $clave ? 'selected' : '' }}>
                                    {{ $desc }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>
            </div>

            {{-- ====== PARTIDAS ====== --}}
            <div class="border-t pt-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Partidas</h4>
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
                                <th class="p-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body">
                            {{-- Filas generadas por JS --}}
                        </tbody>
                    </table>

                    @if(!$isLocked)
                    <div class="mt-3">
                        <button type="button"
                                onclick="addItem()"
                                class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                            + Agregar partida
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ====== TOTALES ====== --}}
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

    {{-- ====== ACCIONES ====== --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center flex-wrap gap-2">

            @if($invoice->estatus === 'BORRADOR')
                <form action="{{ route('admin.invoices.stamp', $invoice) }}" method="POST">
                    @csrf
                    <x-wire-button type="submit" green xs>
                        Timbrar CFDI
                    </x-wire-button>
                </form>
            @endif

            @if($invoice->estatus === 'TIMBRADA')
                <x-wire-button href="{{ route('admin.invoices.pdf', $invoice) }}"
                               gray outline xs target="_blank">
                    Ver PDF
                </x-wire-button>
                <x-wire-button href="{{ route('admin.invoices.download', $invoice) }}"
                               gray xs>
                    Descargar PDF
                </x-wire-button>
                <x-wire-button href="{{ route('admin.invoices.send.form', $invoice) }}"
                               blue xs>
                    Enviar
                </x-wire-button>
                <form action="{{ route('admin.invoices.cancel', $invoice) }}" method="POST"
                      onsubmit="return confirm('¿Cancelar esta factura en el SAT?')">
                    @csrf
                    <div class="grid grid-cols-2 gap-2 items-end">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Motivo cancelación</label>
                            <select name="motivo"
                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500">
                                <option value="02">02 — Comprobante emitido con errores sin relación</option>
                                <option value="01">01 — Comprobante emitido con errores con relación</option>
                                <option value="03">03 — No se llevó a cabo la operación</option>
                                <option value="04">04 — Operación nominativa relacionada en factura global</option>
                            </select>
                        </div>
                        <x-wire-button type="submit" red xs>Cancelar CFDI</x-wire-button>
                    </div>
                </form>
            @endif

            <span class="ml-auto px-2 py-1 text-xs rounded-full font-medium
                {{ $invoice->estatus === 'TIMBRADA'  ? 'bg-emerald-100 text-emerald-700' :
                   ($invoice->estatus === 'CANCELADA' ? 'bg-rose-100 text-rose-700' :
                   'bg-slate-100 text-slate-700') }}">
                {{ $invoice->estatus }}
            </span>

        </div>
    </x-wire-card>

    <script>
    // ─── Datos inyectados desde PHP ───────────────────────────────────────────
    const CLIENTS_MAP  = @json($clientsMap);
    const PRODUCTS_MAP = @json($productsMap);
    const ITEMS_SEED   = @json($itemsSeed);
    const PREFILL_CID  = @json((string)($invoice->client_id ?? ''));
    const IS_LOCKED    = @json($isLocked);

    // ─── Estado ───────────────────────────────────────────────────────────────
    let items = JSON.parse(JSON.stringify(ITEMS_SEED));

    // ─── Inicialización ───────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        renderAllRows();

        // Mostrar info del cliente ya seleccionado sin sobreescribir selects
        if (PREFILL_CID) {
            applyClient(PREFILL_CID, false);
        }

        items.forEach(function (_, i) { recalc(i); });
    });

    // ─── Cliente ──────────────────────────────────────────────────────────────
    function onClientChange(clientId) {
        applyClient(clientId, true);
    }

    function applyClient(clientId, updateSelects) {
        var d = CLIENTS_MAP[String(clientId)];
        var meta = document.getElementById('client-meta');

        if (!d) {
            if (meta) meta.style.display = 'none';
            return;
        }

        document.getElementById('client-rfc').textContent   = d.rfc         || '';
        document.getElementById('client-razon').textContent = d.razon_social || '';
        if (meta) meta.style.display = 'flex';

        // Solo autocompleta los selects cuando el usuario cambia el cliente
        // (no al cargar la página, para respetar los valores guardados)
        if (updateSelects) {
            var regEl = document.getElementById('regimen_fiscal_receptor');
            if (regEl && d.regimen_fiscal) regEl.value = d.regimen_fiscal;

            var usoEl = document.getElementById('uso_cfdi');
            if (usoEl && d.uso_cfdi) usoEl.value = d.uso_cfdi;
        }
    }

    // ─── Productos ────────────────────────────────────────────────────────────
    function onProductChange(i, productId) {
        if (IS_LOCKED) return;

        items[i].product_id = productId;

        if (!productId) {
            items[i].descripcion     = '';
            items[i].clave_prod_serv = '01010101';
            items[i].clave_unidad    = 'H87';
            items[i].unidad          = 'PZA';
            items[i].valor_unitario  = 0;
            updateRowFields(i);
            recalc(i);
            return;
        }

        var p = PRODUCTS_MAP[String(productId)];
        if (!p) return;

        items[i].descripcion     = p.nombre;
        items[i].clave_prod_serv = p.clave_prod_serv || '01010101';
        items[i].clave_unidad    = p.clave_unidad    || 'H87';
        items[i].unidad          = p.unidad          || 'PZA';
        items[i].valor_unitario  = p.precio_base     || 0;

        // Actualizar hidden product_id
        var hidPid = document.querySelector('#item-row-' + i + ' [data-field="product_id"]');
        if (hidPid) hidPid.value = productId;

        updateRowFields(i);
        recalc(i);
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

    // ─── Agregar / Eliminar filas ─────────────────────────────────────────────
    function addItem() {
        if (IS_LOCKED) return;
        items.push({
            id: null, product_id: '',
            clave_prod_serv: '01010101',
            clave_unidad: 'H87', unidad: 'PZA',
            descripcion: '', cantidad: 1,
            valor_unitario: 0, descuento: 0,
            iva_pct: 16, objeto_imp: '02',
            importe: 0, iva_importe: 0,
            ieps_pct: 0, ieps_importe: 0,
        });
        var i = items.length - 1;
        appendRow(i);
        recalc(i);
    }

    function removeItem(i) {
        if (IS_LOCKED) return;
        items.splice(i, 1);
        renderAllRows();
        updateTotals();
    }

    // ─── Render ───────────────────────────────────────────────────────────────
    function renderAllRows() {
        var tbody = document.getElementById('items-body');
        tbody.innerHTML = '';
        items.forEach(function (_, i) { appendRow(i); });
        updateTotals();
    }

    function appendRow(i) {
        var it    = items[i];
        var tbody = document.getElementById('items-body');
        var locked = IS_LOCKED;
        var dis   = locked ? 'disabled' : '';

        // Opciones de productos
        var productOptions = '<option value="">— seleccionar —</option>';
        Object.entries(PRODUCTS_MAP).forEach(function (entry) {
            var id = entry[0], p = entry[1];
            var sel = String(it.product_id) === String(id) ? 'selected' : '';
            productOptions += '<option value="' + id + '" ' + sel + '>' + escHtml(p.nombre) + '</option>';
        });

        var tr = document.createElement('tr');
        tr.id = 'item-row-' + i;
        tr.className = 'border-b hover:bg-gray-50';
        tr.innerHTML = `
            <td class="p-2">
                <select class="w-full border rounded p-1 text-sm mb-1"
                        onchange="onProductChange(${i}, this.value)"
                        ${dis}>
                    ${productOptions}
                </select>
                <input type="hidden" data-field="product_id"
                       name="items[${i}][product_id]"
                       value="${escHtml(it.product_id)}">
                <input type="hidden" name="items[${i}][id]"
                       value="${escHtml(it.id ?? '')}">
                <input class="w-full border rounded p-1 text-xs text-gray-600"
                       data-field="descripcion"
                       name="items[${i}][descripcion]"
                       value="${escHtml(it.descripcion)}"
                       placeholder="Descripción del concepto"
                       oninput="items[${i}].descripcion = this.value"
                       ${dis} required>
            </td>
            <td class="p-2">
                <input class="w-full border rounded p-1 text-sm"
                       data-field="clave_prod_serv"
                       name="items[${i}][clave_prod_serv]"
                       value="${escHtml(it.clave_prod_serv)}"
                       placeholder="01010101"
                       oninput="items[${i}].clave_prod_serv = this.value"
                       ${dis}>
            </td>
            <td class="p-2">
                <input class="w-16 border rounded p-1 text-sm mb-1"
                       data-field="clave_unidad"
                       name="items[${i}][clave_unidad]"
                       value="${escHtml(it.clave_unidad)}"
                       placeholder="H87"
                       oninput="items[${i}].clave_unidad = this.value"
                       ${dis}>
                <input class="w-16 border rounded p-1 text-sm"
                       data-field="unidad"
                       name="items[${i}][unidad]"
                       value="${escHtml(it.unidad)}"
                       placeholder="PZA"
                       oninput="items[${i}].unidad = this.value"
                       ${dis}>
            </td>
            <td class="p-2">
                <input type="number" step="0.001" min="0"
                       class="w-full border rounded p-1 text-right text-sm"
                       data-field="cantidad"
                       name="items[${i}][cantidad]"
                       value="${it.cantidad}"
                       oninput="items[${i}].cantidad = parseFloat(this.value)||0; recalc(${i})"
                       ${dis} required>
            </td>
            <td class="p-2">
                <input type="number" step="0.0001" min="0"
                       class="w-full border rounded p-1 text-right text-sm"
                       data-field="valor_unitario"
                       name="items[${i}][valor_unitario]"
                       value="${it.valor_unitario}"
                       oninput="items[${i}].valor_unitario = parseFloat(this.value)||0; recalc(${i})"
                       ${dis} required>
            </td>
            <td class="p-2">
                <input type="number" step="0.01" min="0"
                       class="w-full border rounded p-1 text-right text-sm"
                       data-field="descuento"
                       name="items[${i}][descuento]"
                       value="${it.descuento}"
                       oninput="items[${i}].descuento = parseFloat(this.value)||0; recalc(${i})"
                       ${dis}>
            </td>
            <td class="p-2 text-center">
                <select class="w-full border rounded p-1 text-sm"
                        onchange="items[${i}].iva_pct = parseInt(this.value); recalc(${i})"
                        ${dis}>
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
                <select class="w-full border rounded p-1 text-sm"
                        name="items[${i}][objeto_imp]"
                        onchange="items[${i}].objeto_imp = this.value"
                        ${dis}>
                    <option value="01" ${it.objeto_imp === '01' ? 'selected' : ''}>01</option>
                    <option value="02" ${it.objeto_imp === '02' ? 'selected' : ''}>02</option>
                    <option value="03" ${it.objeto_imp === '03' ? 'selected' : ''}>03</option>
                </select>
            </td>
            <td class="p-2 text-right font-medium" id="display-importe-${i}">
                $${fmt(it.importe)}
            </td>
            <td class="p-2 text-center">
                ${!locked ? `<button type="button" class="text-red-400 hover:text-red-600" onclick="removeItem(${i})">✕</button>` : ''}
            </td>
        `;

        tbody.appendChild(tr);
    }

    // ─── Cálculos ─────────────────────────────────────────────────────────────
    function recalc(i) {
        var it   = items[i];
        var base = Math.max(
            (parseFloat(it.cantidad)       || 0) *
            (parseFloat(it.valor_unitario) || 0) -
            (parseFloat(it.descuento)      || 0),
            0
        );
        var iva        = base * ((parseFloat(it.iva_pct)  || 0) / 100);
        var ieps       = base * ((parseFloat(it.ieps_pct) || 0) / 100);
        it.iva_importe  = iva;
        it.ieps_importe = ieps;
        it.importe      = base + iva + ieps;

        var hidIvaPct  = document.getElementById('hid-iva-pct-'  + i);
        var hidIvaImp  = document.getElementById('hid-iva-imp-'  + i);
        var hidIepsPct = document.getElementById('hid-ieps-pct-' + i);
        var hidIepsImp = document.getElementById('hid-ieps-imp-' + i);
        var hidImporte = document.getElementById('hid-importe-'  + i);
        if (hidIvaPct)  hidIvaPct.value  = it.iva_pct;
        if (hidIvaImp)  hidIvaImp.value  = it.iva_importe;
        if (hidIepsPct) hidIepsPct.value = it.ieps_pct;
        if (hidIepsImp) hidIepsImp.value = it.ieps_importe;
        if (hidImporte) hidImporte.value = it.importe;

        var cell = document.getElementById('display-importe-' + i);
        if (cell) cell.textContent = '$' + fmt(it.importe);

        updateTotals();
    }

    function updateTotals() {
        var subtotal  = 0;
        var tax_total = 0;
        var grand     = 0;

        items.forEach(function (it) {
            var base = Math.max(
                (parseFloat(it.cantidad)       || 0) *
                (parseFloat(it.valor_unitario) || 0) -
                (parseFloat(it.descuento)      || 0),
                0
            );
            var iva  = base * ((parseFloat(it.iva_pct)  || 0) / 100);
            var ieps = base * ((parseFloat(it.ieps_pct) || 0) / 100);
            subtotal  += base;
            tax_total += iva + ieps;
            grand     += base + iva + ieps;
        });

        document.getElementById('display-subtotal').textContent = '$' + fmt(subtotal);
        document.getElementById('display-iva').textContent      = '$' + fmt(tax_total);
        document.getElementById('display-total').textContent    = '$' + fmt(grand);

        document.getElementById('hidden-subtotal').value  = subtotal;
        document.getElementById('hidden-impuestos').value = tax_total;
        document.getElementById('hidden-total').value     = grand;
    }

    // ─── Envío del formulario ─────────────────────────────────────────────────
    function submitForm() {
        updateTotals();
        document.getElementById('invoice-edit-form').submit();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function fmt(n) {
        return Number(n || 0).toFixed(2);
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    </script>

</x-admin-layout>