<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.purchases.edit', $purchase) }}" blue xs>Editar</x-wire-button>

    @if($purchase->status === 'draft')
        <form action="{{ route('admin.purchases.receive', $purchase) }}" method="POST">
            @csrf
            <x-wire-button type="submit" green xs>Recibir</x-wire-button>
        </form>

        <form action="{{ route('admin.purchases.destroy', $purchase) }}" method="POST" class="delete-form">
            @csrf @method('DELETE')
            <x-wire-button type="submit" red xs>Eliminar</x-wire-button>
        </form>

        <form action="{{ route('admin.purchases.cancel', $purchase) }}" method="POST">
            @csrf
            <x-wire-button type="submit" gray xs>Cancelar</x-wire-button>
        </form>
    @endif
</div>
