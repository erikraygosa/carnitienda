<div class="flex items-center space-x-2">
    <x-wire-button href="{{ route('admin.invoices.edit', $invoice) }}" blue xs>Editar</x-wire-button>

    @if($invoice->status === 'BORRADOR')
        <x-wire-button href="{{ route('admin.invoices.preview', $invoice) }}" gray outline xs target="_blank">Previsualizar</x-wire-button>
        <form action="{{ route('admin.invoices.timbrar', $invoice) }}" method="POST" class="inline">@csrf
            <x-wire-button type="submit" emerald xs>Timbrar</x-wire-button>
        </form>
    @endif

    @if($invoice->status === 'TIMBRADA')
        <x-wire-button href="{{ route('admin.invoices.pdf', $invoice) }}" gray outline xs target="_blank">PDF</x-wire-button>
        <x-wire-button href="{{ route('admin.invoices.xml', $invoice) }}" gray xs>XML</x-wire-button>
        <form action="{{ route('admin.invoices.cancel', $invoice) }}" method="POST" class="inline">@csrf
            <x-wire-button type="submit" red xs>Cancelar</x-wire-button>
        </form>
    @endif

    <form action="{{ route('admin.invoices.destroy', $invoice) }}" method="POST" class="inline delete-form">
        @csrf @method('DELETE')
        <x-wire-button type="submit" red outline xs>Eliminar</x-wire-button>
    </form>
</div>
