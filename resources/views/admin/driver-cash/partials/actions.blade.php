<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.driver-cash.show', $register) }}" blue xs>Ver</x-wire-button>

    @if($register->estatus === 'ABIERTO')
    <form action="{{ route('admin.driver-cash.close', $register) }}" method="POST" class="close-form inline">
        @csrf
        <x-wire-button type="submit" red xs>Cerrar</x-wire-button>
    </form>
    @endif
</div>
