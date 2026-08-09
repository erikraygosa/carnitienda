<x-admin-layout
    title="Notas de Crédito"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturación'],
        ['name'=>'Notas de Crédito'],
    ]"
>
    {{-- Filtros --}}
    <x-wire-card class="mb-4">
        <form method="GET" action="{{ route('admin.notas-credito.index') }}"
              class="flex flex-wrap gap-3 items-end">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Buscar folio o cliente..."
                   class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 flex-1 min-w-[200px]">
            <button type="submit"
                    class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('admin.notas-credito.index') }}"
               class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                Limpiar
            </a>
        </form>
    </x-wire-card>

    <x-wire-card class="mb-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Facturas timbradas — generar nota de crédito</h3>
        @if($facturas->isEmpty())
            <p class="py-6 text-center text-gray-400 text-sm">No hay facturas timbradas.</p>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Folio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Cliente</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Fecha</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase text-xs">Total</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase text-xs">Disponible</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($facturas as $f)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-gray-700">{{ $f->serie }}{{ $f->folio }}</td>
                        <td class="px-4 py-3 text-gray-700 font-medium">{{ $f->client?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($f->fecha)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right font-mono text-gray-800">${{ number_format($f->total, 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono {{ $f->_disponible <= 0 ? 'text-gray-400' : 'text-emerald-700 font-semibold' }}">
                            ${{ number_format($f->_disponible, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($f->_disponible > 0)
                            <a href="{{ route('admin.notas-credito.create', ['invoice_id' => $f->id]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-md bg-indigo-600 text-white hover:bg-indigo-700 whitespace-nowrap">
                                <i class="fa-solid fa-file-circle-minus"></i>
                                Generar nota
                            </a>
                            @else
                                <span class="text-xs text-gray-400">Sin saldo</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $facturas->links() }}</div>
        @endif
    </x-wire-card>

    <x-wire-card>
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Notas de crédito emitidas</h3>
        @if($notasCredito->isEmpty())
            <p class="py-6 text-center text-gray-400 text-sm">Todavía no se ha emitido ninguna nota de crédito.</p>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Folio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Cliente</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Factura relacionada</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase text-xs">Estatus</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase text-xs">Total</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($notasCredito as $n)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-gray-700">{{ $n->serie }}{{ $n->folio }}</td>
                        <td class="px-4 py-3 text-gray-700 font-medium">{{ $n->client?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $n->relatedInvoiceOriginal?->serie }}{{ $n->relatedInvoiceOriginal?->folio ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $n->estatus === 'TIMBRADA' ? 'bg-emerald-100 text-emerald-700' :
                                   ($n->estatus === 'CANCELADA' ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-600') }}">
                                {{ $n->estatus }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-gray-800">${{ number_format($n->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.invoices.edit', $n->id) }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $notasCredito->links() }}</div>
        @endif
    </x-wire-card>
</x-admin-layout>
