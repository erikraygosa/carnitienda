<x-admin-layout
    title="Listado de almacenes"
    :breadcrumbs="[
        ['name' => 'Dashboard',  'url' => route('admin.dashboard')],
        ['name' => 'Almacenes'],
    ]"
>
    <x-slot name="action">
        <x-wire-button href="{{ route('admin.warehouses.create') }}" blue>
            Nuevo
        </x-wire-button>
    </x-slot>

    @livewire('admin.datatables.warehouse-table')

    <script>
      const forms = document.querySelectorAll('.delete-form');
      forms.forEach(form => {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
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
</x-admin-layout>

