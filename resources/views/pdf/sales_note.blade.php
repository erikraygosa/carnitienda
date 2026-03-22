@php
    $client = $sale->client ?? null;
    $wh     = $sale->warehouse ?? null;

    // Datos empresa
    $emp = $empresa ?? null;
    $ef  = $emp?->fiscalData ?? null;

    $dirEmpresa = collect([
        trim(($ef?->calle ?? '') . ' ' . ($ef?->numero_exterior ?? '')),
        $ef?->colonia ?? '',
        'C.P. ' . ($ef?->codigo_postal ?? ''),
        $ef?->municipio ?? '',
        $ef?->estado ?? '',
    ])->filter()->implode(', ');

    $dir = collect([
        trim(($sale->entrega_calle ?? '') . ' ' . ($sale->entrega_numero ?? '')),
        $sale->entrega_colonia ?? '',
        trim(($sale->entrega_ciudad ?? '') . ' ' . ($sale->entrega_estado ?? '') . ' ' . ($sale->entrega_cp ?? '')),
    ])->filter()->implode(', ');

    $logoPath   = public_path('logo.jpg');
    $logoExists = file_exists($logoPath);
    if ($logoExists) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoSrc  = 'data:image/jpeg;base64,' . $logoData;
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Nota de venta {{ $sale->folio ?? '#'.$sale->id }}</title>
<style>
* { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
body { font-size: 11px; color: #1a1a1a; padding: 28px 32px; }
.logo-img { height: 64px; }
.doc-tipo { font-size: 18px; font-weight: bold; color: #333; text-transform: uppercase; letter-spacing: 1px; }
.doc-folio { font-size: 13px; color: #d4a000; font-weight: bold; margin-top: 3px; }
.doc-fecha { font-size: 10px; color: #666; margin-top: 2px; }
.header-line { border-bottom: 3px solid #e6a800; margin: 8px 0 14px; }
.info-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.info-grid td { vertical-align: top; padding: 0 6px 0 0; }
.info-grid td:last-child { padding-right: 0; }
.card { background: #fafafa; border: 1px solid #e5e7eb; border-radius: 5px; padding: 9px 11px; }
.card-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #d4a000; margin-bottom: 5px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
.card .row { margin-bottom: 2px; font-size: 10px; line-height: 1.5; }
.card .lbl { color: #777; }
.card .val { font-weight: bold; }
.tbl { width: 100%; border-collapse: collapse; }
.tbl thead tr { background: #1a1a1a; }
.tbl thead th { padding: 7px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; color: #fff; text-align: left; }
.tbl thead th.r { text-align: right; }
.tbl tbody tr { border-bottom: 1px solid #e5e7eb; }
.tbl tbody tr:nth-child(even) { background: #f9fafb; }
.tbl tbody td { padding: 6px 7px; font-size: 10px; }
.tbl tbody td.r { text-align: right; }
.totales { width: 42%; margin-left: auto; border-collapse: collapse; margin-top: 10px; }
.totales td { padding: 4px 8px; font-size: 10px; }
.totales td.r { text-align: right; }
.totales .sub-row td { color: #555; border-top: 1px solid #e5e7eb; }
.totales .grand-row td { font-size: 14px; font-weight: bold; border-top: 2px solid #1a1a1a; padding-top: 6px; background: #fef9c3; }
.pago-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 10px; font-weight: bold; background: #dcfce7; color: #166534; border: 1px solid #86efac; margin-top: 6px; }
.footer { border-top: 2px solid #e6a800; padding-top: 7px; margin-top: 18px; font-size: 9px; color: #999; }
.firma { border-top: 1px solid #333; margin-top: 36px; width: 170px; text-align: center; padding-top: 4px; font-size: 9px; color: #555; }
</style>
</head>
<body>

{{-- ══════════════ HEADER ══════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        {{-- Logo --}}
        <td style="width:20%;vertical-align:middle">
            @if($logoExists ?? false)
                <img src="{{ $logoSrc }}" class="logo-img" alt="Logo">
            @else
                <div style="font-size:20px;font-weight:bold;color:#d63384">
                    {{ $emp?->nombre_display ?? config('app.name') }}
                </div>
            @endif
        </td>

        {{-- Datos empresa --}}
        <td style="width:45%;vertical-align:middle;padding-left:16px;border-left:1px solid #e0e0e0">
            <div style="font-size:13px;font-weight:bold;color:#111;margin-bottom:4px">
                {{ $emp?->razon_social ?? config('app.name') }}
            </div>
            <div style="font-size:9.5px;color:#555;margin-bottom:2px">
                R.F.C.: <strong>{{ $emp?->rfc ?? '' }}</strong>
            </div>
            @if($dirEmpresa)
            <div style="font-size:9px;color:#666;margin-bottom:2px">{{ $dirEmpresa }}</div>
            @endif
            @if($emp?->telefono)
            <div style="font-size:9px;color:#666;margin-bottom:2px">Tel: {{ $emp->telefono }}</div>
            @endif
            @if($emp?->email)
            <div style="font-size:9px;color:#666;margin-bottom:2px">{{ $emp->email }}</div>
            @endif
            @if($ef?->regimen_fiscal)
            <div style="font-size:9px;color:#666;margin-top:3px">
                Régimen Fiscal: {{ $ef->regimen_fiscal }} — {{ \App\Models\CompanyFiscalData::REGIMENES_FISCALES[$ef->regimen_fiscal] ?? '' }}
            </div>
            @endif
        </td>

        {{-- Tipo + folio --}}
        <td style="width:35%;vertical-align:top;text-align:right">
            <div class="doc-tipo">Nota de venta</div>
            <div class="doc-folio">{{ $sale->folio ?? '#'.$sale->id }}</div>
            <div class="doc-fecha">{{ optional($sale->fecha)->format('d/m/Y H:i') }}</div>
            <div class="pago-badge">{{ $sale->payment_method ?? $sale->metodo_pago ?? 'EFECTIVO' }}</div>
        </td>
    </tr>
</table>
<div class="header-line"></div>

{{-- ══════════════ INFO CLIENTE / ENTREGA ══════════════ --}}
<table class="info-grid" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:50%;padding-right:6px">
            <div class="card">
                <div class="card-title">Cliente</div>
                <div class="row"><span class="val">{{ $client->nombre ?? 'Público en general' }}</span></div>
                @if($client?->telefono)<div class="row"><span class="lbl">Tel: </span>{{ $client->telefono }}</div>@endif
                @if($client?->email)<div class="row"><span class="lbl">Email: </span>{{ $client->email }}</div>@endif
            </div>
        </td>
        <td style="width:50%">
            <div class="card">
                @if(($sale->delivery_type ?? '') === 'ENVIO' && $dir)
                <div class="card-title">Entrega a domicilio</div>
                @if($sale->entrega_nombre)<div class="row"><span class="lbl">Recibe: </span><span class="val">{{ $sale->entrega_nombre }}</span></div>@endif
                @if($sale->entrega_telefono)<div class="row"><span class="lbl">Tel: </span>{{ $sale->entrega_telefono }}</div>@endif
                <div class="row">{{ $dir }}</div>
                @else
                <div class="card-title">Datos de venta</div>
                <div class="row"><span class="lbl">Almacén: </span><span class="val">{{ $wh->nombre ?? '—' }}</span></div>
                <div class="row"><span class="lbl">Moneda: </span>{{ $sale->moneda ?? 'MXN' }}</div>
                <div class="row"><span class="lbl">Fecha: </span>{{ optional($sale->fecha)->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ══════════════ PARTIDAS ══════════════ --}}
<table class="tbl">
    <thead>
        <tr>
            <th style="width:22%">Producto</th>
            <th>Descripción</th>
            <th class="r" style="width:9%">Cant.</th>
            <th class="r" style="width:12%">Precio</th>
            <th class="r" style="width:10%">Desc.</th>
            <th class="r" style="width:10%">Impuesto</th>
            <th class="r" style="width:11%">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->items as $it)
        <tr>
            <td>{{ $it->product->nombre ?? $it->product_id ?? '—' }}</td>
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

{{-- ══════════════ TOTALES ══════════════ --}}
<table class="totales">
    <tr class="sub-row">
        <td>Subtotal</td>
        <td class="r">{{ number_format((float)$sale->subtotal, 2) }}</td>
    </tr>
    @if($sale->descuento > 0)
    <tr class="sub-row">
        <td>Descuento</td>
        <td class="r">- {{ number_format((float)$sale->descuento, 2) }}</td>
    </tr>
    @endif
    @if($sale->impuestos > 0)
    <tr class="sub-row">
        <td>Impuestos</td>
        <td class="r">{{ number_format((float)$sale->impuestos, 2) }}</td>
    </tr>
    @endif
    <tr class="grand-row">
        <td>TOTAL</td>
        <td class="r">{{ $sale->moneda ?? 'MXN' }} {{ number_format((float)$sale->total, 2) }}</td>
    </tr>
</table>

{{-- ══════════════ FIRMA ══════════════ --}}
<div class="firma">Firma del cliente</div>

{{-- ══════════════ FOOTER ══════════════ --}}
<div class="footer" style="display:flex;justify-content:space-between">
    <span>{{ $emp?->razon_social ?? config('app.name') }} — Generado el {{ now()->format('d/m/Y H:i') }}</span>
    <span>{{ $sale->folio ?? '#'.$sale->id }}</span>
</div>

</body>
</html>