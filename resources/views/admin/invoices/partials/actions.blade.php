<div class="flex items-center space-x-2">

    <x-wire-button href="{{ route('admin.invoices.edit', $invoice) }}" blue xs>Editar</x-wire-button>

    <x-wire-button href="{{ route('admin.invoices.pdf', $invoice) }}" gray outline xs target="_blank">Ver PDF</x-wire-button>

    <x-wire-button href="{{ route('admin.invoices.download', $invoice) }}" gray xs>Descargar PDF</x-wire-button>

    @if($invoice->estatus === 'BORRADOR')
        <form action="{{ route('admin.invoices.stamp', $invoice) }}" method="POST" class="inline">
            @csrf
            <x-wire-button type="submit" emerald xs>Timbrar</x-wire-button>
        </form>
    @endif

    @if($invoice->estatus === 'TIMBRADA')
        <x-wire-button href="{{ route('admin.invoices.send.form', $invoice) }}" violet xs>Enviar</x-wire-button>

        <form action="{{ route('admin.invoices.cancel', $invoice) }}" method="POST" class="inline"
              onsubmit="return confirm('¿Cancelar esta factura en el SAT?')">
            @csrf
            <x-wire-button type="submit" red xs>Cancelar CFDI</x-wire-button>
        </form>
    @endif

</div>
