<x-admin-layout
    title="Enviar remisión de pedido"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Pedidos','url'=>route('admin.sales-orders.index')],
        ['name'=>'Enviar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales-orders.edit',$order) }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
    </x-slot>

    <x-wire-card>
        <div class="mb-4">
            <div class="text-lg font-semibold">Pedido #{{ $order->id }}</div>
            <div class="text-sm text-gray-600">Cliente: {{ $order->client->nombre ?? '-' }}</div>
            <div class="text-sm text-gray-600">Total: {{ number_format((float)$order->total, 2) }} {{ $order->moneda ?? 'MXN' }}</div>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 p-3 text-rose-800">
                <ul class="list-disc ml-5 text-sm">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.sales-orders.send',$order) }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Canales --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Canales</label>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="channels[]" value="email" class="mr-2"
                                   {{ in_array('email', old('channels', ['email','whatsapp'])) ? 'checked' : '' }}>
                            Email
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="channels[]" value="whatsapp" class="mr-2"
                                   {{ in_array('whatsapp', old('channels', ['email','whatsapp'])) ? 'checked' : '' }}>
                            WhatsApp
                        </label>
                    </div>
                </div>

                {{-- Email --}}
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo del cliente</label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email', $clientEmail) }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="cliente@correo.com">
                    <p class="text-xs text-gray-500">Si lo dejas vacío, se usará el correo del cliente si existe.</p>
                </div>

                {{-- Teléfono --}}
                <div class="space-y-2">
                    <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono (WhatsApp)</label>
                    <input type="text" id="telefono" name="telefono"
                           value="{{ old('telefono', $clientPhone) }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="5219990000000">
                    <p class="text-xs text-gray-500">Formato sugerido: 52 + lada + número (sin espacios).</p>
                </div>

                {{-- Mensaje --}}
                <div class="space-y-2 md:col-span-2">
                    <label for="mensaje" class="block text-sm font-medium text-gray-700">Mensaje</label>
                    <textarea id="mensaje" name="mensaje" rows="3"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Te adjunto la remisión del pedido 📎">{{ old('mensaje', 'Te adjunto la remisión del pedido 📎') }}</textarea>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="inline-flex px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Enviar
                </button>
            </div>
        </form>
    </x-wire-card>

    <x-wire-card class="mt-4">
        <div class="flex items-center gap-2">
            <x-wire-button href="{{ route('admin.sales-orders.pdf',$order) }}" gray target="_blank>">Ver PDF</x-wire-button>
            <x-wire-button href="{{ route('admin.sales-orders.pdf.download',$order) }}" gray>Descargar PDF</x-wire-button>
        </div>
    </x-wire-card>
</x-admin-layout>
