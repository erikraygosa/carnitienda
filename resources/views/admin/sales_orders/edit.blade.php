<x-admin-layout
    title="Editar pedido"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Pedidos','url'=>route('admin.sales-orders.index')],
        ['name'=>'Editar'],
    ]"
>
    {{-- ====== Acción superior ====== --}}
    <x-slot name="action">
        <a href="{{ route('admin.sales-orders.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>

        @if($order->status === 'BORRADOR')
            <button form="so-edit-form"
                    type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Actualizar
            </button>
        @elseif($order->status === 'PREPARANDO')
            <button form="so-edit-form"
                    type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Guardar cantidades
            </button>
        @endif
    </x-slot>

    @php
        $isLocked     = !in_array($order->status, ['BORRADOR','PREPARANDO']);
        $canEditQty   = in_array($order->status, ['BORRADOR','PREPARANDO']);

        $selClient    = (string) old('client_id',          $order->client_id);
        $selWarehouse = (string) old('warehouse_id',        $order->warehouse_id);
        $selRoute     = (string) old('shipping_route_id',   $order->shipping_route_id);
        $selDriver    = (string) old('driver_id',           $order->driver_id);

        $valueFecha    = old('fecha',           optional($order->fecha)->format('Y-m-d\TH:i'));
        $valueProg     = old('programado_para', optional($order->programado_para)->toDateString());
        $valueMoneda   = old('moneda',          $order->moneda);
        $deliveryType  = old('delivery_type',   $order->delivery_type);
        $paymentMethod = old('payment_method',  $order->payment_method);
        $creditDays    = old('credit_days',     $order->credit_days);

        $statusClasses = [
            'BORRADOR'     => 'bg-gray-100 text-gray-700',
            'APROBADO'     => 'bg-blue-100 text-blue-700',
            'PREPARANDO'   => 'bg-sky-100 text-sky-700',
            'PROCESADO'    => 'bg-amber-100 text-amber-700',
            'EN_RUTA'      => 'bg-violet-100 text-violet-700',
            'ENTREGADO'    => 'bg-emerald-100 text-emerald-700',
            'NO_ENTREGADO' => 'bg-orange-100 text-orange-700',
            'CANCELADO'    => 'bg-rose-100 text-rose-700',
        ];
        $statusClass = $statusClasses[$order->status] ?? 'bg-slate-100 text-slate-700';

        $itemsSeed = $order->items->map(function($i){
            return [
                'id'          => $i->id,
                'product_id'  => $i->product_id,
                'descripcion' => $i->descripcion,
                'cantidad'    => (float)$i->cantidad,
                'precio'      => (float)$i->precio,
                'descuento'   => (float)$i->descuento,
                'iva_pct'     => 0,
                'impuesto'    => (float)$i->impuesto,
                'total'       => (float)$i->total,
            ];
        })->values()->toArray();

        // Defaults por cliente (igual que create)
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

        $JS_CLIENT_DEFAULTS = json_encode($clientDefaults, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_EDITBASE        = json_encode(url('admin/clients'), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT       = json_encode($selClient, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        // Valores actuales de entrega del pedido
        $entregaNombre  = old('entrega_nombre',  $order->entrega_nombre  ?? '');
        $entregaTel     = old('entrega_telefono', $order->entrega_telefono ?? '');
        $entregaCalle   = old('entrega_calle',   $order->entrega_calle   ?? '');
        $entregaNumero  = old('entrega_numero',  $order->entrega_numero  ?? '');
        $entregaColonia = old('entrega_colonia', $order->entrega_colonia ?? '');
        $entregaCiudad  = old('entrega_ciudad',  $order->entrega_ciudad  ?? '');
        $entregaEstado  = old('entrega_estado',  $order->entrega_estado  ?? '');
        $entregaCp      = old('entrega_cp',      $order->entrega_cp      ?? '');
    @endphp

    {{-- ====== FORMULARIO ====== --}}
    <x-wire-card>
        <form id="so-edit-form"
              method="POST"
              action="{{ route('admin.sales-orders.update',$order) }}"
              class="space-y-6"
              x-data="soFormEdit({ canEditQty: @json($canEditQty) })"
              x-init="init()">
            @csrf @method('PUT')

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id_edit"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id" class="w-full rounded-md border-gray-300 shadow-sm" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Lista de precios --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
                    <select name="price_list_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="client">Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}"
                                {{ old('price_list_id', $order->price_list_id) == $pl->id ? 'selected' : '' }}>
                                {{ $pl->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha --}}
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local"
                                  value="{{ $valueFecha }}" :disabled="$isLocked" required/>
                </div>

                {{-- Programado para --}}
                <div>
                    <x-wire-input label="Programado para" name="programado_para" type="date"
                                  value="{{ $valueProg }}" :disabled="$isLocked"/>
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="{{ $valueMoneda }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="delivery_type"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="ENVIO">Envío a domicilio</option>
                        <option value="RECOGER">Recoger en almacén</option>
                    </select>
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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

                {{-- Chofer --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chofer</label>
                    <select name="driver_id" class="w-full rounded-md border-gray-300 shadow-sm" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- sin chofer --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selDriver===(string)$d->id ? 'selected' : '' }}>
                                {{ $d->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="payment_method"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-model="payment_method"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="EFECTIVO">Efectivo</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="CONTRAENTREGA">Contraentrega</option>
                        <option value="CREDITO">Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito (solo si CREDITO) --}}
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
                                <input type="hidden" :name="'items['+i+'][id]'" x-model="it.id">

                                {{-- Producto --}}
                                <td class="p-2">
                                    <select class="w-48 border rounded p-1 text-sm"
                                            x-bind:name="'items['+i+'][product_id]'"
                                            x-model="it.product_id"
                                            @change="syncDescFromProduct(i)"
                                            :disabled="!canEditQty && locked">
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

                                {{-- Descripción --}}
                                <td class="p-2">
                                    <input type="text" class="w-64 border rounded p-1 text-sm"
                                           x-bind:name="'items['+i+'][descripcion]'"
                                           x-model="it.descripcion"
                                           :disabled="!canEditQty && locked" required>
                                </td>

                                {{-- Cantidad --}}
                                <td class="p-2 text-right">
                                    <input type="number" min="0.001" step="0.001"
                                           class="w-24 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][cantidad]'"
                                           x-model.number="it.cantidad"
                                           @input="recalc(i)"
                                           :disabled="!canEditQty" required>
                                </td>

                                {{-- Precio --}}
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.0001"
                                           class="w-28 border rounded p-1 text-right text-sm"
                                           x-bind:name="'items['+i+'][precio]'"
                                           x-model.number="it.precio"
                                           @input="recalc(i)"
                                           :disabled="locked" required>
                                </td>

                                {{-- Descuento --}}
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01"
                                           class="w-24 border rounded p-1 text-right text-sm"
                                           x-model.number="it.descuento"
                                           @input="recalc(i)" :disabled="locked">
                                    <input type="hidden" :name="'items['+i+'][descuento]'" x-model="it.descuento">
                                </td>

                                {{-- IVA --}}
                                <td class="p-2 text-right">
                                    <input type="number" min="0" step="0.01"
                                           class="w-20 border rounded p-1 text-right text-sm"
                                           x-model.number="it.iva_pct"
                                           @input="recalc(i)" :disabled="locked">
                                    <input type="hidden" :name="'items['+i+'][impuesto]'" x-model="it.impuesto">
                                </td>

                                {{-- Total --}}
                                <td class="p-2 text-right font-medium" x-text="fmt(it.total)"></td>

                                {{-- Eliminar --}}
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

    {{-- ====== ACCIONES (PDF / Enviar / Flujo) ====== --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center space-x-2">
            <x-wire-button href="{{ route('admin.sales-orders.edit',$order) }}" blue xs>Editar</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.pdf',$order) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.pdf.download',$order) }}" gray xs>Descargar PDF</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.send.form',$order) }}" violet xs>Enviar</x-wire-button>

            <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                Estatus: {{ $order->status_label }}
            </span>

            <div class="ml-auto flex items-center space-x-2">
                @if($order->status === 'BORRADOR')
                    <form action="{{ route('admin.sales-orders.approve',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" green xs>Aprobar</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.cancel',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>

                @elseif($order->status === 'APROBADO')
                    <form action="{{ route('admin.sales-orders.prepare',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" blue xs>Preparar</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.process',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" amber xs>Procesar</x-wire-button>
                    </form>

                @elseif($order->status === 'PREPARANDO')
                    <form action="{{ route('admin.sales-orders.process',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" amber xs>Procesar</x-wire-button>
                    </form>

                @elseif($order->status === 'PROCESADO')
                    <form action="{{ route('admin.sales-orders.en-ruta',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" violet xs>Enviar a ruta</x-wire-button>
                    </form>

                @elseif($order->status === 'EN_RUTA')
                    <form action="{{ route('admin.sales-orders.deliver',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" emerald xs>Entregar</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.not-delivered',$order) }}" method="POST" class="inline">@csrf
                        <x-wire-button type="submit" gray xs>No entregado</x-wire-button>
                    </form>
                    @if($order->payment_method === 'CONTRAENTREGA')
                        <form action="{{ route('admin.sales-orders.cobrar',$order) }}" method="POST" class="inline-flex items-center space-x-1">
                            @csrf
                            <input type="number" name="monto" min="0" step="0.01" placeholder="Monto" class="w-24 border rounded px-2 py-1 text-sm">
                            <x-wire-button type="submit" gray xs>Cobrar</x-wire-button>
                        </form>
                    @endif

                @elseif($order->status === 'NO_ENTREGADO')
                    <form action="{{ route('admin.sales-orders.en-ruta',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" violet xs>Reintentar ruta</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.cancel',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ====== LIQUIDACIÓN DEL CHOFER ====== --}}
        @if(
            in_array($order->status, ['ENTREGADO', 'NO_ENTREGADO']) &&
            ($order->driver_settlement_status ?? '') !== 'LIQUIDADO'
        )
        <div class="mt-4 border-t pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">Liquidar cobro del chofer</p>
            <form method="POST" action="{{ route('admin.sales-orders.liquidar', $order) }}">
                @csrf
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Monto entregado</label>
                        <input type="number" name="monto_entregado"
                               min="0" step="0.01"
                               value="{{ $order->total }}"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Forma de pago</label>
                        <select name="payment_type_id"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                            <option value="">-- seleccionar --</option>
                            @foreach(\App\Models\PaymentType::orderBy('descripcion')->get() as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->descripcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Caja del día (opcional)</label>
                        <select name="pos_register_id"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- sin caja --</option>
                            @foreach(\App\Models\CashRegister::where('estatus','ABIERTO')->latest()->get() as $cr)
                                <option value="{{ $cr->id }}">
                                    {{ $cr->warehouse->nombre ?? 'Sin almacén' }} — {{ $cr->fecha }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Referencia (opcional)</label>
                        <input type="text" name="referencia" maxlength="255"
                               placeholder="Folio, transferencia, etc."
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-700">
                        Confirmar liquidación
                    </button>
                </div>
            </form>
        </div>
        @endif

        {{-- Badge liquidado --}}
        @if(($order->driver_settlement_status ?? '') === 'LIQUIDADO')
        <div class="mt-4 border-t pt-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-emerald-100 text-emerald-700">
                Chofer liquidado — {{ optional($order->driver_settlement_at)->format('d/m/Y H:i') }}
            </span>
        </div>
        @endif

    </x-wire-card>

    {{-- ====== SCRIPT ALPINE ====== --}}
    <script>
    function soFormEdit(opts = {}){
        const seed          = @json($itemsSeed);
        const locked        = @json($isLocked);
        const CLIENT_DEFAULTS = {!! $JS_CLIENT_DEFAULTS !!};
        const CLIENTS_EDIT_BASE = {!! $JS_EDITBASE !!};
        const DEFAULT_CLIENT_ID = {!! $JS_SELCLIENT !!};

        return {
            items: (seed && seed.length)
                ? seed
                : [{id:null, product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}],
            locked,
            canEditQty:    !!opts.canEditQty,
            client_id:     DEFAULT_CLIENT_ID || '',
            payment_method: @json($paymentMethod),
            delivery_type:  @json($deliveryType),
            shipping_route_id: @json($selRoute),
            creditoDias:   0,
            creditoLimite: 0,
            subtotal:0, desc_total:0, tax_total:0, grand:0,

            init(){
                // Populate credit info from existing client without overwriting address fields
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
                    this.creditoDias      = 0;
                    this.creditoLimite    = 0;
                    this.shipping_route_id = '';
                    return;
                }
                // Apply route and credit
                if(d.shipping_route_id) this.shipping_route_id = d.shipping_route_id;
                this.creditoDias   = d.credito_dias   || 0;
                this.creditoLimite = d.credito_limite || 0;
                if(d.credito_dias > 0) this.payment_method = 'CREDITO';

                // Precargar dirección de entrega
                const set = (name, val) => {
                    const el = document.querySelector(`[name="${name}"]`);
                    if(el && val) el.value = val;
                };
                set('entrega_telefono', d.telefono         || '');
                set('entrega_calle',    d.entrega_calle    || '');
                set('entrega_numero',   d.entrega_numero   || '');
                set('entrega_colonia',  d.entrega_colonia  || '');
                set('entrega_ciudad',   d.entrega_ciudad   || '');
                set('entrega_estado',   d.entrega_estado   || '');
                set('entrega_cp',       d.entrega_cp       || '');
            },

            add(){
                if(this.locked) return;
                this.items.push({id:null, product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0});
            },
            remove(i){ if(this.locked) return; this.items.splice(i,1); this.sum(); },

            syncDescFromProduct(i){
                if(this.locked) return;
                const select = event.target;
                const opt    = select.options[select.selectedIndex];
                if(!this.items[i].descripcion) this.items[i].descripcion = opt?.dataset?.nombre || '';
                if(this.items[i].precio === 0)  this.items[i].precio = parseFloat(opt?.dataset?.precio || '0') || 0;
                this.recalc(i);
            },

            recalc(i){
                const it   = this.items[i];
                const line = (+it.cantidad||0) * (+it.precio||0);
                const disc = +it.descuento||0;
                const base = Math.max(line - disc, 0);
                const tax  = ((+it.iva_pct||0) * 0.01) * base;
                it.impuesto = tax;
                it.total    = base + tax;
                this.sum();
            },

            sum(){
                let s=0,d=0,t=0,g=0;
                this.items.forEach(it=>{
                    const line = (+it.cantidad||0)*(+it.precio||0);
                    const disc = +it.descuento||0;
                    const base = Math.max(line-disc,0);
                    const tax  = ((+it.iva_pct||0)*0.01)*base;
                    const tot  = base+tax;
                    s+=line; d+=disc; t+=tax; g+=tot;
                    it.impuesto=tax; it.total=tot;
                });
                this.subtotal=s; this.desc_total=d; this.tax_total=t; this.grand=g;
            },

            fmt(n){ return Number(n||0).toFixed(2); }
        }
    }
    </script>
</x-admin-layout>