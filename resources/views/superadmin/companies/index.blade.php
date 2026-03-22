@extends('layouts.superadmin-layout')
@section('title', 'Empresas')

@section('content')
<div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-800">
        <h2 class="text-sm font-semibold text-white">Empresas registradas</h2>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-800">
                <th class="px-5 py-3 text-left text-xs text-gray-500 uppercase tracking-wide">Empresa</th>
                <th class="px-5 py-3 text-left text-xs text-gray-500 uppercase tracking-wide">RFC</th>
                <th class="px-5 py-3 text-center text-xs text-gray-500 uppercase tracking-wide">CSD</th>
                <th class="px-5 py-3 text-center text-xs text-gray-500 uppercase tracking-wide">Timbres</th>
                <th class="px-5 py-3 text-center text-xs text-gray-500 uppercase tracking-wide">Estado</th>
                <th class="px-5 py-3 text-right text-xs text-gray-500 uppercase tracking-wide">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-800">
            @foreach($companies as $company)
            <tr>
                <td class="px-5 py-4">
                    <div class="text-white font-medium">{{ $company->nombre_display }}</div>
                    <div class="text-xs text-gray-500">{{ $company->razon_social }}</div>
                </td>
                <td class="px-5 py-4 font-mono text-gray-400 text-xs">{{ $company->rfc }}</td>
                <td class="px-5 py-4 text-center">
                    @if($company->csdActivo)
                        <span class="text-emerald-400 text-xs">✓ Vigente</span>
                    @else
                        <span class="text-gray-600 text-xs">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-center">
                    @if($company->stamp_counter)
                        @php $alerta = $company->alerta_timbres; @endphp
                        <span class="text-xs font-medium
                            {{ $alerta === 'agotado' || $alerta === 'critico' ? 'text-red-400' :
                              ($alerta === 'advertencia' ? 'text-amber-400' : 'text-emerald-400') }}">
                            {{ number_format($company->timbres_restantes) }} restantes
                        </span>
                    @else
                        <span class="text-gray-600 text-xs">Sin paquete</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-center">
                    <span class="text-xs px-2 py-0.5 rounded-full
                        {{ $company->activo ? 'bg-emerald-900 text-emerald-300' : 'bg-gray-800 text-gray-500' }}">
                        {{ $company->activo ? 'Activa' : 'Inactiva' }}
                    </span>
                </td>
                <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <form action="{{ route('superadmin.companies.toggle', $company) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-xs px-2 py-1 rounded border
                                {{ $company->activo ? 'border-red-800 text-red-400 hover:bg-red-900/30' : 'border-emerald-800 text-emerald-400 hover:bg-emerald-900/30' }}">
                                {{ $company->activo ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                        <button type="button"
                                onclick="document.getElementById('modal-{{ $company->id }}').classList.remove('hidden')"
                                class="text-xs px-2 py-1 rounded border border-indigo-800 text-indigo-400 hover:bg-indigo-900/30">
                            + Timbres
                        </button>
                    </div>

                    {{-- Modal --}}
                    <div id="modal-{{ $company->id }}"
                         class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center"
                         onclick="if(event.target===this) this.classList.add('hidden')">
                        <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-md mx-4">
                            <h3 class="text-white font-semibold mb-4">Agregar timbres — {{ $company->nombre_display }}</h3>
                            <form action="{{ route('superadmin.companies.timbres', $company) }}" method="POST" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Cantidad</label>
                                    <input type="number" name="cantidad" min="1" max="10000" required
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Vigencia inicio</label>
                                        <input type="date" name="vigencia_inicio" value="{{ now()->format('Y-m-d') }}" required
                                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Vigencia fin</label>
                                        <input type="date" name="vigencia_fin" value="{{ now()->addYear()->format('Y-m-d') }}" required
                                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Notas (opcional)</label>
                                    <input type="text" name="notas" maxlength="300"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button"
                                            onclick="document.getElementById('modal-{{ $company->id }}').classList.add('hidden')"
                                            class="px-3 py-2 text-sm text-gray-400 hover:text-white">
                                        Cancelar
                                    </button>
                                    <button type="submit"
                                            class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                                        Guardar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection