<div class="flex items-center space-x-2">
    {{-- Editar (siempre) --}}
    <x-wire-button href="{{ route('admin.sales-orders.edit',$order) }}" blue xs>Editar</x-wire-button>

    {{-- PDF / Envío: disponibles desde PROCESADO en adelante --}}
    @if(in_array($order->status, ['PROCESADO','DESPACHADO','ENTREGADO']))
        <x-wire-button href="{{ route('admin.sales-orders.pdf',$order) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
        <x-wire-button href="{{ route('admin.sales-orders.pdf.download',$order) }}" gray xs>Descargar PDF</x-wire-button>
        <x-wire-button href="{{ route('admin.sales-orders.send.form',$order) }}" violet xs>Enviar</x-wire-button>

        {{-- Facturar (crear factura desde el pedido) --}}
        <x-wire-button
            href="{{ route('admin.invoices.create') . '?sales_order=' . $order->id }}"
            indigo
            xs
        >
            Facturara
        </x-wire-button>
    @endif

    {{-- Flujo de estados --}}
    @if($order->status === 'BORRADOR')
        <form action="{{ route('admin.sales-orders.approve',$order) }}" method="POST">
            @csrf
            <x-wire-button type="submit" green xs>Aprobar</x-wire-button>
        </form>
        <form action="{{ route('admin.sales-orders.cancel',$order) }}" method="POST">
            @csrf
            <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
        </form>

    @elseif($order->status === 'APROBADO')
        <form action="{{ route('admin.sales-orders.process',$order) }}" method="POST">
            @csrf
            <x-wire-button type="submit" amber xs>Procesar</x-wire-button>
        </form>
        <form action="{{ route('admin.sales-orders.cancel',$order) }}" method="POST">
            @csrf
            <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
        </form>

    @elseif($order->status === 'PROCESADO')
        <form action="{{ route('admin.sales-orders.en-ruta',$order) }}" method="POST">
            @csrf
            <x-wire-button type="submit" violet xs>Despachar</x-wire-button>
        </form>

    @elseif($order->status === 'DESPACHADO')
        <form action="{{ route('admin.sales-orders.deliver',$order) }}" method="POST">
            @csrf
            <x-wire-button type="submit" emerald xs>Entregar</x-wire-button>
        </form>
    @endif
</div>
