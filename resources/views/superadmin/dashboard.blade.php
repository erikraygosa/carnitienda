@extends('layouts.superadmin-layout')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Estado del PAC --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div class="w-2.5 h-2.5 rounded-full {{ $pacActivo ? 'bg-emerald-400' : 'bg-red-500' }}"></div>
            <div>
                <p class="text-white font-semibold">
                    {{ $pacActivo ? $pacActivo->nombre : 'Sin PAC activo' }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ $pacActivo ? $pacActivo->ambiente_label : 'Ve a PAC / Timbrado para activar uno' }}
                </p>
            </div>
        </div>
        <a href="{{ route('superadmin.pac.index') }}"
           class="text-xs px-3 py-1.5 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">
            Ver PAC / Timbrado
        </a>
    </div>

    {{-- Tarjetas de resumen --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Timbradas hoy</p>
            <p class="text-3xl font-bold text-white mt-1">{{ $timbresHoy }}</p>
        </div>
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Timbradas este mes</p>
            <p class="text-3xl font-bold text-white mt-1">{{ $timbresMes }}</p>
        </div>
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total timbradas</p>
            <p class="text-3xl font-bold text-white mt-1">{{ $timbresTotal }}</p>
            <p class="text-xs text-gray-600 mt-1">
                @if($primerTimbrado)
                    desde {{ \Carbon\Carbon::parse($primerTimbrado)->format('d/m/Y') }}
                @else
                    aún no se timbra nada
                @endif
            </p>
        </div>
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Empresas</p>
            <p class="text-3xl font-bold text-white mt-1">{{ $empresasActivas }} / {{ $totalEmpresas }}</p>
            <p class="text-xs text-gray-600 mt-1">activas / total</p>
        </div>
    </div>

    {{-- Desglose por tipo de comprobante --}}
    @if($timbresPorTipo->isNotEmpty())
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <p class="text-sm font-semibold text-white mb-3">Timbradas por tipo de comprobante</p>
        <div class="flex flex-wrap gap-3">
            @php
                $tipoLabels = ['I' => 'Ingreso', 'E' => 'Egreso', 'P' => 'Pago', 'N' => 'Nómina'];
            @endphp
            @foreach($timbresPorTipo as $tipo => $total)
                <div class="px-4 py-2 rounded-lg bg-gray-800 text-sm">
                    <span class="text-gray-400">{{ $tipoLabels[$tipo] ?? $tipo }}</span>
                    <span class="text-white font-semibold ml-2">{{ (int) $total }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Alertas de timbres --}}
    @if($alertasTimbres->isNotEmpty())
    <div class="bg-red-900/30 border border-red-700 rounded-xl p-5">
        <p class="text-sm font-semibold text-red-200 mb-2">⚠ Empresas con pocos timbres disponibles</p>
        <ul class="text-sm text-red-200 space-y-1">
            @foreach($alertasTimbres as $a)
                <li>{{ $a['empresa'] }}: {{ $a['restantes'] }} restantes ({{ $a['porcentaje'] }}% usado)</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Contadores de timbres por empresa --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800">
            <p class="text-sm font-semibold text-white">Timbres contratados por empresa</p>
        </div>
        @if($empresasConTimbres->isEmpty())
            <p class="text-sm text-gray-500 p-5">
                No hay contadores de timbres configurados todavía. Esto no bloquea el timbrado
                (el PAC no exige timbres si no hay un contador activo), pero tampoco vas a ver
                aquí cuántos tienes contratados. Configúralos en la tabla de contadores si tu
                plan con el PAC los requiere.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-5 py-2">Empresa</th>
                            <th class="px-5 py-2">Usados</th>
                            <th class="px-5 py-2">Contratados</th>
                            <th class="px-5 py-2">Restantes</th>
                            <th class="px-5 py-2">%</th>
                            <th class="px-5 py-2">Alerta</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @foreach($empresasConTimbres as $e)
                        <tr>
                            <td class="px-5 py-2 text-white">{{ $e['empresa'] }}</td>
                            <td class="px-5 py-2 text-gray-300">{{ $e['usados'] }}</td>
                            <td class="px-5 py-2 text-gray-300">{{ $e['contratados'] }}</td>
                            <td class="px-5 py-2 text-gray-300">{{ $e['restantes'] }}</td>
                            <td class="px-5 py-2 text-gray-300">{{ $e['porcentaje'] }}%</td>
                            <td class="px-5 py-2">
                                @if($e['alerta'])
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-900 text-amber-300">{{ $e['alerta'] }}</span>
                                @else
                                    <span class="text-xs text-gray-600">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Histórico por mes --}}
    @if($timbresPorMes->isNotEmpty())
    <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800">
            <p class="text-sm font-semibold text-white">Facturas timbradas por mes</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-5 py-2">Mes</th>
                        <th class="px-5 py-2">Timbradas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @foreach($timbresPorMes as $row)
                    <tr>
                        <td class="px-5 py-2 text-white">
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $row->mes)->translatedFormat('F Y') }}
                        </td>
                        <td class="px-5 py-2 text-gray-300">{{ $row->total }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="flex justify-end">
        <a href="{{ route('superadmin.reset.index') }}"
           class="text-xs px-3 py-1.5 rounded-lg border border-gray-700 text-gray-400 hover:bg-gray-800">
            Reiniciar datos / limpiar facturas de prueba →
        </a>
    </div>

</div>
@endsection
