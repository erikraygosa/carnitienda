<div class="flex items-center gap-1 flex-wrap">

    {{-- Editar --}}
    <a href="{{ route('admin.sales-orders.edit', $order) }}"
       class="inline-flex px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-700 bg-white hover:bg-indigo-50">
        Editar
    </a>

    {{-- PDF / Envío / Facturar --}}
    @if(in_array($order->status, ['PROCESADO','EN_RUTA','ENTREGADO','DESPACHADO']))
        <a href="{{ route('admin.sales-orders.pdf', $order) }}" target="_blank"
           class="inline-flex px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50">
            PDF
        </a>
        <button type="button"
           onclick="preguntarFormatoPdf('{{ route('admin.sales-orders.pdf.download', $order) }}', '{{ route('admin.sales-orders.ticket.pdf', $order) }}')"
           class="inline-flex px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50">
            ↓ PDF
        </button>
        <a href="{{ route('admin.sales-orders.send.form', $order) }}"
           class="inline-flex px-2 py-1 text-xs rounded border border-violet-300 text-violet-700 bg-white hover:bg-violet-50">
            Enviar
        </a>
        <a href="{{ route('admin.invoices.create') }}?sales_order={{ $order->id }}"
           class="inline-flex px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100">
            Facturar
        </a>
    @endif

    {{-- Flujo de estados --}}
    @if($order->status === 'BORRADOR')
        <form action="{{ route('admin.sales-orders.approve', $order) }}" method="POST" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex px-2 py-1 text-xs rounded border border-emerald-300 text-emerald-700 bg-white hover:bg-emerald-50">
                Aprobar
            </button>
        </form>
        <form action="{{ route('admin.sales-orders.cancel', $order) }}" method="POST" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex px-2 py-1 text-xs rounded border border-red-300 text-red-600 bg-white hover:bg-red-50">
                Cancelar
            </button>
        </form>

    @elseif($order->status === 'APROBADO')
        {{-- Solo admin puede procesar --}}
        @can('procesar pedidos')
            <form action="{{ route('admin.sales-orders.process', $order) }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex px-2 py-1 text-xs rounded border border-amber-300 text-amber-700 bg-white hover:bg-amber-50">
                    Procesar
                </button>
            </form>
        @endcan
        <form action="{{ route('admin.sales-orders.cancel', $order) }}" method="POST" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex px-2 py-1 text-xs rounded border border-red-300 text-red-600 bg-white hover:bg-red-50">
                Cancelar
            </button>
        </form>

    @elseif($order->status === 'PREPARANDO')
        {{-- Solo admin puede procesar --}}
        @can('procesar pedidos')
            <form action="{{ route('admin.sales-orders.process', $order) }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex px-2 py-1 text-xs rounded border border-amber-300 text-amber-700 bg-white hover:bg-amber-50">
                    Procesar
                </button>
            </form>
        @endcan

    @elseif($order->status === 'PROCESADO')
        <form action="{{ route('admin.sales-orders.en-ruta', $order) }}" method="POST" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex px-2 py-1 text-xs rounded border border-violet-300 text-violet-700 bg-white hover:bg-violet-50">
                Despachar
            </button>
        </form>

    @elseif(in_array($order->status, ['EN_RUTA','DESPACHADO']))
        <form action="{{ route('admin.sales-orders.deliver', $order) }}" method="POST" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex px-2 py-1 text-xs rounded border border-emerald-300 text-emerald-700 bg-white hover:bg-emerald-50">
                Entregar
            </button>
        </form>
        <form action="{{ route('admin.sales-orders.not-delivered', $order) }}" method="POST" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex px-2 py-1 text-xs rounded border border-orange-300 text-orange-600 bg-white hover:bg-orange-50">
                No entregado
            </button>
        </form>
    @endif

</div>

<script>
// Se define una sola vez aunque este partial se renderice por fila
// (viene de un datatable que hace view(...)->render() por cada pedido).
if (typeof window.preguntarFormatoPdf !== 'function') {
    window.preguntarFormatoPdf = function (notaUrl, ticketUrl) {
        Swal.fire({
            title: '¿Qué formato quieres descargar?',
            text: 'Nota completa (tamaño carta) o ticket angosto (76mm).',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Nota completa',
            denyButtonText: 'Ticket (76mm)',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#4f46e5',
            denyButtonColor: '#6b7280',
        }).then(function (r) {
            if (r.isConfirmed) window.open(notaUrl, '_blank');
            else if (r.isDenied) window.open(ticketUrl, '_blank');
        });
    };
}
</script>