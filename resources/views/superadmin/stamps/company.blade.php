@extends('layouts.superadmin-layout')
@section('title', 'Cortes de timbres')

@php
    $inp = 'w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none';
    $lbl = 'block text-xs text-gray-500 mb-1';
    $m = fn ($v) => '$' . number_format((float) $v, 2);
@endphp

@section('content')
<div class="mb-4">
    <a href="{{ route('superadmin.stamps.index') }}" class="text-xs text-gray-500 hover:text-gray-300">
        <i class="fa-solid fa-arrow-left mr-1"></i> Cortes de timbres
    </a>
    <h2 class="text-white text-lg font-semibold mt-1">{{ $company->nombre_display }}</h2>
    <div class="text-xs text-gray-500">{{ $company->rfc }}</div>
</div>

{{-- Configuración --}}
<div class="bg-gray-900 rounded-xl border border-gray-800 p-5 mb-6">
    <h3 class="text-sm font-semibold text-white mb-4">Configuración de cobro</h3>
    <form method="POST" action="{{ route('superadmin.stamps.config', $company) }}" class="space-y-4">
        @csrf @method('PUT')
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div>
                <label class="{{ $lbl }}">Cortesía por periodo (timbres)</label>
                <input type="number" name="cortesia_mensual" min="0" required class="{{ $inp }}"
                       value="{{ old('cortesia_mensual', $cfg->cortesia_mensual ?? 100) }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Precio por timbre excedente</label>
                <input type="number" step="0.01" name="precio_timbre" min="0" required class="{{ $inp }}"
                       value="{{ old('precio_timbre', $cfg->precio_timbre ?? 0) }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Mensualidad</label>
                <input type="number" step="0.01" name="mensualidad" min="0" required class="{{ $inp }}"
                       value="{{ old('mensualidad', $cfg->mensualidad ?? 0) }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Día de corte (1-28)</label>
                <input type="number" name="dia_corte" min="1" max="28" required class="{{ $inp }}"
                       value="{{ old('dia_corte', $cfg->dia_corte ?? 1) }}">
                <div class="text-[11px] text-gray-600 mt-1">1 = mes calendario</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Cobrar desde</label>
                <input type="date" name="inicio_cobro" required class="{{ $inp }}"
                       value="{{ old('inicio_cobro', optional($cfg?->inicio_cobro)->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d')) }}">
                <div class="text-[11px] text-gray-600 mt-1">Cuenta el periodo que contiene esta fecha</div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            <div>
                <label class="{{ $lbl }}">Notas</label>
                <input type="text" name="notas" maxlength="500" class="{{ $inp }}" value="{{ old('notas', $cfg->notas ?? '') }}">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-300 pb-2">
                <input type="checkbox" name="activo" value="1" class="rounded" @checked(old('activo', $cfg->activo ?? true))>
                Cobro activo
            </label>
        </div>
        <div class="text-xs text-gray-500">
            Total del periodo = mensualidad + (timbres usados − cortesía) × precio. Las cancelaciones no se cobran
            aparte: el timbre ya se contó al emitirse; solo se muestran como informativo.
        </div>
        <div class="flex justify-end">
            <button class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Guardar</button>
        </div>
    </form>
</div>

{{-- Periodos --}}
@if($cfg)
<div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-800"><h3 class="text-sm font-semibold text-white">Periodos</h3></div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-800">
                @foreach(['Periodo' => 'left', 'Usados' => 'center', 'Cortesía' => 'center', 'Excedente' => 'center', 'Cancelados' => 'center', 'Total' => 'right', 'Estado' => 'center', '' => 'right'] as $h => $al)
                    <th class="px-4 py-3 text-{{ $al }} text-xs text-gray-500 uppercase tracking-wide">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-800">
            @foreach($periodos as $p)
            @php $d = $p['corte'] ?? (object) $p['calculo']; @endphp
            <tr>
                <td class="px-4 py-3 text-gray-300 text-xs">{{ $p['inicio']->format('d/m/Y') }} – {{ $p['fin']->format('d/m/Y') }}</td>
                <td class="px-4 py-3 text-center text-white">{{ $d->timbres_usados }}</td>
                <td class="px-4 py-3 text-center text-gray-400">{{ $d->cortesia }}</td>
                <td class="px-4 py-3 text-center {{ $d->excedente ? 'text-amber-400' : 'text-gray-600' }}">{{ $d->excedente }}</td>
                <td class="px-4 py-3 text-center text-gray-500">{{ $d->cancelados }}</td>
                <td class="px-4 py-3 text-right text-white">{{ $m($d->total) }}</td>
                <td class="px-4 py-3 text-center text-xs">
                    @if($p['corte'])
                        <span class="px-2 py-0.5 rounded-full {{ $p['corte']->estado === 'pagado' ? 'bg-emerald-900 text-emerald-300' : 'bg-amber-900/50 text-amber-300' }}">
                            {{ $p['corte']->estado === 'pagado' ? 'Pagado' : 'Pendiente de pago' }}
                        </span>
                    @elseif($p['abierto'])
                        <span class="px-2 py-0.5 rounded-full bg-indigo-900/60 text-indigo-300">Abierto</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full bg-gray-800 text-gray-400">Sin generar</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                        @if($p['corte'])
                            <a href="{{ route('superadmin.stamps.ticket', $p['corte']) }}" target="_blank"
                               class="text-xs px-2 py-1 rounded border border-gray-700 text-gray-300 hover:bg-gray-800">Ticket</a>
                            <form method="POST" action="{{ route('superadmin.stamps.pay', $p['corte']) }}">@csrf
                                <button class="text-xs px-2 py-1 rounded border border-emerald-800 text-emerald-400 hover:bg-emerald-900/30">
                                    {{ $p['corte']->estado === 'pagado' ? 'Quitar pago' : 'Marcar pagado' }}
                                </button>
                            </form>
                        @elseif($p['abierto'])
                            <a href="{{ route('superadmin.stamps.ticket_parcial', ['company' => $company, 'inicio' => $p['inicio']->toDateString()]) }}" target="_blank"
                               class="text-xs px-2 py-1 rounded border border-gray-700 text-gray-300 hover:bg-gray-800">Ticket parcial</a>
                        @else
                            <form method="POST" action="{{ route('superadmin.stamps.generate', $company) }}">@csrf
                                <input type="hidden" name="inicio" value="{{ $p['inicio']->toDateString() }}">
                                <button class="text-xs px-2 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-700">Generar corte y ticket</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
