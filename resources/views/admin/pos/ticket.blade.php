{{-- resources/views/admin/pos/ticket.blade.php --}}
<x-admin-layout
  :title="'Ticket POS #'.$sale->id"
  :breadcrumbs="[
    ['name'=>'Dashboard','url'=>route('admin.dashboard')],
    ['name'=>'POS'],
    ['name'=>'Ticket'],
  ]"
>
  <x-slot name="action" class="no-print">
    <x-wire-button href="{{ route('admin.pos.create') }}" gray class="no-print">
      Regresar al POS
    </x-wire-button>

    @php
      $pdfUrl     = route('admin.pos.ticket.pdf', $sale);
      $cliente    = $sale->client->nombre ?? 'Cliente';
      $monto      = number_format($sale->total, 2);
      $mensaje    = "Hola {$cliente}, te compartimos tu ticket #{$sale->id} por \${$monto}. Puedes descargarlo aquí: {$pdfUrl}";
      // Tel sugerido si existe (se quedan últimos 10 dígitos)
      $telSugerido = preg_replace('/\D+/', '', $sale->client->telefono ?? '');
      if (strlen($telSugerido) > 10) { $telSugerido = substr($telSugerido, -10); }
    @endphp

    {{-- Enviar por WhatsApp (captura teléfono) --}}
    <x-wire-button
      type="button"
      id="btn-wa"
      green
      class="no-print"
      data-msg="{{ $mensaje }}"
      data-default="{{ $telSugerido }}"
    >
      Enviar por WhatsApp
    </x-wire-button>

    <x-wire-button href="{{ route('admin.pos.ticket.pdf', $sale) }}" outline class="no-print"
  target="_blank" rel="noopener noreferrer">
  PDF
</x-wire-button>

  </x-slot>

  {{-- Contenedor tipo ticket 80mm --}}
  <div id="ticket-wrap" class="mx-auto bg-white shadow p-4" style="width: 80mm; max-width: 100%;">
    <div class="text-center">
      <div class="font-semibold">Mi Tienda S.A. de C.V.</div>
      <div class="text-xs">RFC: XAXX010101000</div>
      <div class="text-xs">Calle 123, Col. Centro</div>
      <div class="text-xs">Tel: 555-555-5555</div>
      <hr class="my-2">
      <div class="text-xs">Folio: {{ $sale->id }}</div>
      <div class="text-xs">Fecha: {{ $sale->fecha->format('Y-m-d H:i') }}</div>
      @if($sale->client)
        <div class="text-xs">Cliente: {{ $sale->client->nombre }}</div>
      @endif
      <hr class="my-2">
    </div>

    {{-- Partidas --}}
    <table class="w-full text-xs">
      <thead>
        <tr>
          <th class="text-left">Prod</th>
          <th class="text-right">Cant</th>
          <th class="text-right">P.Unit</th>
          <th class="text-right">Imp.</th>
          <th class="text-right">Impte</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sale->items as $it)
          <tr>
            <td class="pr-1">{{ \Illuminate\Support\Str::limit($it->product->nombre ?? ('#'.$it->product_id), 18) }}</td>
            <td class="text-right">{{ number_format($it->cantidad,3) }}</td>
            <td class="text-right">{{ number_format($it->precio_unitario,2) }}</td>
            <td class="text-right">{{ number_format($it->impuestos,2) }}</td>
            <td class="text-right">{{ number_format($it->importe,2) }}</td>
          </tr>
          @if($it->descuento > 0)
            <tr><td colspan="5" class="text-right text-[10px]">Desc: -{{ number_format($it->descuento,2) }}</td></tr>
          @endif
        @endforeach
      </tbody>
    </table>

    <hr class="my-2">

    {{-- Totales --}}
    <table class="w-full text-xs">
      <tr>
        <td class="text-left">Subtotal</td>
        <td class="text-right">${{ number_format($sale->subtotal,2) }}</td>
      </tr>
      <tr>
        <td class="text-left">Descuento</td>
        <td class="text-right">- ${{ number_format($sale->descuento,2) }}</td>
      </tr>
      <tr>
        <td class="text-left">Impuestos</td>
        <td class="text-right">${{ number_format($sale->impuestos,2) }}</td>
      </tr>
      <tr>
        <td class="text-left font-semibold">TOTAL</td>
        <td class="text-right font-semibold">${{ number_format($sale->total,2) }}</td>
      </tr>
      <tr>
        <td class="text-left">Método</td>
        <td class="text-right">{{ $sale->metodo_pago }}{{ $sale->referencia ? ' • '.$sale->referencia : '' }}</td>
      </tr>
    </table>

    <hr class="my-2">
    <div class="text-center text-[11px]">
      ¡Gracias por su compra!
    </div>
  </div>

  @push('css')
  <style>
    @media print {
      .no-print { display: none !important; }
      #ticket-wrap { box-shadow: none !important; padding: 0 !important; }
      body { background: #fff !important; }
    }
  </style>
  @endpush

  @push('js')
  <script>
    (function () {
      const btn = document.getElementById('btn-wa');
      if (!btn) return;

      const defaultTel = btn.dataset.default || '';
      const msg        = btn.dataset.msg || '';

      // Código de país por defecto (52 = México). Cambia según tu operación.
      const COUNTRY_CODE = '52';

      const onlyDigits = (v) => (v || '').replace(/\D+/g, '');

      btn.addEventListener('click', async () => {
        if (window.Swal) {
          const { value: tel } = await Swal.fire({
            title: 'Enviar por WhatsApp',
            input: 'text',
            inputLabel: 'Número de teléfono (10 dígitos)',
            inputValue: defaultTel,
            inputAttributes: {
              maxlength: 14,
              autocapitalize: 'off',
              autocorrect: 'off',
              inputmode: 'numeric',
            },
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
            preConfirm: (val) => {
              const digits = onlyDigits(val);
              if (digits.length !== 10) {
                Swal.showValidationMessage('Ingresa un número de 10 dígitos');
                return false;
              }
              return digits;
            }
          });

          if (!tel) return;

          const digits = onlyDigits(tel);
          const full   = COUNTRY_CODE + digits; // ej: 52 + 10 dígitos
          const url    = `https://wa.me/${full}?text=${encodeURIComponent(msg)}`;
          window.open(url, '_blank');
        } else {
          // Fallback si no hubiera SweetAlert2
          let tel = prompt('Número de teléfono (10 dígitos):', defaultTel);
          if (!tel) return;
          tel = onlyDigits(tel);
          if (tel.length !== 10) { alert('Ingresa un número válido de 10 dígitos'); return; }
          const url = `https://wa.me/${COUNTRY_CODE}${tel}?text=${encodeURIComponent(msg)}`;
          window.open(url, '_blank');
        }
      });
    })();
  </script>
  @endpush
</x-admin-layout>
