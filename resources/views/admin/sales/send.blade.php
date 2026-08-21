<x-admin-layout
    title="Enviar nota de venta"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Notas de venta','url'=>route('admin.sales.index')],
        ['name'=>'Enviar'],
    ]"
>
    <x-slot name="action">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.sales.pdf',$sale) }}" target="_blank"
               class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">👁 Ver carta</a>
            <a href="{{ route('admin.sales.ticket.pdf',$sale) }}" target="_blank"
               class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">🧾 Ver ticket</a>
            <a href="{{ route('admin.sales.edit',$sale) }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        </div>
    </x-slot>

    <x-wire-card>
        <form method="POST" action="{{ route('admin.sales.send',$sale) }}" class="space-y-4">
            @csrf

            <div class="space-y-2">
                <div class="font-semibold">Canales</div>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="channels[]" value="email" class="rounded border-gray-300" checked>
                    Email
                </label>
                <label class="inline-flex items-center gap-2 ml-4">
                    <input type="checkbox" name="channels[]" value="whatsapp" class="rounded border-gray-300">
                    WhatsApp
                </label>
            </div>

            <div class="space-y-2">
                <div class="font-semibold">Formato</div>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="formato" value="carta" class="border-gray-300" checked>
                    📄 Carta
                </label>
                <label class="inline-flex items-center gap-2 ml-4">
                    <input type="radio" name="formato" value="ticket" class="border-gray-300">
                    🧾 Ticket 80mm
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-wire-input label="Email" name="email" type="email" value="{{ old('email',$clientEmail) }}" />
                <x-wire-input label="Teléfono WhatsApp" name="telefono" value="{{ old('telefono',$clientPhone) }}" />
            </div>

            <div>
                <x-wire-textarea label="Mensaje" name="mensaje">Te adjunto la nota de venta 📎</x-wire-textarea>
            </div>

            <div>
                <x-wire-button type="submit" violet>Enviar</x-wire-button>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>
