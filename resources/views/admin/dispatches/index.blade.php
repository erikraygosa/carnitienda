    <x-admin-layout
    title="Despachos"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Despachos'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.dispatches.create') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Nuevo despacho
        </a>
    </x-slot>

    <x-wire-card>
        @livewire('admin.datatables.dispatch-table')
    </x-wire-card>

    @push('js')
    <script>
        document.querySelectorAll('.delete-form').forEach(f=>{
            f.addEventListener('submit', e=>{
                e.preventDefault();
                Swal.fire({
                    title:'¿Eliminar despacho?', text:'No podrás revertirlo.',
                    icon:'warning', showCancelButton:true,
                    confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar'
                }).then(r=>{ if(r.isConfirmed) f.submit(); });
            });
        });
    </script>
    @endpush
</x-admin-layout>
