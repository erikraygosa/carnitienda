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

    @php
        $valueFecha = old('fecha', now()->format('Y-m-d\TH:i'));
        $selR = (string) old('shipping_route_id', '');
    @endphp

    <x-wire-card>
        <form id="dispatch-form"
              action="{{ route('admin.dispatches.store') }}"
              method="POST"
              class="space-y-6"
              x-data="dispatchForm()"
              x-init="init()">
            @csrf

            {{-- ── Encabezado ── --}}
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

                {{-- Ruta — al cambiar filtra las tablas --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                    <select name="shipping_route_id"
                            class="w-full rounded-md border-gray-300 text-sm"
                            x-model="selectedRoute"
                            @change="onRouteChange()">
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

            {{-- ══ 1. TRASPASOS ══════════════════════════════════════════════ --}}
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold">1</span>
                    <h3 class="font-semibold text-gray-800">Traspasos de almacén</h3>
                    <span class="text-sm font-normal text-gray-400">(PENDIENTES)</span>
                    <a href="{{ route('admin.stock.transfers.create') }}" target="_blank"
                       class="ml-auto text-xs text-indigo-600 hover:underline">+ Nuevo traspaso</a>
                </div>

                @if($traspasosPendientes->isEmpty())
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-400 text-center">
                        No hay traspasos pendientes.
                    </div>
                @else
                    <div class="overflow-auto border rounded">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="p-2 w-8">
                                        <input type="checkbox" id="check-all-transfers" class="rounded border-gray-300">
                                    </th>
                                    <th class="p-2 text-left">Folio</th>
                                    <th class="p-2 text-left">Origen</th>
                                    <th class="p-2 text-left">Destino</th>
                                    <th class="p-2 text-right">Productos</th>
                                    <th class="p-2 text-left">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($traspasosPendientes as $t)
                                <tr class="border-b hover:bg-gray-50 transfer-row">
                                    <td class="p-2">
                                        <input type="checkbox" name="transfers[]"
                                               value="{{ $t->id }}"
                                               class="transfer-check rounded border-gray-300"
                                               {{ in_array($t->id, old('transfers', [])) ? 'checked' : '' }}>
                                    </td>
                                    <td class="p-2 font-mono text-xs text-indigo-600">
                                        <a href="{{ route('admin.stock.transfers.show', $t) }}" target="_blank">
                                            {{ $t->folio }}
                                        </a>
                                    </td>
                                    <td class="p-2 text-gray-700">{{ $t->fromWarehouse?->nombre ?? '—' }}</td>
                                    <td class="p-2 text-gray-700">{{ $t->toWarehouse?->nombre ?? '—' }}</td>
                                    <td class="p-2 text-right text-gray-500">{{ $t->items_count ?? 0 }} prod.</td>
                                    <td class="p-2 text-gray-400 text-xs">{{ $t->fecha->format('d/m/Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @error('transfers')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ══ 2. PEDIDOS ════════════════════════════════════════════════ --}}
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold">2</span>
                    <h3 class="font-semibold text-gray-800">Pedidos candidatos</h3>
                    <span class="text-sm font-normal text-gray-400">(PROCESADOS)</span>

                    {{-- Botón seleccionar todos los de la ruta --}}
                    <template x-if="selectedRoute">
                        <button type="button"
                                @click="selectByRoute()"
                                class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100">
                            ✓ Seleccionar todos de la ruta
                        </button>
                    </template>
                </div>

                <div class="overflow-auto border rounded">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="p-2 w-8">
                                    <input type="checkbox" id="check-all-orders" class="rounded border-gray-300">
                                </th>
                                <th class="p-2 text-left">Folio</th>
                                <th class="p-2 text-left">Cliente</th>
                                <th class="p-2 text-right">Total</th>
                                <th class="p-2 text-left">Pago</th>
                                <th class="p-2 text-left">Ruta</th>
                                <th class="p-2 text-left">Programado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                            <tr class="border-b hover:bg-gray-50 order-row"
                                data-route="{{ $o->shipping_route_id ?? '' }}">
                                <td class="p-2">
                                    <input type="checkbox" name="orders[]"
                                           value="{{ $o->id }}"
                                           class="order-check rounded border-gray-300"
                                           {{ in_array($o->id, old('orders', [])) ? 'checked' : '' }}>
                                </td>
                                <td class="p-2 font-mono text-xs text-indigo-600">{{ $o->folio }}</td>
                                <td class="p-2">{{ $o->client?->nombre ?? '—' }}</td>
                                <td class="p-2 text-right font-medium">${{ number_format($o->total, 2) }}</td>
                                <td class="p-2">
                                    <span class="px-1.5 py-0.5 rounded text-xs
                                        {{ $o->payment_method === 'CREDITO' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $o->payment_method }}
                                    </span>
                                </td>
                                <td class="p-2 text-xs text-gray-500">
                                    @if($o->shipping_route_id)
                                        <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600 text-xs route-badge"
                                              data-route="{{ $o->shipping_route_id }}">
                                            {{ $o->route?->nombre ?? '#'.$o->shipping_route_id }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="p-2 text-gray-400 text-xs">
                                    {{ optional($o->programado_para)->format('d/m/Y') ?? '—' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="p-4 text-center text-gray-400">
                                    No hay pedidos PROCESADOS pendientes.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Contador de seleccionados --}}
                <p class="mt-1 text-xs text-gray-400">
                    <span id="orders-count">0</span> pedido(s) seleccionado(s)
                    <template x-if="selectedRoute">
                        <span> · <span id="route-count">0</span> de esta ruta</span>
                    </template>
                </p>

                @error('orders')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ══ 3. CXC ════════════════════════════════════════════════════ --}}
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold">3</span>
                    <h3 class="font-semibold text-gray-800">Cuentas por cobrar pendientes</h3>
                    <span class="text-sm font-normal text-gray-400">(el chofer las cobra en ruta)</span>
                </div>

                @if($clientesConSaldo->isEmpty())
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-400 text-center">
                        No hay clientes con saldo pendiente.
                    </div>
                @else
                    <div class="overflow-auto border rounded">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="p-2 w-8">
                                        <input type="checkbox" id="check-all-ar" class="rounded border-gray-300">
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
                                    <td class="p-2 font-medium text-gray-700">{{ $cs->nombre }}</td>
                                    <td class="p-2 text-right font-semibold text-amber-700">
                                        ${{ number_format($cs->saldo, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t bg-gray-50">
                                <tr>
                                    <td colspan="2" class="p-2 text-sm font-medium text-right text-gray-600">Total CxC:</td>
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
    function dispatchForm() {
        return {
            selectedRoute: '{{ $selR }}',

            init() {
                this.updateCounters();
                // Escuchar cambios en checkboxes de pedidos para actualizar contador
                document.querySelectorAll('.order-check').forEach(c => {
                    c.addEventListener('change', () => this.updateCounters());
                });
            },

            onRouteChange() {
                this.updateCounters();
            },

            // Selecciona todos los pedidos que pertenecen a la ruta activa
            selectByRoute() {
                const route = this.selectedRoute;
                if (!route) return;
                document.querySelectorAll('.order-row').forEach(row => {
                    const cb = row.querySelector('.order-check');
                    if (cb) cb.checked = row.dataset.route === route;
                });
                this.updateCounters();
            },

            updateCounters() {
                const checked = document.querySelectorAll('.order-check:checked').length;
                const routeCount = this.selectedRoute
                    ? document.querySelectorAll(`.order-row[data-route="${this.selectedRoute}"] .order-check:checked`).length
                    : 0;
                const el = document.getElementById('orders-count');
                const elR = document.getElementById('route-count');
                if (el) el.textContent = checked;
                if (elR) elR.textContent = routeCount;
            },
        }
    }

    // Check-all traspasos
    document.getElementById('check-all-transfers')?.addEventListener('change', function() {
        document.querySelectorAll('.transfer-check').forEach(c => c.checked = this.checked);
    });

    // Check-all pedidos
    document.getElementById('check-all-orders')?.addEventListener('change', function() {
        document.querySelectorAll('.order-check').forEach(c => c.checked = this.checked);
        // Actualizar contador via Alpine
        const alpine = document.querySelector('[x-data]')?._x_dataStack?.[0];
        if (alpine) alpine.updateCounters();
    });

    // Check-all CxC
    document.getElementById('check-all-ar')?.addEventListener('change', function() {
        document.querySelectorAll('.ar-check').forEach(c => c.checked = this.checked);
    });
    </script>
</x-admin-layout>