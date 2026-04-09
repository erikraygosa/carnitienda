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
              class="space-y-6">
            @csrf

           

            @php
    $valueFecha = old('fecha', now()->format('Y-m-d\TH:i'));
    $selR = (string) old('shipping_route_id', '');
@endphp

<div class="grid grid-cols-1 md:grid-cols-4 gap-4">

    {{-- Almacén --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Almacén <span class="text-red-500">*</span>
        </label>
        @php $selW = (string)old('warehouse_id'); @endphp
        <select name="warehouse_id" required
                class="w-full rounded-md border-gray-300 text-sm {{ $errors->has('warehouse_id') ? 'border-red-500' : '' }}">
            <option value="">-- seleccionar --</option>
            @foreach($warehouses as $w)
                <option value="{{ $w->id }}" {{ $selW===(string)$w->id ? 'selected' : '' }}>{{ $w->nombre }}</option>
            @endforeach
        </select>
        @error('warehouse_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Ruta --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Ruta <span class="text-red-500">*</span>
        </label>
        <select name="shipping_route_id" id="shipping_route_id" required
                class="w-full rounded-md border-gray-300 text-sm {{ $errors->has('shipping_route_id') ? 'border-red-500' : '' }}">
            <option value="">-- sin ruta --</option>
            @foreach($routes as $r)
                <option value="{{ $r->id }}" {{ $selR===(string)$r->id ? 'selected' : '' }}>{{ $r->nombre }}</option>
            @endforeach
        </select>
        @error('shipping_route_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Chofer --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Chofer <span class="text-red-500">*</span>
        </label>
        @php $selD = (string)old('driver_id'); @endphp
        <select name="driver_id" required
                class="w-full rounded-md border-gray-300 text-sm {{ $errors->has('driver_id') ? 'border-red-500' : '' }}">
            <option value="">-- sin chofer --</option>
            @foreach($drivers as $d)
                <option value="{{ $d->id }}" {{ $selD===(string)$d->id ? 'selected' : '' }}>{{ $d->nombre }}</option>
            @endforeach
        </select>
        @error('driver_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Vehículo --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Vehículo</label>
        <input type="text" name="vehicle" value="{{ old('vehicle') }}"
               class="w-full rounded-md border-gray-300 shadow-sm text-sm">
    </div>

    {{-- Fecha --}}
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha <span class="text-red-500">*</span></label>
        <input type="datetime-local" name="fecha" value="{{ $valueFecha }}" required
               class="w-full rounded-md border-gray-300 shadow-sm text-sm">
    </div>

</div>

            {{-- ══ 1. TRASPASOS ══ --}}
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

            {{-- ══ 2. PEDIDOS ══ --}}
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold">2</span>
                    <h3 class="font-semibold text-gray-800">Pedidos candidatos</h3>
                    <span class="text-sm font-normal text-gray-400">(PROCESADOS)</span>
                    <button type="button"
                            id="btn-select-route"
                            class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100"
                            style="{{ $selR ? '' : 'display:none' }}">
                        ✓ Seleccionar todos de la ruta
                    </button>
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
                                        <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600 text-xs">
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

                <p class="mt-1 text-xs text-gray-400">
                    <span id="orders-count">0</span> pedido(s) seleccionado(s)
                    <span id="route-count-wrap" style="{{ $selR ? '' : 'display:none' }}">
                        · <span id="route-count">0</span> de esta ruta
                    </span>
                </p>

                @error('orders')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ══ 3. CXC ══ --}}
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
                    <div class="space-y-2">
                        @foreach($clientesConSaldo as $cs)
                        @php
                            $notasPendientesCliente = \App\Models\SalesOrder::where('client_id', $cs->client_id)
                                ->where('payment_method', 'CREDITO')
                                ->whereIn('status', ['ENTREGADO'])
                                ->whereNull('cobrado_at')
                                ->where(function($q) {
                                    $q->whereNull('saldo_pendiente')->orWhere('saldo_pendiente', '>', 0);
                                })
                                ->get(['id','folio','fecha','total','saldo_pendiente']);
                        @endphp
                        <div class="border rounded-lg overflow-hidden">
                            {{-- Fila cliente --}}
                            <div class="flex items-center gap-3 px-4 py-3 bg-gray-50">
                                <input type="checkbox"
                                       name="clientes_ar[]"
                                       value="{{ $cs->client_id }}"
                                       class="ar-check rounded border-gray-300"
                                       id="ar-{{ $cs->client_id }}"
                                       data-saldo="{{ $cs->saldo }}"
                                       {{ in_array($cs->client_id, old('clientes_ar', [])) ? 'checked' : '' }}>
                                <label for="ar-{{ $cs->client_id }}" class="flex-1 cursor-pointer">
                                    <div class="font-medium text-sm text-gray-800">{{ $cs->nombre }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $notasPendientesCliente->count() }} nota(s) · Saldo total:
                                        <span class="font-semibold text-amber-700">${{ number_format($cs->saldo, 2) }}</span>
                                    </div>
                                </label>
                                @if($notasPendientesCliente->count() > 0)
                                <button type="button"
                                        data-toggle="notas-ar-{{ $cs->client_id }}"
                                        class="btn-toggle-notas px-2 py-1 text-xs rounded border border-gray-300 text-gray-500 hover:bg-gray-100">
                                    Ver notas ▼
                                </button>
                                @endif
                            </div>

                            {{-- Notas expandibles --}}
                            @if($notasPendientesCliente->count() > 0)
                            <div id="notas-ar-{{ $cs->client_id }}" class="hidden border-t divide-y divide-gray-100">
                                @foreach($notasPendientesCliente as $nota)
                                @php
                                    $saldoN   = ($nota->saldo_pendiente !== null && (float)$nota->saldo_pendiente > 0)
                                        ? (float)$nota->saldo_pendiente
                                        : (float)$nota->total;
                                    $parcialN = $saldoN < (float)$nota->total;
                                @endphp
                                <div class="flex items-center gap-3 px-6 py-2 bg-white">
                                    <div class="flex-1">
                                        <span class="font-mono text-xs text-indigo-600">{{ $nota->folio }}</span>
                                        <span class="text-xs text-gray-400 ml-2">
                                            {{ \Carbon\Carbon::parse($nota->fecha)->format('d/m/Y') }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        @if($parcialN)
                                            <div class="text-xs text-gray-400 line-through">${{ number_format($nota->total, 2) }}</div>
                                        @endif
                                        <div class="text-sm font-mono font-semibold {{ $parcialN ? 'text-amber-600' : 'text-gray-700' }}">
                                            ${{ number_format($saldoN, 2) }}
                                            @if($parcialN)
                                                <span class="text-xs font-normal text-amber-400">pend.</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                                {{-- Total notas --}}
                                @php
                                    $totalNotasCliente = $notasPendientesCliente->sum(function($n) {
                                        return ($n->saldo_pendiente !== null && (float)$n->saldo_pendiente > 0)
                                            ? (float)$n->saldo_pendiente
                                            : (float)$n->total;
                                    });
                                @endphp
                                <div class="flex justify-between items-center px-6 py-2 bg-gray-50 text-xs text-gray-500">
                                    <span>Total saldo en notas:</span>
                                    <span class="font-mono font-semibold text-amber-700">
                                        ${{ number_format($totalNotasCliente, 2) }}
                                    </span>
                                </div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    {{-- Total seleccionado --}}
                    <div class="flex justify-between items-center mt-3 pt-2 border-t text-sm">
                        <span class="text-gray-500 font-medium">Total CxC seleccionadas:</span>
                        <span id="total-ar-selected" class="font-mono font-bold text-amber-800">$0.00</span>
                    </div>
                @endif
            </div>

            {{-- Notas --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                <textarea name="notas" rows="3"
                          class="w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('notas') }}</textarea>
            </div>

        </form>
    </x-wire-card>

    <script>
    (function(){
        var selectedRoute = '{{ $selR }}';

        // ── Ruta ──────────────────────────────────────────────────────────
        function onRouteChange(val) {
            selectedRoute = val;
            var btn  = document.getElementById('btn-select-route');
            var wrap = document.getElementById('route-count-wrap');
            if (btn)  btn.style.display  = val ? '' : 'none';
            if (wrap) wrap.style.display = val ? '' : 'none';
            updateCounters();
        }

        function selectByRoute() {
            if (!selectedRoute) return;
            document.querySelectorAll('.order-row').forEach(function(row) {
                var cb = row.querySelector('.order-check');
                if (cb) cb.checked = row.dataset.route === selectedRoute;
            });
            updateCounters();
        }

        function updateCounters() {
            var checked    = document.querySelectorAll('.order-check:checked').length;
            var routeCount = selectedRoute
                ? document.querySelectorAll('.order-row[data-route="' + selectedRoute + '"] .order-check:checked').length
                : 0;
            var el  = document.getElementById('orders-count');
            var elR = document.getElementById('route-count');
            if (el)  el.textContent  = checked;
            if (elR) elR.textContent = routeCount;
        }

        document.getElementById('shipping_route_id').addEventListener('change', function() {
            onRouteChange(this.value);
        });

        var btnRoute = document.getElementById('btn-select-route');
        if (btnRoute) btnRoute.addEventListener('click', selectByRoute);

        // ── Checkboxes pedidos ────────────────────────────────────────────
        document.querySelectorAll('.order-check').forEach(function(c) {
            c.addEventListener('change', updateCounters);
        });

        document.getElementById('check-all-transfers')?.addEventListener('change', function() {
            document.querySelectorAll('.transfer-check').forEach(function(c) { c.checked = this.checked; }.bind(this));
        });

        document.getElementById('check-all-orders')?.addEventListener('change', function() {
            document.querySelectorAll('.order-check').forEach(function(c) { c.checked = this.checked; }.bind(this));
            updateCounters();
        });

        // ── CxC ───────────────────────────────────────────────────────────
        function updateTotalAr() {
            var total = 0;
            document.querySelectorAll('.ar-check:checked').forEach(function(chk) {
                total += parseFloat(chk.dataset.saldo || 0);
            });
            var el = document.getElementById('total-ar-selected');
            if (el) el.textContent = '$' + total.toLocaleString('es-MX', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        }

        document.querySelectorAll('.ar-check').forEach(function(chk) {
            chk.addEventListener('change', updateTotalAr);
        });

        // Toggle notas de CxC
        document.querySelectorAll('.btn-toggle-notas').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var panelId = this.dataset.toggle;
                var panel   = document.getElementById(panelId);
                if (!panel) return;
                var isHidden = panel.classList.contains('hidden');
                panel.classList.toggle('hidden', !isHidden);
                this.textContent = isHidden ? 'Ocultar ▲' : 'Ver notas ▼';
            });
        });

        // Init
        updateCounters();
        updateTotalAr();
    })();
    </script>

</x-admin-layout>   