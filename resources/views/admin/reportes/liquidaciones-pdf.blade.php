<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 16mm 13mm 18mm 13mm; }
* { box-sizing: border-box; }
body { margin: 0; padding: 0; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; color: #1f2937; }

table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
table.encabezado td { vertical-align: middle; padding: 0; }
.logo { width: 58px; height: auto; }
.emp-nombre { font-size: 15px; font-weight: bold; color: #111827; }
.emp-rfc    { font-size: 8.5px; color: #6b7280; margin-top: 2px; }
.titulo-box { text-align: right; }
.titulo     { font-size: 14px; font-weight: bold; color: #1e3a8a; }
.generado   { font-size: 7.5px; color: #6b7280; margin-top: 3px; }
.banda { height: 3px; background: #1e3a8a; margin-bottom: 9px; }

table.filtros { width: 100%; border-collapse: collapse; background: #f3f4f6; border: 1px solid #e5e7eb; margin-bottom: 10px; }
table.filtros td { padding: 3px 7px; font-size: 8px; }
table.filtros .label { font-weight: bold; color: #374151; white-space: nowrap; }

.ruta-titulo { background: #4f46e5; color: #fff; font-weight: bold; font-size: 10px; padding: 5px 8px; margin-top: 12px; text-transform: uppercase; }
.cxc-titulo  { background: #7c3aed; color: #fff; font-weight: bold; font-size: 9px; padding: 4px 8px; margin-top: 8px; text-transform: uppercase; }

table.datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.datos thead th { background: #1f2937; color: #fff; font-size: 7.5px; text-transform: uppercase; padding: 5px 6px; text-align: left; }
table.datos thead th.r { text-align: right; }
table.datos td { padding: 3.5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; font-size: 8.5px; }
table.datos td.r { text-align: right; }
table.datos td.c { text-align: center; }
tr.zebra td { background: #f9fafb; }
tr.subtotal td { background: #eef2ff; font-weight: bold; color: #1e3a8a; border-top: 1px solid #9ca3af; border-bottom: 2px solid #1e3a8a; padding: 4.5px 6px; }
tr.vacio td { text-align: center; color: #9ca3af; padding: 8px; }
.badge { font-size: 7px; font-weight: bold; padding: 1px 4px; border-radius: 2px; }
.b-LIQUIDADO { background: #d1fae5; color: #047857; }
.b-PARCIAL   { background: #e0f2fe; color: #0369a1; }
.b-PENDIENTE { background: #fef3c7; color: #b45309; }
.b-otro      { background: #f3f4f6; color: #6b7280; }

table.total-general { width: 100%; border-collapse: collapse; margin-top: 12px; }
table.total-general td { background: #1e3a8a; color: #fff; font-weight: bold; font-size: 10.5px; padding: 8px 8px; }

.pie { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 7px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 3px; }
.pie-izq { float: left; }
.pie-der { float: right; }
.pagenum:before { content: counter(page); }
</style>
</head>
<body>
@php
    $ef     = $empresa?->fiscalData ?? null;
    $nombre = $ef?->razon_social ?? $empresa?->nombre_comercial ?? config('app.name');
    $rfc    = $ef?->rfc ?? '';
    $fmt    = fn ($n) => number_format((float) $n, 2);

    // Logo de la empresa activa o, si no tiene, el del sistema (base64 para dompdf).
    $logoSrc = null;
    $rutasLogo = [];
    if (!empty($empresa?->logo_path)) {
        $rutasLogo[] = storage_path('app/public/' . ltrim($empresa->logo_path, '/'));
        $rutasLogo[] = public_path('storage/' . ltrim($empresa->logo_path, '/'));
    }
    $rutasLogo[] = public_path(\App\Models\SystemSetting::get('app.logo_path', 'logo.jpg') ?: 'logo.jpg');
    foreach ($rutasLogo as $r) {
        if (is_file($r)) {
            $mime = str_ends_with(strtolower($r), '.png') ? 'image/png' : 'image/jpeg';
            $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($r));
            break;
        }
    }
    $estatusLabel = ['pendientes' => 'Pendientes', 'todas' => 'Todas', 'no_entregado' => 'No entregado'][$filtros['estatus']] ?? ucfirst($filtros['estatus']);
@endphp

<table class="encabezado">
    <tr>
        @if($logoSrc)
        <td width="68"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
        @endif
        <td>
            <div class="emp-nombre">{{ $nombre }}</div>
            @if($rfc)<div class="emp-rfc">RFC: {{ $rfc }}</div>@endif
        </td>
        <td class="titulo-box" width="240">
            <div class="titulo">Lista de liquidaciones</div>
            <div class="generado">Fecha: {{ $fechaDoc->format('d/m/Y') }} · Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>
<div class="banda"></div>

<table class="filtros">
    <tr>
        <td class="label">Ruta:</td><td>{{ $filtros['ruta'] }}</td>
        <td class="label">Ronda:</td><td>{{ $filtros['ronda'] }}</td>
        <td class="label">Estatus:</td><td>{{ $estatusLabel }}</td>
        <td class="label">Liquidación:</td><td>{{ $filtros['liq'] }}</td>
    </tr>
</table>

@forelse($rutas as $r)
    <div class="ruta-titulo">{{ $r['nombre'] }}</div>
    @if(count($r['filas']))
    <table class="datos">
        <thead>
            <tr>
                <th width="15%">Nota</th><th width="31%">Cliente</th><th width="11%">Fecha</th><th width="13%" class="r">Monto</th><th width="15%">Estatus pedido</th><th width="15%">Liquidación</th>
            </tr>
        </thead>
        <tbody>
            @foreach($r['filas'] as $i => $f)
            <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                <td>{{ $f['folio'] }}</td>
                <td>{{ $f['cliente'] }}</td>
                <td>{{ $f['fecha'] }}</td>
                <td class="r">{{ $fmt($f['monto']) }}</td>
                <td>{{ $f['pedido'] }}</td>
                <td><span class="badge b-{{ in_array($f['liq'], ['LIQUIDADO','PARCIAL','PENDIENTE']) ? $f['liq'] : 'otro' }}">{{ $f['liq'] }}</span></td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="3" class="r">Subtotal {{ $r['nombre'] }} (liquidado/abonado):</td>
                <td class="r">{{ $fmt($r['subtotal']) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
    @endif

    @if(count($r['cxc']))
    <div class="cxc-titulo">CxC asignadas al chofer — {{ $r['nombre'] }}</div>
    <table class="datos">
        <thead>
            <tr>
                <th width="15%">Folio</th><th width="31%">Cliente</th><th width="11%">Fecha</th><th width="13%" class="r">Saldo pendiente</th><th width="13%" class="r">Cobrado</th><th width="17%">Estatus</th>
            </tr>
        </thead>
        <tbody>
            @foreach($r['cxc'] as $i => $c)
            <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                <td>{{ $c['folio'] }}</td>
                <td>{{ $c['cliente'] }}</td>
                <td>{{ $c['fecha'] }}</td>
                <td class="r">{{ $fmt($c['saldo']) }}</td>
                <td class="r">{{ $fmt($c['cobrado']) }}</td>
                <td>{{ $c['status'] }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="3" class="r">Totales:</td>
                <td class="r">{{ $fmt($r['cxc_saldo']) }}</td>
                <td class="r">{{ $fmt($r['cxc_cobrado']) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endif
@empty
    <table class="datos"><tbody><tr class="vacio"><td>No hay liquidaciones con estos filtros.</td></tr></tbody></table>
@endforelse

<table class="total-general">
    <tr>
        <td style="text-align:right;">TOTAL GENERAL:</td>
        <td style="text-align:right;" width="120">{{ $fmt($totalGeneral) }}</td>
    </tr>
</table>

<div class="pie">
    <span class="pie-izq">Lista de liquidaciones — {{ $fechaDoc->format('d/m/Y') }}</span>
    <span class="pie-der">Página <span class="pagenum"></span></span>
</div>
</body>
</html>
