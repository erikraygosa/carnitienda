<x-admin-layout
    title="Enviar remisión de pedido"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Pedidos','url'=>route('admin.sales-orders.index')],
        ['name'=>'Enviar'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales-orders.edit',$order) }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
    </x-slot>

    <x-wire-card>
        {{-- Info pedido --}}
        <div class="mb-6 p-4 rounded-lg bg-gray-50 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Pedido</p>
                    <p class="text-lg font-semibold text-indigo-700">{{ $order->folio ?? '#'.$order->id }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Cliente</p>
                    <p class="font-medium text-gray-700">{{ $order->client?->nombre ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Total</p>
                    <p class="font-semibold text-gray-800">${{ number_format((float)$order->total, 2) }} {{ $order->moneda ?? 'MXN' }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.sales-orders.pdf',$order) }}" target="_blank"
                       class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                        👁 Ver carta
                    </a>
                    <a href="{{ route('admin.sales-orders.ticket.pdf',$order) }}" target="_blank"
                       class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                        🧾 Ver ticket
                    </a>
                </div>
            </div>
        </div>

        @if($errors->any())
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 p-3 text-rose-800">
            <ul class="list-disc ml-5 text-sm">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.sales-orders.send',$order) }}" class="space-y-5">
            @csrf

            {{-- Canales --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Enviar por</label>
                <div class="flex items-center gap-6">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="channels[]" value="email" id="ch-email"
                               class="rounded border-gray-300 text-indigo-600"
                               {{ in_array('email', old('channels', [])) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">📧 Email</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="channels[]" value="whatsapp" id="ch-whatsapp"
                               class="rounded border-gray-300 text-indigo-600"
                               {{ in_array('whatsapp', old('channels', ['whatsapp'])) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">💬 WhatsApp</span>
                    </label>
                </div>
            </div>

            {{-- Formato del documento --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Formato</label>
                <div class="flex items-center gap-6">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="formato" value="carta" id="fmt-carta"
                               class="border-gray-300 text-indigo-600"
                               {{ old('formato') === 'carta' ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">📄 Carta (remisión)</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="formato" value="ticket" id="fmt-ticket"
                               class="border-gray-300 text-indigo-600"
                               {{ old('formato', 'ticket') === 'ticket' ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">🧾 Ticket 80mm</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Correo del cliente
                    </label>
                    <input type="email" name="email"
                           value="{{ old('email', $clientEmail) }}"
                           placeholder="cliente@correo.com"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">
                        Si lo dejas vacío se usará el correo registrado del cliente.
                    </p>
                </div>

                {{-- Teléfono --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Teléfono (WhatsApp)
                    </label>
                    <input type="text" name="telefono"
                           value="{{ old('telefono', $clientPhone) }}"
                           placeholder="5219990000000"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">
                        Formato: 52 + lada + número (sin espacios ni guiones).
                    </p>
                </div>

                {{-- Mensaje --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje</label>
                    <textarea name="mensaje" rows="3"
                              placeholder="Te adjunto la remisión del pedido 📎"
                              class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('mensaje', 'Te adjunto la remisión del pedido 📎') }}</textarea>
                </div>

            </div>

            <div id="resumen-envio" class="text-sm text-gray-600 bg-indigo-50 border border-indigo-100 rounded-md px-3 py-2"></div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    Enviar
                </button>
            </div>
        </form>
    </x-wire-card>

    <script>
    (function () {
        var chEmail   = document.getElementById('ch-email');
        var chWa      = document.getElementById('ch-whatsapp');
        var inpEmail  = document.querySelector('input[name="email"]');
        var inpTel    = document.querySelector('input[name="telefono"]');
        var fmtCarta  = document.getElementById('fmt-carta');
        var resumen   = document.getElementById('resumen-envio');

        function actualizar() {
            var destinos = [];
            if (chEmail.checked) {
                destinos.push('📧 Email a ' + (inpEmail.value.trim() || '(sin correo capturado)'));
            }
            if (chWa.checked) {
                destinos.push('💬 WhatsApp a ' + (inpTel.value.trim() || '(sin teléfono capturado)'));
            }
            var formato = fmtCarta.checked ? 'formato Carta (remisión)' : 'formato Ticket 80mm';

            resumen.textContent = destinos.length
                ? 'Se enviará por: ' + destinos.join(' · ') + ' — ' + formato
                : 'Selecciona al menos un canal de envío.';
        }

        [chEmail, chWa, fmtCarta, document.getElementById('fmt-ticket')].forEach(function (el) {
            el.addEventListener('change', actualizar);
        });
        [inpEmail, inpTel].forEach(function (el) {
            el.addEventListener('input', actualizar);
        });

        actualizar();
    })();
    </script>
</x-admin-layout>