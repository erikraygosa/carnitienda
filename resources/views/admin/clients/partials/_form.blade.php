@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $isEdit = isset($client) && $client;

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

    $routes = $buildOptionsKV('shipping_routes');
    $pays   = $buildOptionsKV('payment_types');
    $lists  = $buildOptionsKV('price_lists');

    $payCols = Schema::getColumnListing('payment_types');
    $payRows = DB::table('payment_types')->select(array_merge(['id'], $payCols))->get();
    $creditHints = ['crédito','credito','credit','plazo','30d','net','contado diferido'];
    $isCreditById = [];
    foreach ($payRows as $row) {
        $flag = false;
        foreach ($payCols as $col) {
            if ($col === 'id') continue;
            $val = strtolower((string)($row->$col ?? ''));
            foreach ($creditHints as $h) {
                if ($val !== '' && str_contains($val, $h)) { $flag = true; break 2; }
            }
        }
        $isCreditById[$row->id] = $flag;
    }

    $igualFiscal = old('entrega_igual_fiscal', $isEdit ? ($client->entrega_igual_fiscal ?? false) : false);
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

{{-- ====== DATOS GENERALES ====== --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">

        <x-wire-input name="nombre" label="Nombre" required
            placeholder="Ej. Carnes Don Pepe"
            :value="old('nombre', $isEdit ? $client->nombre : '')" />

        <x-wire-input name="email" label="Email"
            placeholder="correo@dominio.com"
            :value="old('email', $isEdit ? $client->email : '')" />

        <x-wire-input name="telefono" label="Teléfono"
            placeholder="(XXX) XXX-XXXX"
            :value="old('telefono', $isEdit ? $client->telefono : '')" />

        <x-wire-input name="direccion" label="Dirección (referencia)"
            placeholder="Referencia general"
            :value="old('direccion', $isEdit ? $client->direccion : '')" />

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de persona</label>
            @php $tipo = old('tipo_persona', $isEdit ? $client->tipo_persona : 'PF'); @endphp
            <select name="tipo_persona" id="tipo_persona"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    onchange="CF.onTipoPersonaChange(this.value)"
                    required>
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

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ruta de reparto</label>
            @php $selRoute = old('shipping_route_id', $isEdit ? $client->shipping_route_id : ''); @endphp
            <select name="shipping_route_id"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">Seleccione ruta</option>
                @foreach ($routes as $id => $label)
                    <option value="{{ $id }}" {{ (string)$selRoute === (string)$id ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de pago (predeterminado)</label>
            @php $selPay = old('payment_type_id', $isEdit ? $client->payment_type_id : ''); @endphp
            <select name="payment_type_id" id="payment_type_id"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    onchange="CF.onPaymentChange(this.value)">
                <option value="">Seleccione tipo</option>
                @foreach ($pays as $id => $label)
                    <option value="{{ $id }}" {{ (string)$selPay === (string)$id ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Lista de precios</label>
            @php $selList = old('price_list_id', $isEdit ? $client->price_list_id : ''); @endphp
            <select name="price_list_id"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">Seleccione lista</option>
                @foreach ($lists as $id => $label)
                    <option value="{{ $id }}" {{ (string)$selList === (string)$id ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <x-wire-input type="number" step="0.01" min="0"
            name="credito_limite" label="Crédito límite"
            placeholder="0.00"
            :value="old('credito_limite', $isEdit ? $client->credito_limite : 0)"
            data-credito="field" id="credito_limite" />

        <x-wire-input type="number" step="1" min="0"
            name="credito_dias" label="Crédito días"
            placeholder="0"
            :value="old('credito_dias', $isEdit ? $client->credito_dias : 0)"
            data-credito="field" id="credito_dias" />
    </div>

    <div class="space-y-6">
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

{{-- ====== DIRECCIÓN FISCAL ====== --}}
<div class="mt-6 border-t pt-5">
    <h4 class="text-sm font-semibold text-gray-700 mb-3">Dirección fiscal</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
            <x-wire-input label="Calle" name="fiscal_calle"
                value="{{ old('fiscal_calle', $isEdit ? $client->fiscal_calle : '') }}" />
        </div>
        <div>
            <x-wire-input label="Número" name="fiscal_numero"
                value="{{ old('fiscal_numero', $isEdit ? $client->fiscal_numero : '') }}" />
        </div>
        <div>
            <x-wire-input label="Colonia" name="fiscal_colonia"
                value="{{ old('fiscal_colonia', $isEdit ? $client->fiscal_colonia : '') }}" />
        </div>
        <div>
            <x-wire-input label="Ciudad" name="fiscal_ciudad"
                value="{{ old('fiscal_ciudad', $isEdit ? $client->fiscal_ciudad : '') }}" />
        </div>
        <div>
            <x-wire-input label="Estado" name="fiscal_estado"
                value="{{ old('fiscal_estado', $isEdit ? $client->fiscal_estado : '') }}" />
        </div>
        <div>
            <x-wire-input label="CP" name="fiscal_cp"
                value="{{ old('fiscal_cp', $isEdit ? $client->fiscal_cp : '') }}" />
        </div>
    </div>
</div>

{{-- ====== DIRECCIÓN DE ENTREGA ====== --}}
<div class="mt-6 border-t pt-5">
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-semibold text-gray-700">Dirección de entrega</h4>
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
            <input type="checkbox"
                   name="entrega_igual_fiscal"
                   id="entrega_igual_fiscal"
                   value="1"
                   class="rounded border-gray-300"
                   {{ $igualFiscal ? 'checked' : '' }}
                   onchange="CF.onIgualFiscalChange(this.checked)">
            Igual a la fiscal
        </label>
    </div>
    <div id="entrega-fields"
         class="grid grid-cols-1 md:grid-cols-3 gap-4"
         style="{{ $igualFiscal ? 'opacity:0.5;pointer-events:none' : '' }}">
        <div class="md:col-span-2">
            <x-wire-input label="Calle" name="entrega_calle" id="entrega_calle"
                value="{{ old('entrega_calle', $isEdit ? $client->entrega_calle : '') }}" />
        </div>
        <div>
            <x-wire-input label="Número" name="entrega_numero" id="entrega_numero"
                value="{{ old('entrega_numero', $isEdit ? $client->entrega_numero : '') }}" />
        </div>
        <div>
            <x-wire-input label="Colonia" name="entrega_colonia" id="entrega_colonia"
                value="{{ old('entrega_colonia', $isEdit ? $client->entrega_colonia : '') }}" />
        </div>
        <div>
            <x-wire-input label="Ciudad" name="entrega_ciudad" id="entrega_ciudad"
                value="{{ old('entrega_ciudad', $isEdit ? $client->entrega_ciudad : '') }}" />
        </div>
        <div>
            <x-wire-input label="Estado" name="entrega_estado" id="entrega_estado"
                value="{{ old('entrega_estado', $isEdit ? $client->entrega_estado : '') }}" />
        </div>
        <div>
            <x-wire-input label="CP" name="entrega_cp" id="entrega_cp"
                value="{{ old('entrega_cp', $isEdit ? $client->entrega_cp : '') }}" />
        </div>
    </div>
</div>

<script>
(function(){
    const IS_CREDIT_MAP = @json($isCreditById ?? []);

    function getVal(name) {
        return document.querySelector(`[name="${name}"]`)?.value || '';
    }
    function setVal(name, val) {
        const el = document.querySelector(`[name="${name}"]`);
        if (el) el.value = val;
    }

    function toggleFiscal(tipo) {
        const v = (tipo || getVal('tipo_persona') || 'PF').toUpperCase();
        document.querySelectorAll('[data-fiscal]').forEach(el => {
            const t = el.getAttribute('data-fiscal');
            const show = t === 'any' || (t === 'moral' && v === 'PM');
            const wrap = el.closest('.space-y-2') || el.closest('div') || el.parentElement;
            if (wrap) wrap.style.opacity = show ? '' : '0.4';
            el.disabled = !show;
        });
    }

    function toggleCredito(payId) {
        const id = payId || getVal('payment_type_id');
        const isCredit = id !== '' && (
            String(IS_CREDIT_MAP[id]) === '1' || IS_CREDIT_MAP[id] === true
        );
        document.querySelectorAll('[data-credito="field"]').forEach(el => {
            const wrap = el.closest('div') || el.parentElement;
            if (wrap) wrap.style.opacity = isCredit ? '' : '0.4';
            el.disabled = !isCredit;
            if (!isCredit) el.value = 0;
        });
    }

    function syncFiscalToEntrega() {
        setVal('entrega_calle',   getVal('fiscal_calle'));
        setVal('entrega_numero',  getVal('fiscal_numero'));
        setVal('entrega_colonia', getVal('fiscal_colonia'));
        setVal('entrega_ciudad',  getVal('fiscal_ciudad'));
        setVal('entrega_estado',  getVal('fiscal_estado'));
        setVal('entrega_cp',      getVal('fiscal_cp'));
    }

    function initActivo() {
        const t = document.getElementById('activo_toggle');
        const h = document.getElementById('activo_hidden');
        if (t && h) {
            const sync = () => h.value = t.checked ? 1 : 0;
            t.addEventListener('change', sync);
            sync();
        }
    }

    window.CF = {
        onTipoPersonaChange(val) { toggleFiscal(val); },
        onPaymentChange(val)     { toggleCredito(val); },
        onIgualFiscalChange(checked) {
            const wrap = document.getElementById('entrega-fields');
            if (!wrap) return;
            if (checked) {
                wrap.style.opacity      = '0.5';
                wrap.style.pointerEvents = 'none';
                syncFiscalToEntrega();
            } else {
                wrap.style.opacity      = '';
                wrap.style.pointerEvents = '';
            }
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        toggleFiscal();
        toggleCredito();
        initActivo();
    });
})();
</script>