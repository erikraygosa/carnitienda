@extends('layouts.superadmin-layout')
@section('title', 'Editar CxC migrada')

@section('content')
<div class="mb-4">
    <a href="{{ route('superadmin.ar-migration.index') }}" class="text-xs text-gray-500 hover:text-gray-300">
        <i class="fa-solid fa-arrow-left mr-1"></i> CxC migradas
    </a>
</div>

@if($errors->any())
<div class="mb-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">
    @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

<div class="bg-gray-900 rounded-xl border border-gray-800 p-6 max-w-xl">
    <h2 class="text-white font-semibold mb-1">Editar — {{ $order->folio }}</h2>
    <p class="text-xs text-gray-500 mb-4">
        Solo se puede editar mientras no se le haya aplicado ningún cobro (saldo = total original).
    </p>

    <form action="{{ route('superadmin.ar-migration.update', $order) }}" method="POST" class="space-y-3">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-xs text-gray-500 mb-1">Cliente</label>
            <select name="client_id" id="client_id_migracion" required class="w-full">
                @foreach($clientes as $c)
                    <option value="{{ $c->id }}" {{ old('client_id', $order->client_id) == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Folio</label>
                <input type="text" value="{{ $order->folio }}" readonly
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-400 cursor-not-allowed">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Fecha</label>
                <input type="date" name="fecha" required value="{{ old('fecha', \Carbon\Carbon::parse($order->fecha)->format('Y-m-d')) }}"
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
                <input type="number" step="0.01" min="0.01" name="total" required value="{{ old('total', $order->total) }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Saldo pendiente</label>
                <input type="number" step="0.01" min="0" name="saldo_pendiente" required value="{{ old('saldo_pendiente', $order->saldo_pendiente) }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Comentario (opcional)</label>
            <input type="text" name="comentario" maxlength="255" value="{{ old('comentario') }}"
                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('superadmin.ar-migration.index') }}" class="px-4 py-2 text-sm text-gray-400 hover:text-white">Cancelar</a>
            <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-medium">
                Guardar cambios
            </button>
        </div>
    </form>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
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
        width: '100%',
        language: { searching: function () { return 'Buscando...'; }, noResults: function () { return 'Sin resultados'; } },
    });
});
</script>
@endsection
