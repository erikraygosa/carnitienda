@extends('layouts.superadmin-layout')
@section('title', 'Cortes de timbres')

@section('content')
<div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-800">
        <h2 class="text-sm font-semibold text-white">Cortes de timbres por empresa</h2>
        <p class="text-xs text-gray-500 mt-1">La cortesía se renueva cada periodo; el cliente no ve este consumo.</p>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-800">
                <th class="px-5 py-3 text-left text-xs text-gray-500 uppercase tracking-wide">Empresa</th>
                <th class="px-5 py-3 text-left text-xs text-gray-500 uppercase tracking-wide">Periodo actual</th>
                <th class="px-5 py-3 text-center text-xs text-gray-500 uppercase tracking-wide">Usados / cortesía</th>
                <th class="px-5 py-3 text-right text-xs text-gray-500 uppercase tracking-wide">Total a la fecha</th>
                <th class="px-5 py-3 text-center text-xs text-gray-500 uppercase tracking-wide">Cortes pendientes</th>
                <th class="px-5 py-3 text-right text-xs text-gray-500 uppercase tracking-wide"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-800">
            @foreach($filas as $fila)
            @php $a = $fila['actual']; @endphp
            <tr>
                <td class="px-5 py-4">
                    <div class="text-white font-medium">{{ $fila['company']->nombre_display }}</div>
                    <div class="text-xs text-gray-500">{{ $fila['company']->rfc }}</div>
                </td>
                @if($a)
                    <td class="px-5 py-4 text-gray-300 text-xs">{{ $a['inicio']->format('d/m/Y') }} – {{ $a['fin']->format('d/m/Y') }}</td>
                    <td class="px-5 py-4 text-center">
                        <span class="{{ $a['excedente'] > 0 ? 'text-amber-400' : 'text-emerald-400' }} font-medium">{{ $a['timbres_usados'] }}</span>
                        <span class="text-gray-500">/ {{ $a['cortesia'] }}</span>
                    </td>
                    <td class="px-5 py-4 text-right text-white">${{ number_format($a['total'], 2) }}</td>
                    <td class="px-5 py-4 text-center {{ $fila['pendientes'] ? 'text-amber-400' : 'text-gray-600' }}">{{ $fila['pendientes'] ?: '—' }}</td>
                @else
                    <td colspan="4" class="px-5 py-4 text-gray-600 text-xs">{{ $fila['cfg'] ? 'Cobro desactivado' : 'Sin configurar' }}</td>
                @endif
                <td class="px-5 py-4 text-right">
                    <a href="{{ route('superadmin.stamps.company', $fila['company']) }}"
                       class="text-xs px-2 py-1 rounded border border-indigo-800 text-indigo-400 hover:bg-indigo-900/30">
                        {{ $fila['cfg'] ? 'Cortes y precio' : 'Configurar' }}
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
