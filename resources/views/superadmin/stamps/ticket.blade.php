<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Corte de timbres {{ $folio }}</title>
<style>
    body { font-family: 'Courier New', monospace; font-size: 13px; color: #000; background: #eee; margin: 0; padding: 16px; }
    .ticket { width: 320px; margin: 0 auto; background: #fff; padding: 16px 14px; }
    h1 { font-size: 15px; text-align: center; margin: 0 0 2px; }
    .c { text-align: center; }
    .sep { border-top: 1px dashed #000; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 2px 0; vertical-align: top; }
    td.r { text-align: right; }
    .tot td { font-weight: bold; font-size: 15px; padding-top: 4px; }
    .parcial { text-align: center; font-weight: bold; border: 1px solid #000; padding: 3px; margin: 6px 0; }
    .noprint { text-align: center; margin: 0 0 12px; }
    .noprint button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
    @media print { body { background: #fff; padding: 0; } .noprint { display: none; } .ticket { width: 100%; padding: 0; } }
</style>
</head>
<body>
@php $m = fn ($v) => '$' . number_format((float) $v, 2); @endphp
<div class="noprint"><button onclick="window.print()">Imprimir</button></div>
<div class="ticket">
    <h1>{{ mb_strtoupper(config('app.name')) }}</h1>
    <div class="c">Corte de timbres CFDI</div>
    <div class="sep"></div>
    <table>
        <tr><td>Folio</td><td class="r">{{ $folio }}</td></tr>
        <tr><td>Empresa</td><td class="r">{{ $company->nombre_display }}</td></tr>
        <tr><td>RFC</td><td class="r">{{ $company->rfc }}</td></tr>
        <tr><td>Periodo</td><td class="r">{{ $inicio->format('d/m/Y') }} al {{ $fin->format('d/m/Y') }}</td></tr>
    </table>
    @if($parcial)<div class="parcial">CORTE PARCIAL — periodo abierto</div>@endif
    <div class="sep"></div>
    <table>
        <tr><td>Timbres emitidos</td><td class="r">{{ $datos['timbres_usados'] }}</td></tr>
        <tr><td>Cortesía del periodo</td><td class="r">{{ $datos['cortesia'] }}</td></tr>
        <tr><td>Timbres excedentes</td><td class="r">{{ $datos['excedente'] }}</td></tr>
        <tr><td>Precio por timbre</td><td class="r">{{ $m($datos['precio_timbre']) }}</td></tr>
        <tr><td>Importe excedente</td><td class="r">{{ $m($datos['importe_excedente']) }}</td></tr>
        <tr><td>Mensualidad</td><td class="r">{{ $m($datos['mensualidad']) }}</td></tr>
    </table>
    <div class="sep"></div>
    <table class="tot"><tr><td>TOTAL</td><td class="r">{{ $m($datos['total']) }}</td></tr></table>
    <div class="sep"></div>
    <table>
        <tr><td>Cancelados (informativo)</td><td class="r">{{ $datos['cancelados'] }}</td></tr>
        @if($estado)
        <tr><td>Estado</td><td class="r">{{ $estado === 'pagado' ? 'PAGADO' : 'PENDIENTE' }}</td></tr>
        @if(!empty($datos['fecha_pago']))<tr><td>Fecha de pago</td><td class="r">{{ \Carbon\Carbon::parse($datos['fecha_pago'])->format('d/m/Y') }}</td></tr>@endif
        @endif
    </table>
    <div class="sep"></div>
    <div class="c" style="font-size:11px">Generado {{ now()->format('d/m/Y H:i') }}</div>
</div>
</body>
</html>
