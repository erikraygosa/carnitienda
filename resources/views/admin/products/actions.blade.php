<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.products.edit', $product) }}" blue xs>
        Editar
    </x-wire-button>

    <form action="{{ route('admin.products.destroy', $product) }}"
          method="POST"
          class="delete-form">
        @csrf
        @method('DELETE')
        <x-wire-button type="submit" red xs>
            Eliminar
        </x-wire-button>
    </form>
</div>
