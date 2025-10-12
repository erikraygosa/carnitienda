<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.clients.edit', $client) }}" blue xs>Editar</x-wire-button>

    <form action="{{ route('admin.clients.destroy', $client) }}" method="POST" class="delete-form inline">
        @csrf @method('DELETE')
        <x-wire-button type="submit" red xs>Desactivar</x-wire-button>
    </form>
</div>
