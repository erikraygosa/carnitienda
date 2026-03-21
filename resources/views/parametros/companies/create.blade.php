<x-admin-layout
    title="Nueva empresa"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Parámetros'],
        ['name' => 'Empresas', 'url' => route('admin.parametros.companies.index')],
        ['name' => 'Nueva'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">

        {{-- Wizard steps --}}
        @include('parametros.companies.partials._wizard_steps', ['paso' => 1])

        <form method="POST" action="{{ route('admin.parametros.companies.store') }}" class="space-y-6 mt-6">
            @csrf
            @include('parametros.companies.partials._form_general', ['company' => null])

            <div class="flex items-center justify-end gap-3 border-t pt-4">
                <x-wire-button href="{{ route('admin.parametros.companies.index') }}" gray outline>
                    Cancelar
                </x-wire-button>
                <x-wire-button type="submit" blue>
                    Guardar y continuar →
                </x-wire-button>
            </div>
        </form>

    </div>
</x-admin-layout>