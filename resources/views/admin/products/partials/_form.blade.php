@php
    $categories = $categories
        ?? \App\Models\Category::query()->orderBy('nombre')->get(['id','nombre']);

    $isEdit = isset($product) && $product;

    $unidades = [
        ['id' => 'PZA', 'name' => 'Pieza (PZA)'],
        ['id' => 'KG',  'name' => 'Kilogramo (KG)'],
        ['id' => 'G',   'name' => 'Gramo (G)'],
        ['id' => 'L',   'name' => 'Litro (L)'],
        ['id' => 'ML',  'name' => 'Mililitro (ML)'],
    ];

    $objetoImpOpts = [
        ['id' => '01', 'name' => '01 – No objeto de impuesto'],
        ['id' => '02', 'name' => '02 – Sí objeto de impuesto'],
        ['id' => '03', 'name' => '03 – Sí objeto, no desglosado'],
    ];
    $tipoFactorOpts = [
        ['id' => 'Tasa',   'name' => 'Tasa'],
        ['id' => 'Exento', 'name' => 'Exento'],
        ['id' => 'Cuota',  'name' => 'Cuota'],
    ];
@endphp

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

{{-- ====== DATOS GENERALES ====== --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">

        <x-wire-input
            name="nombre" label="Nombre"
            placeholder="Ej. Pierna de cerdo (entera)"
            :value="old('nombre', $isEdit ? $product->nombre : '')"
            required
        />

        <x-wire-input
            name="sku" label="SKU"
            placeholder="Ej. CAR-PIER-ENT"
            :value="old('sku', $isEdit ? $product->sku : '')"
        />

        <x-wire-input
            name="barcode" label="Código de barras"
            placeholder="EAN/UPC"
            :value="old('barcode', $isEdit ? $product->barcode : '')"
        />

        {{-- Unidad --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Unidad <span class="text-red-500">*</span>
            </label>
            <select name="unidad"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                @foreach($unidades as $u)
                    <option value="{{ $u['id'] }}"
                        {{ old('unidad', $isEdit ? $product->unidad : 'PZA') === $u['id'] ? 'selected' : '' }}>
                        {{ $u['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Categoría --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Categoría <span class="text-red-500">*</span>
            </label>
            <select name="category_id"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">-- Seleccione una categoría --</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}"
                        {{ old('category_id', $isEdit ? $product->category_id : null) == $cat->id ? 'selected' : '' }}>
                        {{ $cat->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <x-wire-input
            type="number" step="0.0001" min="0"
            name="precio_base" label="Precio base"
            placeholder="0.0000"
            :value="old('precio_base', $isEdit ? $product->precio_base : 0)"
            required
        />

        <x-wire-input
            type="number" step="0.0001" min="0"
            name="costo_promedio" label="Costo promedio"
            placeholder="0.0000"
            :value="old('costo_promedio', $isEdit ? $product->costo_promedio : 0)"
            required
        />

        <x-wire-input
            type="number" step="0.01" min="0" max="100"
            name="tasa_iva" label="Tasa IVA (%)"
            placeholder="0.00"
            :value="old('tasa_iva', $isEdit ? $product->tasa_iva : 0)"
            required
        />

        <x-wire-input
            type="number" step="0.001" min="0"
            name="stock_min" label="Stock mínimo"
            placeholder="0.000"
            :value="old('stock_min', $isEdit ? $product->stock_min : 0)"
            required
        />
    </div>

    <div class="space-y-6">
        {{-- Toggles --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <input id="es_compuesto" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    {{ old('es_compuesto', (int)($isEdit ? $product->es_compuesto : 0)) ? 'checked' : '' }}>
                <label for="es_compuesto" class="text-sm text-gray-700">¿Es producto compuesto?</label>
                <input type="hidden" name="es_compuesto" id="es_compuesto_hidden"
                    value="{{ old('es_compuesto', $isEdit ? (int)$product->es_compuesto : 0) }}">
            </div>

            <div class="flex items-center gap-2">
                <input id="es_subproducto" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    {{ old('es_subproducto', (int)($isEdit ? $product->es_subproducto : 0)) ? 'checked' : '' }}>
                <label for="es_subproducto" class="text-sm text-gray-700">¿Es subproducto?</label>
                <input type="hidden" name="es_subproducto" id="es_subproducto_hidden"
                    value="{{ old('es_subproducto', $isEdit ? (int)$product->es_subproducto : 0) }}">
            </div>

            <div class="flex items-center gap-2">
                <input id="maneja_inventario" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    {{ old('maneja_inventario', (int)($isEdit ? $product->maneja_inventario : 1)) ? 'checked' : '' }}>
                <label for="maneja_inventario" class="text-sm text-gray-700">Maneja inventario</label>
                <input type="hidden" name="maneja_inventario" id="maneja_inventario_hidden"
                    value="{{ old('maneja_inventario', $isEdit ? (int)$product->maneja_inventario : 1) }}">
            </div>

            <div class="flex items-center gap-2">
                <input id="activo_toggle" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    {{ old('activo', (int)($isEdit ? $product->activo : 1)) ? 'checked' : '' }}>
                <label for="activo_toggle" class="text-sm text-gray-700">Activo</label>
                <input type="hidden" name="activo" id="activo_hidden"
                    value="{{ old('activo', $isEdit ? (int)$product->activo : 1) }}">
            </div>
        </div>

        <x-wire-textarea
            name="notas" label="Notas"
            placeholder="Notas internas del producto"
            rows="6"
        >{{ old('notas', $isEdit ? $product->notas : '') }}</x-wire-textarea>
    </div>
</div>

{{-- ====== DATOS SAT / CFDI 4.0 ====== --}}
<div class="mt-8 border-t pt-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">SAT</span>
        Datos para facturación CFDI 4.0
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Clave SAT <span class="text-red-500">*</span>
            </label>
            <input type="text" name="sat_clave_prod_serv"
                   placeholder="Ej. 50202306" maxlength="20"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   value="{{ old('sat_clave_prod_serv', $isEdit ? $product->sat_clave_prod_serv : '') }}">
            <p class="mt-1 text-xs text-gray-400">c_ClaveProdServ del catálogo SAT</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Clave unidad SAT <span class="text-red-500">*</span>
            </label>
            <input type="text" name="sat_clave_unidad"
                   placeholder="Ej. KGM, H87, LTR" maxlength="10"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm uppercase"
                   value="{{ old('sat_clave_unidad', $isEdit ? $product->sat_clave_unidad : '') }}">
            <p class="mt-1 text-xs text-gray-400">c_ClaveUnidad — KGM=kg · H87=pieza · LTR=litro</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">NoIdentificacion</label>
            <input type="text" name="sat_no_identificacion"
                   placeholder="Dejar vacío = usa SKU" maxlength="100"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   value="{{ old('sat_no_identificacion', $isEdit ? $product->sat_no_identificacion : '') }}">
            <p class="mt-1 text-xs text-gray-400">Si se deja vacío se usa el SKU o ID del producto</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Objeto de impuesto</label>
            <select name="sat_objeto_imp"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                @foreach($objetoImpOpts as $opt)
                    <option value="{{ $opt['id'] }}"
                        {{ old('sat_objeto_imp', $isEdit ? ($product->sat_objeto_imp ?? '02') : '02') === $opt['id'] ? 'selected' : '' }}>
                        {{ $opt['name'] }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-400">c_ObjetoImp — casi siempre "02"</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de factor IVA</label>
            <select name="sat_tipo_factor" id="sat_tipo_factor"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                @foreach($tipoFactorOpts as $opt)
                    <option value="{{ $opt['id'] }}"
                        {{ old('sat_tipo_factor', $isEdit ? ($product->sat_tipo_factor ?? 'Tasa') : 'Tasa') === $opt['id'] ? 'selected' : '' }}>
                        {{ $opt['name'] }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-400">c_TipoFactor — "Tasa" para IVA normal</p>
        </div>

        {{-- Tasa IVA decimal — oculto si Exento --}}
        <div id="sat-tasa-wrap">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Tasa IVA CFDI
                <span class="text-xs text-gray-400 font-normal">(decimal, ej. 0.160000)</span>
            </label>
            <input type="number" name="sat_tasa_iva"
                   step="0.000001" min="0" max="1"
                   placeholder="0.160000"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   value="{{ old('sat_tasa_iva', $isEdit ? $product->sat_tasa_iva : '') }}">
            <p class="mt-1 text-xs text-gray-400">Dejar vacío = se calcula de Tasa IVA % automáticamente</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Tasa IEPS CFDI
                <span class="text-xs text-gray-400 font-normal">(decimal, opcional)</span>
            </label>
            <input type="number" name="sat_tasa_ieps"
                   step="0.000001" min="0" max="1"
                   placeholder="Ej. 0.265000 — dejar vacío si no aplica"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   value="{{ old('sat_tasa_ieps', $isEdit ? $product->sat_tasa_ieps : '') }}">
            <p class="mt-1 text-xs text-gray-400">Solo para alcohol, tabaco, gasolina, etc.</p>
        </div>

    </div>
</div>

{{-- ====== SCRIPTS ====== --}}
<script>
document.addEventListener('DOMContentLoaded', () => {

    // Sincroniza checkbox → hidden input
    const bindToggle = (chkId, hidId) => {
        const c = document.getElementById(chkId);
        const h = document.getElementById(hidId);
        if (!c || !h) return;
        const sync = () => h.value = c.checked ? 1 : 0;
        c.addEventListener('change', sync);
        sync();
    };
    bindToggle('es_compuesto',     'es_compuesto_hidden');
    bindToggle('es_subproducto',   'es_subproducto_hidden');
    bindToggle('maneja_inventario','maneja_inventario_hidden');
    bindToggle('activo_toggle',    'activo_hidden');

    // Exclusivos: compuesto y subproducto no pueden ser ambos true
    const a  = document.getElementById('es_compuesto');
    const b  = document.getElementById('es_subproducto');
    const ah = document.getElementById('es_compuesto_hidden');
    const bh = document.getElementById('es_subproducto_hidden');
    if (a && b && ah && bh) {
        a.addEventListener('change', () => { if (a.checked) { b.checked = false; bh.value = 0; } });
        b.addEventListener('change', () => { if (b.checked) { a.checked = false; ah.value = 0; } });
    }

    // Tipo factor → mostrar/ocultar tasa IVA CFDI
    const tipoFactor  = document.getElementById('sat_tipo_factor');
    const tasaWrap    = document.getElementById('sat-tasa-wrap');
    const toggleTasa  = () => {
        if (tipoFactor.value === 'Exento') {
            tasaWrap.style.display = 'none';
        } else {
            tasaWrap.style.display = '';
        }
    };
    if (tipoFactor && tasaWrap) {
        tipoFactor.addEventListener('change', toggleTasa);
        toggleTasa(); // estado inicial
    }

    // Clave unidad SAT → forzar mayúsculas
    const claveUnidad = document.querySelector('[name="sat_clave_unidad"]');
    if (claveUnidad) {
        claveUnidad.addEventListener('input', () => {
            const pos = claveUnidad.selectionStart;
            claveUnidad.value = claveUnidad.value.toUpperCase();
            claveUnidad.setSelectionRange(pos, pos);
        });
    }

    // Clave prod/serv → solo dígitos
    const claveProd = document.querySelector('[name="sat_clave_prod_serv"]');
    if (claveProd) {
        claveProd.addEventListener('input', () => {
            claveProd.value = claveProd.value.replace(/\D/g, '');
        });
    }
});
</script>