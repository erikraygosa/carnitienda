<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 14mm 12mm 16mm 12mm; }
* { box-sizing: border-box; }
body { margin: 0; padding: 0; font-family: Helvetica; font-size: 10.5px; color: #000; }

/* ── Título ── */
table.cabecera { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
table.cabecera td { padding: 0; vertical-align: middle; }
.logo { width: 58px; height: auto; }
.empresa  { font-size: 18px; text-align: center; }
.reporte  { font-size: 20px; font-weight: bold; text-align: center; margin: 12px 0 4px; }

/* ── Filtros ── */
table.filtros { width: 100%; border-collapse: collapse; border-top: 1px solid #000; border-bottom: 1px solid #000; margin-bottom: 4px; }
table.filtros td { padding: 2px 4px; font-size: 10.5px; vertical-align: top; }
table.filtros .lbl { font-weight: bold; white-space: nowrap; }

/* ── Detalle ── */
table.datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.datos thead th { font-size: 10px; font-weight: bold; padding: 3px 3px 2px; text-align: left; border-bottom: 1px solid #000; }
table.datos th.r, table.datos td.r { text-align: right; }
table.datos th.c, table.datos td.c { text-align: center; }
table.datos td { padding: 2px 3px; font-size: 10.5px; vertical-align: top; }
tr.cliente td { padding-top: 9px; padding-bottom: 4px; font-size: 11.5px; }
tr.cliente .clave { padding-left: 14px; }
tr.sub td { border-top: 1px solid #000; padding-top: 3px; font-weight: normal; }
tr.sub td.vacio { border-top: 0; }
tr.total td { padding-top: 10px; font-weight: bold; }
tr.total td.num { border-top: 1px solid #000; padding-top: 4px; }

.pie { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 9.5px; border-top: 1px solid #000; padding-top: 3px; }
.pie table { width: 100%; border-collapse: collapse; }
.pie td { font-size: 9.5px; padding: 0; }
.pagenum:before { content: counter(page); }
</style>
</head>
<body>

@php
    $ef      = $empresa?->fiscalData ?? null;
    $nombre  = mb_strtoupper($ef?->razon_social ?? $empresa?->nombre_comercial ?? config('app.name'));

    $desde  = trim($filtros['cliente_desde'] ?? '');
    $hasta  = trim($filtros['cliente_hasta'] ?? '');
    $fvd    = trim($filtros['fecha_venc_desde'] ?? '');
    $fvh    = trim($filtros['fecha_venc_hasta'] ?? '');
    $status = $filtros['status'] ?? 'todos';
    $f      = fn ($d) => \Carbon\Carbon::parse($d)->locale('es')->format('d/m/Y');
    $meses  = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $mes    = function ($d) use ($meses) { $c = \Carbon\Carbon::parse($d); return $c->format('d') . '/' . $meses[$c->month - 1] . '/' . $c->format('Y'); };
    $n2     = fn ($v) => number_format((float) $v, 2);

    // Logo: el de la empresa activa si tiene; si no, el del sistema.
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

    $usuario = auth()->user()?->name ?? 'SISTEMA';
@endphp

<table class="cabecera">
    <tr>
        <td width="60">@if($logoSrc)<img class="logo" src="{{ $logoSrc }}" alt="">@endif</td>
        <td class="empresa">{{ $nombre }}</td>
        <td width="60"></td>
    </tr>
</table>

<div class="reporte">Cobranza general</div>

<table class="filtros">
    <tr>
        <td class="lbl" width="14%">Desde cliente:</td>
        <td width="24%">{{ $desde ?: 'Todos' }}</td>
        <td class="lbl" width="14%">Hasta cliente:</td>
        <td width="22%">{{ $hasta ?: 'Todos' }}</td>
        <td class="lbl" width="26%">Moneda: Pesos</td>
    </tr>
    <tr>
        <td class="lbl">Fecha de vencimiento:</td>
        <td colspan="4">
            @if($fvd || $fvh)
                {{ $fvd ? 'desde ' . $f($fvd) : '' }} {{ $fvh ? 'hasta ' . $f($fvh) : '' }}
            @else
                Todas
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl" colspan="5">{{ $status === 'todos' ? 'Todos los conceptos' : 'Estado: ' . ucfirst($status) }}</td>
    </tr>
</table>

<table class="datos">
    <thead>
        <tr>
            <th width="13%" style="padding-left:14px">Concepto</th>
            <th width="19%">Documento</th>
            <th width="5%" class="c">Núm.</th>
            <th width="13%" class="c">Fecha aplic.</th>
            <th width="13%" class="c">Fecha venc.</th>
            <th width="13%" class="r">Cargos</th>
            <th width="11%" class="r">Abonos</th>
            <th width="13%" class="r">Saldos</th>
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
            <tr class="cliente">
                <td colspan="8"><span class="clave">{{ $clientId }}</span> &nbsp; {{ mb_strtoupper($primer->client_nombre) }}</td>
            </tr>
            @foreach($notas as $nota)
                @php
                    $saldo  = $nota->saldo_pendiente ?? $nota->total;
                    $cargos = (float) $nota->total;
                    $abonos = $cargos - $saldo;
                @endphp
                <tr>
                    <td style="padding-left:14px">Nota de venta</td>
                    <td>{{ $nota->folio }}</td>
                    <td class="c">1</td>
                    <td class="c">{{ $mes($nota->fecha_aplicacion) }}</td>
                    <td class="c">{{ $mes($nota->fecha_vencimiento) }}</td>
                    <td class="r">{{ $n2($cargos) }}</td>
                    <td class="r">{{ $n2($abonos) }}</td>
                    <td class="r">{{ $n2($saldo) }}</td>
                </tr>
            @endforeach
            <tr class="sub">
                <td colspan="5" class="vacio"></td>
                <td class="r">{{ $n2($subtotalCargos) }}</td>
                <td class="r">{{ $n2($subtotalAbonos) }}</td>
                <td class="r">{{ $n2($subtotalSaldo) }}</td>
            </tr>
        @endforeach

        <tr class="total">
            <td colspan="5" class="r">Totales :</td>
            <td class="r num">{{ $n2($totales['cargos']) }}</td>
            <td class="r num">{{ $n2($totales['abonos']) }}</td>
            <td class="r num">{{ $n2($totales['saldo']) }}</td>
        </tr>
    </tbody>
</table>

<div class="pie">
    <table>
        <tr>
            <td width="40%"><b>Usuario:</b> {{ mb_strtoupper($usuario) }}</td>
            <td width="40%"><b>Fecha y hora:</b> &nbsp; {{ now()->format('d/m/Y H:i') }}</td>
            <td width="20%" style="text-align:right"><b>Pág.</b> &nbsp; <span class="pagenum"></span></td>
        </tr>
    </table>
</div>
</body>
</html>
