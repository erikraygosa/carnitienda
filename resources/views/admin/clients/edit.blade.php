<x-admin-layout
    title="Editar cliente"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Clientes', 'url' => route('admin.clients.index')],
        ['name' => 'Editar'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">

        {{-- FORM PRINCIPAL (UPDATE) --}}
        <form id="client-update-form" method="POST" action="{{ route('admin.clients.update', $client) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('admin.clients.partials._form', ['client' => $client])

            <div class="flex items-center justify-end gap-3">
                <x-wire-button href="{{ route('admin.clients.index') }}" gray outline>
                    Volver
                </x-wire-button>

                <x-wire-button form="client-update-form" type="submit" blue>
                    Actualizar
                </x-wire-button>
            </div>
        </form>

        {{-- BLOQUE: Lista de precios personalizada del cliente --}}
        @livewire('admin.clients.client-price-editor', ['clientId' => $client->id])

        {{-- DESACTIVAR (en vez de eliminar) --}}
        <form id="client-deactivate-form" method="POST" action="{{ route('admin.clients.destroy', $client) }}" class="mt-4">
            @csrf
            @method('DELETE')
            <x-wire-button type="submit" red outline>
                Desactivar
            </x-wire-button>
        </form>

    </div>
</x-admin-layout>
