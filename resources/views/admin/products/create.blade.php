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

            @include('admin.products.partials._form', ['product' => null])

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.products.index') }}"
                   class="inline-flex px-4 py-2 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>