@extends('layouts.superadmin-layout')
@section('title', 'Series y folios')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-4">Nueva serie</h3>
        <form action="{{ route('superadmin.series.store') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                <select name="company_id" required
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    <option value="">Seleccionar</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre_display }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Serie</label>
                    <input type="text" name="serie" maxlength="10" required placeholder="Ej. A, FAC"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                    <select name="tipo_comprobante" required
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                        <option value="I">I — Ingreso</option>
                        <option value="E">E — Egreso</option>
                        <option value="P">P — Pago</option>
                        <option value="N">N — Nómina</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Folio inicial</label>
                <input type="number" name="folio_inicio" min="1" value="1" required
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Descripción (opcional)</label>
                <input type="text" name="descripcion" maxlength="200"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="es_default" value="1" id="es_default"
                       class="rounded border-gray-600 bg-gray-800 text-indigo-600">
                <label for="es_default" class="text-sm text-gray-400">Serie por defecto para este tipo</label>
            </div>
            <button type="submit"
                    class="w-full py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                Crear serie
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800">
            <h3 class="text-white font-semibold">Series configuradas</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Empresa</th>
                    <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Serie</th>
                    <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-right text-xs text-gray-500 uppercase">Folio actual</th>
                    <th class="px-4 py-3 text-center text-xs text-gray-500 uppercase">Default</th>
                    <th class="px-4 py-3 text-center text-xs text-gray-500 uppercase">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($series as $serie)
                <tr>
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $serie->company?->nombre_display }}</td>
                    <td class="px-4 py-3 text-white font-mono font-semibold">{{ $serie->serie }}</td>
                    <td class="px-4 py-3 text-gray-400">{{ $serie->tipo_label }}</td>
                    <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ number_format($serie->folio_actual) }}</td>
                    <td class="px-4 py-3 text-center">
                        @if($serie->es_default)
                            <span class="text-indigo-400 text-xs">✓</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs px-1.5 py-0.5 rounded-full
                            {{ $serie->activa ? 'bg-emerald-900 text-emerald-300' : 'bg-gray-800 text-gray-500' }}">
                            {{ $serie->activa ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($serie->folio_actual <= $serie->folio_inicio)
                        <form action="{{ route('superadmin.series.destroy', $serie) }}" method="POST" class="inline"
                              onsubmit="return confirm('¿Eliminar serie {{ $serie->serie }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-400">Eliminar</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-600">No hay series configuradas.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection