<x-admin-layout
    title="Crear producto"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Productos', 'url' => route('admin.products.index')],
        ['name' => 'Crear'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">
        <form method="POST" action="{{ route('admin.products.store') }}" class="space-y-6">
            @csrf

            @include('admin.products.partials._form', [
                'product' => null,
                // Puedes pasar $categories desde el controlador;
                // si no, el parcial hará un pluck directo.
            ])

            <div class="flex items-center justify-end gap-3">
                <x-wire-button href="{{ route('admin.products.index') }}" gray outline>
                    Cancelar
                </x-wire-button>

                <x-wire-button type="submit" blue>
                    Guardar
                </x-wire-button>
            </div>
        </form>
    </div>
</x-admin-layout>
