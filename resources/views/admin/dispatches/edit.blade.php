<x-admin-layout
    title="Despacho #{{ $dispatch->id }}"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Despachos','url'=>route('admin.dispatches.index')],
        ['name'=>'Despacho #'.$dispatch->id],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.dispatches.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        @if(!in_array($dispatch->status, ['CERRADO','CANCELADO']))
            <button form="dispatch-edit" type="submit"
                    class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                Guardar datos
            </button>
        @endif
    </x-slot>

    @php
        $fechaVal    = old('fecha', optional($dispatch->fecha)->format('Y-m-d\TH:i'));
        $statusClass = $statusClasses[$dispatch->status] ?? 'bg-slate-100 text-slate-700';
        $locked      = in_array($dispatch->status, ['CERRADO','CANCELADO']);
        $enRuta      = $dispatch->status === 'EN_RUTA';

        $pedidosEfectivo = $dispatch->items
            ->filter(fn($i) => $i->salesOrder?->status === 'ENTREGADO'
                && in_array($i->salesOrder?->payment_method, ['EFECTIVO','CONTRAENTREGA']))
            ->sum(fn($i) => $i->salesOrder?->total ?? 0);

        // Incluye abonos PARCIALes, no solo asignaciones ya COBRADO por completo —
        // monto_cobrado acumula lo realmente recibido sin importar el estatus.
        $cxcCobradas  = $dispatch->arAssignments->sum('monto_cobrado');
        $totalACobrar = $pedidosEfectivo + $cxcCobradas;

        $traspasosTotal     = $dispatch->transferAssignments->count();
        $traspasosCompletos = $dispatch->transferAssignments->where('status','COMPLETADO')->count();

        $traspasosPendientesIds = $dispatch->transferAssignments->where('status','PENDIENTE')->pluck('id');
        $pedidosPendientesItems = $dispatch->items->filter(fn($i) => $i->salesOrder?->status === 'EN_RUTA');
        // PENDIENTE+PARCIAL: lo que todavía puede recibir un abono (para el
        // dropdown de "cobrar todas" y el botón por fila) — un PARCIAL ya
        // tiene dinero registrado, pero se le puede seguir abonando.
        $cxcPendientes          = $dispatch->arAssignments->whereIn('status',['PENDIENTE','PARCIAL']);
        // Solo PENDIENTE bloquea el cierre — PARCIAL ya se resolvió (se
        // registró lo que se cobró); lo que falte del saldo sigue como CxC
        // normal, no detiene la liquidación del chofer de hoy.
        $cxcSinResolver         = $dispatch->arAssignments->where('status','PENDIENTE');

        // ── Cierres parciales: traspasos+crédito por un lado, efectivo+CxC por otro ──
        $pedidosCreditoPendientes  = $pedidosPendientesItems->filter(
            fn($i) => !in_array($i->salesOrder?->payment_method, ['EFECTIVO','CONTRAENTREGA'])
        );
        $pedidosEfectivoPendientesItems = $pedidosPendientesItems->filter(
            fn($i) => in_array($i->salesOrder?->payment_method, ['EFECTIVO','CONTRAENTREGA'])
        );
        $traspasosResueltos = $traspasosTotal === 0 || $dispatch->transferAssignments->every(
            fn($ta) => in_array($ta->status, ['COMPLETADO','NO_COMPLETADO'])
        );
        $puedeCerrarTraspasos = $traspasosResueltos && $pedidosCreditoPendientes->count() === 0;
        $puedeCerrarCobranza  = $pedidosEfectivoPendientesItems->count() === 0 && $cxcSinResolver->count() === 0;
        $montoSugerido        = $pedidosEfectivo + $cxcCobradas;
    @endphp

    {{-- Botones de impresión --}}
    @if(in_array($dispatch->status, ['PLANEADO','PREPARANDO','CARGADO','EN_RUTA']))
        <div class="mb-3">
            <a href="{{ route('admin.dispatches.print.ruta', $dispatch) }}" target="_blank"
               class="inline-flex px-3 py-1.5 text-xs rounded border border-teal-500 text-teal-700 hover:bg-teal-50">
                🖨 Imprimir hoja de ruta
            </a>
        </div>
    @endif
    @if($dispatch->status === 'CERRADO')
        <div class="mb-3">
            <a href="{{ route('admin.dispatches.print.liquidacion', $dispatch) }}" target="_blank"
               class="inline-flex px-3 py-1.5 text-xs rounded border border-blue-500 text-blue-700 hover:bg-blue-50">
                🖨 Imprimir liquidación
            </a>
        </div>
    @endif

    {{-- ====== DATOS DEL DESPACHO ====== --}}
    <x-wire-card>
        <div class="flex items-center gap-3 mb-4">
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusClass }}">{{ $dispatch->status }}</span>
            <span class="px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">{{ ($dispatch->ronda ?? 1) == 2 ? '2da ruta' : '1ra ruta' }}</span>
            <span class="text-sm text-gray-500">Chofer: <strong>{{ $dispatch->driver?->nombre ?? '—' }}</strong></span>
            <span class="text-sm text-gray-500">Fecha: <strong>{{ optional($dispatch->fecha)->format('d/m/Y H:i') }}</strong></span>
        </div>

        @if(!$locked)
        <form id="dispatch-edit" action="{{ route('admin.dispatches.update', $dispatch) }}" method="POST">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    @php $selW = (string)old('warehouse_id', $dispatch->warehouse_id ?? '1'); @endphp
                    <select name="warehouse_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selW===(string)$w->id?'selected':'' }}>{{ $w->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    @php $selR = (string)old('shipping_route_id', $dispatch->shipping_route_id); @endphp
                    <select name="shipping_route_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">-- sin ruta --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selR===(string)$r->id?'selected':'' }}>{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta del día</label>
                    @php $selRonda = (string) old('ronda', $dispatch->ronda ?? 1); @endphp
                    <select name="ronda" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="1" {{ $selRonda==='1' ? 'selected' : '' }}>1ra ruta</option>
                        <option value="2" {{ $selRonda==='2' ? 'selected' : '' }}>2da ruta</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vehículo</label>
                    <input type="text" name="vehicle" value="{{ old('vehicle',$dispatch->vehicle) }}"
                           class="w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="datetime-local" name="fecha" value="{{ $fechaVal }}" required
                           class="w-full rounded-md border-gray-300 text-sm">
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                <textarea name="notas" rows="2"
                          class="w-full rounded-md border-gray-300 text-sm">{{ old('notas',$dispatch->notas) }}</textarea>
            </div>
        </form>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            @if($dispatch->status === 'PLANEADO')
                <form action="{{ route('admin.dispatches.preparar',$dispatch) }}" method="POST">@csrf
                    <button type="submit" class="inline-flex px-3 py-1.5 text-xs rounded-md bg-sky-500 text-white hover:bg-sky-600">Preparar</button>
                </form>
            @elseif($dispatch->status === 'PREPARANDO')
                <form action="{{ route('admin.dispatches.cargar',$dispatch) }}" method="POST">@csrf
                    <button type="submit" class="inline-flex px-3 py-1.5 text-xs rounded-md bg-amber-500 text-white hover:bg-amber-600">Marcar como cargado</button>
                </form>
            @elseif($dispatch->status === 'CARGADO')
                <form action="{{ route('admin.dispatches.enruta',$dispatch) }}" method="POST">@csrf
                    <button type="submit" class="inline-flex px-3 py-1.5 text-xs rounded-md bg-violet-600 text-white hover:bg-violet-700">Salir a ruta</button>
                </form>
            @endif
            @if(!in_array($dispatch->status, ['CERRADO','CANCELADO','EN_RUTA']))
                <form action="{{ route('admin.dispatches.cancelar',$dispatch) }}" method="POST">@csrf
                    <button type="submit" class="inline-flex px-3 py-1.5 text-xs rounded-md bg-red-600 text-white hover:bg-red-700">Cancelar despacho</button>
                </form>
            @endif
        </div>
    </x-wire-card>

    {{-- ══ 1. TRASPASOS ══ --}}
    @if($traspasosTotal > 0)
    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold">1</span>
            <h3 class="font-semibold text-gray-800">Traspasos</h3>
            <span class="text-sm font-normal text-gray-400">({{ $traspasosCompletos }}/{{ $traspasosTotal }} completados)</span>
            @if($enRuta && $traspasosPendientesIds->count() > 0)
                <div class="ml-auto flex gap-2">
                    <form action="{{ route('admin.dispatches.traspasos.bulk', $dispatch) }}" method="POST">
                        @csrf
                        <input type="hidden" name="accion" value="completar">
                        @foreach($traspasosPendientesIds as $tid)
                            <input type="hidden" name="ids[]" value="{{ $tid }}">
                        @endforeach
                        <button type="submit"
                                onclick="return confirmarAccionMasiva(this, '¿Completar TODOS los traspasos?')"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                            ✓ Completar todos ({{ $traspasosPendientesIds->count() }})
                        </button>
                    </form>
                    <form action="{{ route('admin.dispatches.traspasos.bulk', $dispatch) }}" method="POST">
                        @csrf
                        <input type="hidden" name="accion" value="no-completar">
                        @foreach($traspasosPendientesIds as $tid)
                            <input type="hidden" name="ids[]" value="{{ $tid }}">
                        @endforeach
                        <button type="submit"
                                onclick="return confirmarAccionMasiva(this, '¿Marcar TODOS como no completados?')"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-md bg-orange-500 text-white hover:bg-orange-600">
                            ✗ No completar todos
                        </button>
                    </form>
                </div>
            @endif
        </div>
        <div class="overflow-auto border rounded">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="p-2 text-left">Folio</th>
                        <th class="p-2 text-left">Origen</th>
                        <th class="p-2 text-left">Destino</th>
                        <th class="p-2 text-right">Productos</th>
                        <th class="p-2 text-center">Estatus</th>
                        @if($enRuta)<th class="p-2 text-center">Acción</th>@endif
                        @if($dispatch->status === 'PLANEADO')<th class="p-2 text-center">Quitar</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($dispatch->transferAssignments as $ta)
                    @php
                        $t = $ta->stockTransfer;
                        $taStatusClass = match($ta->status) {
                            'COMPLETADO'    => 'bg-emerald-100 text-emerald-700',
                            'NO_COMPLETADO' => 'bg-orange-100 text-orange-700',
                            default         => 'bg-violet-100 text-violet-700',
                        };
                    @endphp
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-2">
                            @if($t)
                                <a href="{{ route('admin.stock.transfers.show', $t) }}" target="_blank"
                                   class="font-mono text-xs text-indigo-600 hover:underline">{{ $t->folio }}</a>
                            @else —
                            @endif
                        </td>
                        <td class="p-2 text-gray-700">{{ $t?->fromWarehouse?->nombre ?? '—' }}</td>
                        <td class="p-2 text-gray-700">{{ $t?->toWarehouse?->nombre ?? '—' }}</td>
                        <td class="p-2 text-right text-gray-500">{{ $t?->items->count() ?? 0 }} prod.</td>
                        <td class="p-2 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $taStatusClass }}">{{ $ta->status }}</span>
                        </td>
                        @if($enRuta)
                        <td class="p-2 text-center">
                            @if($ta->status === 'PENDIENTE')
                                <div class="flex items-center justify-center gap-1">
                                    <form action="{{ route('admin.dispatches.traspasos.completar', [$dispatch, $ta]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700">✓</button>
                                    </form>
                                    <form action="{{ route('admin.dispatches.traspasos.no-completar', [$dispatch, $ta]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 text-xs rounded bg-orange-500 text-white hover:bg-orange-600">✗</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        @endif
                        @if($dispatch->status === 'PLANEADO')
                        <td class="p-2 text-center">
                            @if($ta->status === 'PENDIENTE')
                                <form action="{{ route('admin.dispatches.traspaso.quitar', [$dispatch, $ta]) }}" method="POST">
                                    @csrf
                                    <button type="button" onclick="return confirmarAccionMasiva(this, '¿Quitar este traspaso del despacho?')"
                                            class="px-2 py-1 text-xs rounded border border-red-300 text-red-600 hover:bg-red-50">Quitar</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-wire-card>
    @endif

    {{-- ══ 2. PEDIDOS ══ --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold">2</span>
            <h3 class="font-semibold text-gray-800">Pedidos</h3>
            <span class="text-sm font-normal text-gray-500">({{ $dispatch->items->count() }} en total)</span>
            @if($dispatch->status === 'PLANEADO')
                <button type="button" id="btn-toggle-add-pedidos"
                        class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100">
                    + Agregar pedidos
                </button>
            @endif
            @if($enRuta && $pedidosPendientesItems->count() > 0)
                <div class="ml-auto flex gap-2">
                    <form action="{{ route('admin.dispatches.pedidos.bulk', $dispatch) }}" method="POST">
                        @csrf
                        <input type="hidden" name="accion" value="entregar">
                        @foreach($pedidosPendientesItems as $pi)
                            <input type="hidden" name="ids[]" value="{{ $pi->id }}">
                        @endforeach
                        <button type="submit"
                                onclick="return confirmarAccionMasiva(this, '¿Marcar TODOS los pedidos como entregados?')"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                            ✓ Entregar todos ({{ $pedidosPendientesItems->count() }})
                        </button>
                    </form>
                    <form action="{{ route('admin.dispatches.pedidos.bulk', $dispatch) }}" method="POST">
                        @csrf
                        <input type="hidden" name="accion" value="no-entregar">
                        @foreach($pedidosPendientesItems as $pi)
                            <input type="hidden" name="ids[]" value="{{ $pi->id }}">
                        @endforeach
                        <button type="submit"
                                onclick="return confirmarAccionMasiva(this, '¿Marcar TODOS como NO entregados?')"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-md bg-orange-500 text-white hover:bg-orange-600">
                            ✗ No entregar todos
                        </button>
                    </form>
                </div>
            @endif
        </div>
        @php $pedidosNumCols = 6 + ($enRuta ? 1 : 0) + ($dispatch->status === 'PLANEADO' ? 1 : 0); @endphp
        <div class="overflow-auto border rounded">
            <table class="min-w-full text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="p-2 text-left">Folio</th>
                        <th class="p-2 text-left">Cliente</th>
                        <th class="p-2 text-right">Total</th>
                        <th class="p-2 text-left">Pago</th>
                        <th class="p-2 text-left">Estatus</th>
                        <th class="p-2 text-left">Programado</th>
                        @if($enRuta)<th class="p-2 text-center">Acción</th>@endif
                        @if($dispatch->status === 'PLANEADO')<th class="p-2 text-center">Quitar</th>@endif
                    </tr>
                </thead>
                <tbody>
                @foreach($dispatch->items as $item)
                    @php
                        $o = $item->salesOrder;
                        $oStatus = $o?->status ?? '—';
                        $oStatusClass = match($oStatus) {
                            'EN_RUTA'      => 'bg-violet-100 text-violet-700',
                            'ENTREGADO'    => 'bg-emerald-100 text-emerald-700',
                            'NO_ENTREGADO' => 'bg-orange-100 text-orange-700',
                            default        => 'bg-gray-100 text-gray-600',
                        };
                        // ¿Ya pasó completo por Salida de Producto? (mismo criterio
                        // que bloquea "En ruta" — ver DispatchController::pedidosSinSurtir())
                        // Solo aplica mientras el pedido sigue PROCESADO: llegar a
                        // DESPACHADO exige haber pasado completo por saveDespacho(),
                        // así que un pedido ya DESPACHADO nunca debe marcarse "falta
                        // surtir" — de lo contrario un pedido re-asignado a otro
                        // despacho (que crea un DispatchItem nuevo y vacío) se ve
                        // como pendiente aunque ya esté surtido de verdad.
                        $totalItemsPedido   = $o?->items->count() ?? 0;
                        $itemsSurtidosCount = $item->lines->whereNotNull('qty_despachada')->pluck('sales_order_item_id')->unique()->count();
                        $faltaSurtir        = $oStatus === 'PROCESADO' && ($totalItemsPedido === 0 || $itemsSurtidosCount < $totalItemsPedido);
                        $itemSinTocar       = $item->lines->whereNotNull('qty_despachada')->isEmpty();
                    @endphp
                    <tr class="border-b hover:bg-gray-50 {{ $faltaSurtir ? 'bg-amber-50' : '' }}">
                        <td class="p-2">
                            <a href="{{ route('admin.sales-orders.edit', $o) }}"
                               class="text-indigo-600 hover:underline font-mono text-xs">
                                {{ $o?->folio ?? $item->referencia }}
                            </a>
                        </td>
                        <td class="p-2">{{ $o?->client?->nombre ?? '—' }}</td>
                        <td class="p-2 text-right">${{ number_format($o?->total ?? 0, 2) }}</td>
                        <td class="p-2">{{ $o?->payment_method ?? '—' }}</td>
                        <td class="p-2">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $oStatusClass }}">{{ $oStatus }}</span>
                            @if($faltaSurtir)
                                <span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-amber-200 text-amber-800" title="Faltan productos por surtir en Salida de Producto">
                                    ⏳ Falta surtir ({{ $itemsSurtidosCount }}/{{ $totalItemsPedido }})
                                </span>
                            @endif
                        </td>
                        <td class="p-2 text-xs text-gray-400">{{ optional($o?->programado_para)->format('d/m/Y') ?? '—' }}</td>
                        @if($enRuta)
                        <td class="p-2 text-center">
                            @if($oStatus === 'EN_RUTA')
                                <div class="flex items-center justify-center gap-1">
                                    <form action="{{ route('admin.dispatches.pedido.entregar', [$dispatch, $item]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700">✓</button>
                                    </form>
                                    <form action="{{ route('admin.dispatches.pedido.no-entregar', [$dispatch, $item]) }}" method="POST"
                                          class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="nota" placeholder="Nota"
                                               class="w-24 text-xs border rounded px-1 py-1">
                                        <button type="submit" class="px-2 py-1 text-xs rounded bg-orange-500 text-white hover:bg-orange-600">✗</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        @endif
                        @if($dispatch->status === 'PLANEADO')
                        <td class="p-2 text-center">
                            <form action="{{ route('admin.dispatches.pedido.quitar', [$dispatch, $item]) }}" method="POST">
                                @csrf
                                <button type="button"
                                        onclick="return confirmarAccionMasiva(this, '{{ $itemSinTocar ? '¿Quitar este pedido del despacho?' : '¿Quitar este pedido del despacho? Ya tiene productos surtidos — quedará libre para asignarse a otro despacho, sin tocar su inventario.' }}')"
                                        class="px-2 py-1 text-xs rounded border border-red-300 text-red-600 hover:bg-red-50">Quitar</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @php $notasSurtido = $item->lines->filter(fn($l) => filled($l->nota)); @endphp
                    @if($notasSurtido->count() > 0)
                    <tr class="border-b bg-amber-50">
                        <td colspan="{{ $pedidosNumCols }}" class="px-2 pb-2 text-xs text-amber-800">
                            @foreach($notasSurtido as $ln)
                                <div>📝 <strong>{{ strtoupper($ln->salesOrderItem?->product?->nombre ?? $ln->salesOrderItem?->descripcion ?? 'Producto') }}:</strong> {{ $ln->nota }}</div>
                            @endforeach
                        </td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
                <tfoot class="border-t bg-gray-50">
                    <tr>
                        <td colspan="2" class="p-2 text-sm font-medium text-right">Total pedidos:</td>
                        <td class="p-2 text-right font-semibold">
                            ${{ number_format($dispatch->items->sum(fn($i) => $i->salesOrder?->total ?? 0), 2) }}
                        </td>
                        <td colspan="{{ $pedidosNumCols - 3 }}"></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="p-2 text-xs text-right text-gray-500">Solo efectivo/contraentrega:</td>
                        <td class="p-2 text-right text-sm font-semibold text-amber-700">
                            ${{ number_format($pedidosEfectivo, 2) }}
                        </td>
                        <td colspan="{{ $pedidosNumCols - 3 }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Panel para agregar más pedidos mientras el despacho sigue PLANEADO --}}
        @if($dispatch->status === 'PLANEADO')
        <div id="panel-add-pedidos" class="hidden mt-4 border-t pt-4">
            <form action="{{ route('admin.dispatches.pedidos.agregar', $dispatch) }}" method="POST">
                @csrf
                <div class="flex items-center gap-2 mb-2">
                    <input type="text" id="buscar-pedido-disponible" placeholder="Buscar por folio o cliente..."
                           class="flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <span class="text-xs text-gray-400"><span id="pedidos-disp-count">0</span> seleccionado(s)</span>
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 text-xs rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Agregar seleccionados
                    </button>
                </div>
                @if($pedidosDisponibles->isEmpty())
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-400 text-center">
                        No hay pedidos PROCESADOS sin asignar por el momento.
                    </div>
                @else
                    <div class="overflow-auto border rounded max-h-72">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b sticky top-0">
                                <tr>
                                    <th class="p-2 w-8"></th>
                                    <th class="p-2 text-left">Folio</th>
                                    <th class="p-2 text-left">Cliente</th>
                                    <th class="p-2 text-right">Total</th>
                                    <th class="p-2 text-left">Pago</th>
                                    <th class="p-2 text-left">Programado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pedidosDisponibles as $pd)
                                <tr class="border-b hover:bg-gray-50 pedido-disp-row"
                                    data-search="{{ strtolower($pd->folio.' '.($pd->client?->nombre ?? '')) }}">
                                    <td class="p-2">
                                        <input type="checkbox" name="orders[]" value="{{ $pd->id }}" class="pedido-disp-check rounded border-gray-300">
                                    </td>
                                    <td class="p-2 font-mono text-xs text-indigo-600">{{ $pd->folio }}</td>
                                    <td class="p-2">
                                        {{ $pd->client?->nombre ?? '—' }}
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-medium {{ ($pd->ronda ?? 1) == 2 ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ ($pd->ronda ?? 1) == 2 ? '2da' : '1ra' }}
                                        </span>
                                        @if($pd->status === 'DESPACHADO')
                                            <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"
                                                  title="Ya se surtió antes en otro despacho — se asigna aquí sin tocar su inventario">
                                                ya surtido
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-2 text-right font-medium">${{ number_format($pd->total, 2) }}</td>
                                    <td class="p-2 text-xs">{{ $pd->payment_method }}</td>
                                    <td class="p-2 text-gray-400 text-xs">{{ optional($pd->programado_para)->format('d/m/Y') ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </form>
        </div>
        @endif
    </x-wire-card>

    {{-- ══ 3. CXC ══ --}}
    @if($dispatch->arAssignments->count() > 0 || ($dispatch->status === 'PLANEADO' && $clientesConSaldoDisponibles->count() > 0))
    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold">3</span>
            <h3 class="font-semibold text-gray-800">CxC asignadas al chofer</h3>
            <span class="text-sm font-normal text-gray-400">({{ $dispatch->arAssignments->count() }} cliente(s))</span>

            @if($dispatch->status === 'PLANEADO')
                <button type="button" id="btn-toggle-add-cxc"
                        class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100">
                    + Agregar CxC
                </button>
            @endif

            @if($enRuta && $cxcPendientes->count() > 0)
                <div class="ml-auto relative">
                    <button id="btn-bulk-cxc"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                        ✓ Cobrar todas ({{ $cxcPendientes->count() }}) ▾
                    </button>
                    <div id="modal-bulk-cxc"
                         class="hidden absolute right-0 top-9 z-20 bg-white border rounded-lg shadow-xl p-4 w-80">
                        <button id="btn-close-bulk"
                                class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                        <p class="text-xs font-semibold text-gray-700 mb-3">Cobrar todas las CxC pendientes</p>
                        <form action="{{ route('admin.dispatches.cxc.bulk', $dispatch) }}" method="POST">
                            @csrf
                            <input type="hidden" name="accion" value="cobrar">
                            @foreach($cxcPendientes as $cp)
                                <input type="hidden" name="ids[]" value="{{ $cp->id }}">
                            @endforeach
                            <label class="block text-xs text-gray-500 mb-1">Forma de pago:</label>
                            <select name="payment_type_id" class="w-full rounded border-gray-300 text-sm mb-3" required>
                                <option value="">-- seleccionar --</option>
                                @foreach($paymentTypes as $pt)
                                    <option value="{{ $pt->id }}" {{ $pt->clave === 'EFECTIVO' ? 'selected' : '' }}>{{ $pt->descripcion }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                    class="w-full px-3 py-2 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700 font-medium">
                                ✓ Confirmar cobro de todas
                            </button>
                        </form>
                        <div class="border-t my-3"></div>
                        <form action="{{ route('admin.dispatches.cxc.bulk', $dispatch) }}" method="POST">
                            @csrf
                            <input type="hidden" name="accion" value="no-cobrar">
                            @foreach($cxcPendientes as $cp)
                                <input type="hidden" name="ids[]" value="{{ $cp->id }}">
                            @endforeach
                            <button type="submit"
                                    onclick="return confirmarAccionMasiva(this, '¿Marcar TODAS como no cobradas?')"
                                    class="w-full px-3 py-2 text-xs rounded bg-gray-400 text-white hover:bg-gray-500">
                                ✗ Marcar todas como no cobradas
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-3">
            @foreach($dispatch->arAssignments as $assignment)
            @php
                $cxcStatusClass = match($assignment->status) {
                    'COBRADO'    => 'bg-emerald-100 text-emerald-700',
                    'PARCIAL'    => 'bg-amber-100 text-amber-700',
                    'NO_COBRADO' => 'bg-red-100 text-red-700',
                    default      => 'bg-gray-100 text-gray-600',
                };
                $notasCliente = \App\Models\SalesOrder::where('client_id', $assignment->client_id)
                    ->where('payment_method', 'CREDITO')
                    ->whereIn('status', ['ENTREGADO'])
                    ->whereNull('cobrado_at')
                    ->where(function($q) {
                        $q->whereNull('saldo_pendiente')->orWhere('saldo_pendiente', '>', 0);
                    })
                    ->get(['id','folio','total','saldo_pendiente']);
                $puedeAccion = in_array($assignment->status, ['PENDIENTE','PARCIAL']);
                $saldoRestante = round((float)$assignment->saldo_asignado - (float)$assignment->monto_cobrado, 2);
            @endphp

            <div class="border rounded-lg overflow-hidden">
                {{-- Fila resumen del cliente --}}
                <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-gray-800">{{ $assignment->client?->nombre ?? '—' }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            @if($notasCliente->count() > 0)
                                {{ $notasCliente->count() }} nota(s) pendiente(s)
                            @else
                                Sin notas pendientes
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-400">Saldo asignado</div>
                        <div class="font-semibold text-amber-700">${{ number_format($assignment->saldo_asignado, 2) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-400">Cobrado</div>
                        <div class="font-semibold text-emerald-700">
                            {{ $assignment->monto_cobrado > 0 ? '$'.number_format($assignment->monto_cobrado, 2) : '—' }}
                        </div>
                    </div>
                    @if($saldoRestante > 0 && $assignment->monto_cobrado > 0)
                    <div class="text-right">
                        <div class="text-xs text-gray-400">Restante</div>
                        <div class="font-semibold text-rose-600">${{ number_format($saldoRestante, 2) }}</div>
                    </div>
                    @endif
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $cxcStatusClass }}">
                        {{ $assignment->status }}
                    </span>
                    @if($enRuta && $puedeAccion)
                        <button type="button"
                                data-toggle="panel-{{ $assignment->id }}"
                                class="btn-toggle-panel px-3 py-1.5 text-xs rounded-md border border-indigo-300 text-indigo-600 hover:bg-indigo-50 flex items-center gap-1">
                            <span>Cobrar</span>
                            <span class="toggle-icon">▼</span>
                        </button>
                    @endif
                    @if($dispatch->status === 'PLANEADO' && $assignment->status === 'PENDIENTE' && (float) $assignment->monto_cobrado === 0.0)
                        <form action="{{ route('admin.dispatches.cxc.quitar', [$dispatch, $assignment]) }}" method="POST">
                            @csrf
                            <button type="button" onclick="return confirmarAccionMasiva(this, '¿Quitar esta cuenta por cobrar del despacho?')"
                                    class="px-2 py-1 text-xs rounded border border-red-300 text-red-600 hover:bg-red-50">Quitar</button>
                        </form>
                    @endif
                </div>

                {{-- Panel expandible de cobro --}}
                @if($enRuta && $puedeAccion)
                <div id="panel-{{ $assignment->id }}" class="hidden border-t bg-white px-4 py-4">
                    <form action="{{ route('admin.dispatches.cxc.cobrar', [$dispatch, $assignment]) }}"
                          method="POST" class="space-y-4">
                        @csrf

                        {{-- Notas a cubrir --}}
                        @if($notasCliente->count() > 0)
                        <div>
                            <p class="text-xs font-semibold text-gray-600 mb-2">Selecciona las notas que cubre este cobro:</p>
                            <div class="space-y-1">
                                @foreach($notasCliente as $nota)
                                @php
                                    $saldoN   = ($nota->saldo_pendiente !== null && (float)$nota->saldo_pendiente > 0)
                                        ? (float)$nota->saldo_pendiente
                                        : (float)$nota->total;
                                    $parcialN = $saldoN < (float)$nota->total;
                                @endphp
                                <label class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 hover:bg-indigo-50 cursor-pointer">
                                    <input type="checkbox"
                                           name="order_ids[]"
                                           value="{{ $nota->id }}"
                                           data-saldo="{{ $saldoN }}"
                                           data-assignment="{{ $assignment->id }}"
                                           class="nota-chk rounded border-gray-300 text-indigo-600">
                                    <span class="flex-1 text-sm font-mono text-gray-700">{{ $nota->folio }}</span>
                                    <div class="text-right">
                                        @if($parcialN)
                                            <div class="text-xs text-gray-400 line-through">${{ number_format($nota->total, 2) }}</div>
                                        @endif
                                        <div class="text-sm font-mono font-semibold {{ $parcialN ? 'text-amber-600' : 'text-gray-700' }}">
                                            ${{ number_format($saldoN, 2) }}
                                            @if($parcialN)<span class="text-xs font-normal text-amber-400">pend.</span>@endif
                                        </div>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            <div class="flex justify-between items-center mt-2 pt-2 border-t text-sm">
                                <span class="text-gray-500">Total notas seleccionadas:</span>
                                <span id="suma-notas-{{ $assignment->id }}"
                                      class="font-mono font-semibold text-indigo-600">$0.00</span>
                            </div>
                        </div>
                        @else
                        <p class="text-xs text-gray-400 bg-gray-50 rounded p-3">
                            Sin notas pendientes — puedes registrar un abono general al saldo.
                        </p>
                        @endif

                        {{-- Monto y forma de pago --}}
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Monto a cobrar</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 text-xs pointer-events-none">$</span>
                                    <input type="number"
                                           name="monto"
                                           id="monto-{{ $assignment->id }}"
                                           min="0.01" step="0.01"
                                           value="{{ $saldoRestante }}"
                                           class="pl-5 w-36 text-sm border border-gray-300 rounded-md px-2 py-1.5 text-right font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Forma de pago</label>
                                <select name="payment_type_id"
                                        class="text-sm border border-gray-300 rounded-md px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        required>
                                    <option value="">-- seleccionar --</option>
                                    @foreach($paymentTypes as $pt)
                                        <option value="{{ $pt->id }}" {{ $pt->clave === 'EFECTIVO' ? 'selected' : '' }}>{{ $pt->descripcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">&nbsp;</label>
                                <button type="submit"
                                        class="px-4 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-700 font-medium">
                                    ✓ Registrar cobro
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-3 pt-3 border-t">
                        <form action="{{ route('admin.dispatches.cxc.no-cobrar', [$dispatch, $assignment]) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    onclick="return confirmarAccionMasiva(this, '¿Marcar como no cobrada?')"
                                    class="px-3 py-1.5 text-xs rounded-md bg-gray-200 text-gray-600 hover:bg-gray-300">
                                ✗ No cobrar a este cliente
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Totales --}}
        <div class="flex justify-between items-center mt-4 pt-3 border-t text-sm">
            <span class="font-medium text-gray-600">Totales:</span>
            <div class="flex gap-6">
                <div class="text-right">
                    <div class="text-xs text-gray-400">Saldo asignado</div>
                    <div class="font-bold text-amber-800">${{ number_format($dispatch->arAssignments->sum('saldo_asignado'), 2) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-400">Cobrado</div>
                    <div class="font-bold text-emerald-700">${{ number_format($dispatch->arAssignments->sum('monto_cobrado'), 2) }}</div>
                </div>
            </div>
        </div>

        {{-- Panel para agregar más CxC mientras el despacho sigue PLANEADO --}}
        @if($dispatch->status === 'PLANEADO')
        <div id="panel-add-cxc" class="hidden mt-4 border-t pt-4">
            <form action="{{ route('admin.dispatches.cxc.agregar', $dispatch) }}" method="POST">
                @csrf
                <div class="flex items-center gap-2 mb-2">
                    <input type="text" id="buscar-cxc-disponible" placeholder="Buscar cliente..."
                           class="flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <span class="text-xs text-gray-400"><span id="cxc-disp-count">0</span> seleccionado(s)</span>
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 text-xs rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Agregar seleccionados
                    </button>
                </div>
                @if($clientesConSaldoDisponibles->isEmpty())
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-400 text-center">
                        No hay clientes con saldo pendiente sin asignar por el momento.
                    </div>
                @else
                    <div class="overflow-auto border rounded max-h-72">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b sticky top-0">
                                <tr>
                                    <th class="p-2 w-8"></th>
                                    <th class="p-2 text-left">Cliente</th>
                                    <th class="p-2 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clientesConSaldoDisponibles as $cd)
                                <tr class="border-b hover:bg-gray-50 cxc-disp-row"
                                    data-search="{{ strtolower($cd->nombre) }}">
                                    <td class="p-2">
                                        <input type="checkbox" name="clientes_ar[]" value="{{ $cd->client_id }}" class="cxc-disp-check rounded border-gray-300">
                                    </td>
                                    <td class="p-2">{{ $cd->nombre }}</td>
                                    <td class="p-2 text-right font-mono font-semibold text-amber-700">${{ number_format($cd->saldo, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </form>
        </div>
        @endif
    </x-wire-card>
    @endif

    {{-- ====== RESUMEN EN RUTA ====== --}}
    @if($enRuta)
    <x-wire-card class="mt-4">
        <h3 class="font-semibold text-gray-800 mb-3">Resumen de cobro</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
            <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-3">
                <div class="text-xs text-indigo-600 mb-1">Traspasos completados</div>
                <div class="text-lg font-bold text-indigo-800">{{ $traspasosCompletos }}/{{ $traspasosTotal }}</div>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3">
                <div class="text-xs text-amber-600 mb-1">Pedidos efectivo / contraentrega</div>
                <div class="text-lg font-bold text-amber-800">${{ number_format($pedidosEfectivo, 2) }}</div>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3">
                <div class="text-xs text-blue-600 mb-1">CxC asignadas</div>
                <div class="text-lg font-bold text-blue-800">${{ number_format($dispatch->arAssignments->sum('saldo_asignado'), 2) }}</div>
            </div>
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3">
                <div class="text-xs text-emerald-600 mb-1">Total esperado en efectivo</div>
                <div class="text-xl font-bold text-emerald-800">
                    ${{ number_format($pedidosEfectivo + $dispatch->arAssignments->sum('saldo_asignado'), 2) }}
                </div>
            </div>
        </div>
    </x-wire-card>
    @endif

    {{-- ====== CIERRE COMPLETO EN UN CLIC ====== --}}
    @if($dispatch->status === 'EN_RUTA' && $puedeCerrarTraspasos && $puedeCerrarCobranza && !($dispatch->traspasos_cerrado_at && $dispatch->cobranza_cerrado_at))
    <x-wire-card class="mt-4 border-emerald-200 bg-emerald-50">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-emerald-800">Todo listo para cerrar el despacho por completo</h3>
                <p class="text-xs text-emerald-700 mt-0.5">
                    Traspasos/crédito y cobranza ya se pueden cerrar. Este botón hace los dos cierres de un solo golpe
                    (usa el monto sugerido de cobranza: ${{ number_format($montoSugerido, 2) }}), en vez de entrar a cada tarjeta.
                </p>
            </div>
            <form action="{{ route('admin.dispatches.cerrar-completo', $dispatch) }}" method="POST">
                @csrf
                <input type="hidden" name="monto_entregado" value="{{ $montoSugerido }}">
                <button type="button"
                        onclick="return confirmarAccionMasiva(this, '¿Cerrar el despacho por completo (traspasos + cobranza)?')"
                        class="inline-flex items-center px-4 py-2 text-sm rounded-md bg-emerald-700 text-white hover:bg-emerald-800 whitespace-nowrap">
                    Cerrar despacho completo
                </button>
            </form>
        </div>
    </x-wire-card>
    @endif

    {{-- ====== CIERRES PARCIALES (independientes, en cualquier orden) ====== --}}
    @if($dispatch->status === 'EN_RUTA')
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">

        {{-- Cierre A: Traspasos + pedidos a crédito --}}
        <x-wire-card>
            <div class="flex items-center gap-2 mb-1">
                <h3 class="font-semibold text-gray-800">Cerrar traspasos y pedidos a crédito</h3>
                @if($dispatch->traspasos_cerrado_at)
                    <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700 font-medium">
                        ✓ Cerrado {{ $dispatch->traspasos_cerrado_at->format('d/m H:i') }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mb-3">No toca dinero — solo confirma qué se entregó/transfirió.</p>

            @if(!$dispatch->traspasos_cerrado_at)
                @if(!$traspasosResueltos)
                    @php $traspasosSinMarcar = $dispatch->transferAssignments->where('status', 'PENDIENTE'); @endphp
                    <div class="mb-3">
                        <p class="text-sm text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-t px-3 py-2">
                            Aún hay {{ $traspasosSinMarcar->count() }} traspaso(s) pendientes de marcar:
                        </p>
                        <div class="border border-t-0 border-indigo-200 rounded-b divide-y">
                            @foreach($traspasosSinMarcar as $ta)
                            <div class="flex items-center justify-between gap-2 px-3 py-1.5 text-xs">
                                <span class="text-gray-700">
                                    {{ $ta->stockTransfer?->folio ?? '#'.$ta->id }}
                                    <span class="text-gray-400">
                                        ({{ $ta->stockTransfer?->fromWarehouse?->nombre ?? '—' }} → {{ $ta->stockTransfer?->toWarehouse?->nombre ?? '—' }})
                                    </span>
                                </span>
                                <div class="flex items-center gap-1 shrink-0">
                                    <form action="{{ route('admin.dispatches.traspasos.completar', [$dispatch, $ta]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 rounded bg-emerald-600 text-white hover:bg-emerald-700">✓ Completar</button>
                                    </form>
                                    <form action="{{ route('admin.dispatches.traspasos.no-completar', [$dispatch, $ta]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 rounded bg-orange-500 text-white hover:bg-orange-600">✗</button>
                                    </form>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($pedidosCreditoPendientes->count() > 0)
                    <div class="mb-3">
                        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-t px-3 py-2">
                            Faltan {{ $pedidosCreditoPendientes->count() }} pedido(s) a crédito por marcar:
                        </p>
                        <div class="border border-t-0 border-amber-200 rounded-b divide-y">
                            @foreach($pedidosCreditoPendientes as $pi)
                            <div class="flex items-center justify-between gap-2 px-3 py-1.5 text-xs">
                                <span class="text-gray-700">
                                    {{ $pi->salesOrder?->folio ?? '#'.$pi->id }}
                                    <span class="text-gray-400">({{ $pi->salesOrder?->client?->nombre ?? '—' }})</span>
                                </span>
                                <div class="flex items-center gap-1 shrink-0">
                                    <form action="{{ route('admin.dispatches.pedido.entregar', [$dispatch, $pi]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 rounded bg-emerald-600 text-white hover:bg-emerald-700">✓ Entregado</button>
                                    </form>
                                    <form action="{{ route('admin.dispatches.pedido.no-entregar', [$dispatch, $pi]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 rounded bg-orange-500 text-white hover:bg-orange-600">✗</button>
                                    </form>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                    <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-3 text-center">
                        <div class="text-lg font-semibold text-indigo-700">{{ $traspasosCompletos }}/{{ $traspasosTotal }}</div>
                        <div class="text-xs text-indigo-600">Traspasos completados</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-center">
                        <div class="text-lg font-semibold text-gray-700">{{ $pedidosCreditoPendientes->count() }}</div>
                        <div class="text-xs text-gray-500">Pedidos a crédito pendientes</div>
                    </div>
                </div>

                <form action="{{ route('admin.dispatches.cerrar-traspasos', $dispatch) }}" method="POST">
                    @csrf
                    <button type="submit"
                            {{ !$puedeCerrarTraspasos ? 'disabled' : '' }}
                            class="inline-flex items-center px-4 py-2 text-sm rounded-md bg-indigo-700 text-white hover:bg-indigo-800 {{ !$puedeCerrarTraspasos ? 'opacity-50 cursor-not-allowed' : '' }}">
                        Cerrar traspasos
                    </button>
                </form>
            @endif
        </x-wire-card>

        {{-- Cierre B: Efectivo/contraentrega + CxC --}}
        <x-wire-card>
            <div class="flex items-center gap-2 mb-1">
                <h3 class="font-semibold text-gray-800">Cerrar cobranza (efectivo + CxC)</h3>
                @if($dispatch->cobranza_cerrado_at)
                    <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700 font-medium">
                        ✓ Cerrado {{ $dispatch->cobranza_cerrado_at->format('d/m H:i') }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mb-3">Concilia el dinero, registra en caja y liquida al chofer.</p>

            @if(!$dispatch->cobranza_cerrado_at)
                @if($pedidosEfectivoPendientesItems->count() > 0)
                    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-3">
                        Faltan {{ $pedidosEfectivoPendientesItems->count() }} pedido(s) de efectivo/contraentrega por marcar.
                    </p>
                @endif
                @if($cxcSinResolver->count() > 0)
                    <p class="text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded px-3 py-2 mb-3">
                        Faltan {{ $cxcSinResolver->count() }} CxC sin ni siquiera un abono — cóbralas o márcalas como no cobradas.
                    </p>
                @endif

                <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-center">
                        <div class="text-lg font-semibold text-amber-700">${{ number_format($pedidosEfectivo, 2) }}</div>
                        <div class="text-xs text-amber-600">Efectivo pedidos</div>
                    </div>
                    <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-center">
                        <div class="text-lg font-semibold text-blue-700">${{ number_format($cxcCobradas, 2) }}</div>
                        <div class="text-xs text-blue-600">CxC cobradas</div>
                    </div>
                </div>

                <form id="form-cerrar-cobranza" action="{{ route('admin.dispatches.cerrar-cobranza', $dispatch) }}" method="POST">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Monto entregado <span class="text-indigo-500">(sug: ${{ number_format($montoSugerido, 2) }})</span></label>
                            <input type="hidden" name="monto_entregado" id="monto-entregado-real" value="{{ $montoSugerido }}">
                            <input type="text" inputmode="decimal" id="monto-entregado-visible"
                                   value="{{ number_format($montoSugerido, 2) }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm text-sm text-right">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Notas de cierre (opcional)</label>
                            <input type="text" name="notas_cierre" maxlength="500"
                                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit"
                                {{ !$puedeCerrarCobranza ? 'disabled' : '' }}
                                class="inline-flex items-center px-4 py-2 text-sm rounded-md bg-blue-700 text-white hover:bg-blue-800 {{ !$puedeCerrarCobranza ? 'opacity-50 cursor-not-allowed' : '' }}">
                            Cerrar cobranza
                        </button>
                    </div>
                </form>
            @endif
        </x-wire-card>

    </div>
    @endif

    @if($dispatch->status === 'CERRADO')
    <x-wire-card class="mt-4">
        <div class="flex flex-wrap items-center gap-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700 font-medium">
                Despacho cerrado — {{ optional($dispatch->cerrado_at)->format('d/m/Y H:i') }}
            </span>
            @if($dispatch->monto_liquidado)
                <span class="text-sm text-gray-600">Monto liquidado: <strong>${{ number_format($dispatch->monto_liquidado, 2) }}</strong></span>
            @endif
            @if($dispatch->notas_cierre)
                <span class="text-sm text-gray-500">{{ $dispatch->notas_cierre }}</span>
            @endif
            <a href="{{ route('admin.dispatches.print.liquidacion', $dispatch) }}" target="_blank"
               class="ml-auto inline-flex px-3 py-1.5 text-xs rounded border border-blue-500 text-blue-700 hover:bg-blue-50">
                Imprimir liquidación
            </a>
        </div>
    </x-wire-card>
    @endif

    <script>
    // ── Confirmación de acciones masivas (antes confirm() nativo) ───────────
    function confirmarAccionMasiva(btn, mensaje) {
        var form = btn.closest('form');
        Swal.fire({
            title: mensaje,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            confirmButtonColor: '#4f46e5',
            cancelButtonText: 'Cancelar',
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
        return false;
    }

    (function () {
        // ── Monto entregado con comas de miles (input visible formateado +
        //    hidden con el valor numérico real que se manda al servidor) ───
        var montoVisible = document.getElementById('monto-entregado-visible');
        var montoReal     = document.getElementById('monto-entregado-real');
        if (montoVisible && montoReal) {
            function soloNumero(str) {
                return (str || '').replace(/[^0-9.]/g, '');
            }
            montoVisible.addEventListener('focus', function () {
                this.value = soloNumero(this.value);
            });
            montoVisible.addEventListener('blur', function () {
                var n = parseFloat(soloNumero(this.value)) || 0;
                montoReal.value = n;
                this.value = n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
            montoVisible.addEventListener('input', function () {
                montoReal.value = parseFloat(soloNumero(this.value)) || 0;
            });
        }

        // ── Modal bulk CxC ────────────────────────────────────────────────
        var btnBulk  = document.getElementById('btn-bulk-cxc');
        var modal    = document.getElementById('modal-bulk-cxc');
        var btnClose = document.getElementById('btn-close-bulk');

        if (btnBulk && modal) {
            btnBulk.addEventListener('click', function(e) {
                e.stopPropagation();
                modal.classList.toggle('hidden');
            });
        }
        if (btnClose && modal) {
            btnClose.addEventListener('click', function(e) {
                e.stopPropagation();
                modal.classList.add('hidden');
            });
        }
        document.addEventListener('click', function(e) {
            if (modal && !modal.classList.contains('hidden')) {
                if (!modal.contains(e.target) && e.target !== btnBulk) {
                    modal.classList.add('hidden');
                }
            }
        });

        // ── Paneles expandibles ───────────────────────────────────────────
        document.querySelectorAll('.btn-toggle-panel').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var panelId = this.dataset.toggle;
                var panel   = document.getElementById(panelId);
                var icon    = this.querySelector('.toggle-icon');
                if (!panel) return;
                var isHidden = panel.classList.contains('hidden');
                panel.classList.toggle('hidden', !isHidden);
                if (icon) icon.textContent = isHidden ? '▲' : '▼';
            });
        });

        // ── Agregar pedidos (mientras el despacho sigue PLANEADO) ─────────
        (function() {
            var btnToggle = document.getElementById('btn-toggle-add-pedidos');
            var panel     = document.getElementById('panel-add-pedidos');
            if (btnToggle && panel) {
                btnToggle.addEventListener('click', function() {
                    panel.classList.toggle('hidden');
                });
            }

            var buscar = document.getElementById('buscar-pedido-disponible');
            if (buscar) {
                buscar.addEventListener('input', function() {
                    var term = this.value.toLowerCase().trim();
                    document.querySelectorAll('.pedido-disp-row').forEach(function(row) {
                        row.style.display = row.dataset.search.includes(term) ? '' : 'none';
                    });
                });
            }

            var countEl = document.getElementById('pedidos-disp-count');
            document.querySelectorAll('.pedido-disp-check').forEach(function(chk) {
                chk.addEventListener('change', function() {
                    if (countEl) countEl.textContent = document.querySelectorAll('.pedido-disp-check:checked').length;
                });
            });
        })();

        // ── Agregar CxC (mientras el despacho sigue PLANEADO) ──────────────
        (function() {
            var btnToggle = document.getElementById('btn-toggle-add-cxc');
            var panel     = document.getElementById('panel-add-cxc');
            if (btnToggle && panel) {
                btnToggle.addEventListener('click', function() {
                    panel.classList.toggle('hidden');
                });
            }

            var buscar = document.getElementById('buscar-cxc-disponible');
            if (buscar) {
                buscar.addEventListener('input', function() {
                    var term = this.value.toLowerCase().trim();
                    document.querySelectorAll('.cxc-disp-row').forEach(function(row) {
                        row.style.display = row.dataset.search.includes(term) ? '' : 'none';
                    });
                });
            }

            var countEl = document.getElementById('cxc-disp-count');
            document.querySelectorAll('.cxc-disp-check').forEach(function(chk) {
                chk.addEventListener('change', function() {
                    if (countEl) countEl.textContent = document.querySelectorAll('.cxc-disp-check:checked').length;
                });
            });
        })();

        // ── Auto-sumar notas seleccionadas ────────────────────────────────
        document.querySelectorAll('.nota-chk').forEach(function(chk) {
            chk.addEventListener('change', function() {
                var assignId = this.dataset.assignment;
                var total    = 0;

                document.querySelectorAll('.nota-chk[data-assignment="' + assignId + '"]:checked')
                    .forEach(function(c) {
                        total += parseFloat(c.dataset.saldo) || 0;
                    });

                var sumaEl  = document.getElementById('suma-notas-' + assignId);
                var montoEl = document.getElementById('monto-' + assignId);

                if (sumaEl) {
                    sumaEl.textContent = '$' + total.toLocaleString('es-MX', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2
                    });
                }
                if (montoEl && total > 0) {
                    montoEl.value = total.toFixed(2);
                }
            });
        });
    })();
    </script>

</x-admin-layout>