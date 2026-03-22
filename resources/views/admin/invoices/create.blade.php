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
        <button form="inv-form" type="submit"
                class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    @php
        $valueFecha = old('fecha', now()->format('Y-m-d\TH:i'));

        $itemsSeed = old('items', [[
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

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
            <strong class="block mb-2">Revisa los siguientes errores:</strong>
            <ul class="list-disc ml-5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-wire-card>
        <form id="inv-form"
              method="POST"
              action="{{ route('admin.invoices.store') }}"
              class="space-y-6"
              x-data="invForm()"
              x-init="init()">
            @csrf

            {{-- Campos ocultos del emisor --}}
            <input type="hidden" name="lugar_expedicion"      value="{{ old('lugar_expedicion',      $emisorDefaults['lugar_expedicion']) }}">
            <input type="hidden" name="regimen_fiscal_emisor" value="{{ old('regimen_fiscal_emisor', $emisorDefaults['regimen_fiscal_emisor']) }}">
            <input type="hidden" name="serie"      value="A">
            <input type="hidden" name="folio"      value="">
            <input type="hidden" name="exportacion" value="01">

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
            @else
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                ⚠ No hay empresa activa configurada.
                <a href="{{ route('admin.parametros.companies.index') }}" class="underline font-medium ml-1">Configúrala aquí</a>
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
                            required>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ (string)$cid === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                    {{-- Info del cliente seleccionado --}}
                    <div x-show="clientInfo.rfc" x-cloak
                         class="text-xs text-gray-500 flex gap-3 pt-1">
                        <span>RFC: <strong x-text="clientInfo.rfc"></strong></span>
                        <span x-show="clientInfo.razon_social" x-text="clientInfo.razon_social"></span>
                    </div>
                </div>

                {{-- Fecha --}}
                <x-wire-input
                    label="Fecha"
                    name="fecha"
                    type="datetime-local"
                    value="{{ $valueFecha }}"
                    required />

                {{-- Moneda --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Moneda</label>
                    @php $selMoneda = old('moneda', 'MXN'); @endphp
                    <select name="moneda"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="MXN" {{ $selMoneda === 'MXN' ? 'selected' : '' }}>MXN — Peso mexicano</option>
                        <option value="USD" {{ $selMoneda === 'USD' ? 'selected' : '' }}>USD — Dólar americano</option>
                    </select>
                </div>

                {{-- Tipo --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Tipo <span class="text-red-500">*</span></label>
                    @php $tc = old('tipo_comprobante', 'I'); @endphp
                    <select name="tipo_comprobante"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="I" {{ $tc === 'I' ? 'selected' : '' }}>Ingreso</option>
                        <option value="E" {{ $tc === 'E' ? 'selected' : '' }}>Egreso</option>
                    </select>
                </div>

                {{-- Forma de pago --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Forma de pago</label>
                    @php $selForma = old('forma_pago', '03'); @endphp
                    <select name="forma_pago"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($formasPago as $k => $lbl)
                            <option value="{{ $k }}" {{ $selForma === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de pago --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Método de pago</label>
                    @php $selMetodo = old('metodo_pago', 'PUE'); @endphp
                    <select name="metodo_pago"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona régimen</option>
                            @foreach($regimenes as $clave => $desc)
                                <option value="{{ $clave }}" {{ $selRegRec === $clave ? 'selected' : '' }}>
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
                                                @change="onProductChange(i, $event)">
                                            <option value="">— seleccionar —</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}"
                                                        :selected="it.product_id == '{{ $p->id }}'">
                                                    {{ $p->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" :name="'items['+i+'][product_id]'" x-model="it.product_id">
                                        {{-- Descripción editable debajo --}}
                                        <input class="w-full border rounded p-1 text-xs mt-1 text-gray-600"
                                               x-model="it.descripcion"
                                               :name="'items['+i+'][descripcion]'"
                                               placeholder="Descripción del concepto"
                                               required>
                                    </td>

                                    {{-- Clave SAT (autocompleta) --}}
                                    <td class="p-2">
                                        <input class="w-full border rounded p-1 text-sm"
                                               x-model="it.clave_prod_serv"
                                               :name="'items['+i+'][clave_prod_serv]'"
                                               placeholder="01010101">
                                    </td>

                                    {{-- Unidad --}}
                                    <td class="p-2">
                                        <input class="w-16 border rounded p-1 text-sm mb-1"
                                               x-model="it.clave_unidad"
                                               :name="'items['+i+'][clave_unidad]'"
                                               placeholder="H87">
                                        <input class="w-16 border rounded p-1 text-sm"
                                               x-model="it.unidad"
                                               :name="'items['+i+'][unidad]'"
                                               placeholder="PZA">
                                    </td>

                                    {{-- Cantidad --}}
                                    <td class="p-2">
                                        <input type="number" step="0.001" min="0"
                                               class="w-full border rounded p-1 text-right text-sm"
                                               x-model.number="it.cantidad"
                                               :name="'items['+i+'][cantidad]'"
                                               @input="recalc(i)" required>
                                    </td>

                                    {{-- Valor unitario --}}
                                    <td class="p-2">
                                        <input type="number" step="0.0001" min="0"
                                               class="w-full border rounded p-1 text-right text-sm"
                                               x-model.number="it.valor_unitario"
                                               :name="'items['+i+'][valor_unitario]'"
                                               @input="recalc(i)" required>
                                    </td>

                                    {{-- Descuento --}}
                                    <td class="p-2">
                                        <input type="number" step="0.01" min="0"
                                               class="w-full border rounded p-1 text-right text-sm"
                                               x-model.number="it.descuento"
                                               :name="'items['+i+'][descuento]'"
                                               @input="recalc(i)">
                                    </td>

                                    {{-- % IVA --}}
                                    <td class="p-2 text-center">
                                        <select class="w-full border rounded p-1 text-sm"
                                                x-model.number="it.iva_pct"
                                                @change="recalc(i)">
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
                                                :name="'items['+i+'][objeto_imp]'">
                                            <option value="01">01</option>
                                            <option value="02">02</option>
                                            <option value="03">03</option>
                                        </select>
                                    </td>

                                    {{-- Importe --}}
                                    <td class="p-2 text-right font-medium"
                                        x-text="'$' + fmt(it.importe)"></td>

                                    {{-- Eliminar --}}
                                    <td class="p-2 text-center">
                                        <button type="button"
                                                class="text-red-400 hover:text-red-600"
                                                @click="remove(i)">✕</button>
                                    </td>

                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div class="mt-3">
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

    <script>
    function invForm() {
        const SEED         = @json($itemsSeed);
        const CLIENTS_MAP  = @json($clientsMap);
        const PRODUCTS_MAP = @json($productsMap);
        const PREFILL_CID  = @json((string) old('client_id', $prefill['client_id'] ?? ''));

        return {
            items:      JSON.parse(JSON.stringify(SEED)),
            client_id:  PREFILL_CID || '',
            clientInfo: { rfc: '', razon_social: '' },
            subtotal:   0,
            tax_total:  0,
            grand:      0,

            init() {
                if (this.client_id) this._applyClient(this.client_id);
                this.items.forEach((_, i) => this.recalc(i));
            },

            onClientChange() {
                this._applyClient(this.client_id);
            },

            _applyClient(clientId) {
                const d = CLIENTS_MAP[String(clientId)];
                if (!d) {
                    this.clientInfo = { rfc: '', razon_social: '' };
                    return;
                }

                // Mostrar info bajo el select
                this.clientInfo = {
                    rfc:          d.rfc          || '',
                    razon_social: d.razon_social  || '',
                };

                // Autocompletar régimen fiscal receptor
                const regEl = document.getElementById('regimen_fiscal_receptor');
                if (regEl && d.regimen_fiscal) regEl.value = d.regimen_fiscal;

                // Autocompletar uso CFDI
                const usoEl = document.getElementById('uso_cfdi');
                if (usoEl && d.uso_cfdi) usoEl.value = d.uso_cfdi;
            },

            onProductChange(i, ev) {
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
                this.items.push({
                    product_id: '', clave_prod_serv: '01010101',
                    clave_unidad: 'H87', unidad: 'PZA',
                    descripcion: '', cantidad: 1,
                    valor_unitario: 0, descuento: 0,
                    iva_pct: 16, objeto_imp: '02',
                    importe: 0, iva_importe: 0,
                    ieps_pct: 0, ieps_importe: 0,
                });
            },

            remove(i) {
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