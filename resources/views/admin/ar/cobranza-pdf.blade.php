<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 10px; color: #111; }
    .empresa { text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 4px; }
    .titulo  { text-align: center; font-size: 15px; font-weight: bold; margin-bottom: 10px; }
    .filtros { font-size: 8.5px; color: #555; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 6px; }
    .filtros td { padding: 1px 6px; }
    table.datos { width: 100%; border-collapse: collapse; }
    table.datos th {
        background: #d1d5db; font-size: 9px; text-transform: uppercase;
        padding: 4px 5px; text-align: left; border: 1px solid #aaa;
    }
    table.datos td { padding: 3px 5px; border: 1px solid #ddd; vertical-align: top; }
    .cliente-header td {
        background: #e0e7ff; font-weight: bold; color: #3730a3;
        padding: 4px 5px; border-top: 2px solid #6366f1;
    }
    .subtotal td { background: #f3f4f6; font-weight: bold; border-top: 1px solid #999; }
    .total-general td { background: #fef3c7; font-weight: bold; font-size: 11px; border-top: 2px solid #333; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .vencida { color: #dc2626; }
    .spacer td { padding: 3px; border: none; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>

<div class="empresa">
    {{ $empresa?->fiscalData?->razon_social ?? $empresa?->nombre_comercial ?? config('app.name') }}
</div>
<div class="titulo">Cobranza General</div>

<table class="filtros">
    <tr>
        @if(!empty($filtros['fecha_venc_desde']) || !empty($filtros['fecha_venc_hasta']))
        <td><strong>Fecha vencimiento:</strong>
            {{ !empty($filtros['fecha_venc_desde']) ? 'desde '.$filtros['fecha_venc_desde'] : '' }}
            {{ !empty($filtros['fecha_venc_hasta']) ? 'hasta '.$filtros['fecha_venc_hasta'] : '' }}
        </td>
        @endif
        <td><strong>Estado:</strong> {{ ucfirst($filtros['status'] ?? 'todos') }}</td>
        <td><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</td>
    </tr>
</table>

<table class="datos">
    <thead>
        <tr>
            <th>Concepto</th>
            <th>Documento</th>
            <th class="text-center">Num.</th>
            <th class="text-center">Fecha Aplic.</th>
            <th class="text-center">Fecha Venc.</th>
            <th class="text-right">Cargos</th>
            <th class="text-right">Abonos</th>
            <th class="text-right">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($porCliente as $clientId => $notas)
            @php
                $primer = $notas->first();
                $subtotalCargos = $notas->sum('total');
                $subtotalSaldo  = $notas->sum(fn($r) => $r->saldo_pendiente ?? $r->total);
                $subtotalAbonos = $subtotalCargos - $subtotalSaldo;
            @endphp
            <tr class="cliente-header">
                <td colspan="8">{{ $primer->client_nombre }}</td>
            </tr>
            @foreach($notas as $i => $nota)
                @php
                    $saldo  = $nota->saldo_pendiente ?? $nota->total;
                    $cargos = (float) $nota->total;
                    $abonos = $cargos - $saldo;
                    $vencida = \Carbon\Carbon::parse($nota->fecha_vencimiento)->isPast();
                @endphp
                <tr>
                    <td>Nota de venta</td>
                    <td>{{ $nota->folio }}</td>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($nota->fecha)->format('d/m/Y') }}</td>
                    <td class="text-center {{ $vencida ? 'vencida' : '' }}">
                        {{ \Carbon\Carbon::parse($nota->fecha_vencimiento)->format('d/m/Y') }}
                    </td>
                    <td class="text-right">{{ number_format($cargos, 2) }}</td>
                    <td class="text-right">{{ number_format($abonos, 2) }}</td>
                    <td class="text-right">{{ number_format($saldo, 2) }}</td>
                </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="5" class="text-right">Totales {{ $primer->client_nombre }}:</td>
                <td class="text-right">{{ number_format($subtotalCargos, 2) }}</td>
                <td class="text-right">{{ number_format($subtotalAbonos, 2) }}</td>
                <td class="text-right">{{ number_format($subtotalSaldo, 2) }}</td>
            </tr>
            <tr class="spacer"><td colspan="8"></td></tr>
        @endforeach

        <tr class="total-general">
            <td colspan="5" class="text-right">Totales :</td>
            <td class="text-right">{{ number_format($totales['cargos'], 2) }}</td>
            <td class="text-right">{{ number_format($totales['abonos'], 2) }}</td>
            <td class="text-right">{{ number_format($totales['saldo'], 2) }}</td>
        </tr>
    </tbody>
</table>

</body>
</html>
