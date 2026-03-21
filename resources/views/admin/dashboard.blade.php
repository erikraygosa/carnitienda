<x-admin-layout
    title="Dashboard"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')]]"
>
    <x-slot name="action">
        <a href="{{ route('admin.pos.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Nuevo POS
        </a>
    </x-slot>

    {{-- ── KPIs ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Ventas hoy --}}
        <x-wire-card>
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Ventas hoy</div>
                    <div class="mt-1 text-2xl font-bold text-gray-800">
                        ${{ number_format($ventasHoy, 2) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400">
                        Ayer: ${{ number_format($ventasAyer, 2) }}
                    </div>
                </div>
                <span @class([
                    'px-2 py-0.5 rounded-full text-xs font-medium mt-1',
                    'bg-emerald-100 text-emerald-700' => $variacion >= 0,
                    'bg-red-100 text-red-700'         => $variacion < 0,
                ])>
                    {{ $variacion >= 0 ? '+' : '' }}{{ $variacion }}%
                </span>
            </div>
        </x-wire-card>

        {{-- Pedidos hoy --}}
        <x-wire-card>
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Pedidos hoy</div>
                    <div class="mt-1 text-2xl font-bold text-gray-800">
                        {{ number_format($pedidosHoy) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400">
                        Ayer: {{ number_format($pedidosAyer) }}
                    </div>
                </div>
                @php $varPedidos = $pedidosAyer > 0 ? round((($pedidosHoy-$pedidosAyer)/$pedidosAyer)*100,1) : 0; @endphp
                <span @class([
                    'px-2 py-0.5 rounded-full text-xs font-medium mt-1',
                    'bg-emerald-100 text-emerald-700' => $varPedidos >= 0,
                    'bg-red-100 text-red-700'         => $varPedidos < 0,
                ])>
                    {{ $varPedidos >= 0 ? '+' : '' }}{{ $varPedidos }}%
                </span>
            </div>
        </x-wire-card>

        {{-- CxC --}}
        <x-wire-card>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">CxC pendiente</div>
                <div class="mt-1 text-2xl font-bold {{ $cxcTotal > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                    ${{ number_format($cxcTotal, 2) }}
                </div>
                <a href="{{ route('admin.ar.index') }}"
                   class="mt-1 text-xs text-indigo-500 hover:underline">
                    Ver detalle →
                </a>
            </div>
        </x-wire-card>

        {{-- En ruta --}}
        <x-wire-card>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">Pedidos en ruta</div>
                <div class="mt-1 text-2xl font-bold text-violet-600">
                    {{ $pedidosPorEstado['EN_RUTA'] ?? 0 }}
                </div>
                <a href="{{ route('admin.sales-orders.index') }}"
                   class="mt-1 text-xs text-indigo-500 hover:underline">
                    Ver pedidos →
                </a>
            </div>
        </x-wire-card>
    </div>

    {{-- ── Gráfica + estados ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">

        {{-- Gráfica 7 días --}}
        <x-wire-card class="lg:col-span-2">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Ventas últimos 7 días</h3>
            <canvas id="ventas-chart" height="120"></canvas>
        </x-wire-card>

        {{-- Pedidos por estado --}}
        <x-wire-card>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Pedidos por estado</h3>
            <div class="space-y-2">
                @foreach($estados as $key => $meta)
                    @php $count = $pedidosPorEstado[$key] ?? 0; @endphp
                    @if($count > 0)
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $meta['color'] }}">
                            {{ $meta['label'] }}
                        </span>
                        <span class="font-semibold text-sm text-gray-700">{{ $count }}</span>
                    </div>
                    @endif
                @endforeach
                @if(array_sum($pedidosPorEstado) === 0)
                    <p class="text-xs text-gray-400 text-center py-3">Sin pedidos registrados.</p>
                @endif
            </div>
        </x-wire-card>
    </div>

    {{-- ── Últimos pedidos ── --}}
    <x-wire-card class="mt-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">Últimos pedidos</h3>
            <a href="{{ route('admin.sales-orders.index') }}"
               class="text-xs text-indigo-500 hover:underline">Ver todos →</a>
        </div>
        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="p-2 text-left font-medium text-gray-600">Folio</th>
                        <th class="p-2 text-left font-medium text-gray-600">Cliente</th>
                        <th class="p-2 text-right font-medium text-gray-600">Total</th>
                        <th class="p-2 text-center font-medium text-gray-600">Estado</th>
                        <th class="p-2 text-left font-medium text-gray-600">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($ultimosPedidos as $p)
                        @php
                            $sc = match($p->status) {
                                'BORRADOR'     => 'bg-gray-100 text-gray-600',
                                'APROBADO'     => 'bg-blue-100 text-blue-700',
                                'PREPARANDO'   => 'bg-sky-100 text-sky-700',
                                'PROCESADO'    => 'bg-amber-100 text-amber-700',
                                'EN_RUTA'      => 'bg-violet-100 text-violet-700',
                                'ENTREGADO'    => 'bg-emerald-100 text-emerald-700',
                                'NO_ENTREGADO' => 'bg-orange-100 text-orange-700',
                                'CANCELADO'    => 'bg-rose-100 text-rose-700',
                                default        => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="p-2">
                                <a href="{{ route('admin.sales-orders.edit', $p) }}"
                                   class="text-indigo-600 hover:underline font-mono text-xs">
                                    {{ $p->folio }}
                                </a>
                            </td>
                            <td class="p-2 text-gray-700">{{ $p->client?->nombre ?? '—' }}</td>
                            <td class="p-2 text-right font-medium">${{ number_format($p->total, 2) }}</td>
                            <td class="p-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $sc }}">
                                    {{ $p->status }}
                                </span>
                            </td>
                            <td class="p-2 text-gray-400 text-xs">
                                {{ \Carbon\Carbon::parse($p->created_at)->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-400">Sin pedidos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-wire-card>

    @push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
    (function(){
        const labels = @json($chartLabels);
        const so     = @json($chartSO);
        const pos    = @json($chartPOS);

        const ctx = document.getElementById('ventas-chart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Pedidos',
                        data: so,
                        backgroundColor: 'rgba(99,102,241,0.7)',
                        borderRadius: 4,
                        stack: 'ventas',
                    },
                    {
                        label: 'POS',
                        data: pos,
                        backgroundColor: 'rgba(16,185,129,0.7)',
                        borderRadius: 4,
                        stack: 'ventas',
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' $' + Number(ctx.raw).toLocaleString('es-MX', {minimumFractionDigits:2}),
                        }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: {
                        stacked: true,
                        ticks: {
                            callback: v => '$' + Number(v).toLocaleString('es-MX'),
                        }
                    },
                },
            },
        });
    })();
    </script>
    @endpush
</x-admin-layout>