<div class="flex items-center space-x-2">
    {{-- Editar --}}
    <x-wire-button href="{{ route('admin.sales.edit',$sale) }}" blue xs>Editar</x-wire-button>

    {{-- PDF --}}
    <x-wire-button href="{{ route('admin.sales.pdf',$sale) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
    <x-wire-button type="button" gray xs
        onclick="preguntarFormatoPdf('{{ route('admin.sales.pdf.download',$sale) }}', '{{ route('admin.sales.ticket.pdf',$sale) }}')">
        Descargar PDF
    </x-wire-button>

    {{-- Envío (formulario de envío) --}}
    <x-wire-button href="{{ route('admin.sales.send.form',$sale) }}" violet xs>Enviar</x-wire-button>

    {{-- Acciones por estado --}}
    @if(!in_array($sale->status, ['EN_RUTA','ENTREGADA','CANCELADA']))
        <form action="{{ route('admin.sales.cancel',$sale) }}" method="POST" class="inline form-cancel-sale">@csrf
            <x-wire-button type="button" red xs onclick="Swal.fire({title:'¿Cancelar esta nota?',text:'Revierte el inventario y, si aplica, la CxC o el efectivo de caja.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, cancelar',confirmButtonColor:'#dc2626',cancelButtonText:'Volver'}).then(r=>{if(r.isConfirmed) this.closest('form').submit();})">Cancelar</x-wire-button>
        </form>
    @endif
</div>

<script>
// Se define una sola vez aunque este partial se renderice por fila
// (viene de un datatable que hace view(...)->render() por cada nota).
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
