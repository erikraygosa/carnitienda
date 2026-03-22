@php
    $client = $order->client ?? null;
    $wh     = $order->warehouse ?? null;
    $driver = $order->driver ?? null;
    $route  = $order->route ?? null;

    // Datos empresa
    $emp = $empresa ?? null;
    $ef  = $emp?->fiscalData ?? null;

    $dirEmpresa = collect([
        trim(($ef?->calle ?? '') . ' ' . ($ef?->numero_exterior ?? '')),
        $ef?->colonia ?? '',
        'C.P. ' . ($ef?->codigo_postal ?? ''),
        $ef?->municipio ?? '',
        $ef?->estado ?? '',
    ])->filter()->implode(', ');

    // Logo
    $logoPath   = public_path('logo.jpg');
    $logoExists = file_exists($logoPath);
    if ($logoExists) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoSrc  = 'data:image/jpeg;base64,' . $logoData;
    }

    $statusBadge = match($order->status ?? '') {
        'BORRADOR'     => ['bg' => '#f0f0f0', 'color' => '#555',    'border' => '#ccc'],
        'APROBADO'     => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'border' => '#93c5fd'],
        'PREPARANDO'   => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'border' => '#93c5fd'],
        'PROCESADO'    => ['bg' => '#fef9c3', 'color' => '#854d0e', 'border' => '#fde047'],
        'EN_RUTA'      => ['bg' => '#ede9fe', 'color' => '#5b21b6', 'border' => '#c4b5fd'],
        'ENTREGADO'    => ['bg' => '#dcfce7', 'color' => '#166534', 'border' => '#86efac'],
        'NO_ENTREGADO' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fca5a5'],
        'CANCELADO'    => ['bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fca5a5'],
        default        => ['bg' => '#f0f0f0', 'color' => '#555',    'border' => '#ccc'],
    };

    $dir = collect([
        trim(($order->entrega_calle ?? '') . ' ' . ($order->entrega_numero ?? '')),
        $order->entrega_colonia ?? '',
        trim(($order->entrega_ciudad ?? '') . ' ' . ($order->entrega_estado ?? '') . ' ' . ($order->entrega_cp ?? '')),
    ])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Remisión {{ $order->folio }}</title>
<style>
* { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-size: 11px;
    color: #1a1a1a;
    background: #fff;
    margin: 0;
    padding: 28px 32px;
}

/* ── Header ── */
.header-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.logo-img { height: 70px; width: auto; }
.logo-placeholder { height: 70px; line-height: 70px; font-size: 20px; font-weight: bold; color: #d63384; }
.doc-tipo  { font-size: 20px; font-weight: bold; color: #222; text-transform: uppercase; letter-spacing: 1px; }
.doc-folio { font-size: 14px; color: #c89000; font-weight: bold; margin-top: 4px; }
.doc-fecha { font-size: 10px; color: #666; margin-top: 3px; }
.status-badge {
    display: inline-block;
    margin-top: 5px;
    padding: 3px 10px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: {{ $statusBadge['bg'] }};
    color: {{ $statusBadge['color'] }};
    border: 1px solid {{ $statusBadge['border'] }};
}
.divider { border: none; border-top: 3px solid #e6a800; margin: 10px 0 18px; }

/* ── Info cards ── */
.cards-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
.cards-table td { vertical-align: top; padding: 0; }
.card {
    background: #fafafa;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    padding: 10px 13px;
    margin-right: 8px;
}
.card:last-child { margin-right: 0; }
.card-title {
    font-size: 8.5px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #c89000;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 4px;
    margin-bottom: 7px;
}
.card-row { font-size: 10px; margin-bottom: 3px; line-height: 1.5; color: #333; }
.card-row .lbl { color: #888; }
.card-row .val { font-weight: bold; color: #111; }

/* ── Tabla partidas ── */
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
.items-table thead tr { background: #222; }
.items-table thead th {
    color: #fff;
    padding: 8px 7px;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    font-weight: bold;
}
.items-table thead th.r { text-align: right; }
.items-table tbody tr { border-bottom: 1px solid #e8e8e8; }
.items-table tbody tr:nth-child(even) { background: #f7f7f7; }
.items-table tbody td { padding: 7px 7px; font-size: 10px; vertical-align: middle; }
.items-table tbody td.r { text-align: right; }

/* ── Totales ── */
.totales-table { width: 38%; margin-left: auto; border-collapse: collapse; margin-top: 12px; }
.totales-table td { padding: 4px 10px; font-size: 10px; }
.totales-table td.r { text-align: right; }
.totales-table .sub-line td { color: #666; border-top: 1px solid #e8e8e8; }
.totales-table .total-line td {
    font-size: 13px;
    font-weight: bold;
    border-top: 2px solid #222;
    padding-top: 7px;
    color: #111;
}

/* ── Firma ── */
.firma-area { margin-top: 42px; }
.firma-line { border-top: 1px solid #555; width: 180px; text-align: center; padding-top: 4px; font-size: 9px; color: #666; }

/* ── Footer ── */
.footer {
    border-top: 2px solid #e6a800;
    margin-top: 22px;
    padding-top: 8px;
    font-size: 9px;
    color: #aaa;
}
.footer-inner { width: 100%; }
</style>
</head>
<body>

{{-- ══════════════ HEADER ══════════════ --}}
<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        {{-- Logo --}}
        <td style="width:20%;vertical-align:middle">
            @if($logoExists ?? false)
                <img src="{{ $logoSrc }}" class="logo-img" alt="Logo">
            @else
                <div class="logo-placeholder">{{ $emp?->nombre_display ?? config('app.name') }}</div>
            @endif
        </td>

        {{-- Datos empresa --}}
        <td style="width:45%;vertical-align:middle;padding-left:16px;border-left:1px solid #e0e0e0">
            <div style="font-size:13px;font-weight:bold;color:#111;margin-bottom:4px">
                {{ $emp?->razon_social ?? config('app.name') }}
            </div>
            <div style="font-size:9.5px;color:#555;margin-bottom:2px">
                R.F.C.: <strong>{{ $emp?->rfc ?? '' }}</strong>
            </div>
            @if($dirEmpresa)
            <div style="font-size:9px;color:#666;margin-bottom:2px">{{ $dirEmpresa }}</div>
            @endif
            @if($emp?->telefono)
            <div style="font-size:9px;color:#666;margin-bottom:2px">
                Tel: {{ $emp->telefono }}
            </div>
            @endif
            @if($emp?->email)
            <div style="font-size:9px;color:#666;margin-bottom:2px">
                {{ $emp->email }}
            </div>
            @endif
            @if($ef?->regimen_fiscal)
            <div style="font-size:9px;color:#666;margin-top:3px">
                Régimen Fiscal: {{ $ef->regimen_fiscal }} — {{ \App\Models\CompanyFiscalData::REGIMENES_FISCALES[$ef->regimen_fiscal] ?? '' }}
            </div>
            @endif
        </td>

        {{-- Tipo + folio --}}
        <td style="width:35%;vertical-align:top;text-align:right">
            <div class="doc-tipo">Remisión de Pedido</div>
            <div class="doc-folio">{{ $order->folio }}</div>
            <div class="doc-fecha">{{ optional($order->fecha)->format('d/m/Y H:i') }}</div>
            <div><span class="status-badge">{{ $order->status }}</span></div>
        </td>
    </tr>
</table>
<hr class="divider">

{{-- ══════════════ CARDS ══════════════ --}}
<table class="cards-table" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:33%;padding-right:7px">
            <div class="card">
                <div class="card-title">Cliente</div>
                <div class="card-row"><span class="val">{{ $client->nombre ?? '—' }}</span></div>
                @if($client?->telefono)
                <div class="card-row"><span class="lbl">Tel: </span><span class="val">{{ $client->telefono }}</span></div>
                @endif
                @if($client?->email)
                <div class="card-row"><span class="lbl">Email: </span>{{ $client->email }}</div>
                @endif
                @if($client?->rfc)
                <div class="card-row"><span class="lbl">RFC: </span>{{ $client->rfc }}</div>
                @endif
            </div>
        </td>
        <td style="width:34%;padding-right:7px">
            <div class="card">
                <div class="card-title">Entrega</div>
                <div class="card-row">
                    <span class="lbl">Tipo: </span>
                    <span class="val">{{ $order->delivery_type === 'ENVIO' ? 'Envío a domicilio' : 'Recoger en almacén' }}</span>
                </div>
                @if($order->entrega_nombre)
                <div class="card-row"><span class="lbl">Recibe: </span><span class="val">{{ $order->entrega_nombre }}</span></div>
                @endif
                @if($order->entrega_telefono)
                <div class="card-row"><span class="lbl">Tel: </span>{{ $order->entrega_telefono }}</div>
                @endif
                @if($dir)
                <div class="card-row" style="margin-top:3px;color:#444;font-size:9.5px">{{ $dir }}</div>
                @endif
                @if($order->programado_para)
                <div class="card-row"><span class="lbl">Programado: </span><span class="val">{{ optional($order->programado_para)->format('d/m/Y') }}</span></div>
                @endif
            </div>
        </td>
        <td style="width:33%">
            <div class="card">
                <div class="card-title">Pago / Logística</div>
                <div class="card-row"><span class="lbl">Método: </span><span class="val">{{ $order->payment_method }}</span></div>
                @if($order->credit_days)
                <div class="card-row"><span class="lbl">Días crédito: </span><span class="val">{{ $order->credit_days }}d</span></div>
                @endif
                <div class="card-row"><span class="lbl">Moneda: </span>{{ $order->moneda ?? 'MXN' }}</div>
                @if($wh)
                <div class="card-row"><span class="lbl">Almacén: </span>{{ $wh->nombre }}</div>
                @endif
                @if($route)
                <div class="card-row"><span class="lbl">Ruta: </span>{{ $route->nombre }}</div>
                @endif
                @if($driver)
                <div class="card-row"><span class="lbl">Chofer: </span>{{ $driver->nombre }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ══════════════ PARTIDAS ══════════════ --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width:20%">Producto</th>
            <th>Descripción</th>
            <th class="r" style="width:9%">Cant.</th>
            <th class="r" style="width:11%">Precio</th>
            <th class="r" style="width:9%">Desc.</th>
            <th class="r" style="width:9%">Impuesto</th>
            <th class="r" style="width:11%">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $it)
        <tr>
            <td>{{ $it->product->nombre ?? '—' }}</td>
            <td>{{ $it->descripcion }}</td>
            <td class="r">{{ number_format((float)$it->cantidad, 3) }}</td>
            <td class="r">{{ number_format((float)$it->precio, 4) }}</td>
            <td class="r">{{ number_format((float)$it->descuento, 2) }}</td>
            <td class="r">{{ number_format((float)$it->impuesto, 2) }}</td>
            <td class="r" style="font-weight:bold">{{ number_format((float)$it->total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ══════════════ TOTALES ══════════════ --}}
<table class="totales-table">
    <tr class="sub-line">
        <td>Subtotal</td>
        <td class="r">{{ number_format((float)$order->subtotal, 2) }}</td>
    </tr>
    @if((float)$order->descuento > 0)
    <tr class="sub-line">
        <td>Descuento</td>
        <td class="r">- {{ number_format((float)$order->descuento, 2) }}</td>
    </tr>
    @endif
    @if((float)$order->impuestos > 0)
    <tr class="sub-line">
        <td>Impuestos</td>
        <td class="r">{{ number_format((float)$order->impuestos, 2) }}</td>
    </tr>
    @endif
    <tr class="total-line">
        <td>TOTAL</td>
        <td class="r">{{ $order->moneda ?? 'MXN' }} {{ number_format((float)$order->total, 2) }}</td>
    </tr>
</table>

{{-- ══════════════ FIRMA ══════════════ --}}
<div class="firma-area">
    <div class="firma-line">Recibido por / Firma</div>
</div>

{{-- ══════════════ FOOTER ══════════════ --}}
<div class="footer">
    <table class="footer-inner" cellpadding="0" cellspacing="0">
        <tr>
            <td>{{ $emp?->razon_social ?? config('app.name') }} — Documento generado el {{ now()->format('d/m/Y H:i') }}</td>
            <td style="text-align:right">{{ $order->folio }}</td>
        </tr>
    </table>
</div>

</body>
</html>