@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
.select2-container .select2-selection--single { height: 34px !important; border-color: #d1d5db !important; border-radius: 6px !important; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 32px !important; font-size: 0.875rem; color: #374151; padding-left: 10px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 32px !important; }
.select2-dropdown { border-color: #d1d5db; border-radius: 6px; font-size: 0.875rem; }
.select2-container--default .select2-search--dropdown .select2-search__field { border-color: #d1d5db; border-radius: 4px; padding: 4px 8px; }
.select2-container { width: 100% !important; }
</style>
@endpush

<x-admin-layout
    title="Cobranza General"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Finanzas'],
        ['name'=>'Cuentas por cobrar','url'=>route('admin.ar.index')],
        ['name'=>'Cobranza General'],
    ]"
>

{{-- Filtros --}}
<x-wire-card class="mb-4">
    <form method="GET" action="{{ route('admin.ar.cobranza') }}" id="form-filtros" class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Desde cliente</label>
            <select name="cliente_desde" id="sel-desde" class="w-full select2-clientes">
                <option value="">— Todos —</option>
                @foreach($clientes as $c)
                    <option value="{{ $c->nombre }}" {{ request('cliente_desde') === $c->nombre ? 'selected' : '' }}>
                        {{ $c->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Hasta cliente <span class="text-gray-400 font-normal">(opcional)</span></label>
            <select name="cliente_hasta" id="sel-hasta" class="w-full select2-clientes">
                <option value="">— Mismo que desde —</option>
                @foreach($clientes as $c)
                    <option value="{{ $c->nombre }}" {{ request('cliente_hasta') === $c->nombre ? 'selected' : '' }}>
                        {{ $c->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Fecha venc. desde</label>
            <input type="date" name="fecha_venc_desde" value="{{ request('fecha_venc_desde') }}"
                class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Fecha venc. hasta</label>
            <input type="date" name="fecha_venc_hasta" value="{{ request('fecha_venc_hasta') }}"
                class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Estado de notas</label>
            <select name="status" class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="todos"    {{ request('status','todos') === 'todos'    ? 'selected' : '' }}>Todos</option>
                <option value="vencidas" {{ request('status') === 'vencidas' ? 'selected' : '' }}>Vencidas</option>
                <option value="vigentes" {{ request('status') === 'vigentes' ? 'selected' : '' }}>Vigentes</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="hidden" name="solo_con_saldo" value="0">
                <input type="checkbox" name="solo_con_saldo" value="1"
                    {{ request('solo_con_saldo', '1') != '0' ? 'checked' : '' }}
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Solo con saldo pendiente
            </label>
        </div>
        <div class="flex items-end gap-2 md:col-span-2">
            <button type="submit"
                class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition">
                Generar reporte
            </button>
            <a href="{{ route('admin.ar.cobranza') }}"
                class="px-4 py-2 rounded-md border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">
                Limpiar
            </a>
        </div>
    </form>
</x-wire-card>

{{-- Acciones de exportación --}}
@if($porCliente->isNotEmpty())
<div class="flex justify-end gap-2 mb-3">
    <a href="{{ route('admin.ar.cobranza.excel') }}?{{ http_build_query(request()->except('_token')) }}"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Excel
    </a>
    <a href="{{ route('admin.ar.cobranza.pdf') }}?{{ http_build_query(request()->except('_token')) }}"
        target="_blank"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
        </svg>
        PDF
    </a>
    <button type="button" id="btn-enviar-correo"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        Enviar por correo
    </button>
</div>

<script>
(function () {
    const btn = document.getElementById('btn-enviar-correo');
    if (!btn) return;
    const URL_ENVIAR = @json(route('admin.ar.cobranza.enviar') . '?' . http_build_query(request()->except('_token')));
    const EMAIL_SUGERIDO = @json($emailSugerido ?? '');
    const CSRF = @json(csrf_token());

    btn.addEventListener('click', async function () {
        const { value: form } = await Swal.fire({
            title: 'Enviar estado de cuenta',
            html: `
                <div style="text-align:left">
                    <label style="font-size:12px;color:#6b7280">Correo(s) de destino — varios separados por coma</label>
                    <input id="sw-email" type="text" class="swal2-input" style="margin:4px 0 12px;width:100%" placeholder="cobranza@correo.com, conta@correo.com" value="${EMAIL_SUGERIDO}">
                    <label style="font-size:12px;color:#6b7280">Mensaje (opcional)</label>
                    <textarea id="sw-msg" class="swal2-textarea" style="margin:4px 0 0;width:100%" rows="3" maxlength="500" placeholder="Adjunto tu estado de cuenta..."></textarea>
                    <p style="font-size:11px;color:#9ca3af;margin-top:10px">Se envía el PDF con los filtros actuales.</p>
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            preConfirm: () => {
                const email = document.getElementById('sw-email').value.trim();
                const lista = email.split(/[\s,;]+/).filter(Boolean);
                if (!lista.length || lista.some(e => !/^\S+@\S+\.\S+$/.test(e))) { Swal.showValidationMessage('Escribe uno o varios correos válidos, separados por coma.'); return false; }
                return { email, mensaje: document.getElementById('sw-msg').value.trim() };
            },
        });
        if (!form) return;

        Swal.fire({ title: 'Enviando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const res = await fetch(URL_ENVIAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(form),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok) {
                Swal.fire('Enviado', data.message, 'success');
            } else {
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'No se pudo enviar.');
                Swal.fire('No se pudo enviar', msg, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        }
    });
})();
</script>
@endif

{{-- Tabla de resultados --}}
<x-wire-card>
    @if($porCliente->isEmpty())
        <div class="py-12 text-center text-gray-400 text-sm">
            Ajusta los filtros y haz clic en <strong>Generar reporte</strong> para ver los resultados.
        </div>
    @else
        {{-- Totales generales --}}
        <div class="grid grid-cols-3 gap-4 mb-5 pb-4 border-b border-gray-200">
            <div class="text-center">
                <div class="text-xs text-gray-500">Total Cargos</div>
                <div class="text-xl font-bold text-gray-800">${{ number_format($totales['cargos'], 2) }}</div>
            </div>
            <div class="text-center">
                <div class="text-xs text-gray-500">Total Abonos</div>
                <div class="text-xl font-bold text-emerald-600">${{ number_format($totales['abonos'], 2) }}</div>
            </div>
            <div class="text-center">
                <div class="text-xs text-gray-500">Saldo Total</div>
                <div class="text-xl font-bold text-red-600">${{ number_format($totales['saldo'], 2) }}</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 text-xs text-gray-500 uppercase">
                        <th class="px-3 py-2 text-left">Concepto</th>
                        <th class="px-3 py-2 text-left">Documento</th>
                        <th class="px-3 py-2 text-center">Num.</th>
                        <th class="px-3 py-2 text-center">Fecha Aplic.</th>
                        <th class="px-3 py-2 text-center">Fecha Venc.</th>
                        <th class="px-3 py-2 text-right">Cargos</th>
                        <th class="px-3 py-2 text-right">Abonos</th>
                        <th class="px-3 py-2 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($porCliente as $clientId => $notas)
                        @php
                            $primer = $notas->first();
                            $subtotalCargos = $notas->sum('total');
                            $subtotalSaldo  = $notas->sum(fn($r) => $r->saldo_pendiente ?? $r->total);
                            $subtotalAbonos = $subtotalCargos - $subtotalSaldo;
                        @endphp

                        {{-- Encabezado de cliente --}}
                        <tr class="bg-indigo-50">
                            <td colspan="8" class="px-3 py-2 font-semibold text-indigo-800 text-sm">
                                {{ $primer->client_nombre }}
                                <span class="text-xs font-normal text-indigo-500 ml-2">
                                    ({{ $notas->count() }} {{ $notas->count() === 1 ? 'nota' : 'notas' }})
                                </span>
                            </td>
                        </tr>

                        @foreach($notas as $i => $nota)
                            @php
                                $saldo  = $nota->saldo_pendiente ?? $nota->total;
                                $cargos = (float) $nota->total;
                                $abonos = $cargos - $saldo;
                                $vencida = $saldo > 0 && \Carbon\Carbon::parse($nota->fecha_vencimiento)->lt(today());
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $vencida ? 'bg-red-50' : '' }}">
                                <td class="px-3 py-2 text-gray-600">Nota de venta</td>
                                <td class="px-3 py-2 font-mono text-indigo-700 font-semibold">
                                    <a href="{{ route('admin.sales-orders.edit', $nota->id) }}" target="_blank"
                                        class="hover:underline">{{ $nota->folio }}</a>
                                </td>
                                <td class="px-3 py-2 text-center text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-2 text-center text-gray-600">
                                    {{ \Carbon\Carbon::parse($nota->fecha_aplicacion)->format('d/m/Y') }}
                                </td>
                                <td class="px-3 py-2 text-center {{ $vencida ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ \Carbon\Carbon::parse($nota->fecha_vencimiento)->format('d/m/Y') }}
                                    @if($vencida)
                                        <span class="ml-1 text-xs">(vencida)</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right text-gray-700">${{ number_format($cargos, 2) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-600">${{ number_format($abonos, 2) }}</td>
                                <td class="px-3 py-2 text-right font-semibold {{ $saldo > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                    ${{ number_format($saldo, 2) }}
                                </td>
                            </tr>
                        @endforeach

                        {{-- Subtotal cliente --}}
                        <tr class="bg-gray-50 font-semibold text-sm border-t border-gray-300">
                            <td colspan="5" class="px-3 py-1.5 text-right text-gray-600 text-xs">
                                Subtotal {{ $primer->client_nombre }}:
                            </td>
                            <td class="px-3 py-1.5 text-right text-gray-800">${{ number_format($subtotalCargos, 2) }}</td>
                            <td class="px-3 py-1.5 text-right text-emerald-700">${{ number_format($subtotalAbonos, 2) }}</td>
                            <td class="px-3 py-1.5 text-right text-red-700">${{ number_format($subtotalSaldo, 2) }}</td>
                        </tr>
                        <tr><td colspan="8" class="py-1"></td></tr>
                    @endforeach

                    {{-- Total general --}}
                    <tr class="bg-amber-50 font-bold text-sm border-t-2 border-gray-400">
                        <td colspan="5" class="px-3 py-2 text-right text-gray-700">TOTAL GENERAL:</td>
                        <td class="px-3 py-2 text-right text-gray-900">${{ number_format($totales['cargos'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">${{ number_format($totales['abonos'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-red-700">${{ number_format($totales['saldo'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</x-wire-card>

@push('js')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function () {
    $('#sel-desde').select2({
        placeholder: '— Todos —',
        allowClear: true,
        width: '100%',
        language: { searching: function() { return 'Buscando...'; }, noResults: function() { return 'Sin resultados'; } },
    });
    $('#sel-hasta').select2({
        placeholder: '— Mismo que desde —',
        allowClear: true,
        width: '100%',
        language: { searching: function() { return 'Buscando...'; }, noResults: function() { return 'Sin resultados'; } },
    });

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
</script>
@endpush

</x-admin-layout>
