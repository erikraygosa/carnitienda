@php
    // Cargar categorías si no vinieron del controlador
    $categories = $categories
        ?? \App\Models\Category::query()->orderBy('nombre')->get(['id','nombre']);

    $categoryOptions = $categories->map(fn($c) => ['id' => $c->id, 'name' => $c->nombre])->toArray();

    $isEdit = isset($product) && $product;

    $unidades = [
        ['id' => 'PZA', 'name' => 'Pieza (PZA)'],
        ['id' => 'KG',  'name' => 'Kilogramo (KG)'],
        ['id' => 'G',   'name' => 'Gramo (G)'],
        ['id' => 'L',   'name' => 'Litro (L)'],
        ['id' => 'ML',  'name' => 'Mililitro (ML)'],
    ];
@endphp

{{-- Errores de validación --}}
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
        <strong class="block mb-2">Revisa los siguientes errores:</strong>
        <ul class="list-disc ml-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Nombre (requerido) --}}
        <x-wire-input
            name="nombre"
            label="Nombre"
            placeholder="Ej. Pierna de cerdo (entera)"
            :value="old('nombre', $isEdit ? $product->nombre : '')"
            required
        />

        {{-- SKU (opcional) --}}
        <x-wire-input
            name="sku"
            label="SKU"
            placeholder="Ej. CAR-PIER-ENT"
            :value="old('sku', $isEdit ? $product->sku : '')"
        />

        {{-- Código de barras (opcional) --}}
        <x-wire-input
            name="barcode"
            label="Código de barras"
            placeholder="EAN/UPC"
            :value="old('barcode', $isEdit ? $product->barcode : '')"
        />

        {{-- Unidad (requerido) --}}
        <x-wire-select
            name="unidad"
            label="Unidad"
            :options="$unidades"
            :option-label="'name'"
            :option-value="'id'"
            placeholder="Seleccione unidad"
            :selected="old('unidad', $isEdit ? $product->unidad : 'PZA')"
            required
        />

        {{-- Categoría (requerido) --}}
        <x-wire-select
            name="category_id"
            label="Categoría"
            :options="$categoryOptions"
            :option-label="'name'"
            :option-value="'id'"
            placeholder="Seleccione una categoría"
            :selected="old('category_id', $isEdit ? $product->category_id : null)"
            required
            searchable
        />

        {{-- Precio base (requerido) --}}
        <x-wire-input
            type="number"
            step="0.0001"
            min="0"
            name="precio_base"
            label="Precio base"
            placeholder="0.0000"
            :value="old('precio_base', $isEdit ? $product->precio_base : 0)"
            required
        />

        {{-- Costo promedio (requerido) --}}
        <x-wire-input
            type="number"
            step="0.0001"
            min="0"
            name="costo_promedio"
            label="Costo promedio"
            placeholder="0.0000"
            :value="old('costo_promedio', $isEdit ? $product->costo_promedio : 0)"
            required
        />

        {{-- Tasa IVA % (requerido) --}}
        <x-wire-input
            type="number"
            step="0.01"
            min="0"
            max="100"
            name="tasa_iva"
            label="Tasa IVA (%)"
            placeholder="0.00"
            :value="old('tasa_iva', $isEdit ? $product->tasa_iva : 0)"
            required
        />

        {{-- Stock mínimo (requerido) --}}
        <x-wire-input
            type="number"
            step="0.001"
            min="0"
            name="stock_min"
            label="Stock mínimo"
            placeholder="0.000"
            :value="old('stock_min', $isEdit ? $product->stock_min : 0)"
            required
        />
    </div>

    <div class="space-y-6">
        {{-- Toggles / flags --}}
        <div class="space-y-3">
            {{-- Es compuesto --}}
            <div class="flex items-center gap-2">
                <input id="es_compuesto" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    {{ old('es_compuesto', (int)($isEdit ? $product->es_compuesto : 0)) ? 'checked' : '' }}>
                <label for="es_compuesto" class="text-sm text-gray-700">¿Es producto compuesto?</label>
                <input type="hidden" name="es_compuesto" id="es_compuesto_hidden"
                    value="{{ old('es_compuesto', $isEdit ? (int)$product->es_compuesto : 0) }}">
            </div>

            {{-- Es subproducto --}}
            <div class="flex items-center gap-2">
                <input id="es_subproducto" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    {{ old('es_subproducto', (int)($isEdit ? $product->es_subproducto : 0)) ? 'checked' : '' }}>
                <label for="es_subproducto" class="text-sm text-gray-700">¿Es subproducto?</label>
                <input type="hidden" name="es_subproducto" id="es_subproducto_hidden"
                    value="{{ old('es_subproducto', $isEdit ? (int)$product->es_subproducto : 0) }}">
            </div>

            {{-- Maneja inventario --}}
            <div class="flex items-center gap-2">
                <input id="maneja_inventario" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    {{ old('maneja_inventario', (int)($isEdit ? $product->maneja_inventario : 1)) ? 'checked' : '' }}>
                <label for="maneja_inventario" class="text-sm text-gray-700">Maneja inventario</label>
                <input type="hidden" name="maneja_inventario" id="maneja_inventario_hidden"
                    value="{{ old('maneja_inventario', $isEdit ? (int)$product->maneja_inventario : 1) }}">
            </div>

            {{-- Activo --}}
            <div class="flex items-center gap-2">
                <input id="activo_toggle" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    {{ old('activo', (int)($isEdit ? $product->activo : 1)) ? 'checked' : '' }}>
                <label for="activo_toggle" class="text-sm text-gray-700">Activo</label>
                <input type="hidden" name="activo" id="activo_hidden"
                    value="{{ old('activo', $isEdit ? (int)$product->activo : 1) }}">
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const bindToggle = (chkId, hidId) => {
                    const c = document.getElementById(chkId);
                    const h = document.getElementById(hidId);
                    if (!c || !h) return;
                    const sync = () => h.value = c.checked ? 1 : 0;
                    c.addEventListener('change', sync);
                    sync();
                };
                bindToggle('es_compuesto', 'es_compuesto_hidden');
                bindToggle('es_subproducto', 'es_subproducto_hidden');
                bindToggle('maneja_inventario', 'maneja_inventario_hidden');
                bindToggle('activo_toggle', 'activo_hidden');
            });
        </script>

        {{-- Notas (opcional) --}}
        <x-wire-textarea
            name="notas"
            label="Notas"
            placeholder="Notas internas del producto"
            rows="6"
        >{{ old('notas', $isEdit ? $product->notas : '') }}</x-wire-textarea>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const linkExclusive = (aId, aHiddenId, bId, bHiddenId) => {
        const a = document.getElementById(aId);
        const b = document.getElementById(bId);
        const ah = document.getElementById(aHiddenId);
        const bh = document.getElementById(bHiddenId);
        if (!a || !b || !ah || !bh) return;

        const syncHidden = () => {
            ah.value = a.checked ? 1 : 0;
            bh.value = b.checked ? 1 : 0;
        };

        a.addEventListener('change', () => {
            if (a.checked) { b.checked = false; }
            syncHidden();
        });
        b.addEventListener('change', () => {
            if (b.checked) { a.checked = false; }
            syncHidden();
        });

        syncHidden();
    };

    linkExclusive('es_compuesto', 'es_compuesto_hidden', 'es_subproducto', 'es_subproducto_hidden');
});
</script>
