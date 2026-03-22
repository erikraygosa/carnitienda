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
            <button form="invoice-edit-form" type="submit"
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
              class="space-y-6"
              x-data="invoiceEditForm()"
              x-init="init()">
            @csrf
            @method('PUT')

            {{-- Campos ocultos del emisor --}}
            <input type="hidden" name="lugar_expedicion"
                   value="{{ old('lugar_expedicion', $invoice->lugar_expedicion ?? $emisorDefaults['lugar_expedicion']) }}">
            <input type="hidden" name="regimen_fiscal_emisor"
                   value="{{ old('regimen_fiscal_emisor', $invoice->regimen_fiscal_emisor ?? $emisorDefaults['regimen_fiscal_emisor']) }}">
            <input type="hidden" name="exportacion" value="{{ $invoice->exportacion ?? '01' }}">

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
                            x-model="client_id"
                            @change="onClientChange()"
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
                    <div x-show="clientInfo.rfc" x-cloak
                         class="text-xs text-gray-500 flex gap-3 pt-1">
                        <span>RFC: <strong x-text="clientInfo.rfc"></strong></span>
                        <span x-show="clientInfo.razon_social" x-text="clientInfo.razon_social"></span>
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
                    <table class="min-w-full text-sm">
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
                        <tbody>
                            <template x-for="(it, i) in items" :key="i">
                                <tr class="border-b hover:bg-gray-50">

                                    {{-- Producto --}}
                                    <td class="p-2">
                                        <select class="w-full border rounded p-1 text-sm"
                                                @change="onProductChange(i, $event)"
                                                :disabled="locked">
                                            <option value="">— seleccionar —</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}"
                                                        :selected="it.product_id == '{{ $p->id }}'">
                                                    {{ $p->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" :name="'items['+i+'][product_id]'" x-model="it.product_id">
                                        <input class="w-full border rounded p-1 text-xs mt-1 text-gray-600"
                                               x-model="it.descripcion"
                                               :name="'items['+i+'][descripcion]'"
                                               placeholder="Descripción del concepto"
                                               :disabled="locked"
                                               required>
                                    </td>

                                    {{-- Clave SAT --}}
                                    <td class="p-2">
                                        <input class="w-full border rounded p-1 text-sm"
                                               x-model="it.clave_prod_serv"
                                               :name="'items['+i+'][clave_prod_serv]'"
                                               :disabled="locked"
                                               placeholder="01010101">
                                    </td>

                                    {{-- Unidad --}}
                                    <td class="p-2">
                                        <input class="w-16 border rounded p-1 text-sm mb-1"
                                               x-model="it.clave_unidad"
                                               :name="'items['+i+'][clave_unidad]'"
                                               :disabled="locked"
                                               placeholder="H87">
                                        <input class="w-16 border rounded p-1 text-sm"
                                               x-model="it.unidad"
                                               :name="'items['+i+'][unidad]'"
                                               :disabled="locked"
                                               placeholder="PZA">
                                    </td>

                                    {{-- Cantidad --}}
                                    <td class="p-2">
                                        <input type="number" step="0.001" min="0"
                                               class="w-full border rounded p-1 text-right text-sm"
                                               x-model.number="it.cantidad"
                                               :name="'items['+i+'][cantidad]'"
                                               @input="recalc(i)"
                                               :disabled="locked" required>
                                    </td>

                                    {{-- Valor unitario --}}
                                    <td class="p-2">
                                        <input type="number" step="0.0001" min="0"
                                               class="w-full border rounded p-1 text-right text-sm"
                                               x-model.number="it.valor_unitario"
                                               :name="'items['+i+'][valor_unitario]'"
                                               @input="recalc(i)"
                                               :disabled="locked" required>
                                    </td>

                                    {{-- Descuento --}}
                                    <td class="p-2">
                                        <input type="number" step="0.01" min="0"
                                               class="w-full border rounded p-1 text-right text-sm"
                                               x-model.number="it.descuento"
                                               :name="'items['+i+'][descuento]'"
                                               @input="recalc(i)"
                                               :disabled="locked">
                                    </td>

                                    {{-- % IVA --}}
                                    <td class="p-2 text-center">
                                        <select class="w-full border rounded p-1 text-sm"
                                                x-model.number="it.iva_pct"
                                                @change="recalc(i)"
                                                :disabled="locked">
                                            <option value="0">0%</option>
                                            <option value="8">8%</option>
                                            <option value="16">16%</option>
                                        </select>
                                        <input type="hidden" :name="'items['+i+'][iva_pct]'"     x-model="it.iva_pct">
                                        <input type="hidden" :name="'items['+i+'][iva_importe]'" x-model="it.iva_importe">
                                        <input type="hidden" :name="'items['+i+'][ieps_pct]'"    x-model="it.ieps_pct">
                                    </td>

                                    {{-- Objeto impuesto --}}
                                    <td class="p-2 text-center">
                                        <select class="w-full border rounded p-1 text-sm"
                                                x-model="it.objeto_imp"
                                                :name="'items['+i+'][objeto_imp]'"
                                                :disabled="locked">
                                            <option value="01">01</option>
                                            <option value="02">02</option>
                                            <option value="03">03</option>
                                        </select>
                                    </td>

                                    {{-- Importe --}}
                                    <td class="p-2 text-right font-medium"
                                        x-text="'$' + fmt(it.importe)"></td>

                                    {{-- Eliminar --}}
                                    <td class="p-2 text-center" x-show="!locked">
                                        <button type="button"
                                                class="text-red-400 hover:text-red-600"
                                                @click="remove(i)">✕</button>
                                    </td>

                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div class="mt-3" x-show="!locked">
                        <button type="button"
                                class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50"
                                @click="add()">
                            + Agregar partida
                        </button>
                    </div>
                </div>
            </div>

            {{-- ====== TOTALES ====== --}}
            <div class="flex justify-end border-t pt-4">
                <div class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span x-text="'$' + fmt(subtotal)"></span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>IVA</span>
                        <span x-text="'$' + fmt(tax_total)"></span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-900 text-base border-t pt-2">
                        <span>Total</span>
                        <span x-text="'$' + fmt(grand)"></span>
                    </div>
                </div>
            </div>

            <input type="hidden" name="subtotal"  x-model="subtotal">
            <input type="hidden" name="impuestos" x-model="tax_total">
            <input type="hidden" name="total"     x-model="grand">

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
                      x-data x-on:submit.prevent="if(confirm('¿Cancelar esta factura en el SAT?')) $el.submit()">
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
    function invoiceEditForm() {
        const SEED         = @json($itemsSeed);
        const CLIENTS_MAP  = @json($clientsMap);
        const PRODUCTS_MAP = @json($productsMap);
        const PREFILL_CID  = @json((string)($invoice->client_id ?? ''));
        const IS_LOCKED    = @json($isLocked);

        return {
            items:      JSON.parse(JSON.stringify(SEED)),
            locked:     IS_LOCKED,
            client_id:  PREFILL_CID || '',
            clientInfo: { rfc: '', razon_social: '' },
            subtotal:   0,
            tax_total:  0,
            grand:      0,

            init() {
                if (this.client_id) this._applyClient(this.client_id, false);
                this.items.forEach((_, i) => this.recalc(i));
            },

            onClientChange() {
                this._applyClient(this.client_id, true);
            },

            _applyClient(clientId, updateSelects) {
                const d = CLIENTS_MAP[String(clientId)];
                if (!d) {
                    this.clientInfo = { rfc: '', razon_social: '' };
                    return;
                }

                this.clientInfo = {
                    rfc:          d.rfc         || '',
                    razon_social: d.razon_social || '',
                };

                if (updateSelects) {
                    const regEl = document.getElementById('regimen_fiscal_receptor');
                    if (regEl && d.regimen_fiscal) regEl.value = d.regimen_fiscal;

                    const usoEl = document.getElementById('uso_cfdi');
                    if (usoEl && d.uso_cfdi) usoEl.value = d.uso_cfdi;
                }
            },

            onProductChange(i, ev) {
                if (this.locked) return;
                const productId = ev.target.value;
                const it        = this.items[i];

                if (!productId) {
                    it.product_id      = '';
                    it.descripcion     = '';
                    it.clave_prod_serv = '01010101';
                    it.clave_unidad    = 'H87';
                    it.unidad          = 'PZA';
                    it.valor_unitario  = 0;
                    this.recalc(i);
                    return;
                }

                const p = PRODUCTS_MAP[String(productId)];
                if (!p) return;

                it.product_id      = productId;
                it.descripcion     = p.nombre;
                it.clave_prod_serv = p.clave_prod_serv || '01010101';
                it.clave_unidad    = p.clave_unidad    || 'H87';
                it.unidad          = p.unidad          || 'PZA';
                it.valor_unitario  = p.precio_base     || 0;

                this.recalc(i);
            },

            add() {
                if (this.locked) return;
                this.items.push({
                    id: null, product_id: '',
                    clave_prod_serv: '01010101',
                    clave_unidad: 'H87', unidad: 'PZA',
                    descripcion: '', cantidad: 1,
                    valor_unitario: 0, descuento: 0,
                    iva_pct: 16, objeto_imp: '02',
                    importe: 0, iva_importe: 0,
                    ieps_pct: 0, ieps_importe: 0,
                });
            },

            remove(i) {
                if (this.locked) return;
                this.items.splice(i, 1);
                this.sum();
            },

            recalc(i) {
                const it   = this.items[i];
                const base = Math.max(
                    (+it.cantidad || 0) * (+it.valor_unitario || 0) - (+it.descuento || 0),
                    0
                );
                const iva       = base * ((+it.iva_pct  || 0) / 100);
                const ieps      = base * ((+it.ieps_pct || 0) / 100);
                it.iva_importe  = iva;
                it.ieps_importe = ieps;
                it.importe      = base + iva + ieps;
                this.sum();
            },

            sum() {
                let s = 0, t = 0, g = 0;
                this.items.forEach(it => {
                    const base = Math.max(
                        (+it.cantidad || 0) * (+it.valor_unitario || 0) - (+it.descuento || 0),
                        0
                    );
                    const iva       = base * ((+it.iva_pct  || 0) / 100);
                    const ieps      = base * ((+it.ieps_pct || 0) / 100);
                    it.iva_importe  = iva;
                    it.ieps_importe = ieps;
                    it.importe      = base + iva + ieps;
                    s += base; t += iva + ieps; g += it.importe;
                });
                this.subtotal  = s;
                this.tax_total = t;
                this.grand     = g;
            },

            fmt(n) { return Number(n || 0).toFixed(2); }
        };
    }
    </script>

</x-admin-layout>