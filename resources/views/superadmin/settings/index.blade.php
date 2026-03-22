@extends('layouts.superadmin-layout')
@section('title', 'Configuración del sistema')

@section('content')
<form action="{{ route('superadmin.settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
    @csrf @method('PUT')

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-4">General</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nombre del sistema</label>
                <input type="text" name="app.nombre"
                       value="{{ $general['app.nombre']?->valor ?? config('app.name') }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Zona horaria</label>
                <select name="app.timezone"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    @php $tz = $general['app.timezone']?->valor ?? 'America/Mexico_City'; @endphp
                    <option value="America/Mexico_City"  {{ $tz === 'America/Mexico_City'  ? 'selected' : '' }}>America/Mexico_City</option>
                    <option value="America/Cancun"       {{ $tz === 'America/Cancun'       ? 'selected' : '' }}>America/Cancun</option>
                    <option value="America/Chihuahua"    {{ $tz === 'America/Chihuahua'    ? 'selected' : '' }}>America/Chihuahua</option>
                    <option value="America/Hermosillo"   {{ $tz === 'America/Hermosillo'   ? 'selected' : '' }}>America/Hermosillo</option>
                    <option value="America/Tijuana"      {{ $tz === 'America/Tijuana'      ? 'selected' : '' }}>America/Tijuana</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Logo del sistema</label>
                <input type="file" name="app.logo" accept="image/*"
                       class="w-full text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                              file:text-sm file:font-medium file:bg-indigo-900 file:text-indigo-300 hover:file:bg-indigo-800">
            </div>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-4">Facturación</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Versión CFDI</label>
                <input type="text" name="facturacion.version_cfdi"
                       value="{{ $facturacion['facturacion.version_cfdi']?->valor ?? '4.0' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Exportación default</label>
                <select name="facturacion.exportacion"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    @php $exp = $facturacion['facturacion.exportacion']?->valor ?? '01'; @endphp
                    <option value="01" {{ $exp === '01' ? 'selected' : '' }}>01 — No aplica</option>
                    <option value="02" {{ $exp === '02' ? 'selected' : '' }}>02 — Definitiva</option>
                    <option value="03" {{ $exp === '03' ? 'selected' : '' }}>03 — Temporal</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Alerta timbres restantes</label>
                <input type="number" name="facturacion.alerta_timbres" min="1"
                       value="{{ $facturacion['facturacion.alerta_timbres']?->valor ?? 50 }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-4">Correo electrónico</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nombre del remitente</label>
                <input type="text" name="correo.from_name"
                       value="{{ $correo['correo.from_name']?->valor ?? '' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Email del remitente</label>
                <input type="email" name="correo.from_address"
                       value="{{ $correo['correo.from_address']?->valor ?? '' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit"
                class="px-6 py-2.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-medium">
            Guardar configuración
        </button>
    </div>
</form>
@endsection