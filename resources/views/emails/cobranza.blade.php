<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Estado de cuenta</title>
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
    $nombreComercial = $emp?->nombre_comercial ?: ($ef?->razon_social ?: ($emp?->razon_social ?: config('app.name')));
    $razonSocial     = $ef?->razon_social ?: $emp?->razon_social;
    $rfcEmisor       = $emp?->rfc ?: $ef?->rfc;
    $fmt = fn ($n) => '$' . number_format((float) $n, 2);
    $unCliente = count($resumen) === 1;
    $vencidoTotal = collect($resumen)->sum('vencido');

    $logoCid = null;
    $rutasLogo = [];
    if (!empty($emp?->logo_path)) {
        $rutasLogo[] = storage_path('app/public/' . ltrim($emp->logo_path, '/'));
    }
    $rutasLogo[] = public_path(\App\Models\SystemSetting::get('app.logo_path', 'logo.jpg') ?: 'logo.jpg');
    foreach ($rutasLogo as $r) {
        if (is_file($r) && isset($message)) { $logoCid = $message->embed($r); break; }
    }

    $fvd = $filtros['fecha_venc_desde'] ?? ''; $fvh = $filtros['fecha_venc_hasta'] ?? '';
@endphp

<div class="wrapper">
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
                    <div class="doc-tipo">Estado de cuenta</div>
                    <div class="doc-fecha">{{ now()->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">
        <p class="greeting">
            @if($unCliente) Estimado(a) <strong>{{ $resumen[0]['cliente'] }}</strong>,<br> @else Buen día,<br> @endif
            @if(!empty($mensaje)) {{ $mensaje }}
            @else Adjunto encontrarás tu estado de cuenta en formato PDF. A continuación el resumen. @endif
        </p>

        @if($fvd || $fvh)
        <p style="font-size:11px;color:#6b7280;margin-bottom:14px">
            Vencimientos {{ $fvd ? 'desde '.\Carbon\Carbon::parse($fvd)->format('d/m/Y') : '' }} {{ $fvh ? 'hasta '.\Carbon\Carbon::parse($fvh)->format('d/m/Y') : '' }}
        </p>
        @endif

        <table class="items-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th class="r">Notas</th>
                    <th class="r">Vencido</th>
                    <th class="r">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumen as $r)
                <tr>
                    <td>{{ $r['cliente'] }}</td>
                    <td class="r">{{ $r['notas'] }}</td>
                    <td class="r" style="{{ $r['vencido'] > 0 ? 'color:#b91c1c;font-weight:bold' : '' }}">{{ $fmt($r['vencido']) }}</td>
                    <td class="r"><strong>{{ $fmt($r['saldo']) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-wrap">
            <table class="totals" cellpadding="0" cellspacing="0">
                <tr><td class="lbl">Cargos</td><td class="val">{{ $fmt($totales['cargos']) }}</td></tr>
                <tr><td class="lbl">Abonos</td><td class="val">{{ $fmt($totales['abonos']) }}</td></tr>
                @if($vencidoTotal > 0)
                <tr><td class="lbl" style="color:#b91c1c">Vencido</td><td class="val" style="color:#b91c1c">{{ $fmt($vencidoTotal) }}</td></tr>
                @endif
                <tr class="grand"><td class="lbl">Saldo</td><td class="val">{{ $fmt($totales['saldo']) }}</td></tr>
            </table>
        </div>

        <p style="font-size:11px;color:#6b7280;text-align:center;margin-top:22px">
            El detalle por nota viene en el PDF adjunto.<br>
            Si tienes alguna duda contáctanos.
        </p>
    </div>

    <div class="footer">
        <p>{{ $razonSocial ?: $nombreComercial }}</p>
        <p>Este correo fue generado automáticamente, por favor no respondas a este mensaje.@if($emp?->email)<br><a href="mailto:{{ $emp->email }}">{{ $emp->email }}</a>@endif</p>
    </div>
</div>
</body>
</html>
