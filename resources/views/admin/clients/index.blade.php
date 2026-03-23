<x-admin-layout
    title="Listado de clientes"
    :breadcrumbs="[
        ['name'=>'Dashboard', 'url'=>route('admin.dashboard')],
        ['name'=>'Clientes'],
    ]"
>
    <x-slot name="action">
        <x-wire-button href="{{ route('admin.clients.create') }}" blue>Nuevo</x-wire-button>
    </x-slot>

    @livewire('admin.datatables.client-table')

    <script>
        // Confirmación para desactivar
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function(e){
                e.preventDefault();
                Swal.fire({
                    title: '¿Desactivar cliente?',
                    text: 'Podrás activarlo luego.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, desactivar',
                    cancelButtonText: 'Cancelar'
                }).then(r => { if (r.isConfirmed) form.submit(); });
            })
        });
    </script>
</x-admin-layout>

