@php
    $emisor  = $invoice->company ?? $empresaActiva ?? null;
    $fiscal  = $emisor?->fiscalData ?? null;
    $cliente = $invoice->client ?? null;

    // Logo
    $logoPath   = public_path('logo.jpg');
    $logoExists = file_exists($logoPath);
    if ($logoExists) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoSrc  = 'data:image/jpeg;base64,' . $logoData;
    }

    // QR verificación SAT
    $qrUrl = null;
    if ($invoice->uuid) {
        $rfcEmisor   = $emisor?->rfc ?? '';
        $rfcReceptor = $cliente?->rfc ?? 'XAXX010101000';
        $total       = number_format((float)$invoice->total, 6, '.', '');
        $qrUrl = "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx"
               . "?id={$invoice->uuid}"
               . "&re={$rfcEmisor}"
               . "&rr={$rfcReceptor}"
               . "&tt={$total}"
               . "&fe=" . substr($invoice->xml_timbrado ?? '', -8, 8);
    }

    // Dirección fiscal del emisor
    $dirEmisor = collect([
        trim(($fiscal?->calle ?? '') . ' ' . ($fiscal?->numero_exterior ?? '')),
        $fiscal?->colonia ?? '',
        'C.P. ' . ($fiscal?->codigo_postal ?? ''),
        $fiscal?->municipio ?? '',
        $fiscal?->estado ?? '',
    ])->filter()->implode(', ');

    // Dirección fiscal del receptor
    $dirReceptor = collect([
        trim(($cliente?->fiscal_calle ?? '') . ' ' . ($cliente?->fiscal_numero ?? '')),
        $cliente?->fiscal_colonia ?? '',
        'C.P. ' . ($cliente?->fiscal_cp ?? ''),
        $cliente?->fiscal_ciudad ?? '',
        $cliente?->fiscal_estado ?? '',
    ])->filter()->implode(', ');

    $tipoLabel = match($invoice->tipo_comprobante ?? 'I') {
        'I' => 'Factura',
        'E' => 'Nota de crédito',
        'P' => 'Complemento de pago',
        'N' => 'Nómina',
        default => 'Comprobante',
    };

    $statusBadge = match($invoice->estatus ?? 'BORRADOR') {
        'BORRADOR'  => ['bg' => '#f0f0f0', 'color' => '#555',    'border' => '#ccc'],
        'TIMBRADA'  => ['bg' => '#dcfce7', 'color' => '#166534', 'border' => '#86efac'],
        'CANCELADA' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fca5a5'],
        default     => ['bg' => '#f0f0f0', 'color' => '#555',    'border' => '#ccc'],
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $tipoLabel }} {{ $invoice->serie }}{{ $invoice->folio }}</title>
<style>
* { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
body { font-size: 11px; color: #1a1a1a; background: #fff; padding: 28px 32px; }

/* ── Header ── */
.header-table  { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.logo-img      { height: 70px; width: auto; }
.logo-placeholder { height: 70px; line-height: 70px; font-size: 20px; font-weight: bold; color: #d63384; }
.doc-tipo      { font-size: 20px; font-weight: bold; color: #222; text-transform: uppercase; letter-spacing: 1px; }
.doc-folio     { font-size: 14px; color: #c89000; font-weight: bold; margin-top: 4px; }
.doc-fecha     { font-size: 10px; color: #666; margin-top: 3px; }
.status-badge  {
    display: inline-block; margin-top: 5px; padding: 3px 10px;
    border-radius: 3px; font-size: 9px; font-weight: bold;
    text-transform: uppercase; letter-spacing: 0.5px;
    background: {{ $statusBadge['bg'] }};
    color: {{ $statusBadge['color'] }};
    border: 1px solid {{ $statusBadge['border'] }};
}
.divider { border: none; border-top: 3px solid #e6a800; margin: 10px 0 14px; }

/* ── Emisor / Receptor ── */
.parties-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
.party-box {
    border: 1px solid #e0e0e0; border-radius: 5px;
    padding: 10px 13px; background: #fafafa;
}
.party-title {
    font-size: 8.5px; font-weight: bold; text-transform: uppercase;
    letter-spacing: 1px; color: #c89000;
    border-bottom: 1px solid #e0e0e0; padding-bottom: 4px; margin-bottom: 7px;
}
.party-row { font-size: 10px; margin-bottom: 3px; line-height: 1.5; color: #333; }
.party-row .lbl { color: #888; }
.party-row .val { font-weight: bold; color: #111; }

/* ── Datos CFDI ── */
.cfdi-box {
    border: 1px solid #e0e0e0; border-radius: 5px;
    padding: 10px 13px; background: #fffdf0; margin-bottom: 14px;
}
.cfdi-title {
    font-size: 8.5px; font-weight: bold; text-transform: uppercase;
    letter-spacing: 1px; color: #c89000;
    border-bottom: 1px solid #e0e0e0; padding-bottom: 4px; margin-bottom: 7px;
}
.cfdi-grid { width: 100%; border-collapse: collapse; }
.cfdi-grid td { font-size: 9.5px; padding: 2px 6px 2px 0; vertical-align: top; color: #333; }
.cfdi-grid .lbl { color: #888; white-space: nowrap; }
.cfdi-grid .val { font-weight: bold; color: #111; word-break: break-all; }

/* ── UUID ── */
.uuid-row {
    background: #f0f0f0; border-radius: 3px;
    padding: 5px 8px; font-size: 9px;
    font-family: monospace; color: #444;
    margin-bottom: 10px; word-break: break-all;
}

/* ── QR ── */
.qr-img { width: 72px; height: 72px; }

/* ── Partidas ── */
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
.items-table thead tr { background: #222; }
.items-table thead th {
    color: #fff; padding: 7px 6px; font-size: 9px;
    text-transform: uppercase; letter-spacing: 0.5px;
    text-align: left; font-weight: bold;
}
.items-table thead th.r { text-align: right; }
.items-table tbody tr { border-bottom: 1px solid #e8e8e8; }
.items-table tbody tr:nth-child(even) { background: #f7f7f7; }
.items-table tbody td { padding: 6px 6px; font-size: 10px; vertical-align: middle; }
.items-table tbody td.r { text-align: right; }
.items-table tbody td.gray { color: #888; font-size: 9px; }

/* ── Totales ── */
.totales-table { width: 38%; margin-left: auto; border-collapse: collapse; margin-top: 12px; }
.totales-table td { padding: 4px 10px; font-size: 10px; }
.totales-table td.r { text-align: right; }
.totales-table .sub-line td { color: #666; border-top: 1px solid #e8e8e8; }
.totales-table .total-line td {
    font-size: 13px; font-weight: bold;
    border-top: 2px solid #222; padding-top: 7px; color: #111;
}

/* ── Footer ── */
.footer {
    border-top: 2px solid #e6a800; margin-top: 22px;
    padding-top: 8px; font-size: 9px; color: #aaa;
}
.sello-box {
    margin-top: 10px; padding: 6px 8px;
    background: #f9f9f9; border: 1px solid #e0e0e0;
    border-radius: 3px; font-size: 7.5px;
    color: #999; word-break: break-all; line-height: 1.4;
}
</style>
</head>
<body>

{{-- ══════════════ HEADER ══════════════ --}}
{{-- ══════════════ HEADER ══════════════ --}}
<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        {{-- Logo --}}
        <td style="width:20%;vertical-align:middle">
            @if($logoExists ?? false)
                <img src="{{ $logoSrc }}" class="logo-img" alt="Logo">
            @else
                <div class="logo-placeholder">{{ $emisor?->nombre_display ?? config('app.name') }}</div>
            @endif
        </td>

        {{-- Datos del emisor --}}
        <td style="width:45%;vertical-align:middle;padding-left:14px">
            <div style="font-size:13px;font-weight:bold;color:#111;margin-bottom:3px">
                {{ $emisor?->razon_social ?? config('app.name') }}
            </div>
            <div style="font-size:9.5px;color:#555;margin-bottom:2px">
                R.F.C.: <strong>{{ $emisor?->rfc ?? '' }}</strong>
            </div>
            @if($dirEmisor)
            <div style="font-size:9px;color:#666;margin-bottom:2px">{{ $dirEmisor }}</div>
            @endif
            @if($fiscal?->codigo_postal)
            <div style="font-size:9px;color:#666;margin-bottom:2px">
                C.P. {{ $fiscal->codigo_postal }}
                @if($fiscal->municipio), {{ $fiscal->municipio }}@endif
                @if($fiscal->estado), {{ $fiscal->estado }}@endif
            </div>
            @endif
            @if($emisor?->telefono)
            <div style="font-size:9px;color:#666;margin-bottom:2px">
                Tel: {{ $emisor->telefono }}
            </div>
            @endif
            @if($emisor?->email)
            <div style="font-size:9px;color:#666;margin-bottom:2px">
                {{ $emisor->email }}
            </div>
            @endif
            @if($fiscal?->regimen_fiscal)
            <div style="font-size:9px;color:#666;margin-top:3px">
                Régimen Fiscal: {{ $fiscal->regimen_fiscal }} — {{ \App\Models\CompanyFiscalData::REGIMENES_FISCALES[$fiscal->regimen_fiscal] ?? '' }}
            </div>
            @endif
        </td>

        {{-- Tipo documento + folio --}}
        <td style="width:35%;vertical-align:top;text-align:right">
            <div class="doc-tipo">{{ $tipoLabel }}</div>
            <div class="doc-folio">{{ $invoice->serie }}{{ $invoice->folio }}</div>
            <div class="doc-fecha">{{ optional($invoice->fecha)->format('d/m/Y H:i') }}</div>
            <div><span class="status-badge">{{ $invoice->estatus }}</span></div>
        </td>
    </tr>
</table>
<hr class="divider">

{{-- ══════════════ UUID (si está timbrada) ══════════════ --}}
@if($invoice->uuid)
<div class="uuid-row">
    <span style="color:#888">UUID: </span><strong>{{ $invoice->uuid }}</strong>
</div>
@endif

{{-- ══════════════ EMISOR / RECEPTOR ══════════════ --}}
<table class="parties-table" cellpadding="0" cellspacing="0">
    <tr>
        {{-- Emisor --}}
        <td style="width:48%;padding-right:8px;vertical-align:top">
            <div class="party-box">
                <div class="party-title">Emisor</div>
                <div class="party-row"><span class="val">{{ $emisor?->razon_social ?? '—' }}</span></div>
                <div class="party-row"><span class="lbl">RFC: </span><span class="val">{{ $emisor?->rfc ?? '—' }}</span></div>
                <div class="party-row"><span class="lbl">Régimen: </span>{{ $invoice->regimen_fiscal_emisor }} — {{ \App\Models\CompanyFiscalData::REGIMENES_FISCALES[$invoice->regimen_fiscal_emisor] ?? '' }}</div>
                @if($dirEmisor)
                <div class="party-row" style="margin-top:3px;font-size:9.5px;color:#555">{{ $dirEmisor }}</div>
                @endif
                @if($fiscal?->codigo_postal)
                <div class="party-row"><span class="lbl">Lugar expedición: </span><span class="val">{{ $invoice->lugar_expedicion }}</span></div>
                @endif
            </div>
        </td>

        {{-- Receptor --}}
        <td style="width:52%;vertical-align:top">
            <div class="party-box">
                <div class="party-title">Receptor</div>
                <div class="party-row"><span class="val">{{ $cliente?->razon_social ?? $cliente?->nombre ?? '—' }}</span></div>
                <div class="party-row"><span class="lbl">RFC: </span><span class="val">{{ $cliente?->rfc ?? 'XAXX010101000' }}</span></div>
                <div class="party-row"><span class="lbl">Régimen: </span>{{ $invoice->regimen_fiscal_receptor }} — {{ \App\Models\CompanyFiscalData::REGIMENES_FISCALES[$invoice->regimen_fiscal_receptor] ?? '' }}</div>
                <div class="party-row"><span class="lbl">Uso CFDI: </span><span class="val">{{ $invoice->uso_cfdi }}</span></div>
                @if($dirReceptor)
                <div class="party-row" style="margin-top:3px;font-size:9.5px;color:#555">{{ $dirReceptor }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ══════════════ DATOS CFDI ══════════════ --}}
<div class="cfdi-box">
    <div class="cfdi-title">Datos del comprobante</div>
    <table class="cfdi-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td class="lbl" style="width:14%">Tipo:</td>
            <td class="val" style="width:19%">{{ $tipoLabel }} ({{ $invoice->tipo_comprobante }})</td>
            <td class="lbl" style="width:14%">Moneda:</td>
            <td class="val" style="width:19%">{{ $invoice->moneda }}</td>
            <td class="lbl" style="width:14%">Versión:</td>
            <td class="val">{{ $invoice->version_cfdi ?? '4.0' }}</td>
        </tr>
        <tr>
            <td class="lbl">Forma pago:</td>
            <td class="val">{{ $invoice->forma_pago }}</td>
            <td class="lbl">Método pago:</td>
            <td class="val">{{ $invoice->metodo_pago }}</td>
            <td class="lbl">Exportación:</td>
            <td class="val">{{ $invoice->exportacion ?? '01' }}</td>
        </tr>
        @if($invoice->uuid)
        <tr>
            <td class="lbl">No. Cert. SAT:</td>
            <td class="val" colspan="5">{{ $invoice->numero_certificado_sat ?? '—' }}</td>
        </tr>
        @endif
    </table>
</div>

{{-- ══════════════ PARTIDAS ══════════════ --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width:9%">ClaveSAT</th>
            <th>Descripción</th>
            <th style="width:7%">Unidad</th>
            <th class="r" style="width:8%">Cant.</th>
            <th class="r" style="width:10%">V. Unitario</th>
            <th class="r" style="width:8%">Desc.</th>
            <th class="r" style="width:7%">% IVA</th>
            <th class="r" style="width:9%">IVA</th>
            <th class="r" style="width:10%">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $it)
        <tr>
            <td class="gray">{{ $it->clave_prod_serv ?? '—' }}</td>
            <td>{{ $it->descripcion }}</td>
            <td class="gray">{{ $it->clave_unidad ?? '' }} {{ $it->unidad ?? '' }}</td>
            <td class="r">{{ number_format((float)$it->cantidad, 3) }}</td>
            <td class="r">{{ number_format((float)$it->valor_unitario, 4) }}</td>
            <td class="r">{{ number_format((float)$it->descuento, 2) }}</td>
            <td class="r">{{ number_format((float)$it->iva_pct, 0) }}%</td>
            <td class="r">{{ number_format((float)$it->iva_importe, 2) }}</td>
            <td class="r" style="font-weight:bold">{{ number_format((float)$it->importe, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ══════════════ TOTALES + QR ══════════════ --}}
<table style="width:100%;border-collapse:collapse;margin-top:12px">
    <tr>
        {{-- QR --}}
        <td style="vertical-align:bottom;padding-right:10px;width:100px">
            @if($qrUrl)
                @php
                    try {
                        $qrBase64 = \App\Helpers\QrHelper::base64Png($qrUrl, 80);
                    } catch (\Throwable $e) {
                        $qrBase64 = null;
                    }
                @endphp
                @if($qrBase64)
                    <img src="{{ $qrBase64 }}" class="qr-img" alt="QR SAT">
                @else
                    <div style="font-size:7.5px;color:#aaa;max-width:100px;word-break:break-all">{{ $qrUrl }}</div>
                @endif
                <div style="font-size:7.5px;color:#aaa;margin-top:3px;text-align:center">Verificación SAT</div>
            @endif
        </td>

        {{-- Totales --}}
        <td style="vertical-align:top">
            <table class="totales-table">
                <tr class="sub-line">
                    <td>Subtotal</td>
                    <td class="r">{{ number_format((float)$invoice->subtotal, 2) }}</td>
                </tr>
                @if((float)($invoice->descuento ?? 0) > 0)
                <tr class="sub-line">
                    <td>Descuento</td>
                    <td class="r">- {{ number_format((float)$invoice->descuento, 2) }}</td>
                </tr>
                @endif
                @if((float)$invoice->impuestos > 0)
                <tr class="sub-line">
                    <td>IVA</td>
                    <td class="r">{{ number_format((float)$invoice->impuestos, 2) }}</td>
                </tr>
                @endif
                <tr class="total-line">
                    <td>TOTAL</td>
                    <td class="r">{{ $invoice->moneda }} {{ number_format((float)$invoice->total, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
{{-- ══════════════ SELLOS ══════════════ --}}
@if($invoice->uuid)
<div class="sello-box">
    <div style="margin-bottom:4px"><strong>Cadena original SAT:</strong></div>
    <div>||{{ $invoice->version_cfdi ?? '4.0' }}|{{ $invoice->uuid }}|{{ optional($invoice->fecha)->format('Y-m-d\TH:i:s') }}|{{ $emisor?->rfc }}|{{ number_format((float)$invoice->total, 6, '.', '') }}||</div>
    @if($invoice->sello_cfdi ?? null)
    <div style="margin-top:5px"><strong>Sello digital emisor:</strong></div>
    <div>{{ substr($invoice->sello_cfdi, 0, 100) }}...</div>
    @endif
    @if($invoice->sello_sat ?? null)
    <div style="margin-top:5px"><strong>Sello SAT:</strong></div>
    <div>{{ substr($invoice->sello_sat, 0, 100) }}...</div>
    @endif
</div>
@endif

{{-- ══════════════ FOOTER ══════════════ --}}
<div class="footer">
    <table style="width:100%;border-collapse:collapse" cellpadding="0" cellspacing="0">
        <tr>
            <td>{{ $emisor?->razon_social ?? config('app.name') }} — RFC: {{ $emisor?->rfc ?? '' }}</td>
            <td style="text-align:right">Generado el {{ now()->format('d/m/Y H:i') }} — {{ $invoice->serie }}{{ $invoice->folio }}</td>
        </tr>
    </table>
</div>

</body>
</html>