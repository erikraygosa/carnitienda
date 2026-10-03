<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 16mm 14mm 18mm 14mm; }
* { box-sizing: border-box; }
body { margin: 0; padding: 0; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1f2937; }

/* ── Encabezado ── */
table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
table.encabezado td { vertical-align: middle; padding: 0; }
.logo { width: 62px; height: auto; }
.emp-nombre { font-size: 16px; font-weight: bold; color: #111827; }
.emp-rfc    { font-size: 9px; color: #6b7280; margin-top: 2px; }
.titulo-box { text-align: right; }
.titulo     { font-size: 15px; font-weight: bold; color: #1e3a8a; }
.generado   { font-size: 8px; color: #6b7280; margin-top: 3px; }
.banda { height: 3px; background: #1e3a8a; margin-bottom: 10px; }

/* ── Filtros ── */
table.filtros { width: 100%; border-collapse: collapse; background: #f3f4f6; border: 1px solid #e5e7eb; margin-bottom: 12px; }
table.filtros td { padding: 3px 8px; font-size: 8.5px; }
table.filtros .label { font-weight: bold; color: #374151; white-space: nowrap; }

/* ── Tabla de datos ── */
table.datos { width: 100%; border-collapse: collapse; }
table.datos thead th {
    background: #1f2937; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: .3px;
    padding: 6px 7px; text-align: left;
}
table.datos th.r { text-align: right; }
table.datos th.c { text-align: center; }
table.datos td   { padding: 4px 7px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; font-size: 9px; }
table.datos td.r { text-align: right; }
table.datos td.c { text-align: center; }
tr.zebra td { background: #f9fafb; }
td.saldo { font-weight: bold; }

tr.cliente-header td {
    background: #dbeafe; font-weight: bold; color: #1e3a8a;
    padding: 6px 7px; border-top: 8px solid #fff; border-left: 4px solid #1e3a8a; font-size: 10px;
}
tr.subtotal td {
    background: #eef2ff; font-weight: bold; color: #1e3a8a;
    border-top: 1px solid #9ca3af; border-bottom: 2px solid #1e3a8a; font-size: 9px; padding: 5px 7px;
}
tr.total-general td {
    background: #1e3a8a; color: #fff; font-weight: bold; font-size: 10.5px;
    padding: 8px 7px; border: 0;
}
.vencida { color: #b91c1c; font-weight: bold; }
.tag-venc { background: #fee2e2; color: #b91c1c; font-size: 6.5px; font-weight: bold; padding: 1px 3px; border-radius: 2px; margin-left: 3px; }
tr.spacer td { height: 2px; border: 0; background: transparent; padding: 0; }

.pie { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 7px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 3px; }
.pie-izq { float: left; }
.pie-der { float: right; }
.pagenum:before { content: counter(page); }
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

    // Logo: el de la empresa activa si tiene; si no, el del sistema
    // (Superadmin → Configuración). Se incrusta en base64 para dompdf.
    $logoSrc = null;
    $rutas = [];
    if (!empty($empresa?->logo_path)) {
        $rutas[] = storage_path('app/public/' . ltrim($empresa->logo_path, '/'));
        $rutas[] = public_path('storage/' . ltrim($empresa->logo_path, '/'));
    }
    $rutas[] = public_path(\App\Models\SystemSetting::get('app.logo_path', 'logo.jpg') ?: 'logo.jpg');
    foreach ($rutas as $r) {
        if (is_file($r)) {
            $mime = str_ends_with(strtolower($r), '.png') ? 'image/png' : 'image/jpeg';
            $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($r));
            break;
        }
    }
@endphp

<table class="encabezado">
    <tr>
        @if($logoSrc)
        <td width="74"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
        @endif
        <td>
            <div class="emp-nombre">{{ $nombre }}</div>
            @if($rfc)<div class="emp-rfc">RFC: {{ $rfc }}</div>@endif
        </td>
        <td class="titulo-box" width="230">
            <div class="titulo">Cobranza General</div>
            <div class="generado">Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>
<div class="banda"></div>

<table class="filtros">
    <tr>
        <td class="label">Desde cliente:</td>
        <td>{{ $desde ?: 'Todos' }}</td>
        <td class="label">Hasta cliente:</td>
        <td>{{ $hasta ?: 'Todos' }}</td>
        <td class="label">Moneda:</td>
        <td>{{ $moneda }}</td>
        <td class="label">Tipo cambio:</td>
        <td>{{ $tipoCambio }}</td>
    </tr>
    <tr>
        <td class="label">Fecha vencimiento:</td>
        <td colspan="3">
            @if($fvd || $fvh)
                {{ $fvd ? 'desde '.\Carbon\Carbon::parse($fvd)->format('d/m/Y') : '' }} {{ $fvh ? 'hasta '.\Carbon\Carbon::parse($fvh)->format('d/m/Y') : '' }}
            @else
                —
            @endif
        </td>
        <td class="label">Estado:</td>
        <td colspan="3">{{ ucfirst($status) }}</td>
    </tr>
</table>

<table class="datos">
    <thead>
        <tr>
            <th>Concepto</th>
            <th>Documento</th>
            <th class="c">Núm.</th>
            <th class="c">Fecha aplic.</th>
            <th class="c">Fecha venc.</th>
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
                    $vencida = $saldo > 0 && \Carbon\Carbon::parse($nota->fecha_vencimiento)->lt(today());
                @endphp
                <tr class="fila {{ $i % 2 ? 'zebra' : '' }}">
                    <td>Nota de venta</td>
                    <td>{{ $nota->folio }}</td>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ \Carbon\Carbon::parse($nota->fecha_aplicacion)->format('d/m/Y') }}</td>
                    <td class="c {{ $vencida ? 'vencida' : '' }}">
                        {{ \Carbon\Carbon::parse($nota->fecha_vencimiento)->format('d/m/Y') }}@if($vencida) <span class="tag-venc">VENCIDA</span>@endif
                    </td>
                    <td class="r">{{ number_format($cargos, 2) }}</td>
                    <td class="r">{{ number_format($abonos, 2) }}</td>
                    <td class="r saldo">{{ number_format($saldo, 2) }}</td>
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

<div class="pie">
    <span class="pie-izq">Cobranza General</span>
    <span class="pie-der">Página <span class="pagenum"></span></span>
</div>
</body>
</html>
