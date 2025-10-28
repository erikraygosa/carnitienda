{{-- resources/views/admin/pos/ticket-pdf.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ticket POS #{{ $sale->id }}</title>
  <style>
    @page { margin: 8px 10px; }           /* márgenes chicos tipo ticket */
    body  { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
    .center { text-align: center; }
    .right  { text-align: right; }
    .small  { font-size: 10px; }
    hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 2px 0; vertical-align: top; }
  </style>
</head>
<body>
  <div class="center">
    <div style="font-weight:700;">Mi Tienda S.A. de C.V.</div>
    <div class="small">RFC: XAXX010101000</div>
    <div class="small">Calle 123, Col. Centro</div>
    <div class="small">Tel: 555-555-5555</div>
    <hr>
    <div class="small">Folio: {{ $sale->id }}</div>
    <div class="small">Fecha: {{ $sale->fecha->format('Y-m-d H:i') }}</div>
    @if($sale->client)
      <div class="small">Cliente: {{ $sale->client->nombre }}</div>
    @endif
    <hr>
  </div>

  <table>
    <thead>
      <tr>
        <th style="text-align:left;">Prod</th>
        <th class="right">Cant</th>
        <th class="right">P.Unit</th>
        <th class="right">Imp.</th>
        <th class="right">Impte</th>
      </tr>
    </thead>
    <tbody>
      @foreach($sale->items as $it)
        <tr>
          <td>{{ \Illuminate\Support\Str::limit($it->product->nombre ?? ('#'.$it->product_id), 18) }}</td>
          <td class="right">{{ number_format($it->cantidad,3) }}</td>
          <td class="right">{{ number_format($it->precio_unitario,2) }}</td>
          <td class="right">{{ number_format($it->impuestos,2) }}</td>
          <td class="right">{{ number_format($it->importe,2) }}</td>
        </tr>
        @if($it->descuento > 0)
          <tr>
            <td colspan="5" class="right small">Desc: -{{ number_format($it->descuento,2) }}</td>
          </tr>
        @endif
      @endforeach
    </tbody>
  </table>

  <hr>

  <table>
    <tr>
      <td>Subtotal</td>
      <td class="right">${{ number_format($sale->subtotal,2) }}</td>
    </tr>
    <tr>
      <td>Descuento</td>
      <td class="right">- ${{ number_format($sale->descuento,2) }}</td>
    </tr>
    <tr>
      <td>Impuestos</td>
      <td class="right">${{ number_format($sale->impuestos,2) }}</td>
    </tr>
    <tr>
      <td style="font-weight:700;">TOTAL</td>
      <td class="right" style="font-weight:700;">${{ number_format($sale->total,2) }}</td>
    </tr>
    <tr>
      <td>Método</td>
      <td class="right">{{ $sale->metodo_pago }}{{ $sale->referencia ? ' • '.$sale->referencia : '' }}</td>
    </tr>
  </table>

  <hr>
  <div class="center small">¡Gracias por su compra!</div>
</body>
</html>
