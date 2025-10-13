<x-admin-layout
    title="Enviar cotización #{{ $quote->id }}"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cotizaciones','url'=>route('admin.quotes.index')],
        ['name'=>'Enviar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.quotes.edit', $quote) }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
    </x-slot>

    <x-wire-card>
        <form method="POST" action="{{ route('admin.quotes.send', $quote) }}" class="space-y-6">
            @csrf

            @if ($errors->any())
                <div class="text-red-600">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <h3 class="text-base font-semibold">Canales de envío</h3>
                <div class="mt-2 flex gap-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="channels[]" value="email" class="rounded border-gray-300" checked>
                        <span>Email</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="channels[]" value="whatsapp" class="rounded border-gray-300">
                        <span>WhatsApp</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input type="email" name="email" value="{{ old('email', $clientEmail) }}"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Teléfono (WhatsApp)</label>
                    <input type="text" name="telefono" value="{{ old('telefono', $clientPhone) }}"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p class="text-xs text-gray-500 mt-1">Formato con país, p. ej. 5219992759224</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Mensaje (WhatsApp)</label>
                    <textarea name="mensaje" rows="3"
                              class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Te adjunto la cotización 📎">{{ old('mensaje') }}</textarea>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
                    Enviar ahora
                </button>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>
