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

        // Cálculos globales reutilizables
        $pedidosEfectivo = $dispatch->items
            ->filter(fn($i) => $i->salesOrder?->status === 'ENTREGADO'
                && in_array($i->salesOrder?->payment_method, ['EFECTIVO','CONTRAENTREGA']))
            ->sum(fn($i) => $i->salesOrder?->total ?? 0);

        $cxcCobradas = $dispatch->arAssignments->where('status','COBRADO')->sum('monto_cobrado');

        $totalACobrar = $pedidosEfectivo + $cxcCobradas;
    @endphp

    {{-- Botones de impresión --}}
    @if(in_array($dispatch->status, ['PLANEADO','PREPARANDO','CARGADO','EN_RUTA']))
        <div class="mb-3">
            <a href="{{ route('admin.dispatches.print.ruta', $dispatch) }}"
               target="_blank"
               class="inline-flex px-3 py-1.5 text-xs rounded border border-teal-500 text-teal-700 hover:bg-teal-50">
                Imprimir hoja de ruta
            </a>
        </div>
    @endif

    @if($dispatch->status === 'CERRADO')
        <div class="mb-3">
            <a href="{{ route('admin.dispatches.print.liquidacion', $dispatch) }}"
               target="_blank"
               class="inline-flex px-3 py-1.5 text-xs rounded border border-blue-500 text-blue-700 hover:bg-blue-50">
                Imprimir liquidación
            </a>
        </div>
    @endif

    {{-- ====== DATOS DEL DESPACHO ====== --}}
    <x-wire-card>
        <div class="flex items-center gap-3 mb-4">
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusClass }}">
                {{ $dispatch->status }}
            </span>
            <span class="text-sm text-gray-500">
                Chofer: <strong>{{ $dispatch->driver?->nombre ?? '—' }}</strong>
            </span>
            <span class="text-sm text-gray-500">
                Fecha: <strong>{{ optional($dispatch->fecha)->format('d/m/Y H:i') }}</strong>
            </span>
        </div>

        @if(!$locked)
        <form id="dispatch-edit" action="{{ route('admin.dispatches.update', $dispatch) }}" method="POST">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    @php $selW = (string)old('warehouse_id', $dispatch->warehouse_id); @endphp
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chofer</label>
                    @php $selD = (string)old('driver_id', $dispatch->driver_id); @endphp
                    <select name="driver_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">-- sin chofer --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selD===(string)$d->id?'selected':'' }}>{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-wire-input label="Vehículo" name="vehicle" value="{{ old('vehicle',$dispatch->vehicle) }}" />
                </div>
                <div>
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $fechaVal }}" required />
                </div>
            </div>
            <div class="mt-3">
                <x-wire-textarea label="Notas" name="notas">{{ old('notas',$dispatch->notas) }}</x-wire-textarea>
            </div>
        </form>
        @endif

        {{-- Acciones de flujo --}}
        <div class="mt-4 flex flex-wrap gap-2">
            @if($dispatch->status === 'PLANEADO')
                <form action="{{ route('admin.dispatches.preparar',$dispatch) }}" method="POST">@csrf
                    <x-wire-button type="submit" sky xs>Preparar</x-wire-button>
                </form>
            @elseif($dispatch->status === 'PREPARANDO')
                <form action="{{ route('admin.dispatches.cargar',$dispatch) }}" method="POST">@csrf
                    <x-wire-button type="submit" amber xs>Marcar como cargado</x-wire-button>
                </form>
            @elseif($dispatch->status === 'CARGADO')
                <form action="{{ route('admin.dispatches.enruta',$dispatch) }}" method="POST">@csrf
                    <x-wire-button type="submit" violet xs>Salir a ruta</x-wire-button>
                </form>
            @endif

            @if(!in_array($dispatch->status, ['CERRADO','CANCELADO','EN_RUTA']))
                <form action="{{ route('admin.dispatches.cancelar',$dispatch) }}" method="POST">@csrf
                    <x-wire-button type="submit" red xs>Cancelar despacho</x-wire-button>
                </form>
            @endif
        </div>
    </x-wire-card>

    {{-- ====== PEDIDOS DEL DESPACHO ====== --}}
    <x-wire-card class="mt-4">
        <h3 class="font-semibold text-gray-800 mb-3">
            Pedidos
            <span class="ml-2 text-sm font-normal text-gray-500">({{ $dispatch->items->count() }} en total)</span>
        </h3>
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
                        @if($enRuta)
                            <th class="p-2 text-center">Acción</th>
                        @endif
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
                    @endphp
                    <tr class="border-b">
                        <td class="p-2">
                            <a href="{{ route('admin.sales-orders.edit', $o) }}"
                               class="text-indigo-600 hover:underline">
                                {{ $o?->folio ?? $item->referencia }}
                            </a>
                        </td>
                        <td class="p-2">{{ $o?->client?->nombre ?? '—' }}</td>
                        <td class="p-2 text-right">${{ number_format($o?->total ?? 0, 2) }}</td>
                        <td class="p-2">{{ $o?->payment_method ?? '—' }}</td>
                        <td class="p-2">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $oStatusClass }}">
                                {{ $oStatus }}
                            </span>
                        </td>
                        <td class="p-2">{{ optional($o?->programado_para)->format('d/m/Y') ?? '—' }}</td>

                        @if($enRuta)
                        <td class="p-2 text-center">
                            @if($oStatus === 'EN_RUTA')
                                <div class="flex items-center justify-center gap-1">
                                    <form action="{{ route('admin.dispatches.pedido.entregar', [$dispatch, $item]) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                                class="px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700">
                                            Entregado
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.dispatches.pedido.no-entregar', [$dispatch, $item]) }}" method="POST"
                                          class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="nota" placeholder="Nota (opcional)"
                                               class="w-28 text-xs border rounded px-1 py-1">
                                        <button type="submit"
                                                class="px-2 py-1 text-xs rounded bg-orange-500 text-white hover:bg-orange-600">
                                            No entregado
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="border-t bg-gray-50">
                    <tr>
                        <td colspan="2" class="p-2 text-sm font-medium text-right">Total pedidos:</td>
                        <td class="p-2 text-right font-semibold">
                            ${{ number_format($dispatch->items->sum(fn($i) => $i->salesOrder?->total ?? 0), 2) }}
                        </td>
                        <td colspan="{{ $enRuta ? 4 : 3 }}"></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="p-2 text-xs text-right text-gray-500">Solo efectivo/contraentrega:</td>
                        <td class="p-2 text-right text-sm font-semibold text-amber-700">
                            ${{ number_format($pedidosEfectivo, 2) }}
                        </td>
                        <td colspan="{{ $enRuta ? 4 : 3 }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-wire-card>

    {{-- ====== CUENTAS POR COBRAR ASIGNADAS ====== --}}
    @if($dispatch->arAssignments->count() > 0)
    <x-wire-card class="mt-4">
        <h3 class="font-semibold text-gray-800 mb-3">
            CxC asignadas al chofer
            <span class="ml-2 text-sm font-normal text-gray-400">({{ $dispatch->arAssignments->count() }} cliente(s))</span>
        </h3>
        <div class="overflow-auto border rounded">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="p-3 text-left font-medium text-gray-600">Cliente</th>
                        <th class="p-3 text-right font-medium text-gray-600">Saldo asignado</th>
                        <th class="p-3 text-right font-medium text-gray-600">Cobrado</th>
                        <th class="p-3 text-center font-medium text-gray-600">Estado</th>
                        @if($enRuta)
                            <th class="p-3 text-center font-medium text-gray-600">Acción</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($dispatch->arAssignments as $assignment)
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 font-medium text-gray-700">
                                {{ $assignment->client?->nombre ?? '—' }}
                            </td>
                            <td class="p-3 text-right text-amber-700 font-semibold">
                                ${{ number_format($assignment->saldo_asignado, 2) }}
                            </td>
                            <td class="p-3 text-right text-emerald-700 font-medium">
                                {{ $assignment->monto_cobrado > 0 ? '$'.number_format($assignment->monto_cobrado, 2) : '—' }}
                            </td>
                            <td class="p-3 text-center">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-medium',
                                    'bg-gray-100 text-gray-600'       => $assignment->status === 'PENDIENTE',
                                    'bg-emerald-100 text-emerald-700' => $assignment->status === 'COBRADO',
                                    'bg-red-100 text-red-700'         => $assignment->status === 'NO_COBRADO',
                                ])>
                                    {{ $assignment->status }}
                                </span>
                            </td>

                            @if($enRuta)
                            <td class="p-3">
                                @if($assignment->status === 'PENDIENTE')
                                    <div class="flex items-center justify-center gap-1">
                                        <form action="{{ route('admin.dispatches.cxc.cobrar', [$dispatch, $assignment]) }}"
                                              method="POST" class="flex items-center gap-1">
                                            @csrf
                                            <input type="number" name="monto" min="0.01" step="0.01"
                                                   value="{{ $assignment->saldo_asignado }}"
                                                   class="w-24 text-xs border rounded px-1 py-1 text-right">
                                            <select name="payment_type_id" class="text-xs border rounded px-1 py-1" required>
                                                <option value="">Forma</option>
                                                @foreach($paymentTypes as $pt)
                                                    <option value="{{ $pt->id }}">{{ $pt->descripcion }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit"
                                                    class="px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700">
                                                Cobrar
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.dispatches.cxc.no-cobrar', [$dispatch, $assignment]) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2 py-1 text-xs rounded bg-gray-400 text-white hover:bg-gray-500">
                                                No cobrado
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t bg-gray-50">
                    <tr>
                        <td class="p-3 text-sm font-medium text-right text-gray-600">Totales:</td>
                        <td class="p-3 text-right font-bold text-amber-800">
                            ${{ number_format($dispatch->arAssignments->sum('saldo_asignado'), 2) }}
                        </td>
                        <td class="p-3 text-right font-bold text-emerald-700">
                            ${{ number_format($dispatch->arAssignments->sum('monto_cobrado'), 2) }}
                        </td>
                        <td colspan="{{ $enRuta ? 2 : 1 }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-wire-card>
    @endif

    {{-- ====== RESUMEN GLOBAL EN RUTA ====== --}}
    @if($enRuta)
    <x-wire-card class="mt-4">
        <h3 class="font-semibold text-gray-800 mb-3">Resumen de cobro</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3">
                <div class="text-xs text-amber-600 mb-1">Pedidos efectivo / contraentrega</div>
                <div class="text-lg font-bold text-amber-800">${{ number_format($pedidosEfectivo, 2) }}</div>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3">
                <div class="text-xs text-blue-600 mb-1">CxC asignadas a cobrar</div>
                <div class="text-lg font-bold text-blue-800">
                    ${{ number_format($dispatch->arAssignments->sum('saldo_asignado'), 2) }}
                </div>
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

    {{-- ====== CIERRE Y LIQUIDACIÓN ====== --}}
    @if($dispatch->status === 'EN_RUTA')
    @php
        $todosMarcados = $dispatch->items->every(
            fn($i) => $i->salesOrder && in_array($i->salesOrder->status, ['ENTREGADO','NO_ENTREGADO','CANCELADO'])
        );
        // Valor por defecto del monto: efectivo de pedidos + CxC cobradas en ruta
        $montoSugerido = $pedidosEfectivo + $cxcCobradas;
    @endphp

    <x-wire-card class="mt-4">
        <h3 class="font-semibold text-gray-800 mb-1">Cerrar despacho y liquidar chofer</h3>

        @if(!$todosMarcados)
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-3">
                Aún hay pedidos en tránsito. Márcalos antes de cerrar.
            </p>
        @endif

        {{-- Resumen previo al cierre --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-center">
                <div class="text-lg font-semibold text-emerald-700">
                    {{ $dispatch->items->filter(fn($i) => $i->salesOrder?->status === 'ENTREGADO')->count() }}
                </div>
                <div class="text-xs text-emerald-600">Entregados</div>
            </div>
            <div class="rounded-lg bg-orange-50 border border-orange-200 p-3 text-center">
                <div class="text-lg font-semibold text-orange-700">
                    {{ $dispatch->items->filter(fn($i) => $i->salesOrder?->status === 'NO_ENTREGADO')->count() }}
                </div>
                <div class="text-xs text-orange-600">No entregados</div>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-center">
                <div class="text-lg font-semibold text-amber-700">
                    ${{ number_format($pedidosEfectivo, 2) }}
                </div>
                <div class="text-xs text-amber-600">Efectivo pedidos</div>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-center">
                <div class="text-lg font-semibold text-blue-700">
                    ${{ number_format($cxcCobradas, 2) }}
                </div>
                <div class="text-xs text-blue-600">CxC cobradas</div>
            </div>
        </div>

        <form action="{{ route('admin.dispatches.cerrar', $dispatch) }}" method="POST">
            @csrf
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">
                        Monto entregado por chofer
                        <span class="text-indigo-500">(sugerido: ${{ number_format($montoSugerido, 2) }})</span>
                    </label>
                    <input type="number" name="monto_entregado" min="0" step="0.01"
                           value="{{ $montoSugerido }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           required>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Forma de pago</label>
                    <select name="payment_type_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                        <option value="">-- seleccionar --</option>
                        @foreach($paymentTypes as $pt)
                            <option value="{{ $pt->id }}">{{ $pt->descripcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Caja del día (opcional)</label>
                    <select name="pos_register_id"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- sin caja --</option>
                        @foreach($cajasAbiertas as $cr)
                            <option value="{{ $cr->id }}">
                                {{ $cr->warehouse?->nombre ?? 'Sin almacén' }} — {{ $cr->fecha }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Referencia (opcional)</label>
                    <input type="text" name="referencia" maxlength="255"
                           placeholder="Ej. número de sobre"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs text-gray-500 mb-1">Notas de cierre (opcional)</label>
                    <input type="text" name="notas_cierre" maxlength="500"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit"
                        class="inline-flex items-center px-5 py-2 text-sm rounded-md bg-blue-700 text-white hover:bg-blue-800
                               {{ !$todosMarcados ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ !$todosMarcados ? 'disabled' : '' }}>
                    Cerrar despacho y liquidar
                </button>
            </div>
        </form>
    </x-wire-card>
    @endif

    {{-- Badge de despacho cerrado --}}
    @if($dispatch->status === 'CERRADO')
    <x-wire-card class="mt-4">
        <div class="flex flex-wrap items-center gap-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700 font-medium">
                Despacho cerrado — {{ optional($dispatch->cerrado_at)->format('d/m/Y H:i') }}
            </span>
            @if($dispatch->monto_liquidado)
                <span class="text-sm text-gray-600">
                    Monto liquidado: <strong>${{ number_format($dispatch->monto_liquidado, 2) }}</strong>
                </span>
            @endif
            @if($dispatch->notas_cierre)
                <span class="text-sm text-gray-500">{{ $dispatch->notas_cierre }}</span>
            @endif
            <a href="{{ route('admin.dispatches.print.liquidacion', $dispatch) }}"
               target="_blank"
               class="ml-auto inline-flex px-3 py-1.5 text-xs rounded border border-blue-500 text-blue-700 hover:bg-blue-50">
                Imprimir liquidación
            </a>
        </div>
    </x-wire-card>
    @endif

</x-admin-layout>