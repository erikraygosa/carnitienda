<x-admin-layout
    title="Nuevo despacho"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Despachos','url'=>route('admin.dispatches.index')],
        ['name'=>'Crear'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.dispatches.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="dispatch-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    @php
        $valueFecha = old('fecha', now()->format('Y-m-d\TH:i'));
    @endphp

    <x-wire-card>
        <form id="dispatch-form" action="{{ route('admin.dispatches.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Almacén --}}
                <div class="space-y-2 w-full">
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Almacén</label>
                    @php $selWarehouse = (string)old('warehouse_id'); @endphp
                    <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300">
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>{{ $w->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Ruta --}}
                <div class="space-y-2 w-full">
                    <label for="shipping_route_id" class="block text-sm font-medium text-gray-700">Ruta</label>
                    @php $selRoute = (string)old('shipping_route_id'); @endphp
                    <select name="shipping_route_id" id="shipping_route_id" class="w-full rounded-md border-gray-300">
                        <option value="">-- seleccionar --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selRoute===(string)$r->id ? 'selected' : '' }}>{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Chofer --}}
                <div class="space-y-2 w-full">
                    <label for="driver_id" class="block text-sm font-medium text-gray-700">Chofer</label>
                    @php $selDriver = (string)old('driver_id'); @endphp
                    <select name="driver_id" id="driver_id" class="w-full rounded-md border-gray-300">
                        <option value="">-- seleccionar --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selDriver===(string)$d->id ? 'selected' : '' }}>{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <x-wire-input label="Vehículo" name="vehicle" value="{{ old('vehicle') }}" />
                <x-wire-input label="Fecha" name="fecha" type="datetime-local" value="{{ $valueFecha }}" required />
            </div>

            {{-- Pedidos a despachar (checkboxes simples) --}}
            <div class="space-y-2">
                <h3 class="font-semibold">Pedidos candidatos</h3>
                <div class="overflow-auto border rounded">
                    <table class="min-w-full text-sm">
                        <thead class="border-b">
                        <tr>
                            <th class="p-2 text-left">Sel</th>
                            <th class="p-2 text-left">ID</th>
                            <th class="p-2 text-left">Folio</th>
                            <th class="p-2 text-left">Cliente</th>
                            <th class="p-2 text-left">Estatus</th>
                            <th class="p-2 text-left">Programado</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($orders as $o)
                            <tr class="border-b">
                                <td class="p-2"><input type="checkbox" name="orders[]" value="{{ $o->id }}"></td>
                                <td class="p-2">{{ $o->id }}</td>
                                <td class="p-2">{{ $o->folio }}</td>
                                <td class="p-2">{{ $o->client?->nombre }}</td>
                                <td class="p-2">{{ $o->status }}</td>
                                <td class="p-2">{{ optional($o->programado_para)->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @error('orders') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Cuentas por cobrar a asignar a la ruta --}}
            <div class="space-y-2">
                <h3 class="font-semibold">Cuentas por cobrar</h3>
                <div class="overflow-auto border rounded">
                    <table class="min-w-full text-sm">
                        <thead class="border-b">
                        <tr>
                            <th class="p-2 text-left">Sel</th>
                            <th class="p-2 text-left">ID</th>
                            <th class="p-2 text-left">Folio</th>
                            <th class="p-2 text-left">Cliente</th>
                            <th class="p-2 text-left">Saldo</th>
                            <th class="p-2 text-left">Fecha</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($accounts as $ac)
                            <tr class="border-b">
                                <td class="p-2"><input type="checkbox" name="accounts_receivable[]" value="{{ $ac->id }}"></td>
                                <td class="p-2">{{ $ac->id }}</td>
                                <td class="p-2">{{ $ac->folio_documento }}</td>
                                <td class="p-2">{{ $ac->client?->nombre }}</td>
                                <td class="p-2">${{ number_format($ac->saldo,2) }}</td>
                                <td class="p-2">{{ optional($ac->fecha)->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-3 text-center text-gray-500">No hay cuentas por cobrar con saldo pendiente.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @error('accounts_receivable') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-wire-textarea label="Notas" name="notas">{{ old('notas') }}</x-wire-textarea>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>
