<x-admin-layout
    title="Notas de crédito"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cuentas por cobrar','url'=>route('admin.ar.index')],
        ['name'=>'Notas de crédito'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.ar-payments.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Registrar cobro
        </a>
    </x-slot>

    {{-- Filtros --}}
    <x-wire-card class="mb-4">
        <form method="GET" action="{{ route('admin.ar-payments.notas.index') }}" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Buscar folio o cliente..."
                   class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 flex-1 min-w-[200px]">

            <select name="client_id"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos los clientes</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>
                        {{ $c->nombre }}
                    </option>
                @endforeach
            </select>

            <select name="estado"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos</option>
                <option value="pendiente" {{ $estado === 'pendiente' ? 'selected' : '' }}>Pendientes</option>
                <option value="parcial"   {{ $estado === 'parcial'   ? 'selected' : '' }}>Pago parcial</option>
                <option value="pagado"    {{ $estado === 'pagado'    ? 'selected' : '' }}>Pagados</option>
            </select>

            <button type="submit"
                    class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('admin.ar-payments.notas.index') }}"
               class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                Limpiar
            </a>
        </form>
    </x-wire-card>

    <x-wire-card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Folio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Cliente</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Fecha</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Total</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Saldo</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Pagos aplicados</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($query as $orden)
                    @php
                        $saldo     = (float)($orden->saldo_pendiente ?? $orden->total);
                        $pagada    = !is_null($orden->cobrado_at);
                        $parcial   = !$pagada && $saldo < (float)$orden->total && $saldo > 0;
                        $pendiente = !$pagada && !$parcial;

                        $estadoLabel = $pagada
                            ? ['Pagado',      'bg-emerald-100 text-emerald-700']
                            : ($parcial
                                ? ['Pago parcial', 'bg-amber-100 text-amber-700']
                                : ['Pendiente',    'bg-rose-100 text-rose-600']);

                        $pagosNota = $pagosPorNota[$orden->id] ?? [];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        {{-- Folio --}}
                        <td class="px-4 py-3 font-mono text-indigo-700 font-semibold">
                            <a href="{{ route('admin.sales-orders.edit', $orden) }}"
                               class="hover:underline">
                                {{ $orden->folio }}
                            </a>
                        </td>

                        {{-- Cliente --}}
                        <td class="px-4 py-3 text-gray-700">
                            {{ $orden->client?->nombre ?? '—' }}
                        </td>

                        {{-- Fecha --}}
                        <td class="px-4 py-3 text-gray-500">
                            {{ $orden->fecha ? $orden->fecha->format('d/m/Y') : '—' }}
                        </td>

                        {{-- Total --}}
                        <td class="px-4 py-3 text-right font-mono text-gray-800">
                            ${{ number_format($orden->total, 2) }}
                        </td>

                        {{-- Saldo --}}
                        <td class="px-4 py-3 text-right font-mono font-semibold
                            {{ $pagada ? 'text-emerald-600' : ($parcial ? 'text-amber-600' : 'text-rose-600') }}">
                            ${{ number_format($saldo, 2) }}
                        </td>

                        {{-- Estado --}}
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $estadoLabel[1] }}">
                                {{ $estadoLabel[0] }}
                            </span>
                            @if($pagada && $orden->cobrado_at)
                                <div class="text-xs text-gray-400 mt-0.5">
                                    {{ \Carbon\Carbon::parse($orden->cobrado_at)->format('d/m/Y') }}
                                </div>
                            @endif
                        </td>

                        {{-- Pagos aplicados --}}
                        <td class="px-4 py-3">
                            @if(empty($pagosNota))
                                <span class="text-xs text-gray-400">Sin pagos registrados</span>
                            @else
                                <div class="space-y-1.5">
                                    @foreach($pagosNota as $pago)
                                    <div class="flex flex-wrap items-center gap-1.5 text-xs bg-gray-50 rounded px-2 py-1">
                                        <span class="font-mono font-semibold text-gray-800">
                                            ${{ number_format($pago->monto, 2) }}
                                        </span>
                                        <span class="text-gray-300">·</span>
                                        <span class="text-gray-600">
                                            {{ $paymentTypes[$pago->payment_type_id] ?? 'N/D' }}
                                        </span>
                                        <span class="text-gray-300">·</span>
                                        <span class="text-gray-500">
                                            {{ \Carbon\Carbon::parse($pago->fecha)->format('d/m/Y') }}
                                        </span>
                                        @if($pago->referencia)
                                            <span class="text-gray-300">·</span>
                                            <span class="text-indigo-500 italic">{{ $pago->referencia }}</span>
                                        @endif
                                        @if($pago->nota)
                                            <span class="text-gray-400 italic">— {{ $pago->nota }}</span>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">
                            No se encontraron notas con los filtros aplicados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div class="mt-4">
            {{ $query->links() }}
        </div>
    </x-wire-card>
</x-admin-layout>