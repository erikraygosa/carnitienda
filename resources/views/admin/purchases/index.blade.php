<x-admin-layout
    title="Compras"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Compras'],
    ]"
>
    <x-slot name="action">
        <x-wire-button href="{{ route('admin.purchases.create') }}" blue>Nuevo</x-wire-button>
    </x-slot>

    @livewire('admin.datatables.purchase-table')

    @push('js')
    <script>
      document.querySelectorAll('.delete-form').forEach(f=>{
        f.addEventListener('submit',e=>{
          e.preventDefault();
          Swal.fire({
            title:'¿Estás seguro?', text:'Esta acción no se puede revertir',
            icon:'warning', showCancelButton:true,
            confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar'
          }).then(r=>{ if(r.isConfirmed) f.submit(); });
        });
      });
    </script>
    @endpush
</x-admin-layout>
