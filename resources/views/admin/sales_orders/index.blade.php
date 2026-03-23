<x-admin-layout
    title="Pedidos"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')],['name'=>'Pedidos']]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales-orders.create') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Nuevo pedido</a>
    </x-slot>

    <x-wire-card>
        @livewire('admin.datatables.sales-order-table')
    </x-wire-card>
</x-admin-layout>

