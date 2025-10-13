<x-admin-layout
    title="Notas de venta"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Ventas'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales.create') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Nueva nota
        </a>
    </x-slot>

    <x-wire-card>
        @livewire('admin.datatables.sales-table') {{-- ahora coincide con la clase SalesTable --}}
    </x-wire-card>
</x-admin-layout>
