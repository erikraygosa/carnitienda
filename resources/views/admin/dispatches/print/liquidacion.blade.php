<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidación — Despacho #{{ $dispatch->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }
        .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 18px; font-weight: bold; }
        h2 { font-size: 13px; font-weight: bold; margin: 14px 0 5px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .meta { display: flex; gap: 20px; flex-wrap: wrap; margin: 8px 0 14px; font-size: 11px; color: #555; }
        .meta strong { color: #111; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th { background: #f3f4f6; text-align: left; padding: 5px 8px; font-size: 11px; border-bottom: 1px solid #ddd; }
        td { padding: 5px 8px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td { font-weight: bold; border-top: 2px solid #999; background: #f9fafb; }
        .highlight { background: #f0fdf4; }
        .alert     { background: #fff7ed; }
        .badge-ok  { color: #15803d; }
        .badge-no  { color: #b91c1c; }
        .section-num { display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #1a1a1a; color: #fff; font-size: 9px; font-weight: bold; margin-right: 4px; }
        .traspaso-ok  { background: #eff6ff; }
        .traspaso-no  { background: #fff7ed; }
        .summary-box { border: 2px solid #333; border-radius: 6px; padding: 12px 16px; margin: 14px 0; }
        .summary-box .row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; }
        .summary-box .row.big { font-size: 16px; font-weight: bold; border-top: 1px solid #ccc; margin-top: 6px; padding-top: 6px; }
        .summary-box .row.diff-ok  { color: #15803d; }
        .summary-box .row.diff-bad { color: #b91c1c; }
        .firma-row { display: flex; gap: 40px; margin-top: 36px; }
        .firma { border-top: 1px solid #333; width: 200px; text-align: center; padding-top: 6px; font-size: 11px; }

        @media print {
            body.ticket .page { max-width: 72mm; padding: 3mm; font-size: 9px; }
            body.ticket h1 { font-size: 12px; }
            body.ticket h2 { font-size: 10px; }
            body.ticket .meta { flex-direction: column; gap: 1px; font-size: 9px; }
            body.ticket table { font-size: 8px; }
            body.ticket th, body.ticket td { padding: 2px 3px; }
            body.ticket .summary-box .row { font-size: 9px; }
            body.ticket .summary-box .row.big { font-size: 12px; }
            body.ticket .firma-row { flex-direction: column; gap: 16px; }
            body.ticket .firma { width: 100%; }
            body.ticket .no-ticket { display: none; }
        }
        @media screen {
            body.ticket .page { max-width: 340px; padding: 8px; font-size: 10px; }
            body.ticket h1 { font-size: 13px; }
            body.ticket .summary-box .row.big { font-size: 13px; }
            body.ticket .no-ticket { display: none; }
        }

        .btn-bar { display: flex; gap: 8px; margin-bottom: 16px; }
        .btn { padding: 6px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-print  { background: #4f46e5; color: #fff; }
        .btn-ticket { background: #0f766e; color: #fff; }
        .btn-close  { background: #e5e7eb; color: #333; }
        @media print { .btn-bar { display: none !important; } }
    </style>
</head>
<body id="body-root">
<div class="page">

    <div class="btn-bar">
        <button class="btn btn-print"  onclick="printCarta()">Imprimir carta</button>
        <button class="btn btn-ticket" onclick="printTicket()">Imprimir ticket 80mm</button>
        <button class="btn btn-close"  onclick="window.close()">Cerrar</button>
    </div>

    <h1>Liquidación &mdash; Despacho #{{ $dispatch->id }}</h1>
    <div class="meta">
        <div><strong>Cierre:</strong> {{ optional($dispatch->cerrado_at)->format('d/m/Y H:i') }}</div>
        <div><strong>Chofer:</strong> {{ $dispatch->driver?->nombre ?? '—' }}</div>
        <div><strong>Vehículo:</strong> {{ $dispatch->vehicle ?? '—' }}</div>
        <div><strong>Almacén:</strong> {{ $dispatch->warehouse?->nombre ?? '—' }}</div>
    </div>

    @php
        $entregados      = $dispatch->items->filter(fn($i) => $i->salesOrder?->status === 'ENTREGADO');
        $noEntregados    = $dispatch->items->filter(fn($i) => $i->salesOrder?->status === 'NO_ENTREGADO');
        $totalEsperado   = $entregados->filter(fn($i) => in_array($i->salesOrder?->payment_method, ['EFECTIVO','CONTRAENTREGA']))->sum(fn($i) => $i->salesOrder?->total ?? 0);
        $totalCxcCobrada = $dispatch->arAssignments->where('status','COBRADO')->sum('monto_cobrado');
        $totalRecibido   = (float) $dispatch->monto_liquidado;
        $diferencia      = $totalRecibido - $totalEsperado - $totalCxcCobrada;
        $traspasosCount  = $dispatch->transferAssignments->count();
        $traspasosOk     = $dispatch->transferAssignments->where('status','COMPLETADO')->count();
        $traspasosNo     = $dispatch->transferAssignments->where('status','NO_COMPLETADO')->count();
    @endphp

    {{-- ══ 1. TRASPASOS ══ --}}
    @if($traspasosCount > 0)
    <h2><span class="section-num">1</span> Traspasos ({{ $traspasosOk }}/{{ $traspasosCount }} completados)</h2>
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Origen → Destino</th>
                <th class="no-ticket">Productos</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dispatch->transferAssignments as $ta)
            @php $t = $ta->stockTransfer; @endphp
            <tr class="{{ $ta->status === 'COMPLETADO' ? 'traspaso-ok' : 'traspaso-no' }}">
                <td><strong>{{ $t?->folio ?? '—' }}</strong></td>
                <td style="font-size:10px;">
                    <span style="color:#4f46e5;">{{ $t?->fromWarehouse?->nombre ?? '—' }}</span>
                    →
                    <span style="color:#059669;">{{ $t?->toWarehouse?->nombre ?? '—' }}</span>
                </td>
                <td class="no-ticket" style="font-size:10px;">
                    @if($t)
                        {{ $t->items->count() }} producto(s)
                    @else —
                    @endif
                </td>
                <td class="text-center {{ $ta->status === 'COMPLETADO' ? 'badge-ok' : 'badge-no' }}">
                    {{ $ta->status }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ══ 2. PEDIDOS ENTREGADOS ══ --}}
    <h2><span class="section-num">{{ $traspasosCount > 0 ? '2' : '1' }}</span> Pedidos entregados ({{ $entregados->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th>Folio / Cliente</th>
                <th class="text-right">Total</th>
                <th>Pago</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entregados as $item)
                @php $o = $item->salesOrder; @endphp
                {{-- Fila principal --}}
                <tr class="highlight" style="background:#f0fdf4;">
                    <td>
                        <strong>{{ $o?->folio }}</strong> —
                        {{ $o?->client?->nombre ?? '—' }}
                    </td>
                    <td class="text-right"><strong>${{ number_format($o?->total ?? 0, 2) }}</strong></td>
                    <td>{{ $o?->payment_method }}</td>
                </tr>
                {{-- Productos --}}
                @foreach($o?->items ?? [] as $it)
                <tr>
                    <td style="padding-left:18px;font-size:10px;color:#374151;background:#f9fffe;border-bottom:{{ $loop->last ? '2px solid #bbf7d0' : '1px solid #f0fdf4' }};">
                        <span style="color:#9ca3af;">↳</span>
                        <strong>{{ $it->product?->nombre ?? $it->descripcion }}</strong>
                        <span style="color:#6b7280;margin-left:4px;">{{ number_format((float)$it->cantidad, 3) }} {{ $it->product?->unidad ?? '' }}</span>
                    </td>
                    <td class="text-right" style="font-size:10px;color:#374151;background:#f9fffe;border-bottom:{{ $loop->last ? '2px solid #bbf7d0' : '1px solid #f0fdf4' }};">
                        ${{ number_format((float)$it->total, 2) }}
                    </td>
                    <td style="background:#f9fffe;border-bottom:{{ $loop->last ? '2px solid #bbf7d0' : '1px solid #f0fdf4' }};"></td>
                </tr>
                @endforeach
            @endforeach
            <tr class="total-row">
                <td class="text-right">Subtotal entregados:</td>
                <td class="text-right">${{ number_format($entregados->sum(fn($i) => $i->salesOrder?->total ?? 0), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    @if($noEntregados->count() > 0)
    <h2><span class="section-num">{{ $traspasosCount > 0 ? '3' : '2' }}</span> Pedidos no entregados ({{ $noEntregados->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th class="text-right">Total</th>
                <th class="no-ticket">Motivo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($noEntregados as $item)
                @php $o = $item->salesOrder; @endphp
                <tr class="alert">
                    <td>{{ $o?->folio }}</td>
                    <td>{{ $o?->client?->nombre ?? '—' }}</td>
                    <td class="text-right">${{ number_format($o?->total ?? 0, 2) }}</td>
                    <td class="no-ticket">{{ $o?->delivery_notes ? \Str::limit($o->delivery_notes, 40) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ══ CXC ══ --}}
    @if($dispatch->arAssignments->count() > 0)
    <h2>Cuentas por cobrar</h2>
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th class="text-right">Asignado</th>
                <th class="text-right">Cobrado</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dispatch->arAssignments as $a)
                <tr class="{{ $a->status === 'COBRADO' ? 'highlight' : 'alert' }}">
                    <td>{{ $a->client?->nombre ?? '—' }}</td>
                    <td class="text-right">${{ number_format($a->saldo_asignado, 2) }}</td>
                    <td class="text-right">
                        {{ $a->monto_cobrado > 0 ? '$'.number_format($a->monto_cobrado, 2) : '—' }}
                    </td>
                    <td class="text-center {{ $a->status === 'COBRADO' ? 'badge-ok' : 'badge-no' }}">
                        {{ $a->status }}
                    </td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total CxC cobrado:</td>
                <td class="text-right">${{ number_format($dispatch->arAssignments->sum('saldo_asignado'), 2) }}</td>
                <td class="text-right">${{ number_format($totalCxcCobrada, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- Resumen de liquidación --}}
    <h2>Resumen de liquidación</h2>
    <div class="summary-box">
        @if($traspasosCount > 0)
        <div class="row">
            <span>Traspasos completados:</span>
            <span>{{ $traspasosOk }}/{{ $traspasosCount }}</span>
        </div>
        @endif
        <div class="row">
            <span>Efectivo esperado (pedidos):</span>
            <span>${{ number_format($totalEsperado, 2) }}</span>
        </div>
        @if($totalCxcCobrada > 0)
        <div class="row">
            <span>CxC cobradas:</span>
            <span>${{ number_format($totalCxcCobrada, 2) }}</span>
        </div>
        @endif
        <div class="row">
            <span>Total esperado:</span>
            <span>${{ number_format($totalEsperado + $totalCxcCobrada, 2) }}</span>
        </div>
        <div class="row big">
            <span>Entregado por chofer:</span>
            <span>${{ number_format($totalRecibido, 2) }}</span>
        </div>
        <div class="row {{ $diferencia >= 0 ? 'diff-ok' : 'diff-bad' }}">
            <span>Diferencia:</span>
            <span>{{ $diferencia >= 0 ? '+' : '' }}${{ number_format($diferencia, 2) }}</span>
        </div>
    </div>

    @if($dispatch->notas_cierre)
    <p style="font-size:11px;color:#555;margin-top:8px;"><strong>Notas:</strong> {{ $dispatch->notas_cierre }}</p>
    @endif

    <div class="firma-row">
        <div class="firma">Chofer: {{ $dispatch->driver?->nombre ?? '_______________' }}</div>
        <div class="firma no-ticket">Recibido por: _______________</div>
    </div>

</div>

<script>
function printCarta()  { document.getElementById('body-root').classList.remove('ticket'); window.print(); }
function printTicket() { document.getElementById('body-root').classList.add('ticket');    window.print(); }
</script>
</body>
</html>