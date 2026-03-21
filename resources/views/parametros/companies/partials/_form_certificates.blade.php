@php
    $alertColor = fn($alerta) => match($alerta) {
        'vencido'     => 'red',
        'critico'     => 'red',
        'advertencia' => 'yellow',
        default       => 'green',
    };
@endphp

{{-- ===== CSD ===== --}}
<div>
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-semibold text-gray-700">
            🔑 Sello Digital (CSD)
        </h4>
        @if ($csd)
            @php $color = $alertColor($csd->alertaVencimiento()); @endphp
            <span class="text-xs px-2 py-1 rounded-full font-medium
                bg-{{ $color }}-100 text-{{ $color }}-700 border border-{{ $color }}-200">
                @if($csd->alertaVencimiento() === 'vencido')
                    ✗ Vencido el {{ $csd->vigencia_fin->format('d/m/Y') }}
                @else
                    ✓ Vigente hasta {{ $csd->vigencia_fin->format('d/m/Y') }}
                    ({{ $csd->diasParaVencer() }} días)
                @endif
            </span>
        @else
            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-500 border border-gray-200">
                Sin CSD cargado
            </span>
        @endif
    </div>

    @if ($csd)
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 mb-4 text-sm text-gray-600 grid grid-cols-2 gap-2">
        <div><span class="font-medium">No. certificado:</span> {{ $csd->numero_certificado }}</div>
        <div><span class="font-medium">RFC:</span> {{ $csd->rfc_certificado }}</div>
        <div><span class="font-medium">Vigencia inicio:</span> {{ $csd->vigencia_inicio?->format('d/m/Y') }}</div>
        <div><span class="font-medium">Vigencia fin:</span> {{ $csd->vigencia_fin?->format('d/m/Y') }}</div>
    </div>
    @endif

    <form method="POST"
          action="{{ route('admin.parametros.companies.certificates.store', $company) }}"
          enctype="multipart/form-data"
          class="border border-dashed border-gray-300 rounded-lg p-5 space-y-4">
        @csrf
        <input type="hidden" name="tipo" value="csd">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">
                    Archivo .cer <span class="text-red-500">*</span>
                </label>
                <input type="file" name="archivo_cer" accept=".cer" required
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3
                              file:rounded file:border-0 file:text-sm file:font-medium
                              file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">
                    Archivo .key <span class="text-red-500">*</span>
                </label>
                <input type="file" name="archivo_key" accept=".key" required
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3
                              file:rounded file:border-0 file:text-sm file:font-medium
                              file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <x-wire-input type="password" name="password" label="Contraseña del certificado"
                required placeholder="••••••••" />
        </div>

        <div class="flex justify-end">
            <x-wire-button type="submit" blue>
                Cargar CSD
            </x-wire-button>
        </div>
    </form>
</div>

{{-- ===== FIEL ===== --}}
<div>
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-semibold text-gray-700">
            🖊️ Firma Electrónica (FIEL)
        </h4>
        @if ($fiel)
            @php $color = $alertColor($fiel->alertaVencimiento()); @endphp
            <span class="text-xs px-2 py-1 rounded-full font-medium
                bg-{{ $color }}-100 text-{{ $color }}-700 border border-{{ $color }}-200">
                @if($fiel->alertaVencimiento() === 'vencido')
                    ✗ Vencida el {{ $fiel->vigencia_fin->format('d/m/Y') }}
                @else
                    ✓ Vigente hasta {{ $fiel->vigencia_fin->format('d/m/Y') }}
                    ({{ $fiel->diasParaVencer() }} días)
                @endif
            </span>
        @else
            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-500 border border-gray-200">
                Sin FIEL cargada
            </span>
        @endif
    </div>

    @if ($fiel)
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 mb-4 text-sm text-gray-600 grid grid-cols-2 gap-2">
        <div><span class="font-medium">No. certificado:</span> {{ $fiel->numero_certificado }}</div>
        <div><span class="font-medium">RFC:</span> {{ $fiel->rfc_certificado }}</div>
        <div><span class="font-medium">Vigencia inicio:</span> {{ $fiel->vigencia_inicio?->format('d/m/Y') }}</div>
        <div><span class="font-medium">Vigencia fin:</span> {{ $fiel->vigencia_fin?->format('d/m/Y') }}</div>
    </div>
    @endif

    <form method="POST"
          action="{{ route('admin.parametros.companies.certificates.store', $company) }}"
          enctype="multipart/form-data"
          class="border border-dashed border-gray-300 rounded-lg p-5 space-y-4">
        @csrf
        <input type="hidden" name="tipo" value="fiel">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">
                    Archivo .cer <span class="text-red-500">*</span>
                </label>
                <input type="file" name="archivo_cer" accept=".cer" required
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3
                              file:rounded file:border-0 file:text-sm file:font-medium
                              file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">
                    Archivo .key <span class="text-red-500">*</span>
                </label>
                <input type="file" name="archivo_key" accept=".key" required
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3
                              file:rounded file:border-0 file:text-sm file:font-medium
                              file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <x-wire-input type="password" name="password" label="Contraseña del certificado"
                required placeholder="••••••••" />
        </div>

        <div class="flex justify-end">
            <x-wire-button type="submit" blue>
                Cargar FIEL
            </x-wire-button>
        </div>
    </form>
</div>