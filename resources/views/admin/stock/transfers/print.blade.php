@php
    $logoPath   = public_path('logo.jpg');
    $logoExists = file_exists($logoPath);
    if ($logoExists) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoSrc  = 'data:image/jpeg;base64,'.$logoData;
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Traspaso {{ $transfer->folio }}</title>
<style>
* { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
body { font-size: 11px; color: #1a1a1a; padding: 28px 32px; }
.logo-img { height: 64px; }
.doc-tipo { font-size: 18px; font-weight: bold; color: #333; text-transform: uppercase; letter-spacing: 1px; }
.doc-folio { font-size: 13px; color: #d4a000; font-weight: bold; margin-top: 3px; }
.doc-fecha { font-size: 10px; color: #666; margin-top: 2px; }
.header-line { border-bottom: 3px solid #e6a800; margin: 8px 0 14px; }
.info-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.info-grid td { vertical-align: top; padding: 0 6px 0 0; }
.info-grid td:last-child { padding-right: 0; }
.card { background: #fafafa; border: 1px solid #e5e7eb; border-radius: 5px; padding: 9px 11px; }
.card-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #d4a000; margin-bottom: 5px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
.card .row { margin-bottom: 2px; font-size: 10px; line-height: 1.5; }
.card .lbl { color: #777; }
.card .val { font-weight: bold; }
.status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 10px; font-weight: bold; background: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; margin-top: 6px; }
.tbl { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
.tbl thead tr { background: #1a1a1a; }
.tbl thead th { padding: 7px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; color: #fff; text-align: left; }
.tbl thead th.r { text-align: right; }
.tbl tbody tr { border-bottom: 1px solid #e5e7eb; }
.tbl tbody tr:nth-child(even) { background: #f9fafb; }
.tbl tbody td { padding: 6px 7px; font-size: 10px; }
.tbl tbody td.r { text-align: right; }
.signatures { display: table; width: 100%; margin-top: 48px; }
.sig-cell { display: table-cell; width: 50%; padding: 0 20px; text-align: center; vertical-align: bottom; }
.sig-line { border-top: 1px solid #333; padding-top: 5px; margin-top: 48px; font-size: 9px; color: #555; }
.footer { border-top: 2px solid #e6a800; padding-top: 7px; margin-top: 18px; font-size: 9px; color: #999; display: flex; justify-content: space-between; }
.no-print { margin-bottom: 16px; }
@media print {
    .no-print { display: none; }
    body { padding: 10px; }
    @page { margin: 1cm; }
}
</style>
</head>
<body>

{{-- Botón imprimir solo en pantalla --}}
<div class="no-print">
    <button onclick="window.print()"
            style="padding:8px 20px;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">
        🖨 Imprimir
    </button>
    <a href="{{ route('admin.stock.transfers.show', $transfer) }}"
       style="margin-left:12px;font-size:13px;color:#4f46e5;">← Volver</a>
</div>

{{-- Encabezado --}}
<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:50%;vertical-align:top">
            @if($logoExists ?? false)
                <img src="{{ $logoSrc }}" class="logo-img" alt="Logo">
            @else
                <div style="font-size:20px;font-weight:bold;color:#d63384">{{ config('app.name') }}</div>
            @endif
        </td>
        <td style="width:50%;vertical-align:top;text-align:right">
            <div class="doc-tipo">Traspaso de almacén</div>
            <div class="doc-folio">{{ $transfer->folio }}</div>
            <div class="doc-fecha">{{ $transfer->fecha->format('d/m/Y') }}</div>
            <div class="status-badge">{{ $transfer->status_label }}</div>
        </td>
    </tr>
</table>
<div class="header-line"></div>

{{-- Datos de origen / destino --}}
<table class="info-grid" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:50%;padding-right:6px">
            <div class="card">
                <div class="card-title">📦 Almacén Origen</div>
                <div class="row"><span class="val" style="color:#4f46e5;font-size:12px;">{{ $transfer->fromWarehouse?->nombre ?? '—' }}</span></div>
                @if($transfer->creator)
                    <div class="row"><span class="lbl">Creado por: </span>{{ $transfer->creator->name }}</div>
                @endif
                @if($transfer->dispatch?->driver)
                    <div class="row"><span class="lbl">Chofer: </span><span class="val">{{ $transfer->dispatch->driver->nombre }}</span></div>
                @endif
            </div>
        </td>
        <td style="width:50%">
            <div class="card">
                <div class="card-title">🏪 Almacén Destino</div>
                <div class="row"><span class="val" style="color:#059669;font-size:12px;">{{ $transfer->toWarehouse?->nombre ?? '—' }}</span></div>
                @if($transfer->dispatch_id)
                    <div class="row"><span class="lbl">Despacho: </span><span class="val">#{{ $transfer->dispatch_id }}</span></div>
                @endif
                @if($transfer->completado_at)
                    <div class="row"><span class="lbl">Completado: </span>{{ $transfer->completado_at->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

@if($transfer->notas)
<div style="border:1px solid #e5e7eb;border-radius:4px;padding:8px 11px;background:#fafafa;margin-bottom:12px;font-size:10px;color:#555;">
    <strong>Notas:</strong> {{ $transfer->notas }}
</div>
@endif

{{-- Tabla de productos --}}
<table class="tbl">
    <thead>
        <tr>
            <th style="width:40px;">#</th>
            <th>Producto</th>
            <th style="width:80px;">Unidad</th>
            <th class="r" style="width:100px;">Cantidad</th>
        </tr>
    </thead>
    <tbody>
        @foreach($transfer->items as $i => $it)
        <tr>
            <td style="color:#9ca3af;">{{ $i + 1 }}</td>
            <td><strong>{{ $it->product?->nombre ?? '—' }}</strong></td>
            <td>{{ $it->product?->unidad ?? '' }}</td>
            <td class="r" style="font-weight:bold;font-family:monospace;">{{ number_format($it->qty, 3) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Firmas --}}
<div class="signatures">
    <div class="sig-cell">
        <div class="sig-line">
            Entregó<br>
            <strong>{{ $transfer->fromWarehouse?->nombre }}</strong>
        </div>
    </div>
    <div class="sig-cell">
        <div class="sig-line">
            Recibió<br>
            <strong>{{ $transfer->dispatch?->driver?->nombre ?? $transfer->toWarehouse?->nombre }}</strong>
        </div>
    </div>
</div>

{{-- Footer --}}
<div class="footer">
    <span>{{ config('app.name') }} — Generado el {{ now()->format('d/m/Y H:i') }}</span>
    <span>{{ $transfer->folio }}</span>
</div>

</body>
</html>