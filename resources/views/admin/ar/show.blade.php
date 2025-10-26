{{-- resources/views/admin/ar/show.blade.php --}}
<x-admin-layout
    :title="'Estado de cuenta: '.$client->nombre"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Finanzas'],
        ['name'=>'Cuentas por cobrar','url'=>route('admin.ar.index')],
        ['name'=>$client->nombre],
    ]"
>
    <x-slot name="action">
        <x-wire-button href="{{ route('admin.ar-payments.create') }}" blue>Registrar cobro</x-wire-button>
    </x-slot>

    <div class="grid md:grid-cols-3 gap-4">
        <x-wire-card>
            <div class="text-sm text-gray-500">Cliente</div>
            <div class="text-lg font-semibold">{{ $client->nombre }}</div>
        </x-wire-card>
        <x-wire-card>
            <div class="text-sm text-gray-500">Crédito límite</div>
            <div class="text-lg font-semibold">
                {{ $client->credito_limite ? '$'.number_format($client->credito_limite,2) : 'N/D' }}
            </div>
        </x-wire-card>
        <x-wire-card>
            <div class="text-sm text-gray-500">Saldo actual</div>
            <div class="text-lg font-semibold">
                {{ $saldo >= 0 ? '$'.number_format($saldo,2) : '($'.number_format(abs($saldo),2).')' }}
            </div>
        </x-wire-card>
    </div>

    <x-wire-card class="mt-6">
        <h3 class="font-semibold mb-3">Movimientos</h3>
        @livewire('admin.datatables.ar-client-ledger-table', ['clientId'=>$client->id])
    </x-wire-card>
</x-admin-layout>
