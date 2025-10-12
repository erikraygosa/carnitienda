<div class="flex items-center gap-2">
    <x-wire-button sm href="{{ route('admin.products.edit', $product) }}" gray>
        Editar
    </x-wire-button>

    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="delete-form inline">
        @csrf
        @method('DELETE')
        <x-wire-button sm type="submit" red outline>
            Eliminar
        </x-wire-button>
    </form>
</div>
