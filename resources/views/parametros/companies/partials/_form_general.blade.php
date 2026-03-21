@php $isEdit = isset($company) && $company; @endphp

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
        <strong class="block mb-2">Revisa los siguientes errores:</strong>
        <ul class="list-disc ml-5">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <x-wire-input
        name="razon_social"
        label="Razón social"
        required
        placeholder="Ej. Distribuidora del Norte SA de CV"
        :value="old('razon_social', $isEdit ? $company->razon_social : '')" />

    <x-wire-input
        name="nombre_comercial"
        label="Nombre comercial"
        placeholder="Ej. Distrinorte"
        :value="old('nombre_comercial', $isEdit ? $company->nombre_comercial : '')" />

    <div class="space-y-2 w-full">
        <label class="block text-sm font-medium text-gray-700">Tipo de persona <span class="text-red-500">*</span></label>
        @php $tipo = old('tipo_persona', $isEdit ? $company->tipo_persona : 'moral'); @endphp
        <select name="tipo_persona" id="tipo_persona"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required>
            <option value="moral"  {{ $tipo === 'moral'  ? 'selected' : '' }}>Persona moral</option>
            <option value="fisica" {{ $tipo === 'fisica' ? 'selected' : '' }}>Persona física</option>
        </select>
    </div>

    <x-wire-input
        name="rfc"
        label="RFC"
        required
        placeholder="Ej. DIN010203AB1"
        :value="old('rfc', $isEdit ? $company->rfc : '')" />

    <x-wire-input
        name="telefono"
        label="Teléfono"
        placeholder="(999) 123-4567"
        :value="old('telefono', $isEdit ? $company->telefono : '')" />

    <x-wire-input
        name="email"
        label="Email corporativo"
        placeholder="contacto@empresa.com"
        :value="old('email', $isEdit ? $company->email : '')" />

    <x-wire-input
        name="sitio_web"
        label="Sitio web"
        placeholder="https://www.empresa.com"
        :value="old('sitio_web', $isEdit ? $company->sitio_web : '')" />

    <div class="space-y-2 w-full">
        <label class="block text-sm font-medium text-gray-700">Moneda</label>
        @php $moneda = old('moneda', $isEdit ? $company->moneda : 'MXN'); @endphp
        <select name="moneda"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="MXN" {{ $moneda === 'MXN' ? 'selected' : '' }}>MXN — Peso mexicano</option>
            <option value="USD" {{ $moneda === 'USD' ? 'selected' : '' }}>USD — Dólar americano</option>
        </select>
    </div>

    {{-- Activo toggle --}}
    <div class="flex items-center gap-3 md:col-span-2">
        <input type="checkbox" id="activo_toggle"
               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
               {{ old('activo', $isEdit ? $company->activo : true) ? 'checked' : '' }}>
        <label for="activo_toggle" class="text-sm text-gray-700">Empresa activa</label>
        <input type="hidden" name="activo" id="activo_hidden"
               value="{{ old('activo', $isEdit ? (int)$company->activo : 1) }}">
    </div>

</div>

@push('js')
<script>
(function () {
    const toggle = document.getElementById('activo_toggle');
    const hidden = document.getElementById('activo_hidden');
    if (toggle && hidden) {
        toggle.addEventListener('change', () => hidden.value = toggle.checked ? 1 : 0);
    }
})();
</script>
@endpush