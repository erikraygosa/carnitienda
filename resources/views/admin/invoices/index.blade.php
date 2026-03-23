<x-admin-layout
    title="Facturas"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.create') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Nueva factura
        </a>
    </x-slot>

    <x-wire-card>
        @livewire('admin.datatables.invoice-table')
    </x-wire-card>

    <script>
        const forms = document.querySelectorAll('.delete-form');
        forms.forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Eliminar factura?',
                    text: "Esta acción no se puede revertir.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((r) => { if (r.isConfirmed) form.submit(); });
            });
        });
    </script>
</x-admin-layout>

