<x-admin-layout
    title="Editar despacho #{{ $dispatch->id }}"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Despachos','url'=>route('admin.dispatches.index')],
        ['name'=>'Editar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.dispatches.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="dispatch-edit" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Actualizar</button>
    </x-slot>

    @php
        $fechaVal = old('fecha', optional($dispatch->fecha)->format('Y-m-d\TH:i'));
        $statusClass = $statusClasses[$dispatch->status] ?? 'bg-slate-100 text-slate-700';
    @endphp

    <x-wire-card>
        <form id="dispatch-edit" action="{{ route('admin.dispatches.update',$dispatch) }}" method="POST" class="space-y-6">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="space-y-2 w-full">
                    <label for="warehouse_id" class="block text-sm font-medium">Almacén</label>
                    @php $selWarehouse = (string)old('warehouse_id', $dispatch->warehouse_id); @endphp
                    <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300">
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>{{ $w->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 w-full">
                    <label for="shipping_route_id" class="block text-sm font-medium">Ruta</label>
                    @php $selRoute = (string)old('shipping_route_id', $dispatch->shipping_route_id); @endphp
                    <select name="shipping_route_id" id="shipping_route_id" class="w-full rounded-md border-gray-300">
                        <option value="">-- seleccionar --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selRoute===(string)$r->id ? 'selected' : '' }}>{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 w-full">
                    <label for="driver_id" class="block text-sm font-medium">Chofer</label>
                    @php $selDriver = (string)old('driver_id', $dispatch->driver_id); @endphp
                    <select name="driver_id" id="driver_id" class="w-full rounded-md border-gray-300">
                        <option value="">-- seleccionar --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selDriver===(string)$d->id ? 'selected' : '' }}>{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <x-wire-input label="Vehículo" name="vehicle" value="{{ old('vehicle',$dispatch->vehicle) }}" />
                <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $fechaVal }}" required />

                <div class="space-y-2 w-full">
                    <label for="status" class="block text-sm font-medium">Estatus</label>
                    @php $selStatus = old('status', $dispatch->status); @endphp
                    <select name="status" id="status" class="w-full rounded-md border-gray-300">
                        @foreach(['PLANEADO','PREPARANDO','CARGADO','EN_RUTA','ENTREGADO','CERRADO','CANCELADO'] as $st)
                            <option value="{{ $st }}" {{ $selStatus===$st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-wire-textarea label="Notas" name="notas">{{ old('notas',$dispatch->notas) }}</x-wire-textarea>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">Estatus: {{ $dispatch->status }}</span>
                <div class="ml-auto flex items-center space-x-2">
                    <form action="{{ route('admin.dispatches.preparar',$dispatch) }}" method="POST">@csrf <x-wire-button type="submit" sky xs>Preparar</x-wire-button></form>
                    <form action="{{ route('admin.dispatches.cargar',$dispatch) }}"   method="POST">@csrf <x-wire-button type="submit" amber xs>Cargado</x-wire-button></form>
                    <form action="{{ route('admin.dispatches.enruta',$dispatch) }}"   method="POST">@csrf <x-wire-button type="submit" violet xs>En ruta</x-wire-button></form>
                    <form action="{{ route('admin.dispatches.entregar',$dispatch) }}" method="POST">@csrf <x-wire-button type="submit" emerald xs>Entregar</x-wire-button></form>
                    <form action="{{ route('admin.dispatches.cerrar',$dispatch) }}"   method="POST">@csrf <x-wire-button type="submit" blue xs>Cerrar</x-wire-button></form>
                    <form action="{{ route('admin.dispatches.cancelar',$dispatch) }}" method="POST">@csrf <x-wire-button type="submit" red xs>Cancelar</x-wire-button></form>
                </div>
            </div>

            {{-- Items asignados --}}
            <div class="mt-4">
                <h3 class="font-semibold">Pedidos en este despacho</h3>
                <div class="overflow-auto border rounded">
                    <table class="min-w-full text-sm">
                        <thead class="border-b">
                        <tr>
                            <th class="p-2 text-left">Pedido</th>
                            <th class="p-2 text-left">Cliente</th>
                            <th class="p-2 text-left">Estatus</th>
                            <th class="p-2 text-left">Programado</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($dispatch->items as $it)
                            <tr class="border-b">
                                <td class="p-2">#{{ $it->order?->id }} {{ $it->referencia }}</td>
                                <td class="p-2">{{ $it->order?->client?->nombre }}</td>
                                <td class="p-2">{{ $it->order?->status }}</td>
                                <td class="p-2">{{ optional($it->order?->programado_para)->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </form>
    </x-wire-card>
</x-admin-layout>
