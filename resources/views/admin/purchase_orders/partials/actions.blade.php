<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.purchase-orders.edit', $order) }}" blue xs>
        Editar
    </x-wire-button>

    @if($order->status === 'draft')
        <form action="{{ route('admin.purchase-orders.approve', $order) }}" method="POST">
            @csrf
            <x-wire-button type="submit" green xs>Aprobar</x-wire-button>
        </form>

        <form action="{{ route('admin.purchase-orders.destroy', $order) }}" method="POST" class="delete-form">
            @csrf @method('DELETE')
            <x-wire-button type="submit" red xs>Eliminar</x-wire-button>
        </form>
    @endif

    @if(in_array($order->status, ['draft','approved']))
        <form action="{{ route('admin.purchase-orders.cancel', $order) }}" method="POST">
            @csrf
            <x-wire-button type="submit" gray xs>Cancelar</x-wire-button>
        </form>
    @endif

    {{-- ✅ Botón para crear compra desde OC aprobada --}}
    @if($order->status === 'approved')
        <x-wire-button
            href="{{ route('admin.purchases.create', ['purchase_order_id' => $order->id]) }}"
            violet
            xs
        >
            Crear compra
        </x-wire-button>
    @endif
</div>
