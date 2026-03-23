<x-admin-layout
    title="Cuentas por cobrar"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Finanzas'],
        ['name' => 'Cuentas por cobrar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.ar-payments.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Registrar cobro
        </a>
    </x-slot>

    <x-wire-card>
        @livewire('admin.datatables.ar-accounts-table')
    </x-wire-card>

</x-admin-layout>