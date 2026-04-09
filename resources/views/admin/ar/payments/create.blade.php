<x-admin-layout
    title="Registrar cobro"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cuentas por cobrar','url'=>route('admin.ar.index')],
        ['name'=>'Registrar cobro'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.ar.index') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Volver
        </a>
        <button form="pay-form"
                class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Guardar cobro
        </button>
    </x-slot>

    <x-wire-card>
        <form id="pay-form"
              action="{{ route('admin.ar-payments.store') }}"
              method="POST"
              class="space-y-5">
            @csrf

            {{-- Cliente --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                <select name="client_id" id="client_id"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        required>
                    <option value="">-- Selecciona --</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}"
                            {{ ($preClientId ?? '') == $c->id ? 'selected' : '' }}>
                            {{ $c->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('client_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Notas pendientes --}}
            <div id="notas-wrap">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Notas a cubrir
                    <span class="text-gray-400 font-normal">(selecciona las que cubre este pago)</span>
                </label>

                <div id="notas-lista" class="space-y-2">
                    @if($notasPendientes->isEmpty())
                        <p class="text-sm text-gray-400">
                            {{ $preClientId ? 'Este cliente no tiene notas pendientes.' : 'Selecciona un cliente para ver sus notas.' }}
                        </p>
                    @else
                        @foreach($notasPendientes as $orden)
                        @php
                            $saldo = (float)($orden->saldo_pendiente ?? $orden->total);
                            $tieneParcial = $saldo < (float)$orden->total;
                        @endphp
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-indigo-50 cursor-pointer nota-item">
                            <input type="checkbox" name="order_ids[]" value="{{ $orden->id }}"
                                   data-saldo="{{ $saldo }}"
                                   class="nota-chk rounded border-gray-300 text-indigo-600"
                                   {{ in_array($orden->id, old('order_ids', [])) ? 'checked' : '' }}>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-800">{{ $orden->folio }}</div>
                                <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($orden->fecha)->format('d/m/Y') }}</div>
                            </div>
                            <div class="text-right">
                                @if($tieneParcial)
                                    <div class="text-xs text-gray-400 line-through">${{ number_format($orden->total, 2) }}</div>
                                @endif
                                <div class="text-sm font-mono font-semibold {{ $tieneParcial ? 'text-amber-600' : 'text-gray-700' }}">
                                    ${{ number_format($saldo, 2) }}
                                    @if($tieneParcial)
                                        <span class="text-xs font-normal text-amber-500">pendiente</span>
                                    @endif
                                </div>
                            </div>
                        </label>
                        @endforeach

                        <div class="flex justify-between items-center pt-2 border-t text-sm">
                            <span class="text-gray-600">Total notas seleccionadas:</span>
                            <span id="suma-notas" class="font-mono font-semibold text-indigo-600">$0.00</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Fecha y Monto --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="date" name="fecha"
                           value="{{ old('fecha', now()->toDateString()) }}"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                           required>
                    @error('fecha')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm pointer-events-none">$</span>
                        <input type="number" name="amount" id="amount"
                            min="0.01" step="0.01"
                            value="{{ old('amount') }}"
                            class="w-full pl-8 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            required>
                    </div>
                    @error('amount')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Forma de pago --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pago</label>
                <select name="payment_type_id"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        required>
                    <option value="">-- Selecciona --</option>
                    @foreach($types as $t)
                        <option value="{{ $t->id }}"
                            {{ old('payment_type_id') == $t->id ? 'selected' : '' }}>
                            {{ $t->descripcion ?? $t->name ?? 'Tipo '.$t->id }}
                        </option>
                    @endforeach
                </select>
                @error('payment_type_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Referencia y Notas --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Referencia <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <input type="text" name="reference"
                           value="{{ old('reference') }}"
                           placeholder="Folio, transferencia, cheque..."
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Notas <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea name="notes" rows="1"
                              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                </div>
            </div>

        </form>
    </x-wire-card>

    <script>
    (function () {
        var sumaEl   = document.getElementById('suma-notas');
        var amountEl = document.getElementById('amount');

        // ── Calcular suma usando data-saldo ───────────────────────────────
        function calcularSuma() {
            var total = 0;
            document.querySelectorAll('.nota-chk:checked').forEach(function(chk) {
                total += parseFloat(chk.dataset.saldo) || 0;
            });
            if (sumaEl) {
                sumaEl.textContent = '$' + total.toLocaleString('es-MX', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }
            if (amountEl && (amountEl.value === '' || parseFloat(amountEl.value) === 0) && total > 0) {
                amountEl.value = total.toFixed(2);
            }
        }

        function bindChks() {
            document.querySelectorAll('.nota-chk').forEach(function(chk) {
                chk.addEventListener('change', calcularSuma);
            });
        }

        bindChks();

        // ── Cambio de cliente ─────────────────────────────────────────────
        var clientSelect = document.getElementById('client_id');
        var notasLista   = document.getElementById('notas-lista');

        clientSelect.addEventListener('change', function () {
            var clientId = this.value;
            if (!clientId) {
                notasLista.innerHTML = '<p class="text-sm text-gray-400">Selecciona un cliente para ver sus notas.</p>';
                return;
            }

            notasLista.innerHTML = '<p class="text-sm text-gray-400">Cargando...</p>';

            fetch('{{ route("admin.ar-payments.notas") }}?client_id=' + clientId, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.length) {
                    notasLista.innerHTML = '<p class="text-sm text-gray-400">Este cliente no tiene notas pendientes.</p>';
                    return;
                }

                var html = data.map(function(o) {
                    var tieneParcial = o.saldo_pendiente < o.total;
                    var saldoFmt = Number(o.saldo_pendiente).toLocaleString('es-MX', {minimumFractionDigits:2});
                    var totalFmt = Number(o.total).toLocaleString('es-MX', {minimumFractionDigits:2});

                    return '<label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-indigo-50 cursor-pointer nota-item">'
                        + '<input type="checkbox" name="order_ids[]" value="' + o.id + '"'
                        + ' data-saldo="' + o.saldo_pendiente + '"'
                        + ' class="nota-chk rounded border-gray-300 text-indigo-600">'
                        + '<div class="flex-1">'
                        + '<div class="text-sm font-medium text-gray-800">' + o.folio + '</div>'
                        + '<div class="text-xs text-gray-500">' + o.fecha + '</div>'
                        + '</div>'
                        + '<div class="text-right">'
                        + (tieneParcial ? '<div class="text-xs text-gray-400 line-through">$' + totalFmt + '</div>' : '')
                        + '<div class="text-sm font-mono font-semibold ' + (tieneParcial ? 'text-amber-600' : 'text-gray-700') + '">'
                        + '$' + saldoFmt
                        + (tieneParcial ? ' <span class="text-xs font-normal text-amber-500">pendiente</span>' : '')
                        + '</div>'
                        + '</div>'
                        + '</label>';
                }).join('');

                html += '<div class="flex justify-between items-center pt-2 border-t text-sm">'
                    + '<span class="text-gray-600">Total notas seleccionadas:</span>'
                    + '<span id="suma-notas" class="font-mono font-semibold text-indigo-600">$0.00</span>'
                    + '</div>';

                notasLista.innerHTML = html;
                sumaEl = document.getElementById('suma-notas');
                bindChks();
            })
            .catch(function() {
                notasLista.innerHTML = '<p class="text-sm text-red-400">Error al cargar notas.</p>';
            });
        });
    })();
    </script>

</x-admin-layout>