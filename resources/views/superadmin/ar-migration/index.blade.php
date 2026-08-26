@extends('layouts.superadmin-layout')
@section('title', 'CxC migradas')

@section('content')

@if(session('resultado_importacion_cxc'))
    @php $r = session('resultado_importacion_cxc'); @endphp
    <div class="mb-6 rounded-xl border border-emerald-700 bg-emerald-900/20 p-5">
        <h3 class="text-emerald-300 font-semibold mb-2">
            <i class="fa-solid fa-circle-check mr-1"></i> Importación terminada
        </h3>
        <div class="text-sm text-gray-300">✅ {{ $r['creadas'] }} cuenta(s) por cobrar creada(s)</div>
        @if(count($r['errores']))
            <div class="text-red-400 mt-2">❌ {{ count($r['errores']) }} fila(s) con error:</div>
            <ul class="list-disc list-inside text-xs text-red-400/80 pl-2">
                @foreach(array_slice($r['errores'], 0, 30) as $msg)
                    <li>{{ $msg }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if($errors->any())
<div class="mb-6 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">
    @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    {{-- Alta directa --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h2 class="text-white font-semibold mb-1">Agregar CxC migrada</h2>
        <p class="text-xs text-gray-500 mb-4">
            Captura directa de un saldo pendiente de otro sistema, sin pasar por todo el flujo de
            pedido/surtido/despacho. Queda como un pedido a crédito ya "entregado" — aparece en
            Cobranza General y se puede asignar a un despacho/chofer como cualquier otro.
        </p>
        <form action="{{ route('superadmin.ar-migration.store') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Cliente</label>
                <select name="client_id" id="client_id_migracion" required
                        class="w-full">
                    <option value="">— selecciona —</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Folio (serie exclusiva de migración)</label>
                    <input type="text" value="{{ $siguienteFolio }}" readonly
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-400 cursor-not-allowed">
                    <p class="text-xs text-gray-600 mt-1">Se asigna automático, en su propia serie (MIG-####), para no chocar con folios reales.</p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Fecha</label>
                    <input type="date" name="fecha" required value="{{ old('fecha', now()->format('Y-m-d')) }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Folio/Referencia del sistema anterior (opcional)</label>
                <input type="text" name="referencia" maxlength="60" value="{{ old('referencia') }}"
                       placeholder="ej: FACT-00123"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Total original</label>
                    <input type="number" step="0.01" min="0.01" name="total" required value="{{ old('total') }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Saldo pendiente</label>
                    <input type="number" step="0.01" min="0" name="saldo_pendiente" required value="{{ old('saldo_pendiente') }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Comentario (opcional)</label>
                <input type="text" name="comentario" maxlength="255" value="{{ old('comentario') }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-medium">
                Agregar CxC
            </button>
        </form>
    </div>

    {{-- Importación masiva --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h2 class="text-white font-semibold mb-1">Importar varias de golpe</h2>
        <p class="text-xs text-gray-500 mb-4">
            Si son muchos saldos pendientes del sistema anterior, descarga la plantilla, llénala y
            súbela — se crean todas de una vez con la misma lógica de arriba.
        </p>
        <a href="{{ route('superadmin.ar-migration.plantilla') }}"
           class="inline-flex items-center gap-2 mb-4 px-4 py-2 text-sm rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">
            <i class="fa-solid fa-file-arrow-down"></i> Descargar plantilla (.xlsx)
        </a>
        <form action="{{ route('superadmin.ar-migration.importar') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-indigo-600 file:text-white file:text-xs hover:file:bg-indigo-700 focus:border-indigo-500 focus:outline-none">
            <p class="text-xs text-gray-500">
                El cliente se busca por <strong>nombre exacto</strong> (sin distinguir mayúsculas) — debe coincidir
                con el nombre ya registrado en el sistema. El folio de cada fila se asigna automático en la
                serie MIG-#### (el folio/columna del sistema anterior se guarda solo como referencia).
            </p>
            <button type="submit" class="w-full px-4 py-2.5 text-sm rounded-lg bg-gray-700 text-white hover:bg-gray-800 font-medium">
                Subir e importar
            </button>
        </form>
    </div>
</div>

{{-- Listado de migradas --}}
<div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-white">CxC migradas registradas</h2>
        <span class="text-xs text-gray-500">Saldo total pendiente: <strong class="text-amber-400">${{ number_format($totalMigrado, 2) }}</strong></span>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-800">
                <th class="px-5 py-3 text-left text-xs text-gray-500 uppercase tracking-wide">Cliente</th>
                <th class="px-5 py-3 text-left text-xs text-gray-500 uppercase tracking-wide">Folio</th>
                <th class="px-5 py-3 text-left text-xs text-gray-500 uppercase tracking-wide">Fecha</th>
                <th class="px-5 py-3 text-right text-xs text-gray-500 uppercase tracking-wide">Total</th>
                <th class="px-5 py-3 text-right text-xs text-gray-500 uppercase tracking-wide">Saldo</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-800">
            @forelse($migradas as $orden)
            <tr>
                <td class="px-5 py-3 text-white">{{ $orden->client?->nombre ?? '—' }}</td>
                <td class="px-5 py-3 font-mono text-gray-400 text-xs">{{ $orden->folio }}</td>
                <td class="px-5 py-3 text-gray-400">{{ \Carbon\Carbon::parse($orden->fecha)->format('d/m/Y') }}</td>
                <td class="px-5 py-3 text-right text-gray-300">${{ number_format($orden->total, 2) }}</td>
                <td class="px-5 py-3 text-right {{ (float)$orden->saldo_pendiente > 0 ? 'text-amber-400' : 'text-emerald-400' }}">
                    ${{ number_format($orden->saldo_pendiente, 2) }}
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500 text-sm">Aún no hay CxC migradas.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($migradas->hasPages())
    <div class="px-5 py-3 border-t border-gray-800">
        {{ $migradas->links() }}
    </div>
    @endif
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
/* Select2 con la paleta oscura del panel de SuperAdmin */
.select2-container .select2-selection--single {
    height: 38px !important; background: #1f2937 !important; border-color: #374151 !important; border-radius: 8px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 36px !important; font-size: 0.875rem; color: #fff; padding-left: 10px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
.select2-dropdown { background: #1f2937; border-color: #374151; border-radius: 8px; font-size: 0.875rem; }
.select2-container--default .select2-results__option { color: #e5e7eb; }
.select2-container--default .select2-results__option--highlighted[aria-selected] { background: #4f46e5 !important; }
.select2-search--dropdown { background: #1f2937; }
.select2-container--default .select2-search--dropdown .select2-search__field {
    background: #111827; border-color: #374151; color: #fff; border-radius: 4px; padding: 4px 8px;
}
.select2-container--default .select2-results { background: #1f2937; }
</style>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function () {
    $('#client_id_migracion').select2({
        placeholder: '-- seleccionar cliente --',
        allowClear: true,
        width: '100%',
        language: { searching: function () { return 'Buscando...'; }, noResults: function () { return 'Sin resultados'; } },
    });
});
</script>
@endsection
