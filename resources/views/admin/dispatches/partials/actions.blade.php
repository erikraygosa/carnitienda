<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.dispatches.edit',$dispatch) }}" blue xs>Editar</x-wire-button>

    @if(in_array($dispatch->status,['PLANEADO']))
        <form action="{{ route('admin.dispatches.preparar',$dispatch) }}" method="POST">@csrf
            <x-wire-button type="submit" sky xs>Preparar</x-wire-button>
        </form>
    @endif
    @if(in_array($dispatch->status,['PLANEADO','PREPARANDO']))
        <form action="{{ route('admin.dispatches.cargar',$dispatch) }}" method="POST">@csrf
            <x-wire-button type="submit" amber xs>Cargado</x-wire-button>
        </form>
    @endif
    @if(in_array($dispatch->status,['CARGADO']))
        <form action="{{ route('admin.dispatches.enruta',$dispatch) }}" method="POST">@csrf
            <x-wire-button type="submit" violet xs>En ruta</x-wire-button>
        </form>
    @endif
    @if(in_array($dispatch->status,['EN_RUTA']))
        <form action="{{ route('admin.dispatches.entregar',$dispatch) }}" method="POST">@csrf
            <x-wire-button type="submit" emerald xs>Entregar</x-wire-button>
        </form>
    @endif
    @if(in_array($dispatch->status,['ENTREGADO']))
        <form action="{{ route('admin.dispatches.cerrar',$dispatch) }}" method="POST">@csrf
            <x-wire-button type="submit" blue xs>Cerrar</x-wire-button>
        </form>
    @endif

    <form action="{{ route('admin.dispatches.cancelar',$dispatch) }}" method="POST">@csrf
        <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
    </form>

    <form action="{{ route('admin.dispatches.destroy',$dispatch) }}" method="POST" class="inline delete-form">
        @csrf @method('DELETE')
        <x-wire-button type="submit" red outline xs>Eliminar</x-wire-button>
    </form>
</div>
