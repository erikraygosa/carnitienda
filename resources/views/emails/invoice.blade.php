<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Factura {{ $invoice->serie }}{{ $invoice->folio }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#f4f4f5; color:#1a1a1a; }
  .wrapper { max-width:620px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.1); }

  /* Header */
  .header { background:#222; padding:24px 32px; }
  .header table { width:100%; border-collapse:collapse; }
  .header-logo { font-size:20px; font-weight:bold; color:#e6a800; }
  .header-rfc  { font-size:10px; color:#aaa; margin-top:2px; }
  .header-doc  { text-align:right; }
  .header-doc .doc-tipo  { font-size:16px; font-weight:bold; color:#fff; text-transform:uppercase; letter-spacing:1px; }
  .header-doc .doc-folio { font-size:13px; color:#e6a800; font-weight:bold; margin-top:3px; }
  .header-doc .doc-fecha { font-size:10px; color:#aaa; margin-top:2px; }

  /* Badge */
  .badge-wrap { background:#f9fafb; padding:12px 32px; border-bottom:3px solid #e6a800; }
  .badge { display:inline-block; padding:3px 10px; border-radius:3px; font-size:10px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; }
  .badge-draft    { background:#f0f0f0; color:#555;    border:1px solid #ccc; }
  .badge-stamped  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
  .badge-canceled { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

  /* Body */
  .body { padding:28px 32px; }
  .greeting { font-size:15px; color:#374151; margin-bottom:20px; line-height:1.6; }

  /* Info card */
  .info-card { background:#fafafa; border:1px solid #e0e0e0; border-radius:5px; padding:14px 18px; margin-bottom:20px; }
  .info-card-title { font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; color:#e6a800; border-bottom:1px solid #e0e0e0; padding-bottom:4px; margin-bottom:10px; }
  .info-card table { width:100%; border-collapse:collapse; }
  .info-card td { padding:5px 0; font-size:11px; vertical-align:top; }
  .info-card td.lbl { color:#888; width:38%; }
  .info-card td.val { color:#111; font-weight:600; }

  /* Cards grid */
  .cards { width:100%; border-collapse:collapse; margin-bottom:20px; }
  .cards td { vertical-align:top; padding:0 6px 0 0; width:50%; }
  .cards td:last-child { padding-right:0; }
  .card { background:#fafafa; border:1px solid #e0e0e0; border-radius:5px; padding:10px 13px; }
  .card-title { font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; color:#e6a800; border-bottom:1px solid #e0e0e0; padding-bottom:4px; margin-bottom:7px; }
  .card-row { font-size:10px; margin-bottom:3px; line-height:1.5; color:#333; }
  .card-row .lbl { color:#888; }
  .card-row .val { font-weight:bold; color:#111; }

  /* Items */
  .items-table { width:100%; border-collapse:collapse; margin-bottom:0; font-size:11px; }
  .items-table thead tr { background:#222; }
  .items-table thead th { color:#fff; padding:7px 8px; font-size:9px; text-transform:uppercase; letter-spacing:.5px; text-align:left; font-weight:bold; }
  .items-table thead th.r { text-align:right; }
  .items-table tbody tr { border-bottom:1px solid #e8e8e8; }
  .items-table tbody tr:nth-child(even) { background:#f7f7f7; }
  .items-table tbody td { padding:7px 8px; font-size:10px; }
  .items-table tbody td.r { text-align:right; }

  /* Totals */
  .totals-wrap { margin-top:12px; }
  .totals { width:50%; margin-left:auto; border-collapse:collapse; }
  .totals td { padding:4px 8px; font-size:10px; }
  .totals td.lbl { color:#666; text-align:right; }
  .totals td.val { font-weight:bold; color:#111; text-align:right; }
  .totals .grand td { border-top:2px solid #222; padding-top:8px; font-size:14px; }
  .totals .grand .lbl { color:#222; font-weight:700; }
  .totals .grand .val { color:#222; font-weight:800; }

  /* UUID */
  .uuid-box { background:#f0f9ff; border:1px solid #bae6fd; border-radius:5px; padding:10px 14px; margin-top:16px; }
  .uuid-title { font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; color:#0369a1; margin-bottom:4px; }
  .uuid-val { font-family:monospace; font-size:10px; color:#0c4a6e; word-break:break-all; }

  /* Footer */
  .footer { background:#f9fafb; border-top:3px solid #e6a800; padding:16px 32px; text-align:center; }
  .footer p { font-size:10px; color:#9ca3af; line-height:1.8; }
  .footer a { color:#e6a800; text-decoration:none; }
</style>
</head>
<body>

@php
    $emp = $empresa ?? null;
    $ef  = $emp?->fiscalData ?? null;
    $cliente = $invoice->client ?? null;

    // Emisor: nombre comercial (lo que ve el cliente), razón social y RFC.
    $nombreComercial = $emp?->nombre_comercial ?: ($ef?->razon_social ?: ($emp?->razon_social ?: config('app.name')));
    $razonSocial     = $ef?->razon_social ?: $emp?->razon_social;
    $rfcEmisor       = $emp?->rfc ?: $ef?->rfc;

    $badgeClass = match($invoice->estatus ?? 'BORRADOR') {
        'TIMBRADA'  => 'badge-stamped',
        'CANCELADA' => 'badge-canceled',
        default     => 'badge-draft',
    };

    $sat = \App\Support\SatCatalogs::class;
    $regimenes = \App\Models\CompanyFiscalData::REGIMENES_FISCALES;
    $regEmisor   = $invoice->regimen_fiscal_emisor ?: $ef?->regimen_fiscal;
    $regReceptor = $invoice->regimen_fiscal_receptor ?: $cliente?->regimen_fiscal;
    $nombreReceptor = $invoice->receptor_razon_social ?: ($cliente?->razon_social ?: ($cliente?->nombre ?: '—'));
    $rfcReceptor    = $invoice->receptor_rfc ?: ($cliente?->rfc ?: 'XAXX010101000');

    // Logo del sistema (o de la empresa si tiene), como imagen adjunta (CID)
    // para que Gmail/Outlook sí lo muestren.
    $logoCid = null;
    $rutasLogo = [];
    if (!empty($emp?->logo_path)) {
        $rutasLogo[] = storage_path('app/public/' . ltrim($emp->logo_path, '/'));
    }
    $rutasLogo[] = public_path(\App\Models\SystemSetting::get('app.logo_path', 'logo.jpg') ?: 'logo.jpg');
    foreach ($rutasLogo as $r) {
        if (is_file($r) && isset($message)) {
            $logoCid = $message->embed($r);
            break;
        }
    }
@endphp

<div class="wrapper">

    {{-- Header --}}
    <div class="header">
        <table cellpadding="0" cellspacing="0">
            <tr>
                @if($logoCid)
                <td width="78" style="vertical-align:middle;padding-right:14px">
                    <div style="background:#fff;border-radius:8px;padding:4px;width:64px;height:64px;text-align:center">
                        <img src="{{ $logoCid }}" alt="{{ $nombreComercial }}" width="56" height="56" style="display:block;margin:0 auto;border:0;width:56px;height:56px;object-fit:contain">
                    </div>
                </td>
                @endif
                <td style="vertical-align:middle">
                    <div class="header-logo">{{ $nombreComercial }}</div>
                    <div class="header-rfc">
                        @if($razonSocial && $razonSocial !== $nombreComercial){{ $razonSocial }}@endif
                        @if($rfcEmisor) &nbsp;·&nbsp; RFC: {{ $rfcEmisor }}@endif
                    </div>
                </td>
                <td class="header-doc">
                    <div class="doc-tipo">Factura CFDI</div>
                    <div class="doc-folio">{{ $invoice->serie }}{{ $invoice->folio }}</div>
                    <div class="doc-fecha">{{ optional($invoice->fecha)->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Badge estatus --}}
    <div class="badge-wrap">
        <span class="badge {{ $badgeClass }}">{{ $invoice->estatus ?? 'BORRADOR' }}</span>
        <span style="font-size:10px;color:#6b7280;margin-left:10px">
            Folio: <strong style="color:#222">{{ $invoice->serie }}{{ $invoice->folio }}</strong>
            &nbsp;·&nbsp;
            {{ optional($invoice->fecha)->format('d/m/Y') }}
        </span>
    </div>

    {{-- Body --}}
    <div class="body">

        {{-- Saludo --}}
        <p class="greeting">
            Estimado(a) <strong>{{ $nombreReceptor !== '—' ? $nombreReceptor : 'cliente' }}</strong>,<br>
            @if(!empty($mensaje))
                {{ $mensaje }}
            @else
                Adjunto a este correo encontrarás tu factura en formato PDF. A continuación el resumen del comprobante.
            @endif
        </p>

        {{-- Cards emisor / receptor --}}
        <table class="cards" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="card">
                        <div class="card-title">Emisor</div>
                        <div class="card-row"><span class="lbl">Nombre comercial: </span><span class="val">{{ $nombreComercial }}</span></div>
                        <div class="card-row"><span class="lbl">Razón social: </span><span class="val">{{ $razonSocial ?: '—' }}</span></div>
                        <div class="card-row"><span class="lbl">RFC: </span><span class="val">{{ $rfcEmisor ?: '—' }}</span></div>
                        @if($regEmisor)
                        <div class="card-row"><span class="lbl">Régimen: </span><span class="val">{{ $sat::etiqueta($regimenes, $regEmisor) }}</span></div>
                        @endif
                        @if($invoice->lugar_expedicion)
                        <div class="card-row"><span class="lbl">Lugar de expedición: </span><span class="val">{{ $invoice->lugar_expedicion }}</span></div>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="card">
                        <div class="card-title">Receptor</div>
                        <div class="card-row"><span class="lbl">Nombre: </span><span class="val">{{ $nombreReceptor }}</span></div>
                        <div class="card-row"><span class="lbl">RFC: </span><span class="val">{{ $rfcReceptor }}</span></div>
                        @if($regReceptor)
                        <div class="card-row"><span class="lbl">Régimen: </span><span class="val">{{ $sat::etiqueta($regimenes, $regReceptor) }}</span></div>
                        @endif
                        @if($invoice->receptor_cp)
                        <div class="card-row"><span class="lbl">C.P.: </span><span class="val">{{ $invoice->receptor_cp }}</span></div>
                        @endif
                        <div class="card-row"><span class="lbl">Uso CFDI: </span><span class="val">{{ $sat::etiqueta($sat::USO_CFDI, $invoice->uso_cfdi) }}</span></div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Datos del comprobante --}}
        <table class="cards" cellpadding="0" cellspacing="0">
            <tr>
                <td colspan="2">
                    <div class="card">
                        <div class="card-title">Datos del comprobante</div>
                        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">
                            <tr>
                                <td style="width:50%;padding:0 8px 0 0"><div class="card-row"><span class="lbl">Tipo: </span><span class="val">{{ $sat::etiqueta($sat::TIPO_COMPROBANTE, $invoice->tipo_comprobante) }}</span></div></td>
                                <td style="width:50%;padding:0"><div class="card-row"><span class="lbl">Moneda: </span><span class="val">{{ $invoice->moneda ?? 'MXN' }}</span></div></td>
                            </tr>
                            <tr>
                                <td style="padding:0 8px 0 0"><div class="card-row"><span class="lbl">Forma de pago: </span><span class="val">{{ $sat::etiqueta($sat::FORMA_PAGO, $invoice->forma_pago) }}</span></div></td>
                                <td style="padding:0"><div class="card-row"><span class="lbl">Método de pago: </span><span class="val">{{ $sat::etiqueta($sat::METODO_PAGO, $invoice->metodo_pago) }}</span></div></td>
                            </tr>
                            @if($invoice->condiciones_pago)
                            <tr><td colspan="2" style="padding:0"><div class="card-row"><span class="lbl">Condiciones de pago: </span><span class="val">{{ $invoice->condiciones_pago }}</span></div></td></tr>
                            @endif
                            @if($invoice->numero_certificado_sat)
                            <tr><td colspan="2" style="padding:0"><div class="card-row"><span class="lbl">No. certificado SAT: </span><span class="val">{{ $invoice->numero_certificado_sat }}</span></div></td></tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Partidas --}}
        @if($invoice->items && $invoice->items->count())
        <table class="items-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Unidad</th>
                    <th class="r">Cant.</th>
                    <th class="r">P. Unit.</th>
                    <th class="r">Desc.</th>
                    <th class="r">IVA</th>
                    <th class="r">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $it)
                <tr>
                    <td>{{ $it->descripcion }}@if($it->clave_prod_serv)<br><span style="color:#9ca3af;font-size:8px">Clave SAT: {{ $it->clave_prod_serv }}</span>@endif</td>
                    <td>{{ $it->unidad ?: ($it->clave_unidad ?? '') }}</td>
                    <td class="r">{{ number_format((float)$it->cantidad, 3) }}</td>
                    <td class="r">${{ number_format((float)$it->valor_unitario, 2) }}</td>
                    <td class="r">{{ (float)$it->descuento > 0 ? '$'.number_format((float)$it->descuento, 2) : '—' }}</td>
                    <td class="r">{{ (float)($it->iva_importe ?? 0) > 0 ? '$'.number_format((float)$it->iva_importe, 2) : '—' }}</td>
                    <td class="r">${{ number_format((float)$it->importe, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totales --}}
        @php $descTotal = (float) ($invoice->descuento ?? 0) ?: (float) $invoice->items->sum('descuento'); @endphp
        <div class="totals-wrap">
            <table class="totals" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="lbl">Subtotal</td>
                    <td class="val">${{ number_format((float)$invoice->subtotal, 2) }}</td>
                </tr>
                @if($descTotal > 0)
                <tr>
                    <td class="lbl">Descuento</td>
                    <td class="val">- ${{ number_format($descTotal, 2) }}</td>
                </tr>
                @endif
                @if((float)($invoice->impuestos ?? 0) > 0)
                <tr>
                    <td class="lbl">IVA</td>
                    <td class="val">${{ number_format((float)$invoice->impuestos, 2) }}</td>
                </tr>
                @endif
                <tr class="grand">
                    <td class="lbl">Total</td>
                    <td class="val">{{ $invoice->moneda ?? 'MXN' }} ${{ number_format((float)$invoice->total, 2) }}</td>
                </tr>
            </table>
        </div>
        @endif

        {{-- UUID --}}
        @if($invoice->uuid)
        <div class="uuid-box">
            <div class="uuid-title">🔐 Folio Fiscal (UUID)</div>
            <div class="uuid-val">{{ $invoice->uuid }}</div>
        </div>
        @endif

        <p style="font-size:11px;color:#6b7280;text-align:center;margin-top:20px">
            {{ !empty($conXml) ? 'El PDF y el XML de tu factura están adjuntos a este correo.' : 'El PDF de tu factura está adjunto a este correo.' }}<br>
            Si tienes alguna duda contáctanos.
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