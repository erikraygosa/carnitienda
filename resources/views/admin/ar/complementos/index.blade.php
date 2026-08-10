<x-admin-layout
    title="Complementos de Pago"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturación'],
        ['name'=>'Complementos de Pago'],
    ]"
>
    <x-slot name="action">
        @can('registrar cobros')
        <a href="{{ route('admin.ar-payments.create') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            <i class="fa-solid fa-cash-register"></i>
            Registrar cobro
        </a>
        @endcan
    </x-slot>
    {{-- Filtros --}}
    <x-wire-card class="mb-4">
        <form method="GET" action="{{ route('admin.ar.complementos.index') }}"
              class="flex flex-wrap gap-3 items-end">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Buscar cliente..."
                   class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 flex-1 min-w-[200px]">
            <button type="submit"
                    class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('admin.ar.complementos.index') }}"
               class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                Limpiar
            </a>
        </form>
    </x-wire-card>

    <x-wire-card>
        @if($payments->isEmpty())
            <div class="py-10 text-center text-gray-400 text-sm">
                <p class="mb-3">No hay cobros pendientes de complemento de pago.</p>
                <p class="text-xs">
                    Para generar un complemento primero registra el cobro de una factura PPD:
                    @can('registrar cobros')
                    <a href="{{ route('admin.ar-payments.create') }}" class="text-indigo-600 hover:underline font-medium">
                        Registrar cobro →
                    </a>
                    @endcan
                </p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Fecha cobro</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Cliente</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase text-xs">Monto</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Forma pago</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Referencia</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase text-xs"># Facturas</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($payment->fecha)->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 text-gray-700 font-medium">
                            {{ $payment->_cliente }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-gray-800">
                            ${{ number_format($payment->monto, 2) }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $payment->paymentType?->descripcion ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 italic text-xs">
                            {{ $payment->referencia ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">
                                {{ $payment->_related_invoices->count() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.ar.complementos.create', ['payment_id' => $payment->id]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-md bg-indigo-600 text-white hover:bg-indigo-700 whitespace-nowrap">
                                <i class="fa-solid fa-file-circle-plus"></i>
                                Generar complemento
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $payments->links() }}
        </div>
        @endif
    </x-wire-card>
</x-admin-layout>
