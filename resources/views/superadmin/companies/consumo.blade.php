@extends('layouts.superadmin-layout')
@section('title', 'Consumo de timbres')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <div>
        <a href="{{ route('superadmin.companies.index') }}" class="text-xs text-gray-500 hover:text-gray-300">
            <i class="fa-solid fa-arrow-left mr-1"></i> Empresas
        </a>
        <h2 class="text-white text-lg font-semibold mt-1">{{ $company->nombre_display }}</h2>
        <div class="text-xs text-gray-500">{{ $company->rfc }}</div>
    </div>

    <form method="GET" action="{{ route('superadmin.companies.consumo', $company) }}" class="flex items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Desde</label>
            <input type="date" name="desde" value="{{ $desde }}"
                   class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}"
                   class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
        </div>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
            Filtrar
        </button>
    </form>
</div>

{{-- Tarjetas resumen --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Timbradas en el periodo</div>
        <div class="text-2xl font-semibold text-emerald-400 mt-1">{{ number_format($totalTimbradas) }}</div>
    </div>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Canceladas en el periodo</div>
        <div class="text-2xl font-semibold text-red-400 mt-1">{{ number_format($totalCanceladas) }}</div>
    </div>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Restantes del paquete activo</div>
        <div class="text-2xl font-semibold text-white mt-1">
            {{ $counter ? number_format($counter->timbresRestantes()) : '—' }}
        </div>
        @if($counter)
        <div class="text-xs text-gray-500 mt-0.5">de {{ number_format($counter->timbres_contratados) }} contratados</div>
        @endif
    </div>
</div>

{{-- Gráfica --}}
<div class="bg-gray-900 rounded-xl border border-gray-800 p-5 mb-6">
    <h3 class="text-sm font-semibold text-white mb-4">
        Timbres consumidos por {{ $porMes ? 'mes' : 'día' }}
    </h3>
    @if($periodos->isEmpty())
        <div class="text-sm text-gray-500 py-10 text-center">
            No hay facturas timbradas ni canceladas en este rango de fechas.
        </div>
    @else
        <canvas id="consumoChart" height="90"></canvas>
    @endif
</div>

{{-- Tabla detalle --}}
@if($periodos->isNotEmpty())
<div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-800">
                <th class="px-5 py-3 text-left text-xs text-gray-500 uppercase tracking-wide">Periodo</th>
                <th class="px-5 py-3 text-center text-xs text-gray-500 uppercase tracking-wide">Timbradas</th>
                <th class="px-5 py-3 text-center text-xs text-gray-500 uppercase tracking-wide">Canceladas</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-800">
            @foreach($periodos as $i => $periodo)
            <tr>
                <td class="px-5 py-3 text-gray-300">{{ $periodo }}</td>
                <td class="px-5 py-3 text-center text-emerald-400">{{ $timbradas[$i] }}</td>
                <td class="px-5 py-3 text-center text-red-400">{{ $canceladas[$i] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($periodos->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('consumoChart');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($periodos),
            datasets: [
                {
                    label: 'Timbradas',
                    data: @json($timbradas),
                    backgroundColor: 'rgba(52, 211, 153, 0.7)',
                    borderRadius: 4,
                },
                {
                    label: 'Canceladas',
                    data: @json($canceladas),
                    backgroundColor: 'rgba(248, 113, 113, 0.7)',
                    borderRadius: 4,
                },
            ],
        },
        options: {
            responsive: true,
            scales: {
                x: { stacked: false, ticks: { color: '#9ca3af' }, grid: { color: '#1f2937' } },
                y: { beginAtZero: true, ticks: { color: '#9ca3af', precision: 0 }, grid: { color: '#1f2937' } },
            },
            plugins: {
                legend: { labels: { color: '#d1d5db' } },
            },
        },
    });
</script>
@endif
@endsection
