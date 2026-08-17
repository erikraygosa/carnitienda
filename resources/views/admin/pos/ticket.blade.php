<x-admin-layout
    :title="'Ticket POS #'.$sale->id"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'POS'],
        ['name'=>'Ticket #'.$sale->id],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.pos.create') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            ← Regresar al POS
        </a>
        <a href="{{ route('admin.pos.ticket.pdf', $sale) }}" target="_blank"
           class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            🖨 Imprimir / PDF
        </a>
        <button id="btn-wa"
            data-tel="{{ preg_replace('/\D+/', '', $sale->client?->telefono ?? '') }}"
            data-wa-url="{{ route('admin.pos.ticket.whatsapp', $sale) }}"
            class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-green-600 text-white hover:bg-green-700">
            💬 WhatsApp
        </button>
    </x-slot>

    <div class="mx-auto bg-white shadow" style="width:72mm;max-width:100%;">
        @include('admin.pos.partials.ticket-body', ['sale' => $sale, 'company' => $company, 'forPdf' => false])
    </div>

    {{-- Script directo, sin @push --}}
    <script>
    (function () {
        var btn = document.getElementById('btn-wa');
        if (!btn) return;

        var COUNTRY_CODE = '52';
        function onlyDigits(v) { return (v || '').replace(/\D+/g, ''); }

        btn.addEventListener('click', async function () {
            var tel   = btn.dataset.tel;
            var waUrl = btn.dataset.waUrl;

            var result = await Swal.fire({
                title: 'Enviar por WhatsApp',
                input: 'text',
                inputLabel: 'Número de teléfono (10 dígitos)',
                inputValue: tel.slice(-10),
                inputAttributes: { maxlength: 14, inputmode: 'numeric', autocomplete: 'off' },
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                preConfirm: function(val) {
                    var digits = onlyDigits(val);
                    if (digits.length !== 10) {
                        Swal.showValidationMessage('Ingresa un número de 10 dígitos');
                        return false;
                    }
                    return COUNTRY_CODE + digits;
                }
            });

            if (!result.value) return;

            Swal.fire({
                title: 'Enviando...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            try {
                var res = await fetch(waUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ telefono: result.value })
                });

                var data = await res.json();

                if (data.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Enviado!',
                        text: 'Ticket enviado por WhatsApp.',
                        timer: 2500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo enviar.' });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: e.message });
            }
        });
    })();
    </script>

</x-admin-layout>
