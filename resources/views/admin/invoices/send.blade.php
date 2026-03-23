<x-admin-layout
    title="Enviar factura"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas','url'=>route('admin.invoices.index')],
        ['name'=>'Enviar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.edit', $invoice) }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
    </x-slot>

    <x-wire-card>
        <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Correo del destinatario <span class="text-red-500">*</span>
                </label>
                <input type="email" name="email" value="{{ old('email', $clientEmail) }}" required
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje (opcional)</label>
                <textarea name="mensaje" rows="4"
                          class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                          placeholder="Adjunto encontrarás tu factura...">{{ old('mensaje') }}</textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Enviar factura
                </button>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>