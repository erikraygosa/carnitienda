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

    {{-- Ticket 80mm --}}
    <div id="ticket-wrap" class="mx-auto bg-white shadow p-4" style="width:80mm;max-width:100%;">

        {{-- Empresa --}}
        <div class="text-center">
            @if($company?->logo_path)
                <img src="{{ Storage::url($company->logo_path) }}"
                     alt="Logo"
                     style="max-width:55mm;max-height:18mm;object-fit:contain;margin-bottom:4px;">
                <br>
            @endif
            <div style="font-weight:600;font-size:13px;">
                {{ $company?->nombre_comercial ?? $company?->razon_social ?? 'Mi Tienda' }}
            </div>
            @if($company?->razon_social && $company?->nombre_comercial)
                <div style="font-size:10px;">{{ $company->razon_social }}</div>
            @endif
            @if($company?->rfc)
                <div style="font-size:10px;">RFC: {{ $company->rfc }}</div>
            @endif
            @if($company?->telefono)
                <div style="font-size:10px;">Tel: {{ $company->telefono }}</div>
            @endif
            @if($company?->email)
                <div style="font-size:10px;">{{ $company->email }}</div>
            @endif
            @if($company?->sitio_web)
                <div style="font-size:10px;">{{ $company->sitio_web }}</div>
            @endif
        </div>

        <hr class="my-2" style="border:0;border-top:1px dashed #333;">

        <div class="text-center">
            <div style="font-size:11px;font-weight:600;">Ticket #{{ $sale->id }}</div>
            <div style="font-size:10px;">{{ $sale->fecha->format('d/m/Y H:i') }}</div>
            @if($sale->client)
                <div style="font-size:10px;">Cliente: {{ $sale->client->nombre }}</div>
            @endif
        </div>

        <hr class="my-2" style="border:0;border-top:1px dashed #333;">

        <table style="width:100%;border-collapse:collapse;font-size:11px;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:2px 0;">Producto</th>
                    <th style="text-align:right;padding:2px 0;">Cant</th>
                    <th style="text-align:right;padding:2px 0;">P.U.</th>
                    <th style="text-align:right;padding:2px 0;">Imp.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $it)
                <tr>
                    <td style="padding:2px 2px 2px 0;">
                        {{ \Illuminate\Support\Str::limit($it->product?->nombre ?? '#'.$it->product_id, 20) }}
                    </td>
                    <td style="text-align:right;padding:2px 0;">{{ number_format($it->cantidad, 3) }}</td>
                    <td style="text-align:right;padding:2px 0;">${{ number_format($it->precio_unitario, 2) }}</td>
                    <td style="text-align:right;padding:2px 0;">${{ number_format($it->importe, 2) }}</td>
                </tr>
                @if($it->descuento > 0)
                <tr>
                    <td colspan="4" style="text-align:right;font-size:10px;color:#dc2626;">
                        Desc: -${{ number_format($it->descuento, 2) }}
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>

        <hr class="my-2" style="border:0;border-top:1px dashed #333;">

        <table style="width:100%;border-collapse:collapse;font-size:11px;">
            <tr>
                <td>Subtotal</td>
                <td style="text-align:right;">${{ number_format($sale->subtotal, 2) }}</td>
            </tr>
            @if($sale->descuento > 0)
            <tr>
                <td>Descuento</td>
                <td style="text-align:right;color:#dc2626;">-${{ number_format($sale->descuento, 2) }}</td>
            </tr>
            @endif
            @if($sale->impuestos > 0)
            <tr>
                <td>Impuestos</td>
                <td style="text-align:right;">${{ number_format($sale->impuestos, 2) }}</td>
            </tr>
            @endif
            <tr style="font-weight:bold;font-size:13px;border-top:1px solid #333;">
                <td style="padding-top:4px;">TOTAL</td>
                <td style="text-align:right;padding-top:4px;">${{ number_format($sale->total, 2) }}</td>
            </tr>
            <tr>
                <td style="font-size:10px;color:#555;">
                    {{ $sale->metodo_pago }}{{ $sale->referencia ? ' · '.$sale->referencia : '' }}
                </td>
                <td></td>
            </tr>
        </table>

        <hr class="my-2" style="border:0;border-top:1px dashed #333;">
        <div style="text-align:center;font-size:10px;">¡Gracias por su compra!</div>
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