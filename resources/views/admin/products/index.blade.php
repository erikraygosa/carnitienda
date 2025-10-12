<x-admin-layout
    title="Listado de productos"
    :breadcrumbs="[
        ['name' => 'Dashboard',  'url' => route('admin.dashboard')],
        ['name' => 'Productos'],
    ]"
>
    <x-slot name="action">
        <x-wire-button href="{{ route('admin.products.create') }}" blue>
            Nuevo
        </x-wire-button>
    </x-slot>

    {{-- Contenido --}}
    @livewire('admin.datatables.product-table')

    @push('js')
    <script>
        // Confirmación de borrado (para formularios con class="delete-form")
        const forms = document.querySelectorAll('.delete-form');
        forms.forEach(form => {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: '¡No podrás revertir esto!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            });
        });
    </script>
    @endpush
</x-admin-layout>
