<x-admin-layout
    title="Factura {{ $invoice->serie }}-{{ $invoice->folio }}"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas','url'=>route('invoices.index')],
        ['name'=>'Detalle'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('invoices.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <div class="ml-2 inline-flex gap-2">
            @if($invoice->estatus === 'BORRADOR')
                <x-wire-button href="{{ route('invoices.edit',$invoice) }}" blue>Editar</x-wire-button>
                <form action="{{ route('invoices.stamp',$invoice) }}" method="POST">@csrf
                    <x-wire-button type="submit" green>Timbrar</x-wire-button>
                </form>
            @else
                <x-wire-button href="{{ route('invoices.pdf',$invoice) }}" gray outline target="_blank">PDF</x-wire-button>
                <x-wire-button href="{{ route('invoices.download',$invoice) }}" gray>Descargar</x-wire-button>
                <x-wire-button href="{{ route('invoices.send.form',$invoice) }}" violet>Enviar</x-wire-button>
                @if($invoice->estatus !== 'CANCELADA')
                    <form action="{{ route('invoices.cancel',$invoice) }}" method="POST" onsubmit="return confirm('¿Cancelar CFDI?')">@csrf
                        <x-wire-button type="submit" red>Cancelar</x-wire-button>
                    </form>
                @endif
            @endif
        </div>
    </x-slot>

    @php
        $statusClasses = [
            'BORRADOR'  =>'bg-amber-100 text-amber-700',
            'TIMBRADA'  =>'bg-emerald-100 text-emerald-700',
            'ENVIADA'   =>'bg-blue-100 text-blue-700',
            'CANCELADA' =>'bg-rose-100 text-rose-700',
        ];
        $statusClass = $statusClasses[$invoice->estatus] ?? 'bg-slate-100 text-slate-700';
    @endphp

    <x-wire-card class="mb-4">
        <div class="flex flex-wrap gap-3 items-center">
            <x-wire-badge>Folio: {{ $invoice->serie }}-{{ $invoice->folio }}</x-wire-badge>
            <x-wire-badge>Cliente: {{ $invoice->client->nombre ?? '—' }}</x-wire-badge>
            <x-wire-badge>Fecha: {{ optional($invoice->fecha)->format('Y-m-d H:i') }}</x-wire-badge>
            <x-wire-badge>Moneda: {{ $invoice->moneda }}</x-wire-badge>
            <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">Estatus: {{ $invoice->estatus }}</span>
            @if($invoice->uuid)
                <x-wire-badge class="ml-auto">UUID: {{ $invoice->uuid }}</x-wire-badge>
            @endif
        </div>
    </x-wire-card>

    <x-wire-card>
        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b">
                <tr>
                    <th class="p-2">ClaveProdServ</th>
                    <th class="p-2">Descripción</th>
                    <th class="p-2">Unidad</th>
                    <th class="p-2 text-right">Cantidad</th>
                    <th class="p-2 text-right">V. Unitario</th>
                    <th class="p-2 text-right">Desc.</th>
                    <th class="p-2 text-right">IVA</th>
                    <th class="p-2 text-right">Importe</th>
                </tr>
                </thead>
                <tbody>
                @foreach($invoice->items as $it)
                    <tr class="border-b">
                        <td class="p-2">{{ $it->clave_prod_serv }}</td>
                        <td class="p-2">{{ $it->descripcion }}</td>
                        <td class="p-2">{{ $it->unidad }}</td>
                        <td class="p-2 text-right">{{ number_format($it->cantidad,6) }}</td>
                        <td class="p-2 text-right">{{ number_format($it->valor_unitario,6) }}</td>
                        <td class="p-2 text-right">{{ number_format($it->descuento,6) }}</td>
                        <td class="p-2 text-right">{{ number_format($it->iva_importe,6) }}</td>
                        <td class="p-2 text-right">{{ number_format($it->importe,6) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" class="p-2 text-right font-medium">Subtotal</td>
                        <td class="p-2 text-right">{{ number_format($invoice->subtotal,2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="p-2 text-right font-medium">Impuestos</td>
                        <td class="p-2 text-right">{{ number_format($invoice->impuestos,2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="p-2 text-right font-semibold">Total</td>
                        <td class="p-2 text-right font-semibold">{{ number_format($invoice->total,2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-wire-card>
</x-admin-layout>
