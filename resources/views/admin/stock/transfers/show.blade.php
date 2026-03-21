<x-admin-layout
    title="Traspaso {{ $transfer->folio }}"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Traspasos','url'=>route('admin.stock.transfers.index')],
        ['name'=>$transfer->folio],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.transfers.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <a href="{{ route('admin.stock.transfers.print', $transfer) }}"
           target="_blank"
           class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md border">🖨 Imprimir</a>
    </x-slot>

    <x-wire-card>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Folio</p>
                <p class="font-mono font-semibold text-indigo-700 text-base">{{ $transfer->folio }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Fecha</p>
                <p class="font-medium">{{ $transfer->fecha->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Origen</p>
                <p class="font-medium">{{ $transfer->fromWarehouse?->nombre ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Destino</p>
                <p class="font-medium">{{ $transfer->toWarehouse?->nombre ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Estatus</p>
                <span class="px-2 py-0.5 rounded-full text-xs {{ $statusClasses[$transfer->status] ?? 'bg-gray-100' }}">
                    {{ $transfer->status_label }}
                </span>
            </div>
            @if($transfer->dispatch_id)
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Despacho</p>
                <a href="{{ route('admin.dispatches.edit', $transfer->dispatch_id) }}"
                   class="text-indigo-600 hover:underline font-medium">#{{ $transfer->dispatch_id }}</a>
            </div>
            @endif
            @if($transfer->notas)
            <div class="md:col-span-4">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Notas</p>
                <p class="text-gray-600">{{ $transfer->notas }}</p>
            </div>
            @endif
        </div>
    </x-wire-card>

    <x-wire-card class="mt-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Productos</h3>
        <table class="min-w-full text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="p-2 text-left">Producto</th>
                    <th class="p-2 text-right">Cantidad</th>
                    <th class="p-2 text-left">Unidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transfer->items as $it)
                <tr class="border-b">
                    <td class="p-2 font-medium">{{ $it->product?->nombre ?? '—' }}</td>
                    <td class="p-2 text-right font-mono">{{ number_format($it->qty, 3) }}</td>
                    <td class="p-2 text-gray-500">{{ $it->product?->unidad }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </x-wire-card>

    <x-wire-card class="mt-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClasses[$transfer->status] ?? 'bg-gray-100' }}">
                {{ $transfer->status_label }}
            </span>
            <div class="ml-auto flex gap-2">
                @if(in_array($transfer->status, ['PENDIENTE','ASIGNADO','EN_RUTA']))
                    <form method="POST" action="{{ route('admin.stock.transfers.complete', $transfer) }}">
                        @csrf
                        <x-wire-button type="submit" green xs>✓ Completar traspaso</x-wire-button>
                    </form>
                @endif
                @if(in_array($transfer->status, ['PENDIENTE','ASIGNADO']))
                    <form method="POST" action="{{ route('admin.stock.transfers.cancel', $transfer) }}">
                        @csrf
                        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
                    </form>
                @endif
            </div>
        </div>
    </x-wire-card>
</x-admin-layout>