<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page {
    margin: 14mm 16mm 14mm 16mm;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 9.5px; color: #111; }

/* ── Encabezado empresa ── */
.emp-nombre { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 1px; }
.emp-rfc    { text-align: center; font-size: 9px; color: #444; margin-bottom: 6px; }
.titulo     { text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 8px; }
.separador  { border: 0; border-top: 1px solid #555; margin-bottom: 8px; }

/* ── Bloque de filtros estilo "ficha" ── */
.filtros-grid { width: 100%; margin-bottom: 10px; border-collapse: collapse; font-size: 8.5px; }
.filtros-grid td { padding: 1px 4px; }
.filtros-grid .label { font-weight: bold; white-space: nowrap; }

/* ── Tabla de datos ── */
table.datos { width: 100%; border-collapse: collapse; }
table.datos th {
    background: #e5e7eb; font-size: 8px; text-transform: uppercase;
    padding: 3px 5px; text-align: left; border-bottom: 2px solid #9ca3af;
}
table.datos th.r { text-align: right; }
table.datos th.c { text-align: center; }
table.datos td   { padding: 2.5px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; font-size: 9px; }
table.datos td.r { text-align: right; }
table.datos td.c { text-align: center; }

.cliente-header td {
    background: #eff6ff; font-weight: bold; color: #1e40af;
    padding: 3px 5px; border-top: 1.5px solid #93c5fd; font-size: 9px;
}
.subtotal td {
    background: #f9fafb; font-weight: bold;
    border-top: 1px solid #9ca3af; border-bottom: 2px solid #6b7280;
    font-size: 9px;
}
.total-general td {
    background: #fef9c3; font-weight: bold; font-size: 10px;
    border-top: 2px solid #374151; border-bottom: 1px solid #374151;
}
.vencida { color: #dc2626; }
.spacer td { height: 5px; border: none; background: transparent; }
</style>
</head>
<body>

@php
    $ef        = $empresa?->fiscalData ?? null;
    $nombre    = $ef?->razon_social ?? $empresa?->nombre_comercial ?? config('app.name');
    $rfc       = $ef?->rfc ?? '';
    $moneda    = 'Pesos';
    $tipoCambio = '1.000000';

    $desde  = trim($filtros['cliente_desde'] ?? '');
    $hasta  = trim($filtros['cliente_hasta'] ?? '');
    $fvd    = trim($filtros['fecha_venc_desde'] ?? '');
    $fvh    = trim($filtros['fecha_venc_hasta'] ?? '');
    $status = $filtros['status'] ?? 'todos';
@endphp

<div class="emp-nombre">{{ $nombre }}</div>
@if($rfc)
<div class="emp-rfc">RFC: {{ $rfc }}</div>
@endif
<div class="titulo">Cobranza General</div>
<hr class="separador">

<table class="filtros-grid">
    <tr>
        <td class="label">Desde cliente:</td>
        <td>{{ $desde ?: 'Todos' }}</td>
        <td width="30"></td>
        <td class="label">Hasta cliente:</td>
        <td>{{ $hasta ?: 'Todos' }}</td>
        <td width="30"></td>
        <td class="label">Moneda:</td>
        <td>{{ $moneda }}</td>
        <td width="30"></td>
        <td class="label">Tipo cambio:</td>
        <td>{{ $tipoCambio }}</td>
    </tr>
    <tr>
        <td class="label">Fecha vencimiento:</td>
        <td colspan="4">
            @if($fvd || $fvh)
                {{ $fvd ? 'desde '.$fvd : '' }} {{ $fvh ? 'hasta '.$fvh : '' }}
            @else
                —
            @endif
        </td>
        <td colspan="2"></td>
        <td class="label">Estado:</td>
        <td colspan="3">{{ ucfirst($status) }}</td>
    </tr>
    <tr>
        <td class="label">Generado:</td>
        <td colspan="10">{{ now()->format('d/m/Y H:i') }}</td>
    </tr>
</table>

<table class="datos">
    <thead>
        <tr>
            <th>Concepto</th>
            <th>Documento</th>
            <th class="c">Núm.</th>
            <th class="c">Fecha Aplic.</th>
            <th class="c">Fecha Venc.</th>
            <th class="r">Cargos</th>
            <th class="r">Abonos</th>
            <th class="r">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($porCliente as $clientId => $notas)
            @php
                $primer         = $notas->first();
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
                    $vencida = $saldo > 0 && \Carbon\Carbon::parse($nota->fecha_vencimiento)->isPast();
                @endphp
                <tr>
                    <td>Nota de venta</td>
                    <td>{{ $nota->folio }}</td>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ \Carbon\Carbon::parse($nota->fecha)->format('d/m/Y') }}</td>
                    <td class="c {{ $vencida ? 'vencida' : '' }}">
                        {{ \Carbon\Carbon::parse($nota->fecha_vencimiento)->format('d/m/Y') }}
                    </td>
                    <td class="r">{{ number_format($cargos, 2) }}</td>
                    <td class="r">{{ number_format($abonos, 2) }}</td>
                    <td class="r">{{ number_format($saldo, 2) }}</td>
                </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="5" class="r">Totales {{ $primer->client_nombre }}:</td>
                <td class="r">{{ number_format($subtotalCargos, 2) }}</td>
                <td class="r">{{ number_format($subtotalAbonos, 2) }}</td>
                <td class="r">{{ number_format($subtotalSaldo, 2) }}</td>
            </tr>
            <tr class="spacer"><td colspan="8"></td></tr>
        @endforeach

        <tr class="total-general">
            <td colspan="5" class="r">Totales :</td>
            <td class="r">{{ number_format($totales['cargos'], 2) }}</td>
            <td class="r">{{ number_format($totales['abonos'], 2) }}</td>
            <td class="r">{{ number_format($totales['saldo'], 2) }}</td>
        </tr>
    </tbody>
</table>

</body>
</html>
