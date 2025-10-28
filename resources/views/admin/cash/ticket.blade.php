{{-- resources/views/admin/cash/ticket.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ticket Caja #{{ $register->id }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root { --w: 80mm; }
    body { font-family: Arial, Helvetica, sans-serif; background:#f8fafc; margin:0; }
    .wrap { width: var(--w); max-width: 100%; margin: 12px auto; background:#fff; box-shadow: 0 1px 4px rgba(0,0,0,.12); padding: 12px; }
    .center { text-align:center; }
    .small { font-size: 11px; }
    .xs { font-size: 10px; }
    .bold { font-weight: 600; }
    hr { border:0; border-top:1px dashed #333; margin:6px 0; }
    table { width:100%; border-collapse: collapse; }
    th, td { padding:2px 0; font-size: 11px; vertical-align: top; }
    .right { text-align:right; }
    .mt-2 { margin-top:8px; }
    .btns { display:flex; gap:8px; justify-content:flex-end; margin:8px auto 0; width: var(--w); max-width:100%; }
    .btn { font-size:12px; border:1px solid #ddd; background:#fff; padding:6px 10px; border-radius:6px; cursor:pointer; text-decoration:none; color:#111; }
    .btn-primary { background:#4f46e5; color:#fff; border-color:#4f46e5; }
    @media print {
      .btns { display:none !important; }
      body { background:#fff; }
      .wrap { box-shadow:none; padding:0; }
    }
  </style>
</head>
<body>
  <div class="wrap" id="ticket">
    <div class="center">
      <div class="bold">Mi Tienda S.A. de C.V.</div>
      <div class="xs">RFC: XAXX010101000</div>
      <div class="xs">Calle 123, Col. Centro</div>
      <div class="xs">Tel: 555-555-5555</div>
      <hr>
      <div class="xs">Caja #{{ $register->id }} • Fecha: {{ $register->fecha->format('Y-m-d') }}</div>
      <div class="xs">Usuario: {{ $register->user->name ?? 'N/D' }}</div>
      <div class="xs">Almacén: {{ $register->warehouse->nombre ?? 'N/D' }}</div>
      <hr>
    </div>

    <table>
      <tbody>
        <tr>
          <td>Apertura</td>
          <td class="right">${{ number_format($register->monto_apertura,2) }}</td>
        </tr>
        <tr>
          <td>Ingresos</td>
          <td class="right">${{ number_format($register->ingresos,2) }}</td>
        </tr>
        <tr>
          <td>Egresos</td>
          <td class="right">- ${{ number_format($register->egresos,2) }}</td>
        </tr>
        <tr>
          <td>Ventas efectivo</td>
          <td class="right">${{ number_format($register->ventas_efectivo,2) }}</td>
        </tr>
        <tr>
          <td class="bold">Saldo final</td>
          <td class="right bold">${{ number_format($register->monto_cierre,2) }}</td>
        </tr>
      </tbody>
    </table>

    <hr class="mt-2">

    <div class="small center">Movimientos</div>
    <table>
      <thead>
        <tr>
          <th class="left">Fecha</th>
          <th>Tipo</th>
          <th>Concepto</th>
          <th class="right">Monto</th>
        </tr>
      </thead>
      <tbody>
        @forelse($register->movements()->oldest()->get() as $m)
          <tr>
            <td class="xs">{{ $m->created_at->format('H:i') }}</td>
            <td class="xs">{{ $m->tipo }}</td>
            <td class="xs">{{ \Illuminate\Support\Str::limit($m->concepto, 20) }}</td>
            <td class="xs right">{{ number_format($m->monto,2) }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="xs">Sin movimientos</td></tr>
        @endforelse
      </tbody>
    </table>

    <hr class="mt-2">
    <div class="center xs">Gracias</div>
  </div>

  {{-- Botones fuera del área imprimible --}}
  <div class="btns">
    <a href="#" onclick="window.print();return false;" class="btn btn-primary">Imprimir</a>
    <a href="{{ route('admin.cash.show', $register) }}" class="btn">Volver</a>
  </div>
</body>
</html>
