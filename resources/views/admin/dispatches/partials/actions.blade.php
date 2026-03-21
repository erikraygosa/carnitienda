<div class="flex items-center gap-1">
    <a href="{{ route('admin.dispatches.edit', $dispatch) }}"
       class="px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50">
        Ver
    </a>

    @if(in_array($dispatch->status, ['PLANEADO','PREPARANDO','CARGADO','EN_RUTA']))
        <a href="{{ route('admin.dispatches.print.ruta', $dispatch) }}"
           target="_blank"
           class="px-2 py-1 text-xs rounded border border-teal-300 text-teal-600 hover:bg-teal-50">
            Ruta
        </a>
    @endif

    @if($dispatch->status === 'CERRADO')
        <a href="{{ route('admin.dispatches.print.liquidacion', $dispatch) }}"
           target="_blank"
           class="px-2 py-1 text-xs rounded border border-blue-300 text-blue-600 hover:bg-blue-50">
            Liquidación
        </a>
    @endif

    @if(!in_array($dispatch->status, ['EN_RUTA','ENTREGADO','CERRADO']))
        <form method="POST"
              action="{{ route('admin.dispatches.destroy', $dispatch) }}"
              class="delete-form inline">
            @csrf @method('DELETE')
            <button type="submit"
                    class="px-2 py-1 text-xs rounded border border-red-200 text-red-500 hover:bg-red-50">
                Eliminar
            </button>
        </form>
    @endif
</div>