<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.warehouses.edit', $warehouse) }}" blue xs>
        Editar
    </x-wire-button>

    <form action="{{ route('admin.warehouses.destroy', $warehouse) }}" method="POST" class="delete-form">
        @csrf
        @method('DELETE')
        <x-wire-button type="submit" red xs>
            Eliminar
        </x-wire-button>
    </form>
</div>
