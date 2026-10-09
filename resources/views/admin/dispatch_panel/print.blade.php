<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Pendientes de salida — {{ now()->format('d/m/Y H:i') }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; padding: 10px; }

    .toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .toolbar button {
        padding: 5px 14px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: 600;
    }
    .btn-print { background: #1d4ed8; color: #fff; }
    .btn-toggle { background: #e5e7eb; color: #374151; }
    .btn-toggle.active { background: #374151; color: #fff; }
    .toolbar span { font-size: 12px; color: #666; }

    @media print { .toolbar { display: none !important; } }

    h1 { font-size: 13pt; font-weight: bold; margin-bottom: 2px; }
    .sub { font-size: 8.5pt; color: #555; margin-bottom: 12px; }

    /* ── Vista por pedido ── */
    .pedido { margin-bottom: 16px; page-break-inside: avoid; }
    .pedido-header {
        background: #1e3a5f; color: #fff;
        padding: 5px 8px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .pedido-header .folio { font-weight: bold; font-size: 10.5pt; }
    .pedido-header .meta { font-size: 8pt; opacity: 0.85; }
    .entrega-info { margin-top: 3px; font-size: 8pt; color: #444; padding: 3px 6px; background: #f3f4f6; }
    .nota-info    { margin-top: 2px; font-size: 8pt; color: #444; padding: 3px 6px; }

    /* ── Vista detallada por producto ── */
    .detalle-header {
        background: #374151; color: #fff;
        padding: 5px 8px; font-weight: bold; font-size: 10pt;
        display: flex; justify-content: space-between;
    }
    .detalle-item { page-break-inside: avoid; margin-bottom: 14px; }
    .ruta-bloque { margin-bottom: 18px; }
    .ruta-header {
        background: #1e3a5f; color: #fff; padding: 6px 8px; font-weight: bold; font-size: 10.5pt;
        display: flex; justify-content: space-between;
    }
    .ruta-bloque tr.pedido-fila td { border-top: 1px solid #9ca3af; }
    .ruta-bloque td.folio { font-family: monospace; font-size: 8.5pt; white-space: nowrap; }

    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    thead tr { background: #e5e7eb; }
    th { padding: 3px 6px; text-align: left; font-size: 8pt; text-transform: uppercase; color: #374151; }
    th.r { text-align: right; }
    th.c { text-align: center; }
    td { padding: 3px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    td.r { text-align: right; }
    td.c { text-align: center; }
    .comentario { font-size: 8.5pt; font-style: italic; color: #92400e; margin-top: 1px; }
    .total-row td { font-weight: bold; border-top: 2px solid #374151; background: #f9fafb; }
</style>
</head>
<body>

@php
    // Comentario del renglón (lo que se captura en el pedido como instrucción
    // de surtido, ej. "ESPECIAL"): solo si trae algo distinto al nombre del producto.
    $comentarioDe = function ($item) {
        $t = trim((string) ($item->descripcion ?? ''));
        return ($item->product && $t !== '' && mb_strtolower($t) !== mb_strtolower($item->product->nombre)) ? $t : null;
    };

    // Agrupar items por producto para vista detallada
    $porProducto = collect();
    foreach ($pedidos as $pedido) {
        foreach ($pedido->items as $item) {
            $nombre = $item->product?->nombre ?? $item->descripcion ?? '—';
            if (!$porProducto->has($nombre)) {
                $porProducto[$nombre] = collect();
            }
            $porProducto[$nombre]->push([
                'folio'   => $pedido->folio,
                'cliente' => $pedido->client?->nombre ?? '—',
                'comentario' => $comentarioDe($item),
                'cantidad'=> (float) $item->cantidad,
                'num_cajas'=> $item->num_cajas,
                'precio'  => (float) $item->precio,
                'total'   => (float) $item->total,
            ]);
        }
    }
    $porProducto = $porProducto->sortKeys();

    // Agrupar pedidos por ruta (y ronda) para la vista por ruta
    $porRuta = $pedidos
        ->groupBy(fn ($p) => ($p->route?->nombre ?? 'Sin ruta') . '|' . ((int) ($p->ronda ?? 1)))
        ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
@endphp

<div class="toolbar">
    <button class="btn-print" onclick="window.print()">Imprimir</button>
    <button class="btn-toggle active" data-vista="pedidos" onclick="cambiarVista('pedidos')">Por pedido</button>
    <button class="btn-toggle" data-vista="detalle" onclick="cambiarVista('detalle')">Detallado por producto</button>
    <button class="btn-toggle" data-vista="ruta" onclick="cambiarVista('ruta')">Por ruta</button>
    <span>{{ $pedidos->count() }} pedido(s) · {{ $porProducto->count() }} producto(s) · {{ $porRuta->count() }} ruta(s)</span>
</div>

<h1>Pendientes de Salida</h1>
<div class="sub">
    {{ $empresa?->fiscalData?->razon_social ?? $empresa?->nombre_comercial ?? config('app.name') }}
    &nbsp;·&nbsp; {{ now()->format('d/m/Y H:i') }}
    &nbsp;·&nbsp; {{ $pedidos->count() }} pedido(s)
</div>

{{-- ====== VISTA POR PEDIDO ====== --}}
<div id="vista-pedidos">
@foreach($pedidos as $pedido)
<div class="pedido">
    <div class="pedido-header">
        <div>
            <span class="folio">{{ $pedido->folio }}</span>
            &nbsp;&nbsp;
            <span>{{ $pedido->client?->nombre ?? '—' }}</span>
        </div>
        <div class="meta">
            {{-- Fecha programada de entrega, no la de captura --}}
            {{ optional($pedido->programado_para ?? $pedido->fecha)->format('d/m/Y') }}
            &nbsp;·&nbsp; {{ $pedido->items->count() }} partida(s)
            &nbsp;·&nbsp; ${{ number_format($pedido->total, 2) }}
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="c">Cajas</th>
                <th class="r">Cant.</th>
                <th class="r">Precio</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->items as $item)
            <tr>
                <td>{{ $item->product?->nombre ?? $item->descripcion ?? '—' }}
                    @if($c = $comentarioDe($item))<div class="comentario">📝 {{ $c }}</div>@endif
                </td>
                <td class="c">{{ $item->num_cajas ?? '—' }}</td>
                <td class="r">{{ number_format($item->cantidad, 3) }}</td>
                <td class="r">${{ number_format($item->precio, 2) }}</td>
                <td class="r">${{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" style="text-align:right">Total:</td>
                <td class="r">${{ number_format($pedido->total, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @if($pedido->delivery_type === 'ENVIO' && ($pedido->entrega_calle || $pedido->entrega_ciudad))
    <div class="entrega-info">
        <strong>Entrega:</strong>
        {{ implode(', ', array_filter([
            $pedido->entrega_nombre,
            trim(($pedido->entrega_calle ?? '') . ' ' . ($pedido->entrega_numero ?? '')),
            $pedido->entrega_colonia,
            $pedido->entrega_ciudad,
            $pedido->entrega_estado,
        ])) }}
        @if($pedido->entrega_telefono) · Tel: {{ $pedido->entrega_telefono }} @endif
    </div>
    @endif
    @if($pedido->comentarios)
    <div class="nota-info"><strong>Nota:</strong> {{ $pedido->comentarios }}</div>
    @endif
</div>
@endforeach
</div>

{{-- ====== VISTA DETALLADA POR PRODUCTO ====== --}}
<div id="vista-detalle" style="display:none">
<div class="sub" style="margin-bottom:10px">Agrupado por producto — total de todos los pedidos pendientes</div>

@foreach($porProducto as $nombreProducto => $lineas)
@php $totalCajas = $lineas->sum('num_cajas'); $totalKg = $lineas->sum('cantidad'); @endphp
<div class="detalle-item">
    <div class="detalle-header">
        <span>{{ $nombreProducto }}</span>
        <span style="font-size:9pt;font-weight:normal">
            Total: {{ number_format($totalKg, 3) }} kg
            @if($totalCajas) · {{ $totalCajas }} cajas @endif
        </span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th class="c">Cajas</th>
                <th class="r">Cantidad</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineas as $l)
            <tr>
                <td style="font-family:monospace;font-size:8.5pt">{{ $l['folio'] }}</td>
                <td>{{ $l['cliente'] }}
                    @if($l['comentario'])<div class="comentario">📝 {{ $l['comentario'] }}</div>@endif
                </td>
                <td class="c">{{ $l['num_cajas'] ?? '—' }}</td>
                <td class="r">{{ number_format($l['cantidad'], 3) }}</td>
                <td class="r">${{ number_format($l['total'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2" style="text-align:right">Totales:</td>
                <td class="c">{{ $totalCajas ?: '—' }}</td>
                <td class="r">{{ number_format($totalKg, 3) }}</td>
                <td class="r">${{ number_format($lineas->sum('total'), 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endforeach
</div>

{{-- ====== VISTA POR RUTA ====== --}}
<div id="vista-ruta" style="display:none">

@foreach($porRuta as $clave => $pedidosRuta)
@php
    [$nombreRuta, $rondaRuta] = explode('|', $clave);
    $kgRuta    = $pedidosRuta->sum(fn ($p) => $p->items->sum('cantidad'));
    $cajasRuta = $pedidosRuta->sum(fn ($p) => $p->items->sum('num_cajas'));
@endphp
<div class="ruta-bloque">
    <div class="ruta-header">
        <span>{{ $nombreRuta }} <span style="font-weight:normal;font-size:9pt">· {{ $rondaRuta == 2 ? '2da' : '1ra' }} ronda</span></span>
        <span style="font-size:9pt;font-weight:normal">
            {{ $pedidosRuta->count() }} pedido(s) · {{ number_format($kgRuta, 3) }} kg
            @if($cajasRuta) · {{ $cajasRuta }} cajas @endif
        </span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Producto</th>
                <th class="c">Cajas</th>
                <th class="r">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedidosRuta->sortBy(fn ($p) => mb_strtolower($p->client?->nombre ?? '')) as $pedido)
                @foreach($pedido->items as $i => $item)
                <tr class="{{ $i === 0 ? 'pedido-fila' : '' }}">
                    <td class="folio">{{ $i === 0 ? $pedido->folio : '' }}</td>
                    <td>{{ $i === 0 ? ($pedido->client?->nombre ?? '—') : '' }}</td>
                    <td>{{ $item->product?->nombre ?? $item->descripcion ?? '—' }}
                        @if($c = $comentarioDe($item))<div class="comentario">📝 {{ $c }}</div>@endif
                    </td>
                    <td class="c">{{ $item->num_cajas ?? '—' }}</td>
                    <td class="r">{{ number_format($item->cantidad, 3) }}</td>
                </tr>
                @endforeach
            @endforeach
            <tr class="total-row">
                <td colspan="3" style="text-align:right">Total {{ $nombreRuta }}:</td>
                <td class="c">{{ $cajasRuta ?: '—' }}</td>
                <td class="r">{{ number_format($kgRuta, 3) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endforeach
</div>

<script>
function cambiarVista(vista) {
    ['pedidos', 'detalle', 'ruta'].forEach(function (v) {
        document.getElementById('vista-' + v).style.display = (v === vista) ? '' : 'none';
    });
    document.querySelectorAll('.btn-toggle').forEach(function (b) {
        b.classList.toggle('active', b.dataset.vista === vista);
    });
}
// ?vista=ruta|detalle abre directo en esa vista
(function () {
    var v = new URLSearchParams(location.search).get('vista');
    if (v === 'ruta' || v === 'detalle') cambiarVista(v);
})();
window.addEventListener('load', function() { window.print(); });
</script>
</body>
</html>
