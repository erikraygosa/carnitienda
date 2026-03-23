<x-admin-layout
    title="Listado de categorías"
    :breadcrumbs="[
        ['name' => 'Dashboard',  'url' => route('admin.dashboard')],
        ['name' => 'Categorias', ],
    ]"
    title="Categorías"
>
    
    <x-slot name="action">
       
        <x-wire-button href="{{ route('admin.categories.create') }}" blue >
            Nuevo
        </x-wire-button>
    </x-slot>

    {{-- contenido --}}

    @livewire('admin.datatables.category-table')

    <script>
        forms = document.querySelectorAll('.delete-form');   
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                event.preventDefault(); // Evita el envío inmediato del formulario

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esto!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit(); // Envía el formulario si el usuario confirma
                    }
                });
            });
        });
    </script>    

</x-admin-layout>

