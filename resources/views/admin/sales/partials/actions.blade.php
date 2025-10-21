<div class="flex items-center space-x-2">
    {{-- Editar --}}
    <x-wire-button href="{{ route('admin.sales.edit',$sale) }}" blue xs>Editar</x-wire-button>

    {{-- PDF --}}
    <x-wire-button href="{{ route('admin.sales.pdf',$sale) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
    <x-wire-button href="{{ route('admin.sales.pdf.download',$sale) }}" gray xs>Descargar PDF</x-wire-button>

    {{-- Envío (formulario de envío) --}}
    <x-wire-button href="{{ route('admin.sales.send.form',$sale) }}" violet xs>Enviar</x-wire-button>

    {{-- Acciones por estado --}}
    @if($sale->status === 'ABIERTA')
        <form action="{{ route('admin.sales.close',$sale) }}" method="POST" class="inline">@csrf
            <x-wire-button type="submit" green xs>Cerrar</x-wire-button>
        </form>
        <form action="{{ route('admin.sales.cancel',$sale) }}" method="POST" class="inline">@csrf
            <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
        </form>
    @endif
</div>
