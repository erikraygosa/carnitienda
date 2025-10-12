<x-admin-layout
    title="Nuevo cliente"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Clientes', 'url' => route('admin.clients.index')],
        ['name' => 'Nuevo'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">
        <form method="POST" action="{{ route('admin.clients.store') }}" class="space-y-6">
            @csrf

            @include('admin.clients.partials._form', ['client' => null])

            <div class="flex items-center justify-end gap-3">
                <x-wire-button href="{{ route('admin.clients.index') }}" gray outline>
                    Cancelar
                </x-wire-button>

                <x-wire-button type="submit" blue>
                    Guardar
                </x-wire-button>
            </div>
        </form>
    </div>
</x-admin-layout>
