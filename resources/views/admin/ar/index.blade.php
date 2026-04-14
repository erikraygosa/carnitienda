<x-admin-layout
    title="Cuentas por cobrar"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Finanzas'],
        ['name'=>'Cuentas por cobrar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.ar-payments.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Registrar cobro
        </a>
    </x-slot>

    {{-- Saldo global --}}
    <div class="mb-4 rounded-lg border bg-amber-50 border-amber-200 px-4 py-3 flex items-center justify-between">
        <span class="text-sm text-amber-800">Saldo total por cobrar</span>
        <span class="text-xl font-semibold text-amber-900">
            ${{ number_format($saldoGlobal, 2) }}
        </span>
    </div>

    <x-wire-card>
        {{-- Filtros --}}
        <form method="GET" action="{{ route('admin.ar.index') }}" class="flex flex-col sm:flex-row gap-3 mb-4">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Buscar cliente o RFC..."
                   class="flex-1 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">

            <div class="flex gap-2">
                <a href="{{ route('admin.ar.index', ['filtro'=>'todos', 'search'=>$search]) }}"
                   class="px-3 py-1.5 text-xs rounded-md border {{ $filtro === 'todos' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700' }}">
                    Todos
                </a>
                <a href="{{ route('admin.ar.index', ['filtro'=>'con_saldo', 'search'=>$search]) }}"
                   class="px-3 py-1.5 text-xs rounded-md border {{ $filtro === 'con_saldo' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-700' }}">
                    Con saldo
                </a>
                <a href="{{ route('admin.ar.index', ['filtro'=>'vencidos', 'search'=>$search]) }}"
                   class="px-3 py-1.5 text-xs rounded-md border {{ $filtro === 'vencidos' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-700' }}">
                    Vencidos
                </a>
                <button type="submit"
                        class="px-3 py-1.5 text-xs rounded-md border bg-gray-700 text-white hover:bg-gray-800">
                    Buscar
                </button>
                @if($search)
                    <a href="{{ route('admin.ar.index', ['filtro'=>$filtro]) }}"
                       class="px-3 py-1.5 text-xs rounded-md border border-gray-300 hover:bg-gray-50">
                        Limpiar
                    </a>
                @endif
            </div>
        </form>

        {{-- Tabla --}}
        <div class="overflow-auto rounded-lg border">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="p-3 text-left font-medium text-gray-600">Cliente</th>
                        <th class="p-3 text-right font-medium text-gray-600">Saldo</th>
                        <th class="p-3 text-right font-medium text-gray-600">Límite crédito</th>
                        <th class="p-3 text-center font-medium text-gray-600">Días crédito</th>
                        <th class="p-3 text-center font-medium text-gray-600">Vencimiento</th>
                        <th class="p-3 text-left font-medium text-gray-600">Último pago</th>
                        <th class="p-3 text-center font-medium text-gray-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    @php
                        $saldo        = (float) $row->saldo;
                        $limite       = (float) ($row->credito_limite ?? 0);
                        $diasCredito  = (int)   ($row->credito_dias  ?? 30);
                        $cargoAntiguo = $row->cargo_mas_antiguo
                            ? \Carbon\Carbon::parse($row->cargo_mas_antiguo)
                            : null;
                        $fechaVence   = $cargoAntiguo ? $cargoAntiguo->copy()->addDays($diasCredito) : null;
                        $diasVencido  = $fechaVence ? (int) $fechaVence->diffInDays(now(), false) : 0;
                        $vencido      = $saldo > 0 && $diasVencido > 0;
                        $porcentaje   = $limite > 0 ? min(100, round(($saldo / $limite) * 100)) : 0;
                        $diasRestantes = $fechaVence && !$vencido
                            ? (int) now()->diffInDays($fechaVence, false)
                            : 0;
                    @endphp
                    <tr class="hover:bg-gray-50 {{ $vencido ? 'bg-red-50' : '' }}">

                        {{-- Cliente --}}
                        <td class="p-3">
                            <a href="{{ route('admin.ar.show', $row) }}"
                               class="font-medium text-indigo-600 hover:underline">
                                {{ $row->nombre }}
                            </a>
                            @if($limite > 0)
                                <div class="mt-1 w-32 h-1.5 rounded-full bg-gray-200">
                                    <div class="h-1.5 rounded-full {{ $porcentaje >= 90 ? 'bg-red-500' : ($porcentaje >= 60 ? 'bg-amber-400' : 'bg-emerald-500') }}"
                                         style="width: {{ $porcentaje }}%"></div>
                                </div>
                                <span class="text-xs text-gray-400">{{ $porcentaje }}% del límite</span>
                            @endif
                        </td>

                        {{-- Saldo --}}
                        <td class="p-3 text-right">
                            @if($saldo > 0)
                                <span class="font-semibold {{ $vencido ? 'text-red-700' : 'text-gray-900' }}">
                                    ${{ number_format($saldo, 2) }}
                                </span>
                            @else
                                <span class="text-emerald-600 font-medium">Al corriente</span>
                            @endif
                        </td>

                        {{-- Límite --}}
                        <td class="p-3 text-right text-gray-500">
                            {{ $limite > 0 ? '$'.number_format($limite, 2) : '—' }}
                        </td>

                        {{-- Días crédito --}}
                        <td class="p-3 text-center text-gray-500">
                            {{ $diasCredito > 0 ? $diasCredito.'d' : '—' }}
                        </td>

                        {{-- Vencimiento --}}
                        <td class="p-3 text-center">
                            @if($saldo <= 0)
                                <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">
                                    Sin deuda
                                </span>
                            @elseif($vencido)
                                <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">
                                    {{ $diasVencido }}d vencido
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">
                                    {{ $diasRestantes > 0 ? $diasRestantes.'d restantes' : 'Hoy vence' }}
                                </span>
                            @endif
                        </td>

                        {{-- Último pago --}}
                        <td class="p-3 text-gray-500">
                            @if($row->ultimo_pago)
                                {{ \Carbon\Carbon::parse($row->ultimo_pago)->format('d/m/Y') }}
                                <span class="text-xs text-gray-400 block">
                                    hace {{ \Carbon\Carbon::parse($row->ultimo_pago)->diffForHumans() }}
                                </span>
                            @else
                                <span class="text-gray-400">Sin pagos</span>
                            @endif
                        </td>

                        {{-- Acciones --}}
                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('admin.ar.show', $row) }}"
                                   class="px-2 py-1 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                                    Ver
                                </a>
                                @if($saldo > 0)
                                    <a href="{{ route('admin.ar-payments.create', ['client_id' => $row->id]) }}"
                                       class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700">
                                        Cobrar
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-gray-400">
                            No se encontraron clientes.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </x-wire-card>
</x-admin-layout>