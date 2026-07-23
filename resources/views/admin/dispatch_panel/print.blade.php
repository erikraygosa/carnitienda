<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Pendientes de salida — {{ now()->format('d/m/Y H:i') }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; }
    .no-print { margin-bottom: 12px; }
    @media print { .no-print { display: none !important; } }

    h1 { font-size: 14pt; font-weight: bold; margin-bottom: 2px; }
    .sub { font-size: 9pt; color: #555; margin-bottom: 14px; }

    .pedido { margin-bottom: 18px; page-break-inside: avoid; }
    .pedido-header {
        background: #1e3a5f; color: #fff;
        padding: 5px 8px;
        display: flex; justify-content: space-between; align-items: center;
        font-size: 10pt;
    }
    .pedido-header .folio { font-weight: bold; font-size: 11pt; }
    .pedido-header .meta { font-size: 8.5pt; opacity: 0.85; }

    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    thead tr { background: #e5e7eb; }
    th { padding: 4px 6px; text-align: left; font-size: 8.5pt; text-transform: uppercase; color: #374151; }
    th.r { text-align: right; }
    th.c { text-align: center; }
    td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    td.r { text-align: right; }
    td.c { text-align: center; }
    .total-row td { font-weight: bold; border-top: 2px solid #374151; background: #f9fafb; }

    .firma-block { margin-top: 28px; border-top: 1px solid #999; padding-top: 6px; display: flex; justify-content: space-between; }
    .firma-item { text-align: center; width: 30%; }
    .firma-line { border-top: 1px solid #333; margin-bottom: 4px; }
    .firma-label { font-size: 8pt; color: #555; }
</style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()"
        style="padding:6px 18px;background:#1d4ed8;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:13px;">
        Imprimir
    </button>
    <span style="margin-left:12px;font-size:12px;color:#666;">{{ $pedidos->count() }} pedido(s) pendiente(s) de salida</span>
</div>

<h1>Pendientes de Salida</h1>
<div class="sub">
    {{ $empresa?->fiscalData?->razon_social ?? $empresa?->nombre_comercial ?? config('app.name') }}
    &nbsp;·&nbsp; Generado: {{ now()->format('d/m/Y H:i') }}
    &nbsp;·&nbsp; {{ $pedidos->count() }} pedido(s)
</div>

@foreach($pedidos as $pedido)
<div class="pedido">
    <div class="pedido-header">
        <div>
            <span class="folio">{{ $pedido->folio }}</span>
            &nbsp;&nbsp;
            <span>{{ $pedido->client?->nombre ?? '—' }}</span>
        </div>
        <div class="meta">
            Fecha: {{ optional($pedido->fecha)->format('d/m/Y') }}
            &nbsp;·&nbsp;
            {{ $pedido->items->count() }} partida(s)
            &nbsp;·&nbsp;
            Total: ${{ number_format($pedido->total, 2) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto / Descripción</th>
                <th class="c">Cajas</th>
                <th class="r">Cant. Solicitada</th>
                <th class="r">Precio</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->items as $item)
            <tr>
                <td>
                    <strong>{{ $item->product?->nombre ?? '—' }}</strong>
                    @if($item->descripcion && $item->descripcion !== ($item->product?->nombre ?? ''))
                        <br><span style="color:#555;font-size:8.5pt">{{ $item->descripcion }}</span>
                    @endif
                </td>
                <td class="c">{{ $item->num_cajas ?? '—' }}</td>
                <td class="r">{{ number_format($item->cantidad, 3) }}</td>
                <td class="r">${{ number_format($item->precio, 2) }}</td>
                <td class="r">${{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" style="text-align:right">Total del pedido:</td>
                <td class="r">${{ number_format($pedido->total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($pedido->delivery_type === 'ENVIO' && ($pedido->entrega_calle || $pedido->entrega_ciudad))
    <div style="margin-top:4px;font-size:8.5pt;color:#444;padding:3px 6px;background:#f3f4f6;">
        <strong>Entrega:</strong>
        {{ implode(', ', array_filter([
            $pedido->entrega_nombre,
            trim(($pedido->entrega_calle ?? '') . ' ' . ($pedido->entrega_numero ?? '')),
            $pedido->entrega_colonia,
            $pedido->entrega_ciudad,
            $pedido->entrega_estado,
        ])) }}
        @if($pedido->entrega_telefono) &nbsp;· Tel: {{ $pedido->entrega_telefono }} @endif
    </div>
    @endif

    @if($pedido->comentarios)
    <div style="margin-top:3px;font-size:8.5pt;color:#444;padding:3px 6px;">
        <strong>Nota:</strong> {{ $pedido->comentarios }}
    </div>
    @endif
</div>
@endforeach

<div class="firma-block">
    <div class="firma-item">
        <div class="firma-line"></div>
        <div class="firma-label">Preparó</div>
    </div>
    <div class="firma-item">
        <div class="firma-line"></div>
        <div class="firma-label">Revisó / Autorizó</div>
    </div>
    <div class="firma-item">
        <div class="firma-line"></div>
        <div class="firma-label">Recibió (Chofer)</div>
    </div>
</div>

<script>
// Auto-print al abrir
window.addEventListener('load', function() { window.print(); });
</script>
</body>
</html>
