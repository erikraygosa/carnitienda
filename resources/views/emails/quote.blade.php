<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cotización {{ $quote->folio }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#f4f4f5; color:#1a1a1a; }
  .wrapper { max-width:620px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.1); }
  .header { background:#222; padding:24px 32px; }
  .header table { width:100%; border-collapse:collapse; }
  .header-logo { font-size:20px; font-weight:bold; color:#e6a800; }
  .header-rfc  { font-size:10px; color:#aaa; margin-top:2px; }
  .header-doc  { text-align:right; }
  .header-doc .doc-tipo  { font-size:16px; font-weight:bold; color:#fff; text-transform:uppercase; letter-spacing:1px; }
  .header-doc .doc-folio { font-size:13px; color:#e6a800; font-weight:bold; margin-top:3px; }
  .header-doc .doc-fecha { font-size:10px; color:#aaa; margin-top:2px; }
  .badge-wrap { background:#f9fafb; padding:12px 32px; border-bottom:3px solid #e6a800; }
  .badge { display:inline-block; padding:3px 10px; border-radius:3px; font-size:10px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
  .body { padding:28px 32px; }
  .greeting { font-size:15px; color:#374151; margin-bottom:20px; line-height:1.6; }
  .cards { width:100%; border-collapse:collapse; margin-bottom:20px; }
  .cards td { vertical-align:top; padding:0 6px 0 0; width:50%; }
  .cards td:last-child { padding-right:0; }
  .card { background:#fafafa; border:1px solid #e0e0e0; border-radius:5px; padding:10px 13px; }
  .card-title { font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; color:#e6a800; border-bottom:1px solid #e0e0e0; padding-bottom:4px; margin-bottom:7px; }
  .card-row { font-size:10px; margin-bottom:3px; line-height:1.5; color:#333; }
  .card-row .lbl { color:#888; }
  .card-row .val { font-weight:bold; color:#111; }
  .items-table { width:100%; border-collapse:collapse; font-size:11px; }
  .items-table thead tr { background:#222; }
  .items-table thead th { color:#fff; padding:7px 8px; font-size:9px; text-transform:uppercase; letter-spacing:.5px; text-align:left; font-weight:bold; }
  .items-table thead th.r { text-align:right; }
  .items-table tbody tr { border-bottom:1px solid #e8e8e8; }
  .items-table tbody tr:nth-child(even) { background:#f7f7f7; }
  .items-table tbody td { padding:7px 8px; font-size:10px; }
  .items-table tbody td.r { text-align:right; }
  .totals-wrap { margin-top:12px; }
  .totals { width:50%; margin-left:auto; border-collapse:collapse; }
  .totals td { padding:4px 8px; font-size:10px; }
  .totals td.lbl { color:#666; text-align:right; }
  .totals td.val { font-weight:bold; color:#111; text-align:right; }
  .totals .grand td { border-top:2px solid #222; padding-top:8px; font-size:14px; }
  .totals .grand .lbl { color:#222; font-weight:700; }
  .totals .grand .val { color:#222; font-weight:800; }
  .vigencia-box { background:#fffbeb; border-left:4px solid #e6a800; border-radius:4px; padding:12px 16px; margin:16px 0; font-size:11px; color:#78350f; }
  .footer { background:#f9fafb; border-top:3px solid #e6a800; padding:16px 32px; text-align:center; }
  .footer p { font-size:10px; color:#9ca3af; line-height:1.8; }
  .footer a { color:#e6a800; text-decoration:none; }
</style>
</head>
<body>

@php
    $emp = $empresa ?? null;
    $ef  = $emp?->fiscalData ?? null;
@endphp

<div class="wrapper">

    {{-- Header --}}
    <div class="header">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="header-logo">{{ $emp?->razon_social ?? config('app.name') }}</div>
                    @if($emp?->rfc)
                    <div class="header-rfc">RFC: {{ $emp->rfc }}</div>
                    @endif
                </td>
                <td class="header-doc">
                    <div class="doc-tipo">Cotización</div>
                    <div class="doc-folio">{{ $quote->folio ?? '#'.$quote->id }}</div>
                    <div class="doc-fecha">{{ optional($quote->fecha)->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Badge --}}
    <div class="badge-wrap">
        <span class="badge">{{ $quote->status }}</span>
        @if($quote->vigencia)
        <span style="font-size:10px;color:#6b7280;margin-left:10px">
            Vigente hasta: <strong>{{ optional($quote->vigencia)->format('d/m/Y') }}</strong>
        </span>
        @endif
    </div>

    {{-- Body --}}
    <div class="body">

        <p class="greeting">
            Estimado(a) <strong>{{ $quote->client?->nombre ?? 'cliente' }}</strong>,<br>
            @if(!empty($mensaje))
                {{ $mensaje }}
            @else
                Adjunto encontrarás nuestra cotización en formato PDF. A continuación el resumen.
            @endif
        </p>

        {{-- Cards --}}
        <table class="cards" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="card">
                        <div class="card-title">Emisor</div>
                        <div class="card-row">
                            <span class="lbl">Empresa: </span>
                            <span class="val">{{ $emp?->razon_social ?? config('app.name') }}</span>
                        </div>
                        @if($emp?->rfc)
                        <div class="card-row">
                            <span class="lbl">RFC: </span>
                            <span class="val">{{ $emp->rfc }}</span>
                        </div>
                        @endif
                        @if($emp?->telefono)
                        <div class="card-row">
                            <span class="lbl">Tel: </span>
                            <span class="val">{{ $emp->telefono }}</span>
                        </div>
                        @endif
                        @if($emp?->email)
                        <div class="card-row">
                            <span class="lbl">Email: </span>
                            <span class="val">{{ $emp->email }}</span>
                        </div>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="card">
                        <div class="card-title">Cliente</div>
                        <div class="card-row">
                            <span class="lbl">Nombre: </span>
                            <span class="val">{{ $quote->client?->nombre ?? '—' }}</span>
                        </div>
                        @if($quote->client?->rfc)
                        <div class="card-row">
                            <span class="lbl">RFC: </span>
                            <span class="val">{{ $quote->client->rfc }}</span>
                        </div>
                        @endif
                        @if($quote->client?->telefono)
                        <div class="card-row">
                            <span class="lbl">Tel: </span>
                            <span class="val">{{ $quote->client->telefono }}</span>
                        </div>
                        @endif
                        <div class="card-row">
                            <span class="lbl">Moneda: </span>
                            <span class="val">{{ $quote->moneda ?? 'MXN' }}</span>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Vigencia --}}
        @if($quote->vigencia)
        <div class="vigencia-box">
            ⏰ Esta cotización tiene vigencia hasta el <strong>{{ optional($quote->vigencia)->format('d/m/Y') }}</strong>.
        </div>
        @endif

        {{-- Partidas --}}
        @if($quote->items && $quote->items->count())
        <table class="items-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="r">Cant.</th>
                    <th class="r">Precio</th>
                    <th class="r">Desc.</th>
                    <th class="r">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $it)
                <tr>
                    <td>{{ $it->descripcion }}</td>
                    <td class="r">{{ number_format((float)$it->cantidad, 3) }}</td>
                    <td class="r">${{ number_format((float)$it->precio, 2) }}</td>
                    <td class="r">
                        @if((float)($it->descuento ?? 0) > 0)
                            ${{ number_format((float)$it->descuento, 2) }}
                        @else —
                        @endif
                    </td>
                    <td class="r">${{ number_format((float)$it->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-wrap">
            <table class="totals" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="lbl">Subtotal</td>
                    <td class="val">${{ number_format((float)$quote->subtotal, 2) }}</td>
                </tr>
                @if((float)($quote->descuento ?? 0) > 0)
                <tr>
                    <td class="lbl">Descuento</td>
                    <td class="val">- ${{ number_format((float)$quote->descuento, 2) }}</td>
                </tr>
                @endif
                @if((float)($quote->impuestos ?? 0) > 0)
                <tr>
                    <td class="lbl">IVA</td>
                    <td class="val">${{ number_format((float)$quote->impuestos, 2) }}</td>
                </tr>
                @endif
                <tr class="grand">
                    <td class="lbl">Total</td>
                    <td class="val">{{ $quote->moneda ?? 'MXN' }} ${{ number_format((float)$quote->total, 2) }}</td>
                </tr>
            </table>
        </div>
        @endif

        <p style="font-size:11px;color:#6b7280;text-align:center;margin-top:20px">
            El PDF de la cotización está adjunto a este correo.<br>
            Si tienes alguna duda contáctanos con gusto.
        </p>

    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>
            {{ $emp?->razon_social ?? config('app.name') }}<br>
            Este correo fue generado automáticamente, por favor no respondas a este mensaje.<br>
            <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
        </p>
    </div>

</div>

</body>
</html>