<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Factura {{ $invoice->serie }}{{ $invoice->folio }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#f4f4f5; color:#1a1a1a; }
  .wrapper { max-width:600px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.1); }

  /* Header */
  .header { background:#1e1b4b; padding:32px 40px; text-align:center; }
  .header img { height:48px; margin-bottom:12px; }
  .header h1 { color:#fff; font-size:22px; font-weight:700; letter-spacing:.5px; }
  .header p  { color:#a5b4fc; font-size:13px; margin-top:4px; }

  /* Badge */
  .badge-wrap { background:#f0f0ff; padding:16px 40px; border-bottom:1px solid #e5e7eb; }
  .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
  .badge-draft    { background:#f3f4f6; color:#6b7280; }
  .badge-stamped  { background:#dcfce7; color:#166534; }
  .badge-canceled { background:#fee2e2; color:#991b1b; }

  /* Body */
  .body { padding:32px 40px; }
  .greeting { font-size:16px; color:#374151; margin-bottom:20px; line-height:1.6; }

  /* Info card */
  .info-card { background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:20px 24px; margin-bottom:24px; }
  .info-card table { width:100%; border-collapse:collapse; }
  .info-card td { padding:6px 0; font-size:13px; vertical-align:top; }
  .info-card td:first-child { color:#6b7280; width:40%; }
  .info-card td:last-child  { color:#111827; font-weight:600; }

  /* Divider */
  .divider { border:none; border-top:1px solid #e5e7eb; margin:24px 0; }

  /* Items */
  .items-table { width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px; }
  .items-table thead tr { background:#1e1b4b; }
  .items-table thead th { color:#fff; padding:8px 10px; text-align:left; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.5px; }
  .items-table thead th.r { text-align:right; }
  .items-table tbody tr { border-bottom:1px solid #f3f4f6; }
  .items-table tbody tr:nth-child(even) { background:#fafafa; }
  .items-table tbody td { padding:8px 10px; color:#374151; }
  .items-table tbody td.r { text-align:right; }

  /* Totals */
  .totals { width:100%; border-collapse:collapse; margin-bottom:24px; }
  .totals td { padding:5px 10px; font-size:13px; }
  .totals td.lbl { color:#6b7280; text-align:right; width:70%; }
  .totals td.val { color:#111827; font-weight:600; text-align:right; }
  .totals .grand td { border-top:2px solid #1e1b4b; padding-top:10px; font-size:16px; }
  .totals .grand .lbl { color:#1e1b4b; font-weight:700; }
  .totals .grand .val { color:#1e1b4b; font-weight:800; }

  /* Message */
  .message-box { background:#fffbeb; border-left:4px solid #f59e0b; border-radius:4px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:#78350f; line-height:1.6; }

  /* CTA */
  .cta-wrap { text-align:center; margin:28px 0; }
  .cta { display:inline-block; background:#4f46e5; color:#fff; padding:12px 32px; border-radius:6px; font-size:14px; font-weight:600; text-decoration:none; letter-spacing:.3px; }

  /* Footer */
  .footer { background:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 40px; text-align:center; }
  .footer p { font-size:11px; color:#9ca3af; line-height:1.8; }
  .footer a { color:#6366f1; text-decoration:none; }
</style>
</head>
<body>

<div class="wrapper">

    {{-- Header --}}
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <p>Comprobante Fiscal Digital por Internet</p>
    </div>

    {{-- Badge estatus --}}
    <div class="badge-wrap">
        @php
            $badgeClass = match($invoice->estatus ?? 'BORRADOR') {
                'TIMBRADA'  => 'badge-stamped',
                'CANCELADA' => 'badge-canceled',
                default     => 'badge-draft',
            };
        @endphp
        <span class="badge {{ $badgeClass }}">{{ $invoice->estatus ?? 'BORRADOR' }}</span>
        &nbsp;
        <span style="font-size:13px;color:#6b7280">
            Factura <strong style="color:#1e1b4b">{{ $invoice->serie }}{{ $invoice->folio }}</strong>
            &nbsp;·&nbsp;
            {{ optional($invoice->fecha)->format('d/m/Y') }}
        </span>
    </div>

    {{-- Body --}}
    <div class="body">

        {{-- Saludo --}}
        <p class="greeting">
            Estimado(a) <strong>{{ $invoice->client?->razon_social ?? $invoice->client?->nombre ?? 'cliente' }}</strong>,<br>
            @if($mensaje)
                {{ $mensaje }}
            @else
                Adjunto a este correo encontrarás tu factura en formato PDF. A continuación el resumen del comprobante.
            @endif
        </p>

        {{-- Info del comprobante --}}
        <div class="info-card">
            <table>
                <tr>
                    <td>Folio:</td>
                    <td>{{ $invoice->serie }}{{ $invoice->folio }}</td>
                </tr>
                <tr>
                    <td>Fecha:</td>
                    <td>{{ optional($invoice->fecha)->format('d/m/Y H:i') }}</td>
                </tr>
                <tr>
                    <td>RFC Emisor:</td>
                    <td>{{ $invoice->company?->rfc ?? '—' }}</td>
                </tr>
                <tr>
                    <td>RFC Receptor:</td>
                    <td>{{ $invoice->client?->rfc ?? 'XAXX010101000' }}</td>
                </tr>
                <tr>
                    <td>Uso CFDI:</td>
                    <td>{{ $invoice->uso_cfdi ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Forma de pago:</td>
                    <td>{{ $invoice->forma_pago ?? '—' }}</td>
                </tr>
                @if($invoice->uuid)
                <tr>
                    <td>UUID:</td>
                    <td style="font-family:monospace;font-size:11px;word-break:break-all">{{ $invoice->uuid }}</td>
                </tr>
                @endif
            </table>
        </div>

        {{-- Partidas --}}
        @if($invoice->items && $invoice->items->count())
        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="r">Cant.</th>
                    <th class="r">P. Unit.</th>
                    <th class="r">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $it)
                <tr>
                    <td>{{ $it->descripcion }}</td>
                    <td class="r">{{ number_format((float)$it->cantidad, 2) }}</td>
                    <td class="r">${{ number_format((float)$it->valor_unitario, 2) }}</td>
                    <td class="r">${{ number_format((float)$it->importe, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totales --}}
        <table class="totals">
            <tr>
                <td class="lbl">Subtotal</td>
                <td class="val">${{ number_format((float)$invoice->subtotal, 2) }}</td>
            </tr>
            @if((float)($invoice->descuento ?? 0) > 0)
            <tr>
                <td class="lbl">Descuento</td>
                <td class="val">- ${{ number_format((float)$invoice->descuento, 2) }}</td>
            </tr>
            @endif
            @if((float)$invoice->impuestos > 0)
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
        @endif

        <hr class="divider">

        <p style="font-size:13px;color:#6b7280;text-align:center">
            El PDF de tu factura está adjunto a este correo.<br>
            Si tienes alguna duda, no dudes en contactarnos.
        </p>

    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>
            {{ config('app.name') }}<br>
            Este es un correo generado automáticamente, por favor no respondas a este mensaje.<br>
            <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
        </p>
    </div>

</div>

</body>
</html>