<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Estado de cuenta</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Helvetica, Arial, sans-serif; background:#f4f4f5; color:#000; }
  .wrapper { max-width:620px; margin:24px auto; background:#fff; border:1px solid #d4d4d8; padding:28px 32px; }
  .empresa { text-align:center; font-size:17px; text-transform:uppercase; }
  .titulo  { text-align:center; font-size:19px; font-weight:bold; margin:14px 0 6px; }
  .filtros { border-top:1px solid #000; border-bottom:1px solid #000; padding:6px 4px; font-size:12px; line-height:1.6; margin-bottom:18px; }
  .saludo  { font-size:14px; line-height:1.6; margin-bottom:16px; }
  table.datos { width:100%; border-collapse:collapse; font-size:12px; }
  table.datos th { text-align:left; font-weight:bold; padding:4px 6px; border-bottom:1px solid #000; }
  table.datos th.r, table.datos td.r { text-align:right; }
  table.datos td { padding:4px 6px; }
  table.datos tr.tot td { border-top:1px solid #000; font-weight:bold; padding-top:6px; }
  .nota  { font-size:12px; text-align:center; margin-top:22px; line-height:1.6; }
  .pie   { border-top:1px solid #000; margin-top:22px; padding-top:8px; font-size:11px; text-align:center; color:#444; line-height:1.6; }
  .pie a { color:#000; }
</style>
</head>
<body>
@php
    $emp = $empresa ?? null;
    $ef  = $emp?->fiscalData ?? null;
    $nombreComercial = $emp?->nombre_comercial ?: ($ef?->razon_social ?: ($emp?->razon_social ?: config('app.name')));
    $razonSocial     = $ef?->razon_social ?: $emp?->razon_social;
    $fmt = fn ($n) => number_format((float) $n, 2);
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
    @if($logoCid)
    <div style="text-align:center;margin-bottom:8px">
        <img src="{{ $logoCid }}" alt="{{ $nombreComercial }}" width="56" height="56" style="border:0;width:56px;height:56px;object-fit:contain">
    </div>
    @endif
    <div class="empresa">{{ $razonSocial ?: $nombreComercial }}</div>
    <div class="titulo">Estado de cuenta</div>

    <div class="filtros">
        <b>Fecha:</b> {{ now()->format('d/m/Y H:i') }}
        @if($fvd || $fvh)
            &nbsp;&nbsp;·&nbsp;&nbsp;<b>Vencimientos:</b>
            {{ $fvd ? 'desde ' . \Carbon\Carbon::parse($fvd)->format('d/m/Y') : '' }} {{ $fvh ? 'hasta ' . \Carbon\Carbon::parse($fvh)->format('d/m/Y') : '' }}
        @endif
        &nbsp;&nbsp;·&nbsp;&nbsp;<b>Moneda:</b> Pesos
    </div>

    <p class="saludo">
        @if($unCliente) Estimado(a) <b>{{ $resumen[0]['cliente'] }}</b>,<br> @else Buen día,<br> @endif
        @if(!empty($mensaje)) {{ $mensaje }}
        @else Adjunto encontrarás tu estado de cuenta en formato PDF. A continuación el resumen. @endif
    </p>

    <table class="datos" cellpadding="0" cellspacing="0">
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
                <td>{{ mb_strtoupper($r['cliente']) }}</td>
                <td class="r">{{ $r['notas'] }}</td>
                <td class="r">{{ $fmt($r['vencido']) }}</td>
                <td class="r">{{ $fmt($r['saldo']) }}</td>
            </tr>
            @endforeach
            <tr class="tot">
                <td colspan="2" class="r">Totales :</td>
                <td class="r">{{ $fmt($vencidoTotal) }}</td>
                <td class="r">{{ $fmt($totales['saldo']) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="datos" cellpadding="0" cellspacing="0" style="width:55%;margin:14px 0 0 auto">
        <tr><td>Cargos</td><td class="r">{{ $fmt($totales['cargos']) }}</td></tr>
        <tr><td>Abonos</td><td class="r">{{ $fmt($totales['abonos']) }}</td></tr>
        <tr class="tot"><td>Saldo</td><td class="r">{{ $fmt($totales['saldo']) }}</td></tr>
    </table>

    <p class="nota">
        El detalle por nota viene en el PDF adjunto.<br>
        Si tienes alguna duda contáctanos.
    </p>

    <div class="pie">
        {{ $razonSocial ?: $nombreComercial }}<br>
        Este correo fue generado automáticamente, por favor no respondas a este mensaje.@if($emp?->email)<br><a href="mailto:{{ $emp->email }}">{{ $emp->email }}</a>@endif
    </div>
</div>
</body>
</html>
