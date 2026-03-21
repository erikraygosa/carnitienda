<x-admin-layout
    title="Empresas"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Parámetros'],
        ['name' => 'Empresas'],
    ]"
>
    <div class="bg-white rounded-xl shadow overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-base font-semibold text-gray-800">Empresas registradas</h2>
            <x-wire-button href="{{ route('admin.parametros.companies.create') }}" blue>
                + Nueva empresa
            </x-wire-button>
        </div>

        {{-- Tabla --}}
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
                <tr>
                    <th class="px-6 py-3">Empresa</th>
                    <th class="px-6 py-3">RFC</th>
                    <th class="px-6 py-3">Tipo</th>
                    <th class="px-6 py-3 text-center">CSD</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($companies as $company)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">{{ $company->nombre_display }}</div>
                        @if($company->nombre_comercial && $company->nombre_comercial !== $company->razon_social)
                            <div class="text-xs text-gray-400">{{ $company->razon_social }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 font-mono text-gray-600">{{ $company->rfc }}</td>
                    <td class="px-6 py-4 text-gray-500">
                        {{ $company->tipo_persona === 'moral' ? 'Moral' : 'Física' }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if ($company->csdActivo)
                            @php $alerta = $company->csdActivo->alertaVencimiento(); @endphp
                            @if ($alerta === 'vencido')
                                <span class="text-red-500 text-xs font-medium">✗ Vencido</span>
                            @elseif ($alerta === 'critico')
                                <span class="text-red-500 text-xs font-medium">⚠ {{ $company->csdActivo->diasParaVencer() }}d</span>
                            @elseif ($alerta === 'advertencia')
                                <span class="text-yellow-600 text-xs font-medium">⚠ {{ $company->csdActivo->diasParaVencer() }}d</span>
                            @else
                                <span class="text-green-600 text-xs font-medium">✓ Vigente</span>
                            @endif
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if ($company->activo)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">Activa</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">Inactiva</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <x-wire-button href="{{ route('admin.parametros.companies.edit', $company) }}" gray outline xs>
                                Editar
                            </x-wire-button>
                            <x-wire-button href="{{ route('admin.parametros.companies.fiscal', $company) }}" gray outline xs>
                                Fiscal
                            </x-wire-button>
                            <x-wire-button href="{{ route('admin.parametros.companies.certificates', $company) }}" gray outline xs>
                                CSD/FIEL
                            </x-wire-button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                        No hay empresas registradas.
                        <a href="{{ route('admin.parametros.companies.create') }}" class="text-indigo-600 ml-1 hover:underline">
                            Crear la primera
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Paginación --}}
        @if ($companies->hasPages())
            <div class="px-6 py-4 border-t">
                {{ $companies->links() }}
            </div>
        @endif

    </div>
</x-admin-layout>