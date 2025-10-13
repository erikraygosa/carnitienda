<x-admin-layout
    title="Cotizaciones"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cotizaciones'],
    ]"
>
    <x-slot name="action">
        <x-wire-button href="{{ route('admin.quotes.create') }}" blue>Nuevo</x-wire-button>
    </x-slot>

    <x-wire-card>
        @livewire('admin.datatables.quote-table')
    </x-wire-card>

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
