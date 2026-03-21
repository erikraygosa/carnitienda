<x-admin-layout
    title="Nuevo despacho"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Despachos','url'=>route('admin.dispatches.index')],
        ['name'=>'Crear'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.dispatches.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="dispatch-form" type="submit"
                class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    @php $valueFecha = old('fecha', now()->format('Y-m-d\TH:i')); @endphp

    <x-wire-card>
        <form id="dispatch-form"
              action="{{ route('admin.dispatches.store') }}"
              method="POST"
              class="space-y-6">
            @csrf

            {{-- Encabezado --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén</label>
                    @php $selW = (string)old('warehouse_id'); @endphp
                    <select name="warehouse_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selW===(string)$w->id?'selected':'' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    @php $selR = (string)old('shipping_route_id'); @endphp
                    <select name="shipping_route_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">-- sin ruta --</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" {{ $selR===(string)$r->id?'selected':'' }}>
                                {{ $r->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chofer</label>
                    @php $selD = (string)old('driver_id'); @endphp
                    <select name="driver_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">-- sin chofer --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ $selD===(string)$d->id?'selected':'' }}>
                                {{ $d->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-wire-input label="Vehículo" name="vehicle" value="{{ old('vehicle') }}" />
                </div>
                <div class="md:col-span-2">
                    <x-wire-input label="Fecha" name="fecha" type="datetime-local"
                                  value="{{ $valueFecha }}" required />
                </div>
            </div>

            {{-- Pedidos PROCESADOS --}}
            <div>
                <h3 class="font-semibold text-gray-800 mb-2">
                    Pedidos candidatos
                    <span class="text-sm font-normal text-gray-400">(solo PROCESADOS)</span>
                </h3>
                <div class="overflow-auto border rounded">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="p-2 text-left w-8">
                                    <input type="checkbox" id="check-all-orders"
                                           class="rounded border-gray-300">
                                </th>
                                <th class="p-2 text-left">Folio</th>
                                <th class="p-2 text-left">Cliente</th>
                                <th class="p-2 text-right">Total</th>
                                <th class="p-2 text-left">Pago</th>
                                <th class="p-2 text-left">Programado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2">
                                        <input type="checkbox" name="orders[]"
                                               value="{{ $o->id }}"
                                               class="order-check rounded border-gray-300"
                                               {{ in_array($o->id, old('orders', [])) ? 'checked' : '' }}>
                                    </td>
                                    <td class="p-2 font-mono text-xs text-indigo-600">{{ $o->folio }}</td>
                                    <td class="p-2">{{ $o->client?->nombre ?? '—' }}</td>
                                    <td class="p-2 text-right font-medium">
                                        ${{ number_format($o->total, 2) }}
                                    </td>
                                    <td class="p-2">
                                        <span class="px-1.5 py-0.5 rounded text-xs
                                            {{ $o->payment_method === 'CREDITO' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $o->payment_method }}
                                        </span>
                                    </td>
                                    <td class="p-2 text-gray-500 text-xs">
                                        {{ optional($o->programado_para)->format('d/m/Y') ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-4 text-center text-gray-400">
                                        No hay pedidos PROCESADOS pendientes.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @error('orders')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- CxC pendientes por cliente --}}
            <div>
                <h3 class="font-semibold text-gray-800 mb-2">
                    Cuentas por cobrar pendientes
                    <span class="text-sm font-normal text-gray-400">
                        (clientes con saldo — el chofer las cobra en ruta)
                    </span>
                </h3>

                @if($clientesConSaldo->isEmpty())
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-400 text-center">
                        No hay clientes con saldo pendiente.
                    </div>
                @else
                    <div class="overflow-auto border rounded">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="p-2 text-left w-8">
                                        <input type="checkbox" id="check-all-ar"
                                               class="rounded border-gray-300">
                                    </th>
                                    <th class="p-2 text-left">Cliente</th>
                                    <th class="p-2 text-right">Saldo pendiente</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clientesConSaldo as $cs)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-2">
                                            <input type="checkbox" name="clientes_ar[]"
                                                   value="{{ $cs->client_id }}"
                                                   class="ar-check rounded border-gray-300"
                                                   {{ in_array($cs->client_id, old('clientes_ar', [])) ? 'checked' : '' }}>
                                        </td>
                                        <td class="p-2 font-medium text-gray-700">
                                            {{ $cs->nombre }}
                                        </td>
                                        <td class="p-2 text-right font-semibold text-amber-700">
                                            ${{ number_format($cs->saldo, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t bg-gray-50">
                                <tr>
                                    <td colspan="2" class="p-2 text-sm font-medium text-right text-gray-600">
                                        Total CxC:
                                    </td>
                                    <td class="p-2 text-right font-bold text-amber-800">
                                        ${{ number_format($clientesConSaldo->sum('saldo'), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

            <div>
                <x-wire-textarea label="Notas" name="notas">{{ old('notas') }}</x-wire-textarea>
            </div>
        </form>
    </x-wire-card>

    <script>
    // Seleccionar todos los pedidos
    document.getElementById('check-all-orders')?.addEventListener('change', function() {
        document.querySelectorAll('.order-check').forEach(c => c.checked = this.checked);
    });
    // Seleccionar todas las CxC
    document.getElementById('check-all-ar')?.addEventListener('change', function() {
        document.querySelectorAll('.ar-check').forEach(c => c.checked = this.checked);
    });
    </script>
</x-admin-layout>