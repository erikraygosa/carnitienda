<x-admin-layout
    title="Datos fiscales"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Parámetros'],
        ['name' => 'Empresas', 'url' => route('admin.parametros.companies.index')],
        ['name' => $company->nombre_display],
        ['name' => 'Datos fiscales'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">

        @include('parametros.companies.partials._wizard_steps', ['paso' => 2])

        <form method="POST" action="{{ route('admin.parametros.companies.fiscal.update', $company) }}" class="space-y-6 mt-6">
            @csrf
            @method('PUT')
            @include('parametros.companies.partials._form_fiscal', [
                'company'    => $company,
                'fiscalData' => $fiscalData,
                'regimenes'  => $regimenes,
            ])

            <div class="flex items-center justify-between border-t pt-4">
                <x-wire-button href="{{ route('admin.parametros.companies.edit', $company) }}" gray outline>
                    ← Anterior
                </x-wire-button>
                <x-wire-button type="submit" blue>
                    Guardar y continuar →
                </x-wire-button>
            </div>
        </form>

    </div>
</x-admin-layout>