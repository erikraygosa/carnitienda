<x-admin-layout
    title="Cuentas por cobrar"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Finanzas'],
        ['name'=>'Cuentas por cobrar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.ar.cobranza') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 mr-2">
            Reporte cobranza
        </a>
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
        {{-- Filtros: reactivos (se actualizan solos al escribir o cambiar de filtro) --}}
        <form id="ar-filtros" method="GET" action="{{ route('admin.ar.index') }}"
              class="flex flex-col md:flex-row md:items-center gap-3 mb-5"
              onsubmit="return false">
            <div class="relative flex-1">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                </svg>
                <input type="text" id="ar-search" name="search" value="{{ $search }}" autocomplete="off"
                       placeholder="Buscar cliente o RFC..."
                       class="w-full h-10 pl-9 pr-9 rounded-lg border border-gray-300 bg-white shadow-sm text-sm placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                <button type="button" id="ar-limpiar" title="Limpiar búsqueda"
                        class="{{ $search ? '' : 'hidden' }} absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 flex items-center justify-center">
                    ✕
                </button>
            </div>

            <input type="hidden" name="filtro" id="ar-filtro" value="{{ $filtro }}">
            <div class="inline-flex rounded-lg border border-gray-300 bg-gray-50 p-0.5 shrink-0" id="ar-chips">
                @foreach(['todos' => 'Todos', 'con_saldo' => 'Con saldo', 'vencidos' => 'Vencidos'] as $key => $label)
                    <button type="button" data-filtro="{{ $key }}"
                            class="ar-chip h-9 px-4 text-sm font-medium rounded-md transition {{ $filtro === $key ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-800' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </form>

        <div id="ar-resultados">
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
            {{ $rows->withQueryString()->links() }}
        </div>
        </div>{{-- /ar-resultados --}}
    </x-wire-card>

    <script>
    (function () {
        const form = document.getElementById('ar-filtros');
        const search = document.getElementById('ar-search');
        const filtro = document.getElementById('ar-filtro');
        const limpiar = document.getElementById('ar-limpiar');
        const box = () => document.getElementById('ar-resultados');
        let timer = null, ctrl = null;

        function urlActual(extra) {
            const p = new URLSearchParams({ search: search.value.trim(), filtro: filtro.value });
            if (extra) for (const k in extra) p.set(k, extra[k]);
            return form.action + '?' + p.toString();
        }

        async function cargar(url) {
            if (ctrl) ctrl.abort();
            ctrl = new AbortController();
            box().style.opacity = '.5';
            try {
                const res = await fetch(url, { signal: ctrl.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nuevo = doc.getElementById('ar-resultados');
                if (nuevo) { box().innerHTML = nuevo.innerHTML; history.replaceState(null, '', url); }
            } catch (e) { if (e.name !== 'AbortError') box().innerHTML = '<div class="p-6 text-center text-red-500 text-sm">No se pudo actualizar, intenta de nuevo.</div>'; }
            box().style.opacity = '';
        }

        search.addEventListener('input', function () {
            limpiar.classList.toggle('hidden', !search.value);
            clearTimeout(timer);
            timer = setTimeout(() => cargar(urlActual()), 300);
        });
        limpiar.addEventListener('click', function () {
            search.value = ''; limpiar.classList.add('hidden'); search.focus(); cargar(urlActual());
        });
        document.querySelectorAll('.ar-chip').forEach(function (b) {
            b.addEventListener('click', function () {
                filtro.value = b.dataset.filtro;
                document.querySelectorAll('.ar-chip').forEach(function (x) {
                    const on = x === b;
                    x.classList.toggle('bg-white', on); x.classList.toggle('text-indigo-700', on); x.classList.toggle('shadow-sm', on);
                    x.classList.toggle('ring-1', on); x.classList.toggle('ring-gray-200', on);
                    x.classList.toggle('text-gray-600', !on);
                });
                cargar(urlActual());
            });
        });
        // Paginación sin recargar toda la página
        document.addEventListener('click', function (e) {
            const a = e.target.closest('#ar-resultados nav a[href], #ar-resultados .pagination a[href]');
            if (a) { e.preventDefault(); cargar(a.href); }
        });
    })();
    </script>
</x-admin-layout>