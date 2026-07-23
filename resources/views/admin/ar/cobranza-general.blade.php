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
            <input type="text" name="cliente_desde" value="{{ request('cliente_desde') }}"
                placeholder="Nombre inicial..."
                class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Hasta cliente</label>
            <input type="text" name="cliente_hasta" value="{{ request('cliente_hasta') }}"
                placeholder="Nombre final..."
                class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
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
</div>
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
                                $vencida = \Carbon\Carbon::parse($nota->fecha_vencimiento)->isPast();
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $vencida ? 'bg-red-50' : '' }}">
                                <td class="px-3 py-2 text-gray-600">Nota de venta</td>
                                <td class="px-3 py-2 font-mono text-indigo-700 font-semibold">
                                    <a href="{{ route('admin.sales-orders.edit', $nota->id) }}" target="_blank"
                                        class="hover:underline">{{ $nota->folio }}</a>
                                </td>
                                <td class="px-3 py-2 text-center text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-2 text-center text-gray-600">
                                    {{ \Carbon\Carbon::parse($nota->fecha)->format('d/m/Y') }}
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

</x-admin-layout>
