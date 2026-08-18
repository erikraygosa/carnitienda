<x-admin-layout
    title="Editar pedido"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Pedidos','url'=>route('admin.sales-orders.index')],
        ['name'=>'Editar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales-orders.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        @if($order->status !== 'CANCELADO' && !($order->status === 'ENTREGADO' && !($puedeEditarCerrados ?? false)))
            <button form="so-edit-form" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                {{ $order->status === 'BORRADOR' ? 'Actualizar' : 'Guardar cambios' }}
            </button>
        @endif
    </x-slot>

    @php
        $puedeEditarCerrados = $puedeEditarCerrados ?? false;
        // Quien tiene permiso de Gestión de notas puede corregir un pedido
        // ENTREGADO (error después del hecho) — CANCELADO se queda bloqueado
        // siempre, es un caso distinto.
        $isLocked     = $order->status === 'CANCELADO' || ($order->status === 'ENTREGADO' && !$puedeEditarCerrados);
        $canEditQty   = !$isLocked;
        $canEditItems = $order->status === 'BORRADOR' || ($puedeEditarCerrados && $order->status === 'ENTREGADO');
        $editandoCerrado = $puedeEditarCerrados && $order->status === 'ENTREGADO';

        $selClient    = (string) old('client_id',         $order->client_id);
        $selWarehouse = (string) old('warehouse_id',       $order->warehouse_id);
        $selRoute     = (string) old('shipping_route_id',  $order->shipping_route_id);
        $selDriver    = (string) old('driver_id',          $order->driver_id);

        $valueFecha    = old('fecha',           optional($order->fecha)->format('Y-m-d\TH:i'));
        $valueProg     = old('programado_para', optional($order->programado_para)->toDateString());
        $valueMoneda   = old('moneda',          $order->moneda);
        $deliveryType  = old('delivery_type',   $order->delivery_type);
        $paymentMethod = old('payment_method',  $order->payment_method);

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

        $itemsSeed = $order->items->map(fn($i) => [
            'id'              => $i->id,
            'product_id'      => $i->product_id,
            '_productoNombre' => $i->product?->nombre ?? $i->descripcion ?? '',
            'descripcion'     => $i->descripcion,
            'cantidad'        => (float)$i->cantidad,
            'num_cajas'       => $i->num_cajas,
            'precio'          => (float)$i->precio,
            'descuento'       => (float)$i->descuento,
            'iva_pct'         => 0,
            'impuesto'        => (float)$i->impuesto,
            'total'           => (float)$i->total,
            // Ya se surtió con producto real en el Panel de Surtido — no se
            // puede volver a tocar desde aquí.
            'ya_surtido'      => in_array($i->id, $itemsSurtidosIds ?? []),
            // Comentario capturado al surtir esta línea (panel de Salida de producto).
            'nota_surtido'    => $notasSurtidoPorItem[$i->id] ?? null,
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

        $JS_CLIENT_DEFAULTS = json_encode($clientDefaults, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_SELCLIENT       = json_encode($selClient,      JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $JS_ITEMS           = json_encode($itemsSeed,      JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        $entregaNombre  = old('entrega_nombre',   $order->entrega_nombre   ?? '');
        $entregaTel     = old('entrega_telefono', $order->entrega_telefono ?? '');
        $entregaCalle   = old('entrega_calle',    $order->entrega_calle    ?? '');
        $entregaNumero  = old('entrega_numero',   $order->entrega_numero   ?? '');
        $entregaColonia = old('entrega_colonia',  $order->entrega_colonia  ?? '');
        $entregaCiudad  = old('entrega_ciudad',   $order->entrega_ciudad   ?? '');
        $entregaEstado  = old('entrega_estado',   $order->entrega_estado   ?? '');
        $entregaCp      = old('entrega_cp',       $order->entrega_cp       ?? '');
    @endphp

    @if($editandoCerrado)
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        ⚠️ Este pedido ya está <strong>ENTREGADO</strong> — lo estás editando con permiso de Gestión de notas.
        Si cambias cantidades, productos o los quitas, el sistema ajusta automáticamente el stock y (si es a
        crédito) el saldo de CxC del cliente al guardar. Queda registrado en Auditoría.
    </div>
    @endif

    {{-- ====== FORMULARIO ====== --}}
    <x-wire-card>
        <form id="so-edit-form" method="POST"
              action="{{ route('admin.sales-orders.update',$order) }}"
              class="space-y-6">
            @csrf @method('PUT')
            <input type="hidden" name="then_approve" id="then_approve" value="0">
            @if(request('origen') === 'gestion-notas')
                <input type="hidden" name="origen" value="gestion-notas">
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p class="font-medium">No se guardó el pedido:</p>
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ====== ENCABEZADO ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" id="client_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">-- seleccionar --</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $selClient===(string)$c->id ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @if($isLocked)
                        <input type="hidden" name="client_id" value="{{ $selClient }}">
                    @endif
                    <p id="credito-info" class="mt-1 text-xs text-gray-500 hidden"></p>
                </div>

                {{-- Almacén --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    <select name="warehouse_id"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
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
                    <select id="price_list_sel"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            onchange="SOE.onPriceListChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="client" {{ !$order->price_list_id ? 'selected' : '' }}>Personalizada del cliente</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}"
                                {{ old('price_list_id', $order->price_list_id) == $pl->id ? 'selected' : '' }}>
                                {{ $pl->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="price_list_id" id="price_list_id"
                           value="{{ old('price_list_id', $order->price_list_id) }}">
                </div>

                {{-- Fecha --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="fecha" value="{{ $valueFecha }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

                {{-- Programado para --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Programado para</label>
                    <input type="date" name="programado_para" value="{{ $valueProg }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

                {{-- Moneda --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                    <input type="text" name="moneda" value="{{ $valueMoneda }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                </div>

                {{-- Tipo de entrega --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrega</label>
                    <select name="delivery_type" id="delivery_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="SOE.onDeliveryChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="ENVIO"   {{ $deliveryType==='ENVIO'   ? 'selected' : '' }}>Envío a domicilio</option>
                        <option value="RECOGER" {{ $deliveryType==='RECOGER' ? 'selected' : '' }}>Recoger en almacén</option>
                    </select>
                </div>

                {{-- Ruta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id" id="shipping_route_id"
                            class="w-full rounded-md border-gray-300 shadow-sm"
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
                    <select name="driver_id"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            {{ $isLocked ? 'disabled' : '' }}>
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
                    <select name="payment_method" id="payment_method"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            onchange="SOE.onPaymentChange(this.value)"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <option value="EFECTIVO"      {{ $paymentMethod==='EFECTIVO'      ? 'selected' : '' }}>Efectivo</option>
                        <option value="TRANSFERENCIA" {{ $paymentMethod==='TRANSFERENCIA' ? 'selected' : '' }}>Transferencia</option>
                        <option value="CONTRAENTREGA" {{ $paymentMethod==='CONTRAENTREGA' ? 'selected' : '' }}>Contraentrega</option>
                        <option value="CREDITO"       {{ $paymentMethod==='CREDITO'       ? 'selected' : '' }}>Crédito</option>
                    </select>
                </div>

                {{-- Días de crédito --}}
                <div id="credito-wrap" style="{{ $paymentMethod==='CREDITO' ? '' : 'display:none' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Días de crédito <span class="text-gray-400 font-normal text-xs">(del cliente)</span>
                    </label>
                    <input type="number" name="credit_days" id="credit_days"
                           value="{{ $order->credit_days ?? 0 }}"
                           class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm text-sm"
                           {{ $isLocked ? 'readonly' : '' }}>
                </div>

            </div>

            {{-- ====== DIRECCIÓN DE ENTREGA ====== --}}
            <div id="entrega-section" class="border-t pt-4" style="{{ $deliveryType==='ENVIO' ? '' : 'display:none' }}">
                <button type="button" id="entrega-toggle"
                        onclick="toggleEntrega()"
                        class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-indigo-600 w-full text-left mb-2">
                    <svg id="entrega-chevron" class="w-4 h-4 transition-transform duration-200"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    Datos de entrega
                </button>
                <div id="entrega-fields" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach([
                        ['entrega_nombre',   'Nombre quien recibe', $entregaNombre],
                        ['entrega_telefono', 'Teléfono',            $entregaTel],
                        ['entrega_calle',    'Calle',               $entregaCalle],
                        ['entrega_numero',   'Número',              $entregaNumero],
                        ['entrega_colonia',  'Colonia',             $entregaColonia],
                        ['entrega_ciudad',   'Ciudad',              $entregaCiudad],
                        ['entrega_estado',   'Estado',              $entregaEstado],
                        ['entrega_cp',       'CP',                  $entregaCp],
                    ] as [$fname, $flabel, $fval])
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $flabel }}</label>
                        <input type="text" name="{{ $fname }}" id="{{ $fname }}" value="{{ $fval }}"
                               autocomplete="off"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                               {{ $isLocked ? 'readonly' : '' }}>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- ====== COMENTARIOS ====== --}}
            <div class="border-t pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Comentarios</label>
                <textarea name="comentarios" rows="2" autocomplete="off"
                          placeholder="Notas u observaciones del pedido..."
                          class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                          {{ $isLocked ? 'readonly' : '' }}>{{ old('comentarios', $order->comentarios) }}</textarea>
            </div>

            {{-- ====== ALERTA precio cero ====== --}}
            @if($canEditItems)
            <div id="zero-price-alert" class="hidden rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800 flex items-center justify-between">
                <span>Algunos productos no tienen precio para este cliente. Se estableció $0.00.</span>
                <a id="zero-price-link" href="#" target="_blank"
                   class="ml-3 inline-flex px-3 py-1.5 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">
                    Editar precios
                </a>
            </div>
            @endif

            {{-- ====== PARTIDAS ====== --}}
            <div class="overflow-auto border-t pt-4">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50">
                        <tr>
                            <th class="p-2 text-left">Producto</th>
                            <th class="p-2 text-left">Descripción</th>
                            <th class="p-2 text-right">Cantidad</th>
                            <th class="p-2 text-center" title="Número aproximado de cajas">Cajas</th>
                            <th class="p-2 text-right">Precio</th>
                            <th class="p-2 text-right">Desc.</th>
                            @if($mostrarIva)<th class="p-2 text-right">% IVA</th>@endif
                            <th class="p-2 text-right">Total</th>
                            @if($canEditItems)<th class="p-2 w-8"></th>@endif
                        </tr>
                    </thead>
                    <tbody id="items-body"></tbody>
                </table>
                @if($canEditItems)
                <div class="mt-3">
                    <button type="button" onclick="SOE.addRow()"
                            class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                        + Agregar partida
                    </button>
                </div>
                @endif
            </div>

            {{-- ====== TOTALES ====== --}}
            <div class="text-right space-y-1 border-t pt-3">
                <div class="text-sm text-gray-600">Subtotal: <span id="tot-subtotal" class="font-medium">0.00</span></div>
                <div class="text-sm text-gray-600">Descuento: <span id="tot-desc" class="font-medium">0.00</span></div>
                @if($mostrarIva)<div class="text-sm text-gray-600">Impuestos: <span id="tot-tax" class="font-medium">0.00</span></div>@endif
                <div class="text-lg font-bold text-gray-800">Total: $<span id="tot-grand">0.00</span></div>
            </div>

            <input type="hidden" name="subtotal"  id="h-subtotal">
            <input type="hidden" name="descuento" id="h-descuento">
            <input type="hidden" name="impuestos" id="h-impuestos">
            <input type="hidden" name="total"     id="h-total">

        </form>
    </x-wire-card>

    {{-- ====== ACCIONES ====== --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center space-x-2">
            @if(in_array($order->status, ['APROBADO','PROCESADO']))
                <form id="reopen-form" action="{{ route('admin.sales-orders.reopen',$order) }}" method="POST">
                    @csrf
                    <x-wire-button type="button" onclick="confirmReopen()" blue xs>Editar</x-wire-button>
                </form>
                <script>
                    function confirmReopen() {
                        Swal.fire({
                            icon: 'warning',
                            title: '¿Deseas editar el pedido?',
                            text: 'Está en estatus {{ $order->status_label }}. Esto regresará el pedido a Borrador para poder agregar/quitar productos — las partidas ya surtidas quedan protegidas, no se tocan.',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, editar',
                            cancelButtonText: 'Cancelar',
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                document.getElementById('reopen-form').submit();
                            }
                        });
                    }
                </script>
            @else
                <x-wire-button href="{{ route('admin.sales-orders.edit',$order) }}" blue xs>Editar</x-wire-button>
            @endif
            <x-wire-button href="{{ route('admin.sales-orders.pdf',$order) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.pdf.download',$order) }}" gray xs>Descargar PDF</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.ticket',$order) }}" gray outline xs target="_blank">Imprimir</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.send.form',$order) }}" violet xs>Enviar</x-wire-button>

            <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                Estatus: {{ $order->status_label }}
            </span>

            <div class="ml-auto flex items-center space-x-2">
                @if($order->status === 'BORRADOR')
                    {{-- Envía el form principal (guarda cualquier cambio pendiente) y de una
                         vez marca then_approve=1 para que el servidor apruebe justo después
                         de guardar — antes este botón era un form aparte que solo aprobaba,
                         ignorando cualquier edición que no se hubiera guardado con "Actualizar". --}}
                    <x-wire-button type="submit" form="so-edit-form" green xs
                        onclick="document.getElementById('then_approve').value='1'">
                        Aprobar y procesar
                    </x-wire-button>
                    <form action="{{ route('admin.sales-orders.cancel',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @elseif(in_array($order->status, ['APROBADO', 'PREPARANDO']))
                    <form action="{{ route('admin.sales-orders.process',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" amber xs>Procesar</x-wire-button>
                    </form>
                @elseif($order->status === 'EN_RUTA')
                    <form action="{{ route('admin.sales-orders.deliver',$order) }}" method="POST">@csrf
                        <x-wire-button type="submit" emerald xs>Entregar</x-wire-button>
                    </form>
                    <form action="{{ route('admin.sales-orders.not-delivered',$order) }}" method="POST" class="inline-flex items-center gap-1">
                        @csrf
                        <input type="text" name="nota" placeholder="Motivo (opcional)"
                               class="w-36 border rounded px-2 py-1 text-xs text-gray-700">
                        <x-wire-button type="submit" gray xs>No entregado</x-wire-button>
                    </form>
                    @if($order->payment_method === 'CONTRAENTREGA')
                    <form action="{{ route('admin.sales-orders.cobrar',$order) }}" method="POST" class="inline-flex items-center space-x-1">
                        @csrf
                        <input type="number" name="monto" min="0" step="0.01" placeholder="Monto"
                               class="w-24 border rounded px-2 py-1 text-sm">
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

        {{-- Liquidación chofer --}}
        @if(in_array($order->status, ['ENTREGADO','NO_ENTREGADO']) && $order->payment_method !== 'CREDITO' && ($order->driver_settlement_status ?? '') !== 'LIQUIDADO')
        <div class="mt-4 border-t pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">Liquidar cobro del chofer</p>
            <form method="POST" action="{{ route('admin.sales-orders.liquidar',$order) }}">
                @csrf
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Monto entregado</label>
                        <input type="number" name="monto_entregado" min="0" step="0.01"
                               value="{{ $order->total }}"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Forma de pago</label>
                        <select name="payment_type_id"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                            <option value="">-- seleccionar --</option>
                            @foreach(\App\Models\PaymentType::orderBy('descripcion')->get() as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->descripcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Caja del día (opcional)</label>
                        <select name="pos_register_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
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
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit"
                            class="inline-flex px-4 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-700">
                        Confirmar liquidación
                    </button>
                </div>
            </form>
        </div>
        @endif

        @if(($order->driver_settlement_status ?? '') === 'LIQUIDADO')
        <div class="mt-4 border-t pt-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-emerald-100 text-emerald-700">
                Chofer liquidado — {{ optional($order->driver_settlement_at)->format('d/m/Y H:i') }}
            </span>
        </div>
        @endif
    </x-wire-card>

    <script>
    (function(){
        const LOCKED          = {{ $isLocked ? 'true' : 'false' }};
        const CAN_EDIT_QTY    = {{ $canEditQty ? 'true' : 'false' }};
        const CAN_EDIT_ITEMS  = {{ $canEditItems ? 'true' : 'false' }};
        const CLIENT_DEFAULTS = {!! $JS_CLIENT_DEFAULTS !!};
        const DEFAULT_CLIENT  = {!! $JS_SELCLIENT !!};
        const INITIAL_ITEMS   = {!! $JS_ITEMS !!};
        const CLIENTS_EDIT_BASE    = '{{ url('admin/clients') }}';
        const CLIENT_PRICES_BASE   = '{{ url('admin/sales-orders/client-prices') }}';

        const CLIENTS_OVERRIDES = @json($overrides ?? []);
        const LISTS_PRICES      = @json($listItems ?? []);
        const INITIAL_PRICE_LIST = @json((string) ($order->price_list_id ?: 'client'));

        const PRODUCTS = @json($productsJson);
        const MOSTRAR_IVA = @json($mostrarIva);

        let state = {
            items: [],
            clientId: DEFAULT_CLIENT || '',
            priceList: INITIAL_PRICE_LIST,
        };

        function getPrice(productId) {
            if (!productId) return 0;
            const pid = String(productId);
            if (state.priceList === 'client') {
                return parseFloat((CLIENTS_OVERRIDES[state.clientId]||{})[pid] ?? 0) || 0;
            }
            return parseFloat((LISTS_PRICES[state.priceList]||{})[pid] ?? 0) || 0;
        }

        const fmt = n => Number(n||0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const $   = id => document.getElementById(id);
        const set = (id, val) => { const el = $(id); if(el) el.value = val; };

        function recalcRow(i) {
            const it   = state.items[i];
            const line = (+it.cantidad||0) * (+it.precio||0);
            const disc = +it.descuento||0;
            const base = Math.max(line - disc, 0);
            const tax  = ((+it.iva_pct||0) / 100) * base;
            it.impuesto = tax;
            it.total    = base + tax;
            const row = document.querySelector(`#items-body tr[data-idx="${i}"]`);
            if (row) {
                row.querySelector('.td-total').textContent  = fmt(it.total);
                row.querySelector('.hid-impuesto').value    = it.impuesto;
            }
            updateTotals();
        }

        function updateTotals() {
            let s=0, d=0, t=0, g=0, hasZero=false;
            state.items.forEach(it => {
                const line = (+it.cantidad||0)*(+it.precio||0);
                const disc = +it.descuento||0;
                const base = Math.max(line-disc, 0);
                const tax  = ((+it.iva_pct||0)/100)*base;
                s += line; d += disc; t += tax; g += base+tax;
                if ((+it.precio||0) === 0 && it.product_id) hasZero = true;
            });
            $('tot-subtotal').textContent = fmt(s);
            $('tot-desc').textContent     = fmt(d);
            if ($('tot-tax')) $('tot-tax').textContent = fmt(t);
            $('tot-grand').textContent    = fmt(g);
            set('h-subtotal',  fmt(s));
            set('h-descuento', fmt(d));
            set('h-impuestos', fmt(t));
            set('h-total',     fmt(g));
            const alertEl = $('zero-price-alert');
            if (alertEl) alertEl.classList.toggle('hidden', !hasZero || !state.clientId);
            const link = $('zero-price-link');
            if (link && state.clientId) link.href = `${CLIENTS_EDIT_BASE}/${state.clientId}/edit`;
        }

        function escHtml(str) {
            return String(str||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        // ── Portal global para autocomplete de producto ─────────────────
        const PROD_PORTAL = document.createElement('ul');
        PROD_PORTAL.id = 'product-dropdown-portal';
        PROD_PORTAL.className = 'fixed z-[9999] bg-white border border-gray-200 rounded shadow-md hidden max-h-48 overflow-y-auto text-sm list-none py-1';
        document.body.appendChild(PROD_PORTAL);

        let portalSelectFn = null;
        function hidePortal() { PROD_PORTAL.classList.add('hidden'); portalSelectFn = null; }
        function positionPortal(input) {
            const r = input.getBoundingClientRect();
            PROD_PORTAL.style.left  = r.left + 'px';
            PROD_PORTAL.style.top   = (r.bottom + 2) + 'px';
            PROD_PORTAL.style.width = Math.max(r.width, 240) + 'px';
        }
        document.addEventListener('scroll', hidePortal, true);
        document.addEventListener('click', function(e) {
            if (!PROD_PORTAL.contains(e.target)) hidePortal();
        });

        function attachProductSearch(tr, i) {
            const input  = tr.querySelector('.inp-product-search');
            const hidden = tr.querySelector('.hid-product-id');

            function selectProduct(p) {
                hidden.value = p.id;
                input.value  = p.nombre;
                state.items[i].product_id      = p.id;
                state.items[i]._productoNombre = p.nombre;
                state.items[i].descripcion     = p.nombre;
                tr.querySelector('.inp-desc').value   = p.nombre;
                state.items[i].precio = getPrice(p.id);
                tr.querySelector('.inp-precio').value = state.items[i].precio;
                recalcRow(i);
                hidePortal();
                input.focus();
            }

            function clearProduct() {
                hidden.value = '';
                input.value  = '';
                state.items[i].product_id      = '';
                state.items[i]._productoNombre = '';
                state.items[i].descripcion     = '';
                state.items[i].precio          = 0;
                tr.querySelector('.inp-desc').value   = '';
                tr.querySelector('.inp-precio').value = 0;
                recalcRow(i);
                input.focus();
            }

            function showDropdown(term) {
                const t = term.toLowerCase().trim();
                const matches = t.length === 0 ? [] : PRODUCTS.filter(p =>
                    p.nombre.toLowerCase().includes(t) ||
                    (p.sku && p.sku.toLowerCase().includes(t))
                ).slice(0, 12);
                PROD_PORTAL.innerHTML = '';
                if (matches.length === 0) { hidePortal(); return; }
                portalSelectFn = selectProduct;
                matches.forEach(p => {
                    const li = document.createElement('li');
                    li.className = 'px-3 py-1.5 hover:bg-indigo-50 cursor-pointer flex justify-between items-center';
                    li.innerHTML = `<span class="truncate">${escHtml(p.nombre)}</span>`
                                 + (p.sku ? `<span class="text-xs text-gray-400 ml-2 shrink-0">${escHtml(p.sku)}</span>` : '');
                    li.addEventListener('mousedown', function(e) { e.preventDefault(); selectProduct(p); });
                    PROD_PORTAL.appendChild(li);
                });
                positionPortal(input);
                PROD_PORTAL.classList.remove('hidden');
            }

            input.addEventListener('input', function() {
                if (!this.value.trim()) {
                    hidden.value = '';
                    state.items[i].product_id      = '';
                    state.items[i]._productoNombre = '';
                }
                showDropdown(this.value);
            });
            input.addEventListener('focus', function() {
                if (this.value.trim()) showDropdown(this.value);
            });
            input.addEventListener('blur', function() {
                setTimeout(hidePortal, 150);
            });
            tr.querySelector('.btn-clear-product').addEventListener('click', clearProduct);
        }

        function renderRow(i) {
            const it        = state.items[i];
            const surtido   = !!it.ya_surtido;
            const canItems  = CAN_EDIT_ITEMS && !surtido;
            // 'disabled' NO se envía al guardar el formulario — una partida ya
            // surtida debe seguir mandando su descripción/cantidad/precio tal
            // cual (si no, el servidor la rechaza como "campo requerido"), solo
            // que de solo lectura ('readonly' sí se envía). 'disabled' de
            // verdad solo aplica cuando el PEDIDO completo está bloqueado
            // (ENTREGADO/CANCELADO), donde ni se muestra el botón de guardar.
            const dis       = LOCKED ? 'disabled' : '';
            const roSurtido = surtido ? 'readonly' : '';
            const disQty    = LOCKED ? 'disabled' : (surtido ? 'readonly' : (CAN_EDIT_QTY ? '' : 'disabled'));
            const roItems   = (!LOCKED && !canItems) ? 'readonly' : '';
            const tr        = document.createElement('tr');
            tr.className    = 'border-b' + (surtido ? ' bg-emerald-50/40' : '');
            tr.dataset.idx  = i;

            // En BORRADOR: autocomplete editable. En PREPARANDO/locked/ya surtido: texto de solo lectura.
            const productCell = canItems
                ? `<input type="hidden" class="hid-product-id" name="items[${i}][product_id]" value="${escHtml(String(it.product_id||''))}">
                   <div class="flex items-center gap-1">
                       <input type="text" class="w-52 border rounded p-1 text-sm inp-product-search"
                              placeholder="Buscar por nombre o SKU..." autocomplete="off"
                              value="${escHtml(it._productoNombre||'')}">
                       <button type="button" class="btn-clear-product text-gray-400 hover:text-red-500 text-base leading-none px-1" title="Quitar producto">✕</button>
                   </div>
                   ${it.nota_surtido ? `<div class="mt-0.5 text-[11px] text-amber-700">📝 ${escHtml(it.nota_surtido)}</div>` : ''}`
                : `<input type="hidden" name="items[${i}][product_id]" value="${escHtml(String(it.product_id||''))}">
                   <span class="text-sm text-gray-700">${escHtml(it._productoNombre||'—')}</span>
                   ${surtido ? '<span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-medium align-middle">✓ Surtido</span>' : ''}
                   ${it.nota_surtido ? `<div class="mt-0.5 text-[11px] text-amber-700">📝 ${escHtml(it.nota_surtido)}</div>` : ''}`;

            tr.innerHTML = `
                <input type="hidden" name="items[${i}][id]" value="${it.id || ''}">
                <td class="p-2">${productCell}</td>
                <td class="p-2">
                    <input type="text" class="w-64 border rounded p-1 text-sm inp-desc"
                           name="items[${i}][descripcion]" value="${escHtml(it.descripcion)}"
                           ${dis} ${roItems} ${roSurtido} required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0.001" step="0.001"
                           class="w-24 border rounded p-1 text-right text-sm inp-cantidad"
                           name="items[${i}][cantidad]" value="${it.cantidad}"
                           ${disQty} required>
                </td>
                <td class="p-2 text-center">
                    <input type="number" min="1" step="1"
                           class="w-16 border rounded p-1 text-center text-sm inp-cajas"
                           name="items[${i}][num_cajas]" value="${it.num_cajas || ''}"
                           placeholder="—" title="Cajas aprox." ${surtido ? 'readonly' : dis}>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.0001"
                           class="w-28 border rounded p-1 text-right text-sm bg-gray-50 inp-precio"
                           name="items[${i}][precio]" value="${it.precio}"
                           ${dis} readonly required>
                </td>
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01"
                           class="w-24 border rounded p-1 text-right text-sm inp-descuento"
                           name="items[${i}][descuento]" value="${it.descuento}" ${dis} ${roItems} ${roSurtido}>
                </td>
                ${MOSTRAR_IVA ? `
                <td class="p-2 text-right">
                    <input type="number" min="0" step="0.01"
                           class="w-20 border rounded p-1 text-right text-sm inp-iva"
                           value="${it.iva_pct}" ${dis} ${roItems}>
                </td>` : ''}
                <td class="p-2 text-right font-medium">
                    <span class="td-total">${fmt(it.total)}</span>
                    <input type="hidden" class="hid-impuesto"
                           name="items[${i}][impuesto]" value="${it.impuesto}">
                </td>
                ${CAN_EDIT_ITEMS ? `<td class="p-2 text-center">${surtido ? '' : '<button type="button" class="text-red-500 hover:text-red-700 text-xs btn-remove">✕</button>'}</td>` : ''}
            `;

            if (canItems) {
                attachProductSearch(tr, i);
                tr.querySelector('.inp-descuento').addEventListener('input', function() {
                    state.items[i].descuento = parseFloat(this.value)||0; recalcRow(i);
                });
                if (MOSTRAR_IVA) {
                    tr.querySelector('.inp-iva').addEventListener('input', function() {
                        state.items[i].iva_pct = parseFloat(this.value)||0; recalcRow(i);
                    });
                }
                tr.querySelector('.inp-desc').addEventListener('input', function() {
                    state.items[i].descripcion = this.value;
                });
                tr.querySelector('.btn-remove')?.addEventListener('click', function() {
                    state.items.splice(i, 1); renderAll();
                });
            }

            if (CAN_EDIT_QTY && !surtido) {
                tr.querySelector('.inp-cantidad').addEventListener('input', function() {
                    state.items[i].cantidad = parseFloat(this.value)||0; recalcRow(i);
                });
            }

            return tr;
        }

        function renderAll() {
            const tbody = $('items-body');
            tbody.innerHTML = '';
            state.items.forEach((_, i) => tbody.appendChild(renderRow(i)));
            updateTotals();
        }

        window.SOE = {
            addRow() {
                if (!CAN_EDIT_ITEMS) return;
                state.items.push({id:null,product_id:'',_productoNombre:'',descripcion:'',cantidad:1,num_cajas:null,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0});
                renderAll();
            },
            onClientChange(clientId) {
                if (LOCKED) return;
                state.clientId = clientId;
                const d = CLIENT_DEFAULTS[clientId];
                if (d) {
                    if (d.shipping_route_id) set('shipping_route_id', d.shipping_route_id);
                    set('credit_days', d.credito_dias || 0);
                    if (d.credito_dias > 0) { set('payment_method', 'CREDITO'); SOE.onPaymentChange('CREDITO'); }
                    if (d.credito_limite > 0) {
                        const info = $('credito-info');
                        if(info) { info.textContent = `Límite: $${fmt(d.credito_limite)} · Días: ${d.credito_dias}d`; info.classList.remove('hidden'); }
                    }
                    const fields = {
                        entrega_telefono: d.telefono,
                        entrega_calle:    d.entrega_calle,
                        entrega_numero:   d.entrega_numero,
                        entrega_colonia:  d.entrega_colonia,
                        entrega_ciudad:   d.entrega_ciudad,
                        entrega_estado:   d.entrega_estado,
                        entrega_cp:       d.entrega_cp,
                    };
                    Object.entries(fields).forEach(([id, val]) => { if(val) set(id, val); });
                }
                SOE.repriceAll();
            },
            onPriceListChange(val) {
                state.priceList = val;
                set('price_list_id', val === 'client' ? '' : val);
                SOE.repriceAll();
            },
            onDeliveryChange(val) {
                $('entrega-section').style.display = val === 'ENVIO' ? '' : 'none';
            },
            onPaymentChange(val) {
                $('credito-wrap').style.display = val === 'CREDITO' ? '' : 'none';
            },
            repriceAll() {
                if (!CAN_EDIT_ITEMS) return; // en PREPARANDO no se toca el precio ya facturable
                state.items.forEach((it, i) => {
                    if (!it.product_id) return;
                    it.precio = getPrice(it.product_id);
                    const row = document.querySelector(`#items-body tr[data-idx="${i}"]`);
                    if (row) {
                        const inp = row.querySelector('.inp-precio');
                        if (inp) inp.value = it.precio;
                    }
                    recalcRow(i);
                });
            },
        };

        // Init
        state.items = JSON.parse(JSON.stringify(INITIAL_ITEMS));
        if (!state.items.length) {
            state.items = [{id:null,product_id:'',descripcion:'',cantidad:1,num_cajas:null,precio:0,descuento:0,iva_pct:0,impuesto:0,total:0}];
        }
        // Init crédito
        if (DEFAULT_CLIENT) {
            const d = CLIENT_DEFAULTS[DEFAULT_CLIENT];
            if (d && d.credito_limite > 0) {
                const info = $('credito-info');
                if(info) { info.textContent = `Límite: $${fmt(d.credito_limite)} · Días: ${d.credito_dias}d`; info.classList.remove('hidden'); }
            }
        }
        SOE.onDeliveryChange('{{ $deliveryType }}');
        SOE.onPaymentChange('{{ $paymentMethod }}');
        renderAll();

        // Refrescar precios si el usuario editó precios del cliente en otra pestaña y regresó
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState !== 'visible' || !state.clientId) return;
            fetch(`${CLIENT_PRICES_BASE}/${state.clientId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    const cid = String(state.clientId);
                    CLIENTS_OVERRIDES[cid] = data;
                    if (CAN_EDIT_ITEMS) SOE.repriceAll();
                })
                .catch(() => {});
        });
    })();
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
<script>
$(function () {
    @if(!$isLocked)
    $('#client_id').select2({
        placeholder: '-- seleccionar cliente --',
        allowClear: true,
        width: '100%',
        language: { searching: function() { return 'Buscando...'; }, noResults: function() { return 'Sin resultados'; } },
    }).on('change', function () {
        SOE.onClientChange(this.value);
    });
    @endif

    // Bug conocido de select2: al dar clic en la "x" de limpiar, el mismo clic
    // se propaga y vuelve a abrir el dropdown (se ve como "abre y cierra sin borrar").
    $(document).on('select2:unselecting', function(e) {
        $(e.target).data('unselecting', true);
    }).on('select2:opening', function(e) {
        if ($(e.target).data('unselecting')) {
            $(e.target).removeData('unselecting');
            e.preventDefault();
        }
    });
});

function toggleEntrega() {
    const fields  = document.getElementById('entrega-fields');
    const chevron = document.getElementById('entrega-chevron');
    const open    = !fields.classList.contains('hidden');
    fields.classList.toggle('hidden', open);
    chevron.style.transform = open ? '' : 'rotate(90deg)';
}

// Abrir automáticamente si hay datos de entrega
(function(){
    const vals = ['entrega_nombre','entrega_telefono','entrega_calle','entrega_numero','entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp'];
    const hasData = vals.some(id => { const el = document.getElementById(id); return el && el.value.trim(); });
    if (hasData) toggleEntrega();
})();
</script>
@endpush

</x-admin-layout>