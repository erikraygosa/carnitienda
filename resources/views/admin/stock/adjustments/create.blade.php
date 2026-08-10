<x-admin-layout
    title="Ajuste de inventario"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Stock','url'=>route('admin.stock.index')],
        ['name'=>'Ajuste'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="adj-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    @php
        $selWarehouse = (string) old('warehouse_id', request('warehouse_id'));
        $selProduct   = (string) old('product_id', request('product_id'));
        $selTipo      = (string) old('tipo', 'AJUSTE');
    @endphp

    <x-wire-card>
        <form id="adj-form" method="POST" action="{{ route('admin.stock.adjustments.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="space-y-2">
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Almacén</label>
                    <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="product_id" class="block text-sm font-medium text-gray-700">Producto</label>
                    <select name="product_id" id="product_id" class="w-full rounded-md border-gray-300" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ $selProduct===(string)$p->id ? 'selected' : '' }}>
                                {{ $p->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select name="tipo" id="tipo" class="w-full rounded-md border-gray-300" required>
                        <option value="IN" {{ $selTipo==='IN' ? 'selected' : '' }}>Entrada</option>
                        <option value="OUT" {{ $selTipo==='OUT' ? 'selected' : '' }}>Salida</option>
                        <option value="AJUSTE" {{ $selTipo==='AJUSTE' ? 'selected' : '' }}>Ajuste</option>
                    </select>
                </div>

                <div>
                    <x-wire-input label="Cantidad" name="cantidad" type="number" min="0.001" step="0.001" value="{{ old('cantidad',1) }}" required />
                </div>

                <div class="md:col-span-4">
                    <x-wire-input label="Motivo (opcional)" name="motivo" type="text" value="{{ old('motivo') }}" />
                </div>
            </div>
        </form>
    </x-wire-card>

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
.select2-container .select2-selection--single { height: 38px !important; border-color: #d1d5db !important; border-radius: 6px !important; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 0.875rem; color: #374151; padding-left: 10px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
.select2-container--disabled .select2-selection--single { background-color: #f9fafb !important; cursor: not-allowed; }
.select2-dropdown { border-color: #d1d5db; border-radius: 6px; font-size: 0.875rem; }
.select2-container--default .select2-search--dropdown .select2-search__field { border-color: #d1d5db; border-radius: 4px; padding: 4px 8px; }
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function () {
    $('#product_id').select2({
        placeholder: '-- seleccionar producto --',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#adj-form'),
        language: { searching: function() { return 'Buscando...'; }, noResults: function() { return 'Sin resultados'; } },
    });

    // Bug conocido de select2: al dar clic en la "x" de limpiar, el mismo clic
    // se propaga y vuelve a abrir el dropdown (se ve como "abre y cierra sin borrar").
    $(document).on('select2:unselecting', function(e) {
        $(e.target).data('unselecting', true);
    }).on('select2:opening', function(e) {
        if ($(e.target).data('unselecting')) {
            $(e.target).removeData('unselecting');
            e.preventDefault();
        }
    });
});
</script>
@endpush
</x-admin-layout>
