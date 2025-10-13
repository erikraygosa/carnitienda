<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.quotes.edit',$quote) }}" blue xs>Editar</x-wire-button>

    {{-- PDF --}}
    <x-wire-button href="{{ route('admin.quotes.pdf',$quote) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>
    <x-wire-button href="{{ route('admin.quotes.pdf.download',$quote) }}" gray xs>Descargar PDF</x-wire-button>

    {{-- Envío (ir al formulario de envío) --}}
    <x-wire-button href="{{ route('admin.quotes.send.form',$quote) }}" violet xs>Enviar</x-wire-button>

    @if(in_array($quote->status, ['BORRADOR','RECHAZADA']))
        <form action="{{ route('admin.quotes.destroy',$quote) }}" method="POST" class="delete-form">
            @csrf @method('DELETE')
            <x-wire-button type="submit" red xs>Eliminar</x-wire-button>
        </form>
    @endif

    @if($quote->status === 'BORRADOR')
        <form action="{{ route('admin.quotes.approve',$quote) }}" method="POST">
            @csrf
            <x-wire-button type="submit" green xs>Aprobar</x-wire-button>
        </form>
        <form action="{{ route('admin.quotes.reject',$quote) }}" method="POST">
            @csrf
            <x-wire-button type="submit" amber xs>Rechazar</x-wire-button>
        </form>
    @elseif($quote->status === 'ENVIADA')
        <form action="{{ route('admin.quotes.approve',$quote) }}" method="POST">
            @csrf
            <x-wire-button type="submit" green xs>Aprobar</x-wire-button>
        </form>
        <form action="{{ route('admin.quotes.reject',$quote) }}" method="POST">
            @csrf
            <x-wire-button type="submit" amber xs>Rechazar</x-wire-button>
        </form>
    @endif

    @if($quote->status !== 'CONVERTIDA')
        <form action="{{ route('admin.quotes.cancel',$quote) }}" method="POST">
            @csrf
            <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
        </form>
    @endif
</div>
