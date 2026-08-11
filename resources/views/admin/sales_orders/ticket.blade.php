@php
    $client  = $order->client ?? null;
    $emp     = $empresa ?? null;
    $ef      = $emp?->fiscalData ?? null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket {{ $order->folio }}</title>
<style>
{{-- Sin esto, el navegador imprime con el tamaño de hoja por default
     (Carta/A4) y el ticket se parte en varias páginas al imprimir. --}}
@page { size: 72mm auto; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    color: #000;
    background: #fff;
}
.ticket {
    width: 72mm;
    max-width: 72mm;
    margin: 0 auto;
    padding: 4mm 3mm;
}
.center  { text-align: center; }
.right   { text-align: right; }
.bold    { font-weight: bold; }
.sm      { font-size: 9px; }
.dashed  { border: 0; border-top: 1px dashed #000; margin: 3mm 0; }

table { width: 100%; border-collapse: collapse; }
td, th { vertical-align: top; }
.items thead tr th { font-size: 9px; border-bottom: 1px solid #000; padding-bottom: 2px; }
.items td { font-size: 10px; padding: 1px 0; }
.totals td { font-size: 10px; padding: 1px 0; }
.total-final td { font-size: 14px; font-weight: bold; border-top: 2px solid #000; padding-top: 3px; }

.observaciones {
    font-size: 9px;
    line-height: 1.4;
    margin: 3mm 0;
}
.pagare {
    font-size: 9px;
    line-height: 1.45;
    text-align: justify;
    margin: 2mm 0;
}
.firma-line {
    border-top: 1px solid #000;
    margin-top: 10mm;
    padding-top: 2px;
    font-size: 9px;
    text-align: center;
    width: 55mm;
    margin-left: auto;
    margin-right: auto;
}

@media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    .ticket { margin: 0; padding: 2mm; }
}
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:8px;background:#f3f4f6;border-bottom:1px solid #e5e7eb;">
    <button onclick="window.print()"
            style="padding:6px 20px;background:#1d4ed8;color:#fff;border:0;border-radius:4px;font-size:13px;cursor:pointer;">
        Imprimir
    </button>
    <a href="{{ route('admin.sales-orders.edit', $order) }}"
       style="margin-left:12px;font-size:12px;color:#6b7280;text-decoration:none;">← Volver</a>
</div>

<div class="ticket">

    {{-- EMPRESA --}}
    <div class="center">
        @if($emp?->nombre_comercial || $emp?->razon_social)
            <div class="bold" style="font-size:13px;">
                ** {{ strtoupper($emp?->nombre_comercial ?? $emp?->razon_social) }} **
            </div>
        @endif
        @if($emp?->razon_social && $emp?->nombre_comercial)
            <div class="sm">{{ strtoupper($emp->razon_social) }}</div>
        @endif
        @if($ef?->calle)
            <div class="sm">{{ trim($ef->calle.' '.($ef->numero_exterior ?? '')) }}</div>
        @endif
        @if($ef?->colonia)
            <div class="sm">{{ strtoupper($ef->colonia) }}</div>
        @endif
        @php
            $ciudadEstado = trim(($ef?->municipio ?? '').', '.($ef?->estado ?? '').
                            ($ef?->codigo_postal ? '    CP '.$ef->codigo_postal : ''));
        @endphp
        @if($ciudadEstado !== ',')
            <div class="sm">{{ strtoupper($ciudadEstado) }}</div>
        @endif
        @if($emp?->representante ?? $ef?->nombre)
            <div class="sm">{{ strtoupper($emp?->representante ?? '') }}</div>
        @endif
        @if($ef?->curp)
            <div class="sm">CURP:  {{ $ef->curp }}</div>
        @endif
        @if($emp?->rfc)
            <div class="sm">R.F.C. {{ $emp->rfc }}</div>
        @endif
        @if($emp?->telefono)
            <div class="sm">TEL. ({{ substr($emp->telefono, 0, 3) }}) {{ substr($emp->telefono, 3) }}</div>
        @endif
    </div>

    <hr class="dashed">

    {{-- FOLIO / FECHA --}}
    <table>
        <tr>
            <td>Nota no.:</td>
            <td class="right bold">{{ $order->folio }}</td>
        </tr>
        <tr>
            <td>Fecha:</td>
            <td class="right">{{ optional($order->fecha)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>Hora:</td>
            <td class="right">{{ optional($order->fecha)->format('h:i:s a') }}</td>
        </tr>
    </table>

    <hr class="dashed">

    {{-- CLIENTE --}}
    @if($client)
    <div>
        <span class="sm">Cliente:</span>
        <span class="sm">{{ $client->telefono ?? '' }}</span>
        <div class="bold">{{ strtoupper($client->nombre) }}</div>
        @php
            $dirParts = array_filter([
                trim(($order->entrega_calle ?? $client->calle ?? '').
                     ' '.($order->entrega_numero ?? $client->numero ?? '')),
                $order->entrega_colonia ?? $client->colonia ?? '',
                $client->telefono ? 'Teléfono: '.$client->telefono : '',
            ]);
        @endphp
        @if(count($dirParts))
            <div class="sm">{{ implode(', ', $dirParts) }}</div>
        @endif
    </div>
    @endif

    @if(($order->delivery_type ?? '') === 'ENVIO' || $order->entrega_nombre)
    <div class="sm bold" style="margin-top:3px;">Entregar</div>
    @endif

    <hr class="dashed">

    {{-- PARTIDAS --}}
    <div class="sm bold center" style="margin-bottom:3px;">Emisión de notas de Producto</div>
    <table class="items">
        <thead>
            <tr>
                <th style="text-align:left;">Cant&nbsp; Producto</th>
                <th style="text-align:right;">Precio unit</th>
                <th style="text-align:right;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $it)
            <tr>
                <td>{{ number_format((float)$it->cantidad, 2) }}
                    {{ strtoupper($it->product->nombre ?? '#'.$it->product_id) }}</td>
                <td class="right">{{ number_format((float)$it->precio, 2) }}</td>
                <td class="right bold">{{ number_format((float)$it->total, 2) }}</td>
            </tr>
            @if($it->unidad ?? $it->product?->unidad_medida)
            <tr>
                <td colspan="3" class="sm" style="padding-bottom:2px;">
                    {{ strtoupper($it->unidad ?? $it->product?->unidad_medida ?? '') }}
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    <hr class="dashed">

    {{-- TOTALES --}}
    <table class="totals">
        @if((float)$order->descuento > 0)
        <tr>
            <td>Descuento</td>
            <td class="right">-{{ number_format((float)$order->descuento, 2) }}</td>
        </tr>
        @endif
        @if((float)$order->impuestos > 0)
        <tr>
            <td>Impuestos</td>
            <td class="right">{{ number_format((float)$order->impuestos, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td>SubTotal</td>
            <td class="right">{{ number_format((float)$order->subtotal, 2) }}</td>
        </tr>
        <tr class="total-final">
            <td>Total</td>
            <td class="right">{{ number_format((float)$order->total, 2) }}</td>
        </tr>
    </table>

    {{-- ESPACIO + OBSERVACIONES --}}
    <div style="margin-top:5mm;"></div>
    <div class="observaciones">
        <div class="bold">OBSERVACIONES:</div>
        <div style="margin-top:2px;">
            NOTA: Por Políticas de la empresa se manejan kilogramos de origen.
        </div>
        <div style="margin-top:3px;font-weight:bold;">Política de devoluciones y/o reclamaciones:</div>
        <div>
            Es responsabilidad del Cliente revisar al momento de la entrega que el producto vaya conforme fue solicitado, etiquetado y pesado, esto sin manipularlo, únicamente mediante inspección visual, después de que el chofer se retire no serán aceptadas devoluciones y/o reclamaciones.
        </div>
    </div>

    <hr class="dashed">

    {{-- PAGARÉ --}}
    <div class="pagare">
        DEBO(EMOS) Y PAGARE(MOS) INCONDICIONALMENTE EL IMPORTE QUE AMPARA ESTE DOCUMENTO A LA ORDEN DE
        {{ strtoupper($emp?->razon_social ?? config('app.name')) }}
        EL VALOR DE LAS MERCANCIAS RECIBIDAS A MI (NUESTRA) ENTERA SATISFACCION, LA FALTA DE PAGO AL VENCER EL DOCUMENTO CAUSARA INTERESES MORATORIOS, A RAZON DE ______%
    </div>

    {{-- FIRMA --}}
    <div class="firma-line">FIRMA: ___________________________</div>

    <hr class="dashed" style="margin-top:6mm;">
    <div class="center bold" style="font-size:12px;margin-bottom:4mm;">Gracias por su compra!!!</div>

</div>
<script>
// Auto-imprimir si se abrió desde el panel de despacho
if (window.opener) {
    window.addEventListener('load', function () { window.print(); });
}
</script>
</body>
</html>
