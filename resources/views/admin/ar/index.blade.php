{{-- resources/views/admin/ar/index.blade.php --}}
<x-admin-layout
    title="Cuentas por cobrar"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Finanzas'],
        ['name' => 'Cuentas por cobrar'],
    ]"
>
    {{-- Botón de acción superior derecho --}}
    <x-slot name="action">
        <x-wire-button href="{{ route('admin.ar-payments.create') }}" blue>
            Registrar cobro
        </x-wire-button>
    </x-slot>

    {{-- Contenedor principal --}}
    <x-wire-card>
        @livewire('admin.datatables.ar-accounts-table')
    </x-wire-card>

    @push('js')
    <script>
        // Puedes agregar confirmaciones de acción o recargas Livewire aquí si lo necesitas
    </script>
    @endpush
</x-admin-layout>
