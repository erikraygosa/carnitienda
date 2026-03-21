<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de ruta — Despacho #{{ $dispatch->id }}</title>
    <style>
        /* ── Base ── */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }

        /* ── Modo carta (default) ── */
        .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 18px; font-weight: bold; }
        h2 { font-size: 14px; font-weight: bold; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .meta { display: flex; gap: 24px; flex-wrap: wrap; margin: 8px 0 16px; font-size: 11px; color: #555; }
        .meta strong { color: #111; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #ddd; }
        td { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 9999px; font-size: 10px; border: 1px solid #ccc; }
        .total-row td { font-weight: bold; border-top: 2px solid #ccc; background: #f9fafb; }
        .firma { margin-top: 40px; border-top: 1px solid #333; width: 220px; text-align: center; padding-top: 6px; font-size: 11px; }
        .notas-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 8px 12px; font-size: 11px; margin-bottom: 12px; }
        .direccion { font-size: 10px; color: #555; }

        /* ── Modo ticket 80mm ── */
        @media print {
            body.ticket .page { max-width: 72mm; padding: 4mm; font-size: 10px; }
            body.ticket h1 { font-size: 13px; }
            body.ticket h2 { font-size: 11px; }
            body.ticket .meta { flex-direction: column; gap: 2px; }
            body.ticket table { font-size: 9px; }
            body.ticket th, body.ticket td { padding: 3px 4px; }
            body.ticket .firma { width: 100%; }
            body.ticket .no-ticket { display: none; }
        }

        @media screen {
            body.ticket .page { max-width: 340px; padding: 8px; font-size: 11px; }
            body.ticket h1 { font-size: 14px; }
            body.ticket .no-ticket { display: none; }
        }

        .btn-bar { display: flex; gap: 8px; margin-bottom: 16px; }
        .btn { padding: 6px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-ticket { background: #0f766e; color: #fff; }
        .btn-close { background: #e5e7eb; color: #333; }
        @media print { .btn-bar { display: none !important; } }
    </style>
</head>
<body id="body-root">
<div class="page">

    {{-- Botones (solo pantalla) --}}
    <div class="btn-bar">
        <button class="btn btn-print" onclick="printCarta()">Imprimir carta</button>
        <button class="btn btn-ticket" onclick="printTicket()">Imprimir ticket 80mm</button>
        <button class="btn btn-close" onclick="window.close()">Cerrar</button>
    </div>

    {{-- Encabezado --}}
    <h1>Hoja de ruta &mdash; Despacho #{{ $dispatch->id }}</h1>
    <div class="meta">
        <div><strong>Fecha:</strong> {{ optional($dispatch->fecha)->format('d/m/Y H:i') }}</div>
        <div><strong>Chofer:</strong> {{ $dispatch->driver?->nombre ?? '—' }}</div>
        <div><strong>Vehículo:</strong> {{ $dispatch->vehicle ?? '—' }}</div>
        <div><strong>Ruta:</strong> {{ $dispatch->route?->nombre ?? '—' }}</div>
        <div><strong>Almacén:</strong> {{ $dispatch->warehouse?->nombre ?? '—' }}</div>
    </div>

    @if($dispatch->notas)
    <div class="notas-box">
        <strong>Notas:</strong> {{ $dispatch->notas }}
    </div>
    @endif

    {{-- Pedidos --}}
    <h2>Pedidos a entregar ({{ $dispatch->items->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Folio</th>
                <th>Cliente</th>
                <th class="no-ticket">Dirección</th>
                <th class="text-right">Total</th>
                <th>Pago</th>
                <th class="text-center no-ticket">Entregado</th>
            </tr>
        </thead>
        <tbody>
            @php $totalEfectivo = 0; $totalPedidos = 0; @endphp
            @foreach($dispatch->items as $i => $item)
                @php
                    $o = $item->salesOrder;
                    if(!$o) continue;
                    $totalPedidos += $o->total;
                    if(in_array($o->payment_method, ['EFECTIVO','CONTRAENTREGA'])) $totalEfectivo += $o->total;
                    $dir = collect([
                        $o->entrega_calle.' '.$o->entrega_numero,
                        $o->entrega_colonia,
                        $o->entrega_ciudad,
                    ])->filter()->implode(', ');
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $o->folio }}</strong></td>
                    <td>
                        {{ $o->client?->nombre ?? '—' }}
                        @if($o->entrega_nombre)
                            <br><span class="direccion">Recibe: {{ $o->entrega_nombre }}</span>
                        @endif
                        @if($o->entrega_telefono)
                            <br><span class="direccion">Tel: {{ $o->entrega_telefono }}</span>
                        @endif
                    </td>
                    <td class="no-ticket direccion">{{ $dir ?: '—' }}</td>
                    <td class="text-right"><strong>${{ number_format($o->total, 2) }}</strong></td>
                    <td>{{ $o->payment_method }}</td>
                    <td class="text-center no-ticket">☐</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" class="text-right">Total pedidos:</td>
                <td class="text-right">${{ number_format($totalPedidos, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    {{-- CxC a cobrar --}}
    @if($dispatch->arAssignments->count() > 0)
    <h2>Cuentas por cobrar</h2>
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th class="text-right">Saldo</th>
                <th class="text-center no-ticket">Cobrado ☐</th>
            </tr>
        </thead>
        <tbody>
            @php $totalCxc = 0; @endphp
            @foreach($dispatch->arAssignments as $a)
                @php $totalCxc += $a->saldo_asignado; @endphp
                <tr>
                    <td>{{ $a->client?->nombre ?? '—' }}</td>
                    <td class="text-right">${{ number_format($a->saldo_asignado, 2) }}</td>
                    <td class="text-center no-ticket">☐</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total CxC:</td>
                <td class="text-right">${{ number_format($totalCxc, 2) }}</td>
                <td class="no-ticket"></td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- Resumen de cobro esperado --}}
    <h2>Resumen de cobro esperado</h2>
    <table>
        <tbody>
            <tr>
                <td>Pedidos en efectivo / contraentrega</td>
                <td class="text-right"><strong>${{ number_format($totalEfectivo, 2) }}</strong></td>
            </tr>
            @if($dispatch->arAssignments->count() > 0)
            <tr>
                <td>CxC a cobrar</td>
                <td class="text-right"><strong>${{ number_format($totalCxc ?? 0, 2) }}</strong></td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total a traer en efectivo</td>
                <td class="text-right">${{ number_format($totalEfectivo + ($totalCxc ?? 0), 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Firma --}}
    <div class="firma">
        Firma del chofer
    </div>

</div>

<script>
function printCarta() {
    document.getElementById('body-root').classList.remove('ticket');
    window.print();
}
function printTicket() {
    document.getElementById('body-root').classList.add('ticket');
    window.print();
}
</script>
</body>
</html>