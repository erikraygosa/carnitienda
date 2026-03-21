<x-admin-layout
    title="Cotización {{ $quote->folio ?? '#'.$quote->id }}"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cotizaciones','url'=>route('admin.quotes.index')],
        ['name'=>$quote->folio ?? '#'.$quote->id],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.quotes.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <a href="{{ route('admin.quotes.send.form',$quote) }}" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-violet-600 text-white">Enviar</a>
        <a href="{{ route('admin.quotes.pdf',$quote) }}" target="_blank" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">Ver PDF</a>
        <a href="{{ route('admin.quotes.pdf.download',$quote) }}" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">↓ PDF</a>
        @if($quote->status === 'BORRADOR')
            <button form="quote-edit-form" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @endif
    </x-slot>

    @php
        $isLocked     = $quote->status !== 'BORRADOR';
        $selClient    = (string) old('client_id',         $quote->client_id);
        $selPriceList = (string) old('price_list_id',     $quote->price_list_id ?? '');
        $selRoute     = (string) old('shipping_route_id', $quote->shipping_route_id ?? '');
        $valueFecha   = old('fecha',           optional($quote->fecha)->format('Y-m-d\TH:i'));
        $valueProg    = old('programado_para', optional($quote->programado_para ?? null)->toDateString());
        $valueMoneda  = old('moneda',          $quote->moneda);
        $valueVigencia= old('vigencia_hasta',  optional($quote->vigencia_hasta)->toDateString());
        $deliveryType = old('delivery_type',   $quote->delivery_type ?? 'ENVIO');
        $paymentMethod= old('payment_method',  $quote->payment_method ?? 'EFECTIVO');

        $statusClasses = [
            'BORRADOR'   => 'bg-gray-100 text-gray-700',
            'ENVIADA'    => 'bg-sky-100 text-sky-700',
            'APROBADA'   => 'bg-emerald-100 text-emerald-700',
            'RECHAZADA'  => 'bg-rose-100 text-rose-700',
            'CONVERTIDA' => 'bg-indigo-100 text-indigo-700',
            'CANCELADA'  => 'bg-amber-100 text-amber-700',
        ];
        $statusClass = $statusClasses[$quote->status] ?? 'bg-slate-100 text-slate-700';

        $itemsSeed = $quote->items->map(fn($i) => [
            'product_id'  => $i->product_id,
            'descripcion' => $i->descripcion,
            'cantidad'    => (float)$i->cantidad,
            'precio'      => (float)$i->precio,
            'descuento'   => (float)$i->descuento,
            'iva_pct'     => 0,
            'impuesto'    => (float)$i->impuesto,
            'total'       => (float)$i->total,
        ])->values()->toArray();

        $clientDefaults = $clients->mapWithKeys(fn($c) => [(string)$c->id => [
            'shipping_route_id' => (string) ($c->shipping_route_id ?? ''),
            'price_list_id'     => (string) ($c->price_list_id ?? ''),
            'credito_dias'      => (int)    ($c->credito_dias  ?? 0),
            'credito_limite'    => (float)  ($c->credito_limite ?? 0),
            'telefono'          => (string) ($c->telefono ?? ''),
            'entrega_calle'    => $c->entrega_igual_fiscal ? ($c->fiscal_calle   ?? '') : ($c->entrega_calle   ?? ''),
            'entrega_numero'   => $c->entrega_igual_fiscal ? ($c->fiscal_numero  ?? '') : ($c->entrega_numero  ?? ''),
            'entrega_colonia'  => $c->entrega_igual_fiscal ? ($c->fiscal_colonia ?? '') : ($c->entrega_colonia ?? ''),
            'entrega_ciudad'   => $c->entrega_igual_fiscal ? ($c->fiscal_ciudad  ?? '') : ($c->entrega_ciudad  ?? ''),
            'entrega_estado'   => $c->entrega_igual_fiscal ? ($c->fiscal_estado  ?? '') : ($c->entrega_estado  ?? ''),
            'entrega_cp'       => $c->entrega_igual_fiscal ? ($c->fiscal_cp      ?? '') : ($c->entrega_cp      ?? ''),
        ]])->toArray();

        $JS_OVERRIDES  = json_encode($overridesMap  ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_LISTPRICES = json_encode($listPricesMap ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_CDEFAULTS  = json_encode($clientDefaults,     JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT  = json_encode($selClient,           JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_EDITBASE   = json_encode(url('admin/clients'), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_PRICELIST  = json_encode($selPriceList ?: 'client', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        // Valores de entrega guardados en la cotización
        $entregaNombre  = old('entrega_nombre',   $quote->entrega_nombre  ?? '');
        $entregaTel     = old('entrega_telefono',  $quote->entrega_telefono ?? '');
        $entregaCalle   = old('entrega_calle',     $quote->entrega_calle   ?? '');
        $entregaNumero  = old('entrega_numero',    $quote->entrega_numero  ?? '');
        $entregaColonia = old('entrega_colonia',   $quote->entrega_colonia ?? '');
        $entregaCiudad  = old('entrega_ciudad',    $quote->entrega_ciudad  ?? '');
        $entregaEstado  = old('entrega_estado',    $quote->entrega_estado  ?? '');
        $entregaCp      = old('entrega_cp',        $quote->entrega_cp      ?? '');
    @endphp

    {{-- ====== FORMULARIO ====== --}}
    <x-wire-card>
        @if ($errors->any())
            <div class="mb-4 text-red-600 text-sm"><ul class="list-disc pl-5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul></div>
        @endif

        <form id="quote-edit-form" method="POST" action="{{ route('admin.quotes.update',$quote) }}"
              class="space-y-6" x-data="qFormEdit()" x-init="init()">
            @csrf @method('PUT')

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id_quote_edit"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            x-model="client_id"
                            @change="onClientChange()"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <template x-if="creditoLimite > 0">
                        <p class="mt-1 text-xs text-gray-500">
                            Límite: $<span x-text="creditoLimite.toFixed(2)"></span>
                            &nbsp;·&nbsp; Días: <span x-text="creditoDias"></span>d
                        </p>
                    </template>
                </div>

                {{-- Lista de precios --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
                    <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            x-model="selectedPriceList"
                            @change="repriceAll()"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="client">Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}">{{ $pl->nombre }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="price_list_id" :value="selectedPriceList === 'client' ? '' : selectedPriceList">
                </div>

                {{-- Fecha --}}
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local"
                                  value="{{ $valueFecha }}" :disabled="$isLocked" required />
                </div>

                {{-- Programado para --}}
                <div>
                    <x-wire-input label="Programado para" name="programado_para" type="date"
                                  value="{{ $valueProg }}" :disabled="$isLocked" />
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="{{ $valueMoneda }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

                {{-- Vigencia --}}
                <div>
                    <x-wire-input label="Vigencia hasta" name="vigencia_hasta" type="date"
                                  value="{{ $valueVigencia }}" :disabled="$isLocked" />
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            x-model="shipping_route_id"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- sin ruta --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selRoute===(string)$r->id ? 'selected' : '' }}>
                                {{ $r->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            x-model="delivery_type"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="ENVIO">Envío a domicilio</option>
                        <option value="RECOGER">Recoger en almacén</option>
                    </select>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="payment_method"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            x-model="payment_method"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="EFECTIVO">Efectivo</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="CONTRAENTREGA">Contraentrega</option>
                        <option value="CREDITO">Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div x-show="payment_method === 'CREDITO'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Días de crédito
                        <span class="text-gray-400 font-normal text-xs">(del cliente)</span>
                    </label>
                    <input type="number" name="credit_days"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm"
                           x-model="creditoDias"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

            </div>

            {{-- ====== DIRECCIÓN DE ENTREGA (solo si ENVIO) ====== --}}
            <div x-show="delivery_type === 'ENVIO'"
                 x-transition
                 class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-4">
                <div class="md:col-span-3">
                    <p class="text-sm font-medium text-gray-700">Datos de entrega</p>
                </div>
                <div>
                    <x-wire-input label="Nombre quien recibe" name="entrega_nombre"
                                  value="{{ $entregaNombre }}" :disabled="$isLocked" />
                </div>
                <div>
                    <x-wire-input label="Teléfono" name="entrega_telefono"
                                  value="{{ $entregaTel }}" :disabled="$isLocked" />
                </div>
                <div>
                    <x-wire-input label="Calle" name="entrega_calle"
                                  value="{{ $entregaCalle }}" :disabled="$isLocked" />
                </div>
                <div>
                    <x-wire-input label="Número" name="entrega_numero"
                                  value="{{ $entregaNumero }}" :disabled="$isLocked" />
                </div>
                <div>
                    <x-wire-input label="Colonia" name="entrega_colonia"
                                  value="{{ $entregaColonia }}" :disabled="$isLocked" />
                </div>
                <div>
                    <x-wire-input label="Ciudad" name="entrega_ciudad"
                                  value="{{ $entregaCiudad }}" :disabled="$isLocked" />
                </div>
                <div>
                    <x-wire-input label="Estado" name="entrega_estado"
                                  value="{{ $entregaEstado }}" :disabled="$isLocked" />
                </div>
                <div>
                    <x-wire-input label="CP" name="entrega_cp"
                                  value="{{ $entregaCp }}" :disabled="$isLocked" />
                </div>
            </div>

            {{-- ====== ALERTA: precio cero ====== --}}
            <template x-if="client_id && anyZeroPrice">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 flex items-center justify-between">
                    <span>Algunos productos no tienen precio para este cliente. Se estableció $0.00.</span>
                    <a :href="clientEditUrl" target="_blank"
                       class="ml-3 inline-flex px-3 py-1.5 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">
                        Editar precios
                    </a>
                </div>
            </template>

            {{-- ====== PARTIDAS ====== --}}
            <div class="overflow-auto border-t pt-4">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50">
                        <tr>
                            <th class="p-2 text-left">Producto</th>
                            <th class="p-2 text-left">Descripción</th>
                            <th class="p-2 text-right">Cantidad</th>
                            <th class="p-2 text-right">Precio</th>
                            <th class="p-2 text-right">Desc.</th>
                            <th class="p-2 text-right">% IVA</th>
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(it, i) in items" :key="i">
                            <tr class="border-b">
                                <td class="p-2">
                                    <select class="w-48 border rounded p-1 text-sm"
                                            x-bind:name="'items['+i+'][product_id]'"
                                            x-model="it.product_id"
                                            @change="onProductChange(i, $event)"
                                            :disabled="locked">
                                        <option value="">—</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}"
                                                    data-nombre="{{ $p->nombre }}"
                                                    data-precio="{{ $p->precio_base }}">
                                                {{ $p->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input type="text" class="w-64 border rounded p-1 text-sm"
                                           x-bind:name="'items['+i+'][descripcion]'"
                                           x-model="it.descripcion" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0.001" step="0.001"
                                           class="w-24 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][cantidad]'"
                                           x-model.number="it.cantidad" @input="recalc(i)" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.0001"
                                           class="w-28 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][precio]'"
                                           x-model.number="it.precio" @input="recalc(i)" :disabled="locked" required>
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01"
                                           class="w-24 border rounded p-1 text-right text-sm"
                                           x-model.number="it.descuento" @input="recalc(i)" :disabled="locked">
                                    <input type="hidden" x-bind:name="'items['+i+'][descuento]'" x-model="it.descuento">
                                </td>
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01"
                                           class="w-20 border rounded p-1 text-right text-sm"
                                           x-model.number="it.iva_pct" @input="recalc(i)" :disabled="locked">
                                    <input type="hidden" x-bind:name="'items['+i+'][impuesto]'" x-model="it.impuesto">
                                </td>
                                <td class="p-2 text-right font-medium" x-text="fmt(it.total)"></td>
                                <td class="p-2" x-show="!locked">
                                    <button type="button" class="text-red-500 hover:text-red-700 text-xs"
                                            @click="remove(i)">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="mt-3" x-show="!locked">
                    <button type="button"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50"
                            @click="add()">+ Agregar partida</button>
                </div>
            </div>

            {{-- ====== TOTALES ====== --}}
            <div class="text-right space-y-1 border-t pt-3">
                <div class="text-sm text-gray-600">Subtotal: <span class="font-medium" x-text="fmt(subtotal)"></span></div>
                <div class="text-sm text-gray-600">Descuento: <span class="font-medium" x-text="fmt(desc_total)"></span></div>
                <div class="text-sm text-gray-600">Impuestos: <span class="font-medium" x-text="fmt(tax_total)"></span></div>
                <div class="text-lg font-bold text-gray-800">Total: $<span x-text="fmt(grand)"></span></div>
            </div>
        </form>
    </x-wire-card>

    {{-- ====== ACCIONES ====== --}}
    <x-wire-card class="mt-4">
        <div class="flex flex-wrap items-center gap-2">
            {{-- Badge de estatus --}}
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                {{ $quote->status_label ?? $quote->status }}
            </span>

            <div class="ml-auto flex flex-wrap items-center gap-2">

                @if(in_array($quote->status, ['BORRADOR','ENVIADA']))
                    {{-- Rechazar --}}
                    <form method="POST" action="{{ route('admin.quotes.reject',$quote) }}">@csrf
                        <x-wire-button type="submit" amber xs>Rechazar</x-wire-button>
                    </form>
                @endif

                @if(!in_array($quote->status, ['CONVERTIDA','CANCELADA']))
                    {{-- Cancelar --}}
                    <form method="POST" action="{{ route('admin.quotes.cancel',$quote) }}">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @endif

                @if(in_array($quote->status, ['BORRADOR','ENVIADA']))
                    {{--
                        Aprobar y convertir en pedido.
                        Todos los datos necesarios (almacén, método de pago, tipo de entrega,
                        días de crédito, ruta, dirección) vienen ya guardados en la cotización.
                        El controller los lee directamente del modelo $quote.
                    --}}
                    <form method="POST" action="{{ route('admin.quotes.approve',$quote) }}">
                        @csrf
                        {{-- Pasamos los campos de la cotización como hidden para que el controller
                             los tenga disponibles sin necesidad de inputs visibles --}}
                        <input type="hidden" name="warehouse_id"       value="{{ $quote->warehouse_id       ?? $mainWarehouseId ?? '' }}">
                        <input type="hidden" name="payment_method"     value="{{ $quote->payment_method     ?? 'EFECTIVO' }}">
                        <input type="hidden" name="delivery_type"      value="{{ $quote->delivery_type      ?? 'ENVIO' }}">
                        <input type="hidden" name="credit_days"        value="{{ $quote->credit_days        ?? 0 }}">
                        <input type="hidden" name="programado_para"    value="{{ $valueProg }}">
                        <input type="hidden" name="shipping_route_id"  value="{{ $quote->shipping_route_id  ?? '' }}">
                        <input type="hidden" name="entrega_nombre"     value="{{ $entregaNombre }}">
                        <input type="hidden" name="entrega_telefono"   value="{{ $entregaTel }}">
                        <input type="hidden" name="entrega_calle"      value="{{ $entregaCalle }}">
                        <input type="hidden" name="entrega_numero"     value="{{ $entregaNumero }}">
                        <input type="hidden" name="entrega_colonia"    value="{{ $entregaColonia }}">
                        <input type="hidden" name="entrega_ciudad"     value="{{ $entregaCiudad }}">
                        <input type="hidden" name="entrega_estado"     value="{{ $entregaEstado }}">
                        <input type="hidden" name="entrega_cp"         value="{{ $entregaCp }}">
                        <x-wire-button type="submit" green xs>✓ Aprobar y generar pedido</x-wire-button>
                    </form>
                @endif

            </div>
        </div>

        {{-- Badge ya convertida --}}
        @if(in_array($quote->status, ['APROBADA','CONVERTIDA']))
        <div class="mt-4 border-t pt-4">
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-emerald-100 text-emerald-700">
                ✓ Cotización aprobada — convertida en pedido
            </span>
        </div>
        @endif
    </x-wire-card>

    <script>
    function qFormEdit(){
        const seed            = @json($itemsSeed);
        const locked          = @json($isLocked);
        const CLIENTS_OVERRIDES = {!! $JS_OVERRIDES !!};
        const LISTS_PRICES      = {!! $JS_LISTPRICES !!};
        const CLIENT_DEFAULTS   = {!! $JS_CDEFAULTS !!};
        const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};
        const CLIENTS_EDIT_BASE = {!! $JS_EDITBASE !!};
        const INITIAL_PRICE_LIST= {!! $JS_PRICELIST !!};

        return {
            items: (seed && seed.length) ? seed
                : [{product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}],
            locked,
            client_id:         DEFAULT_CLIENT_ID || '',
            selectedPriceList: INITIAL_PRICE_LIST || 'client',
            shipping_route_id: @json($selRoute),
            payment_method:    @json($paymentMethod),
            delivery_type:     @json($deliveryType),
            creditoDias:       0,
            creditoLimite:     0,
            overridesMap:      CLIENTS_OVERRIDES,
            listPricesMap:     LISTS_PRICES,
            anyZeroPrice:      false,
            subtotal:0, desc_total:0, tax_total:0, grand:0,

            init(){
                // Cargar info de crédito del cliente guardado sin sobreescribir campos
                if(this.client_id){
                    const d = CLIENT_DEFAULTS[this.client_id];
                    if(d){
                        this.creditoDias   = d.credito_dias   || 0;
                        this.creditoLimite = d.credito_limite || 0;
                    }
                }
                this.sum();
            },

            get clientEditUrl(){
                return this.client_id ? `${CLIENTS_EDIT_BASE}/${this.client_id}/edit` : '#';
            },

            onClientChange(){
                if(this.locked) return;
                const d = CLIENT_DEFAULTS[this.client_id];
                if(!d){
                    this.creditoDias       = 0;
                    this.creditoLimite     = 0;
                    this.shipping_route_id = '';
                    return;
                }
                if(d.shipping_route_id) this.shipping_route_id = d.shipping_route_id;
                if(d.price_list_id)     this.selectedPriceList  = d.price_list_id;
                this.creditoDias   = d.credito_dias   || 0;
                this.creditoLimite = d.credito_limite || 0;
                if(d.credito_dias > 0) this.payment_method = 'CREDITO';

                const set = (name, val) => {
                    const el = document.querySelector(`[name="${name}"]`);
                    if(el && val) el.value = val;
                };
                set('entrega_telefono', d.telefono        || '');
                set('entrega_calle',    d.entrega_calle   || '');
                set('entrega_numero',   d.entrega_numero  || '');
                set('entrega_colonia',  d.entrega_colonia || '');
                set('entrega_ciudad',   d.entrega_ciudad  || '');
                set('entrega_estado',   d.entrega_estado  || '');
                set('entrega_cp',       d.entrega_cp      || '');

                this.repriceAll();
            },

            getUnitPrice(productId){
                if(!productId) return 0;
                if(this.selectedPriceList === 'client'){
                    const map = this.overridesMap[this.client_id] || {};
                    const val = map[productId];
                    return (val == null) ? 0 : parseFloat(val) || 0;
                }
                const listMap = this.listPricesMap[this.selectedPriceList] || {};
                const val = listMap[productId];
                return (val == null) ? 0 : parseFloat(val) || 0;
            },

            onProductChange(i, ev){
                if(this.locked) return;
                const it = this.items[i];
                if(!it.descripcion && ev?.target){
                    it.descripcion = (ev.target.options[ev.target.selectedIndex]?.text || '').trim();
                }
                it.precio = this.getUnitPrice(it.product_id);
                this.recalc(i);
            },

            repriceAll(){
                this.items.forEach((it, i) => {
                    if(it.product_id){
                        it.precio = this.getUnitPrice(it.product_id);
                        this.recalc(i, true);
                    }
                });
                this.sum();
            },

            add(){
                if(this.locked) return;
                this.items.push({product_id:'',descripcion:'',cantidad:1,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0});
            },
            remove(i){ if(this.locked) return; this.items.splice(i,1); this.sum(); },

            recalc(i, skipSum=false){
                const it   = this.items[i];
                const line = (+it.cantidad||0)*(+it.precio||0);
                const disc = +it.descuento||0;
                const base = Math.max(line-disc,0);
                const tax  = ((+it.iva_pct||0)*0.01)*base;
                it.impuesto = tax; it.total = base+tax;
                if(!skipSum) this.sum();
            },

            sum(){
                let s=0,d=0,t=0,g=0,hasZero=false;
                this.items.forEach(it=>{
                    const line=(+it.cantidad||0)*(+it.precio||0);
                    const disc=+it.descuento||0;
                    const base=Math.max(line-disc,0);
                    const tax=((+it.iva_pct||0)*0.01)*base;
                    const tot=base+tax;
                    s+=line; d+=disc; t+=tax; g+=tot;
                    if((+it.precio||0)===0 && it.product_id) hasZero=true;
                    it.impuesto=tax; it.total=tot;
                });
                this.subtotal=s; this.desc_total=d; this.tax_total=t; this.grand=g;
                this.anyZeroPrice=hasZero;
            },

            fmt(n){ return Number(n||0).toFixed(2); }
        }
    }
    </script>
</x-admin-layout>