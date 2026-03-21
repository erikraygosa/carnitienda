@php $isEdit = isset($fiscalData) && $fiscalData?->id; @endphp

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
        <strong class="block mb-2">Revisa los siguientes errores:</strong>
        <ul class="list-disc ml-5">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- Alerta tipo persona --}}
<div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-700 text-sm">
    Empresa registrada como
    <strong>{{ $company->tipo_persona === 'moral' ? 'Persona Moral' : 'Persona Física' }}</strong>
    — RFC: <strong>{{ $company->rfc }}</strong>
</div>

{{-- Dirección fiscal --}}
<h4 class="text-sm font-semibold text-gray-700 mb-3">Dirección fiscal</h4>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

    <div class="md:col-span-2">
        <x-wire-input label="Calle" name="calle" required
            :value="old('calle', $isEdit ? $fiscalData->calle : '')" />
    </div>

    <x-wire-input label="Número exterior" name="numero_exterior" required
        :value="old('numero_exterior', $isEdit ? $fiscalData->numero_exterior : '')" />

    <x-wire-input label="Número interior" name="numero_interior"
        :value="old('numero_interior', $isEdit ? $fiscalData->numero_interior : '')" />

    <x-wire-input label="Colonia" name="colonia" required
        :value="old('colonia', $isEdit ? $fiscalData->colonia : '')" />

    <x-wire-input label="Código postal" name="codigo_postal" required
        placeholder="97000"
        :value="old('codigo_postal', $isEdit ? $fiscalData->codigo_postal : '')" />

    <x-wire-input label="Municipio / Alcaldía" name="municipio" required
        :value="old('municipio', $isEdit ? $fiscalData->municipio : '')" />

    <x-wire-input label="Estado" name="estado" required
        :value="old('estado', $isEdit ? $fiscalData->estado : '')" />

</div>

{{-- Régimen fiscal --}}
<div class="border-t pt-5">
    <h4 class="text-sm font-semibold text-gray-700 mb-3">Régimen fiscal SAT</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="space-y-2 w-full md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">
                Régimen fiscal <span class="text-red-500">*</span>
            </label>
            @php $selReg = old('regimen_fiscal', $isEdit ? $fiscalData->regimen_fiscal : ''); @endphp
            <select name="regimen_fiscal" required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Selecciona un régimen</option>
                @foreach ($regimenes as $clave => $descripcion)
                    <option value="{{ $clave }}" {{ $selReg === $clave ? 'selected' : '' }}>
                        {{ $clave }} — {{ $descripcion }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <x-wire-input label="Actividad económica (opcional)" name="actividad_economica"
                placeholder="Ej. Comercio al por mayor de alimentos"
                :value="old('actividad_economica', $isEdit ? $fiscalData->actividad_economica : '')" />
        </div>

    </div>
</div>

{{-- Campos persona moral --}}
@if ($company->tipo_persona === 'moral')
<div class="border-t pt-5 mt-2">
    <h4 class="text-sm font-semibold text-gray-700 mb-3">Datos constitutivos</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-wire-input label="Acta constitutiva" name="acta_constitutiva"
            placeholder="No. de acta"
            :value="old('acta_constitutiva', $isEdit ? $fiscalData->acta_constitutiva : '')" />

        <x-wire-input type="date" label="Fecha de constitución" name="fecha_constitucion"
            :value="old('fecha_constitucion', $isEdit ? $fiscalData->fecha_constitucion?->format('Y-m-d') : '')" />

        <x-wire-input label="Notario público" name="notario"
            placeholder="Ej. Notario No. 42"
            :value="old('notario', $isEdit ? $fiscalData->notario : '')" />
    </div>
</div>
@endif

{{-- Campos persona física --}}
@if ($company->tipo_persona === 'fisica')
<div class="border-t pt-5 mt-2">
    <h4 class="text-sm font-semibold text-gray-700 mb-3">Datos personales</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-wire-input label="CURP" name="curp"
            placeholder="18 caracteres"
            :value="old('curp', $isEdit ? $fiscalData->curp : '')" />

        <x-wire-input type="date" label="Fecha de nacimiento" name="fecha_nacimiento"
            :value="old('fecha_nacimiento', $isEdit ? $fiscalData->fecha_nacimiento?->format('Y-m-d') : '')" />
    </div>
</div>
@endif