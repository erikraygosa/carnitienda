<div class="flex items-center space-x-2">
    {{-- Editar --}}
    <x-wire-button href="{{ route('admin.sales.edit',$sale) }}" blue xs>Editar</x-wire-button>

    {{-- PDF --}}
    <x-wire-button href="{{ route('admin.sales.pdf',$sale) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
    <x-wire-button href="{{ route('admin.sales.pdf.download',$sale) }}" gray xs>Descargar PDF</x-wire-button>

    {{-- Envío (formulario de envío) --}}
    <x-wire-button href="{{ route('admin.sales.send.form',$sale) }}" violet xs>Enviar</x-wire-button>

    {{-- Acciones por estado --}}
    @if(!in_array($sale->status, ['EN_RUTA','ENTREGADA','CANCELADA']))
        <form action="{{ route('admin.sales.cancel',$sale) }}" method="POST" class="inline form-cancel-sale">@csrf
            <x-wire-button type="button" red xs onclick="Swal.fire({title:'¿Cancelar esta nota?',text:'Revierte el inventario y, si aplica, la CxC o el efectivo de caja.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, cancelar',confirmButtonColor:'#dc2626',cancelButtonText:'Volver'}).then(r=>{if(r.isConfirmed) this.closest('form').submit();})">Cancelar</x-wire-button>
        </form>
    @endif
</div>
