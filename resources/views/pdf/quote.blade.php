@php
    $client = $quote->client ?? null;

    $statusColors = match($quote->status ?? '') {
        'BORRADOR'  => ['bg'=>'#f0f0f0','color'=>'#555','border'=>'#ccc'],
        'ENVIADA'   => ['bg'=>'#dbeafe','color'=>'#1d4ed8','border'=>'#93c5fd'],
        'APROBADA'  => ['bg'=>'#dcfce7','color'=>'#166534','border'=>'#86efac'],
        'RECHAZADA' => ['bg'=>'#fee2e2','color'=>'#991b1b','border'=>'#fca5a5'],
        'CANCELADA' => ['bg'=>'#fee2e2','color'=>'#991b1b','border'=>'#fca5a5'],
        default     => ['bg'=>'#f0f0f0','color'=>'#555','border'=>'#ccc'],
    };

    $logoPath   = public_path('logo.jpg');
    $logoExists = file_exists($logoPath);
    if ($logoExists) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoSrc  = 'data:image/jpeg;base64,'.$logoData;
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cotización {{ $quote->folio ?? '#'.$quote->id }}</title>
<style>
* { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
body { font-size: 11px; color: #1a1a1a; padding: 28px 32px; }
.logo-img { height: 70px; width: auto; }
.doc-tipo  { font-size: 20px; font-weight: bold; color: #222; text-transform: uppercase; letter-spacing: 1px; }
.doc-folio { font-size: 14px; color: #c89000; font-weight: bold; margin-top: 4px; }
.doc-fecha { font-size: 10px; color: #666; margin-top: 3px; }
.divider   { border: none; border-top: 3px solid #e6a800; margin: 10px 0 18px; }
.vigencia-box { background: #fef9c3; border: 1px solid #fde047; border-radius: 5px; padding: 8px 12px; margin-bottom: 14px; font-size: 10px; }
.info-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
.info-grid td { vertical-align: top; padding: 0; }
.card { background: #fafafa; border: 1px solid #e0e0e0; border-radius: 5px; padding: 10px 13px; }
.card-title { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #c89000; border-bottom: 1px solid #e0e0e0; padding-bottom: 4px; margin-bottom: 7px; }
.card-row { font-size: 10px; margin-bottom: 3px; line-height: 1.5; color: #333; }
.card-row .lbl { color: #888; }
.card-row .val { font-weight: bold; color: #111; }
.items-table { width: 100%; border-collapse: collapse; }
.items-table thead tr { background: #222; }
.items-table thead th { color: #fff; padding: 8px 7px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; font-weight: bold; }
.items-table thead th.r { text-align: right; }
.items-table tbody tr { border-bottom: 1px solid #e8e8e8; }
.items-table tbody tr:nth-child(even) { background: #f7f7f7; }
.items-table tbody td { padding: 7px 7px; font-size: 10px; vertical-align: middle; }
.items-table tbody td.r { text-align: right; }
.totales-table { width: 38%; margin-left: auto; border-collapse: collapse; margin-top: 12px; }
.totales-table td { padding: 4px 10px; font-size: 10px; }
.totales-table td.r { text-align: right; }
.totales-table .sub-line td { color: #666; border-top: 1px solid #e8e8e8; }
.totales-table .total-line td { font-size: 13px; font-weight: bold; border-top: 2px solid #222; padding-top: 7px; color: #111; }
.condiciones { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 5px; padding: 9px 12px; margin-top: 16px; font-size: 10px; color: #555; }
.footer { border-top: 2px solid #e6a800; margin-top: 22px; padding-top: 8px; font-size: 9px; color: #aaa; }
</style>
</head>
<body>

{{-- HEADER --}}
<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:45%;vertical-align:middle">
            @if($logoExists ?? false)
                <img src="{{ $logoSrc }}" class="logo-img" alt="CarniTienda">
            @else
                <div style="font-size:20px;font-weight:bold;color:#d63384">CarniTienda</div>
            @endif
        </td>
        <td style="width:55%;vertical-align:top;text-align:right">
            <div class="doc-tipo">Cotización</div>
            <div class="doc-folio">{{ $quote->folio ?? '#'.$quote->id }}</div>
            <div class="doc-fecha">{{ optional($quote->fecha)->format('d/m/Y H:i') }}</div>
            <div style="margin-top:5px">
                <span style="display:inline-block;padding:3px 10px;border-radius:3px;font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:0.5px;background:{{ $statusColors['bg'] }};color:{{ $statusColors['color'] }};border:1px solid {{ $statusColors['border'] }}">
                    {{ $quote->status ?? 'BORRADOR' }}
                </span>
            </div>
        </td>
    </tr>
</table>
<hr class="divider">

{{-- Vigencia --}}
@if(!empty($quote->vigencia_hasta))
<div class="vigencia-box">
    Válida hasta el <strong>{{ optional($quote->vigencia_hasta)->format('d/m/Y') }}</strong>.
    Precios sujetos a cambio sin previo aviso.
</div>
@endif

{{-- INFO CARDS --}}
<table class="info-grid" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:50%;padding-right:7px">
            <div class="card">
                <div class="card-title">Cliente</div>
                <div class="card-row"><span class="val">{{ $client->nombre ?? 'Público en general' }}</span></div>
                @if($client?->telefono)<div class="card-row"><span class="lbl">Tel: </span>{{ $client->telefono }}</div>@endif
                @if($client?->email)<div class="card-row"><span class="lbl">Email: </span>{{ $client->email }}</div>@endif
                @if($client?->rfc)<div class="card-row"><span class="lbl">RFC: </span>{{ $client->rfc }}</div>@endif
            </div>
        </td>
        <td style="width:50%">
            <div class="card">
                <div class="card-title">Datos de la cotización</div>
                <div class="card-row"><span class="lbl">Fecha: </span><span class="val">{{ optional($quote->fecha)->format('d/m/Y') }}</span></div>
                <div class="card-row"><span class="lbl">Moneda: </span><span class="val">{{ $quote->moneda ?? 'MXN' }}</span></div>
                @if(!empty($quote->payment_method))
                <div class="card-row"><span class="lbl">Pago: </span>{{ $quote->payment_method }}</div>
                @endif
                @if(!empty($quote->credit_days))
                <div class="card-row"><span class="lbl">Días crédito: </span>{{ $quote->credit_days }}d</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- PARTIDAS --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width:20%">Producto</th>
            <th>Descripción</th>
            <th class="r" style="width:9%">Cant.</th>
            <th class="r" style="width:12%">Precio unit.</th>
            <th class="r" style="width:9%">Desc.</th>
            <th class="r" style="width:9%">Impuesto</th>
            <th class="r" style="width:11%">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($quote->items as $it)
        <tr>
            <td>{{ $it->product?->nombre ?? '—' }}</td>
            <td>{{ $it->descripcion }}</td>
            <td class="r">{{ number_format((float)$it->cantidad, 3) }}</td>
            <td class="r">{{ number_format((float)$it->precio, 4) }}</td>
            <td class="r">{{ number_format((float)$it->descuento, 2) }}</td>
            <td class="r">{{ number_format((float)$it->impuesto, 2) }}</td>
            <td class="r" style="font-weight:bold">{{ number_format((float)$it->total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- TOTALES --}}
<table class="totales-table">
    <tr class="sub-line"><td>Subtotal</td><td class="r">{{ number_format((float)$quote->subtotal, 2) }}</td></tr>
    @if((float)($quote->descuento ?? 0) > 0)
    <tr class="sub-line"><td>Descuento</td><td class="r">- {{ number_format((float)$quote->descuento, 2) }}</td></tr>
    @endif
    @if((float)($quote->impuestos ?? 0) > 0)
    <tr class="sub-line"><td>Impuestos</td><td class="r">{{ number_format((float)$quote->impuestos, 2) }}</td></tr>
    @endif
    <tr class="total-line">
        <td>TOTAL</td>
        <td class="r">{{ $quote->moneda ?? 'MXN' }} {{ number_format((float)$quote->total, 2) }}</td>
    </tr>
</table>

<div class="condiciones">
    <strong>Condiciones:</strong> Los precios son en pesos mexicanos e incluyen impuestos cuando aplica.
    Esta cotización no representa un compromiso de compra-venta hasta confirmar el pedido.
</div>

<div class="footer">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>{{ config('app.name') }} — Generado el {{ now()->format('d/m/Y H:i') }}</td>
            <td style="text-align:right">{{ $quote->folio ?? '#'.$quote->id }}</td>
        </tr>
    </table>
</div>

</body>
</html>