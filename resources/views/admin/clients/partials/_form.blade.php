@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $isEdit = isset($client) && $client;

    /** Devuelve opciones [id => label] detectando columnas disponibles */
    $buildOptionsKV = function (string $table, array $candidates = ['nombre','name','descripcion','codigo','titulo']) {
        $cols = Schema::getColumnListing($table);
        $labelCols = array_values(array_intersect($candidates, $cols));

        $q = DB::table($table)->select('id');
        foreach ($labelCols as $c) { $q->addSelect($c); }
        $rows = $q->orderBy('id')->get();

        $out = [];
        foreach ($rows as $r) {
            $label = null;
            foreach ($labelCols as $c) {
                if (isset($r->$c) && $r->$c !== '') { $label = $r->$c; break; }
            }
            $out[$r->id] = $label ?? ('ID '.$r->id);
        }
        return $out;
    };

    $routes = $buildOptionsKV('shipping_routes');   // [id => label]
    $pays   = $buildOptionsKV('payment_types');     // [id => label]
    $lists  = $buildOptionsKV('price_lists');       // [id => label]

    // Mapa: payment_type_id => esCredito (true/false) por pistas en columnas
    $payCols = Schema::getColumnListing('payment_types');
    $payRows = DB::table('payment_types')->select(array_merge(['id'], $payCols))->get();
    $creditHints = ['crédito','credito','credit','plazo','30d','net','contado diferido'];
    $isCreditById = [];
    foreach ($payRows as $row) {
        $flag = false;
        foreach ($payCols as $col) {
            if ($col === 'id') continue;
            $val = strtolower((string)($row->$col ?? ''));
            foreach ($creditHints as $h) { if ($val !== '' && str_contains($val, $h)) { $flag = true; break 2; } }
        }
        $isCreditById[$row->id] = $flag;
    }
@endphp

{{-- Errores --}}
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
        <strong class="block mb-2">Revisa los siguientes errores:</strong>
        <ul class="list-disc ml-5">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Generales --}}
        <x-wire-input name="nombre" label="Nombre" required
            placeholder="Ej. Carnes Don Pepe"
            :value="old('nombre', $isEdit ? $client->nombre : '')" />

        <x-wire-input name="email" label="Email"
            placeholder="correo@dominio.com"
            :value="old('email', $isEdit ? $client->email : '')" />

        <x-wire-input name="telefono" label="Teléfono"
            placeholder="(XXX) XXX-XXXX"
            :value="old('telefono', $isEdit ? $client->telefono : '')" />

        <x-wire-input name="direccion" label="Dirección"
            placeholder="Calle, número, colonia, ciudad"
            :value="old('direccion', $isEdit ? $client->direccion : '')" />

        {{-- Tipo de persona (PF/PM) - SELECT NATIVO --}}
        <div class="space-y-2 w-full">
            <label for="tipo_persona" class="block text-sm font-medium text-gray-700">Tipo de persona</label>
            @php $tipo = old('tipo_persona', $isEdit ? $client->tipo_persona : 'PF'); @endphp
            <select
                name="tipo_persona"
                id="tipo_persona"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
            >
                <option value="PF" {{ $tipo === 'PF' ? 'selected' : '' }}>Física</option>
                <option value="PM" {{ $tipo === 'PM' ? 'selected' : '' }}>Moral</option>
            </select>
        </div>

        <x-wire-input name="rfc" label="RFC"
            placeholder="Ej. ABCD010203XYZ"
            :value="old('rfc', $isEdit ? $client->rfc : '')"
            data-fiscal="any" id="rfc" />

        <x-wire-input name="razon_social" label="Razón social"
            :value="old('razon_social', $isEdit ? $client->razon_social : '')"
            data-fiscal="moral" id="razon_social" />

        <x-wire-input name="nombre_comercial" label="Nombre comercial"
            :value="old('nombre_comercial', $isEdit ? $client->nombre_comercial : '')"
            data-fiscal="any" />

        <x-wire-input name="regimen_fiscal" label="Régimen fiscal (código SAT)"
            placeholder="Ej. 601"
            :value="old('regimen_fiscal', $isEdit ? $client->regimen_fiscal : '')"
            data-fiscal="moral" id="regimen_fiscal" />

        <x-wire-input name="uso_cfdi_default" label="Uso CFDI (código SAT)"
            placeholder="Ej. G01"
            :value="old('uso_cfdi_default', $isEdit ? $client->uso_cfdi_default : '')"
            data-fiscal="any" id="uso_cfdi_default" />

        {{-- Ruta de reparto (SELECT NATIVO) --}}
        <div class="space-y-2 w-full">
            <label for="shipping_route_id" class="block text-sm font-medium text-gray-700">Ruta de reparto</label>
            @php $selRoute = old('shipping_route_id', $isEdit ? $client->shipping_route_id : ''); @endphp
            <select
                name="shipping_route_id"
                id="shipping_route_id"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">Seleccione ruta</option>
                @foreach ($routes as $id => $label)
                    <option value="{{ $id }}" {{ (string)$selRoute === (string)$id ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Tipo de pago (SELECT NATIVO) --}}
        <div class="space-y-2 w-full">
            <label for="payment_type_id" class="block text-sm font-medium text-gray-700">Tipo de pago (predeterminado)</label>
            @php $selPay = old('payment_type_id', $isEdit ? $client->payment_type_id : ''); @endphp
            <select
                name="payment_type_id"
                id="payment_type_id"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">Seleccione tipo</option>
                @foreach ($pays as $id => $label)
                    <option value="{{ $id }}" {{ (string)$selPay === (string)$id ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Lista de precios (SELECT NATIVO) --}}
        <div class="space-y-2 w-full">
            <label for="price_list_id" class="block text-sm font-medium text-gray-700">Lista de precios del cliente</label>
            @php $selList = old('price_list_id', $isEdit ? $client->price_list_id : ''); @endphp
            <select
                name="price_list_id"
                id="price_list_id"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">Seleccione lista</option>
                @foreach ($lists as $id => $label)
                    <option value="{{ $id }}" {{ (string)$selList === (string)$id ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Crédito (se activa si el tipo de pago parece crédito) --}}
        <x-wire-input type="number" step="0.01" min="0" name="credito_limite" label="Crédito límite"
            placeholder="0.00"
            :value="old('credito_limite', $isEdit ? $client->credito_limite : 0)"
            data-credito="field" id="credito_limite" />

        <x-wire-input type="number" step="1" min="0" name="credito_dias" label="Crédito días"
            placeholder="0"
            :value="old('credito_dias', $isEdit ? $client->credito_dias : 0)"
            data-credito="field" id="credito_dias" />
    </div>

    <div class="space-y-6">
        {{-- Activo --}}
        <div class="flex items-center gap-2">
            <input id="activo_toggle" type="checkbox"
                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                   {{ old('activo', (int)($isEdit ? $client->activo : 1)) ? 'checked' : '' }}>
            <label for="activo_toggle" class="text-sm text-gray-700">Activo</label>
            <input type="hidden" name="activo" id="activo_hidden"
                   value="{{ old('activo', $isEdit ? (int)$client->activo : 1) }}">
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    function getValueByName(name) {
        const el = document.querySelector(`[name="${name}"]`);
        return el ? (el.value ?? null) : null;
    }

    function toggleFiscal() {
        // PF = Física, PM = Moral
        const v = (getValueByName('tipo_persona') || 'PF').toUpperCase();
        document.querySelectorAll('[data-fiscal]').forEach(el => {
            const t = el.getAttribute('data-fiscal'); // any | moral
            const mustEnable = (t === 'any') || (t === 'moral' && v === 'PM');
            const wrapper = el.closest('.space-y-2, .w-full, .col-span-1, .grid, .form-control') || el.parentElement;
            if (wrapper) wrapper.classList.toggle('opacity-50', !mustEnable);
            el.toggleAttribute('disabled', !mustEnable);
        });
    }

    function detectEsCredito() {
        const isCreditMap = @json($isCreditById ?? []);
        const payEl = document.querySelector('[name="payment_type_id"]');
        const selId = payEl ? String(payEl.value || '') : '';
        const isCredit = selId !== '' && (String(isCreditMap[selId]) === '1' || isCreditMap[selId] === true);

        document.querySelectorAll('[data-credito="field"]').forEach(el => {
            const wrapper = el.closest('.space-y-2, .w-full, .col-span-1, .grid, .form-control') || el.parentElement;
            if (wrapper) wrapper.classList.toggle('opacity-50', !isCredit);
            el.toggleAttribute('disabled', !isCredit);
            if (!isCredit) { el.value = 0; }
        });
    }

    function bindHandlers() {
        document.addEventListener('change', (e) => {
            const name = e.target?.getAttribute?.('name');
            if (name === 'tipo_persona') toggleFiscal();
            if (name === 'payment_type_id') detectEsCredito();
        });
    }

    function initActivoHidden() {
        const t = document.getElementById('activo_toggle');
        const h = document.getElementById('activo_hidden');
        if (t && h) {
            const sync = () => h.value = t.checked ? 1 : 0;
            t.addEventListener('change', sync); sync();
        }
    }

    function applyAll() { toggleFiscal(); detectEsCredito(); initActivoHidden(); }

    document.addEventListener('DOMContentLoaded', () => { bindHandlers(); applyAll(); });
    document.addEventListener('livewire:load', applyAll);
    document.addEventListener('livewire:navigated', applyAll);
    document.addEventListener('livewire:update', applyAll);
})();
</script>
@endpush
