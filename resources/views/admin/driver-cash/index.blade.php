{{-- resources/views/admin/driver-cash/index.blade.php --}}
<x-admin-layout
    title="Cortes de choferes"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Logística'],
        ['name'=>'Cortes de choferes'],
    ]"
>
    <x-slot name="action">
        <x-wire-button href="{{ route('admin.driver-cash.create') }}" blue>Nuevo corte</x-wire-button>
    </x-slot>

    <x-wire-card>
        @livewire('admin.datatables.driver-cash-registers-table')
    </x-wire-card>

    @push('js')
    <script>
        // Confirmar cierre con SweetAlert
        document.querySelectorAll('.close-form')?.forEach(form => {
            form.addEventListener('submit', e => {
                e.preventDefault();
                Swal.fire({
                    title: '¿Cerrar corte?',
                    text: 'Esta acción no se puede deshacer.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, cerrar',
                    cancelButtonText: 'Cancelar'
                }).then(r => { if (r.isConfirmed) form.submit(); });
            });
        });
    </script>
    @endpush
</x-admin-layout>
