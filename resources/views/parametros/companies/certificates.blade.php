<x-admin-layout
    title="Certificados digitales"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Parámetros'],
        ['name' => 'Empresas', 'url' => route('admin.parametros.companies.index')],
        ['name' => $company->nombre_display],
        ['name' => 'Certificados'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">

        @include('parametros.companies.partials._wizard_steps', ['paso' => 3])

        <div class="mt-6 space-y-8">
            @include('parametros.companies.partials._form_certificates', [
                'company' => $company,
                'csd'     => $csd,
                'fiel'    => $fiel,
            ])
        </div>

        <div class="flex items-center justify-between border-t pt-4 mt-6">
            <x-wire-button href="{{ route('admin.parametros.companies.fiscal', $company) }}" gray outline>
                ← Anterior
            </x-wire-button>
            <x-wire-button href="{{ route('admin.parametros.companies.index') }}" blue>
                Finalizar ✓
            </x-wire-button>
        </div>

    </div>
</x-admin-layout>