<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de ruta — Despacho #{{ $dispatch->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }
        .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 18px; font-weight: bold; }
        h2 { font-size: 14px; font-weight: bold; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .meta { display: flex; gap: 24px; flex-wrap: wrap; margin: 8px 0 16px; font-size: 11px; color: #555; }
        .meta strong { color: #111; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #ddd; }
        td { padding: 5px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td { font-weight: bold; border-top: 2px solid #ccc; background: #f9fafb; }
        .firma { margin-top: 40px; border-top: 1px solid #333; width: 220px; text-align: center; padding-top: 6px; font-size: 11px; }
        .notas-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 8px 12px; font-size: 11px; margin-bottom: 12px; }
        .section-num { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background: #1a1a1a; color: #fff; font-size: 10px; font-weight: bold; margin-right: 4px; }
        .traspaso-origen  { color: #4f46e5; font-weight: bold; font-size: 11px; }
        .traspaso-destino { color: #059669; font-weight: bold; font-size: 11px; }
        .folio  { font-size: 10px; color: #6b7280; font-family: monospace; }
        .cliente { font-weight: bold; font-size: 11px; }
        .tel    { font-size: 10px; color: #6b7280; }
        .dir    { font-size: 10px; color: #555; }
        .prods  { font-size: 10px; color: #374151; }

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
    <div class="notas-box"><strong>Notas:</strong> {{ $dispatch->notas }}</div>
    @endif

    {{-- ══ 1. TRASPASOS ══ --}}
    @if($dispatch->transferAssignments->count() > 0)
    <h2><span class="section-num">1</span> Traspasos a entregar ({{ $dispatch->transferAssignments->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th style="width:24px;">#</th>
                <th>Folio</th>
                <th>Origen</th>
                <th>Destino</th>
                <th class="no-ticket">Productos</th>
                <th class="text-center no-ticket" style="width:60px;">✓</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dispatch->transferAssignments as $i => $ta)
            @php $t = $ta->stockTransfer; @endphp
            <tr>
                <td style="font-weight:bold;color:#555;">{{ $i + 1 }}</td>
                <td><strong style="font-size:11px;">{{ $t?->folio ?? '—' }}</strong></td>
                <td class="traspaso-origen">{{ $t?->fromWarehouse?->nombre ?? '—' }}</td>
                <td class="traspaso-destino">{{ $t?->toWarehouse?->nombre ?? '—' }}</td>
                <td class="no-ticket prods">
                    @if($t)
                        {{ $t->items->map(fn($it) => ($it->product?->nombre ?? '—') . ' × ' . number_format($it->qty, 3) . ' ' . ($it->product?->unidad ?? ''))->implode(' · ') }}
                    @else —
                    @endif
                </td>
                <td class="text-center no-ticket" style="font-size:18px;">☐</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ══ 2. PEDIDOS ══ --}}
    <h2>
        <span class="section-num">{{ $dispatch->transferAssignments->count() > 0 ? '2' : '1' }}</span>
        Pedidos a entregar ({{ $dispatch->items->count() }})
    </h2>
    <table>
        <thead>
            <tr>
                <th style="width:24px;">#</th>
               <th style="width:100px;">Folio</th>
                <th style="width:140px;">Cliente</th>
                <th class="no-ticket">Dirección</th>
                <th>Productos</th>
                <th class="text-right" style="width:70px;">Total</th>
                <th style="width:70px;">Pago</th>
                <th class="text-center no-ticket" style="width:30px;">✓</th>
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
                        trim(($o->entrega_calle ?? '').' '.($o->entrega_numero ?? '')),
                        $o->entrega_colonia ?? '',
                        $o->entrega_ciudad  ?? '',
                    ])->filter()->implode(', ');
                    $productosResumen = $o->items->map(fn($it) =>
                        ($it->product?->nombre ?? $it->descripcion)
                        . ' × ' . number_format((float)$it->cantidad, 2)
                        . ' ' . ($it->product?->unidad ?? '')
                    )->implode(' · ');
                @endphp
                <tr>
                    <td style="font-weight:bold;color:#555;">{{ $i + 1 }}</td>
                   <td>
                        <div class="folio">{{ $o->folio }}</div>
                    </td>
                    <td>
                        <div class="cliente">{{ $o->client?->nombre ?? '—' }}</div>
                      
                    </td>
                    <td class="no-ticket dir">{{ $dir ?: '—' }}</td>
                    <td class="prods">{{ $productosResumen }}</td>
                    <td class="text-right"><strong>${{ number_format($o->total, 2) }}</strong></td>
                    <td>
                        <span style="font-size:10px;padding:1px 5px;border-radius:9999px;border:1px solid #ccc;
                            {{ $o->payment_method === 'CREDITO' ? 'background:#dbeafe;color:#1d4ed8;' : 'background:#f3f4f6;' }}">
                            {{ $o->payment_method }}
                        </span>
                    </td>
                    <td class="text-center no-ticket" style="font-size:18px;">☐</td>
                </tr>
            @endforeach
           <tr class="total-row">
            <td colspan="5" class="text-right">Total pedidos:</td>
            <td class="text-right">${{ number_format($totalPedidos, 2) }}</td>
            <td colspan="2"></td>
        </tr>
        </tbody>
    </table>

    {{-- ══ 3. CXC ══ --}}
    @if($dispatch->arAssignments->count() > 0)
    @php $sectionNum = $dispatch->transferAssignments->count() > 0 ? 3 : 2; @endphp
    <h2><span class="section-num">{{ $sectionNum }}</span> Cuentas por cobrar</h2>
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

    <div class="firma">Firma del chofer</div>

</div>

<script>
function printCarta()  { document.getElementById('body-root').classList.remove('ticket'); window.print(); }
function printTicket() { document.getElementById('body-root').classList.add('ticket');    window.print(); }
</script>
</body>
</html>