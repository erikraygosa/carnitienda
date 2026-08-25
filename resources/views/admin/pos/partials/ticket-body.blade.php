{{--
    Contenido del ticket POS, compartido entre la vista en pantalla
    (admin/pos/ticket.blade.php) y el PDF (admin/pos/ticket-pdf.blade.php)
    para que no se desincronicen. Espera $sale (con items/client/user/
    warehouse cargados) y $company (con fiscalData cargada).
--}}
@php
    $fd = $company?->fiscalData;
    // Mismo logo que ya usan el sidebar y los PDFs de carta (invoice, quote,
    // sales_order): un archivo fijo en public/logo.jpg, no un campo de BD.
    // Se embebe en base64 para que funcione igual en pantalla y en el PDF.
    // Configurable en Superadmin → Configuración ('tickets.mostrar_logo').
    $logoPath   = public_path('logo.jpg');
    $logoExists = file_exists($logoPath) && \App\Models\SystemSetting::get('tickets.mostrar_logo', true);
    if ($logoExists) {
        $logoMime = mime_content_type($logoPath) ?: 'image/jpeg';
        $logoSrc  = 'data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }
@endphp
<style>
{{-- Sin esto, el navegador/PDF imprime con el tamaño de hoja por default
     (Carta/A4) y el ticket se parte en varias páginas al imprimir.
     "auto" de alto + orientación explícita "portrait": algunos drivers de
     impresoras de ticket (sobre todo de cinta/matriz de punto) ignoran el
     ancho de 72mm y abren el diálogo en Horizontal por default, partiendo
     el ticket en varias hojas. Con altura fija y holgada, el ancho (72mm)
     queda como lado corto sin importar la orientación seleccionada. --}}
@media print {
    @page { size: 72mm 2000mm portrait; margin: 0; }
}
@page { size: 72mm 2000mm portrait; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
.ticket {
    font-family: 'Courier New', monospace;
    font-size: 16px;
    font-weight: bold;
    color: #000;
    background: #fff;
    width: 72mm;
    max-width: 72mm;
    margin: 0 auto;
    padding: 4mm 3mm;
}
.ticket .center { text-align: center; }
.ticket .right  { text-align: right; }
.ticket .bold   { font-weight: bold; }
.ticket .sm     { font-size: 16px; }
.ticket .dashed { border: 0; border-top: 1px dashed #000; margin: 3mm 0; }
.ticket table { width: 100%; border-collapse: collapse; }
.ticket td, .ticket th { vertical-align: top; }
.ticket .items thead tr th { font-size: 16px; border-bottom: 1px solid #000; padding-bottom: 2px; }
.ticket .items td { font-size: 16px; padding: 1px 0; }
.ticket .totals td { font-size: 16px; padding: 1px 0; }
.ticket .total-final td { font-size: 16px; font-weight: bold; border-top: 2px solid #000; padding-top: 3px; }
.ticket .logo { max-width: 55mm; max-height: 18mm; object-fit: contain; display: block; margin: 0 auto 4px; }
@media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    .ticket { margin: 0; padding: 2mm; }
}
</style>

<div class="ticket">

    {{-- EMPRESA --}}
    <div class="center">
        @if($logoExists)
            <img src="{{ $logoSrc }}" alt="Logo" class="logo">
        @endif
        @if($company?->nombre_comercial || $company?->razon_social)
            <div class="bold" style="font-size:15px;">
                ** {{ strtoupper($company?->nombre_comercial ?? $company?->razon_social) }} **
            </div>
        @endif
        @if($company?->razon_social && $company?->nombre_comercial)
            <div class="sm">{{ strtoupper($company->razon_social) }}</div>
        @endif
        @if($fd?->calle)
            <div class="sm">{{ trim($fd->calle.' '.($fd->numero_exterior ?? '')) }}</div>
        @endif
        @if($fd?->colonia)
            <div class="sm">{{ strtoupper($fd->colonia) }}</div>
        @endif
        @php
            $ciudadEstado = trim(($fd?->municipio ?? '').', '.($fd?->estado ?? '').
                            ($fd?->codigo_postal ? '    CP '.$fd->codigo_postal : ''));
        @endphp
        @if($ciudadEstado !== ',')
            <div class="sm">{{ strtoupper($ciudadEstado) }}</div>
        @endif
        @if($company?->rfc)
            <div class="sm">R.F.C. {{ $company->rfc }}</div>
        @endif
        @if($company?->telefono)
            <div class="sm">TEL. {{ $company->telefono }}</div>
        @endif
    </div>

    <hr class="dashed">

    {{-- FOLIO / FECHA / CAJERO --}}
    <table>
        <tr>
            <td>Ticket no.:</td>
            <td class="right bold">POS-{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <td>Fecha:</td>
            <td class="right">{{ $sale->fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>Hora:</td>
            <td class="right">{{ $sale->fecha->format('h:i:s a') }}</td>
        </tr>
        @if($sale->warehouse)
        <tr>
            <td>Almacén:</td>
            <td class="right">{{ strtoupper($sale->warehouse->nombre) }}</td>
        </tr>
        @endif
        @if($sale->user)
        <tr>
            <td>Cajero:</td>
            <td class="right">{{ strtoupper($sale->user->name) }}</td>
        </tr>
        @endif
    </table>

    <hr class="dashed">

    {{-- CLIENTE --}}
    @if($sale->client)
    <div>
        @if($sale->client->telefono)
            <span class="sm">Cliente:</span>
            <span class="sm">{{ $sale->client->telefono }}</span>
        @else
            <span class="sm">Cliente:</span>
        @endif
        <div class="bold">{{ strtoupper($sale->client->nombre) }}</div>
        @if($sale->client->direccion)
            <div class="sm">{{ $sale->client->direccion }}</div>
        @endif
    </div>
    <hr class="dashed">
    @endif

    {{-- PARTIDAS --}}
    <table class="items">
        <thead>
            <tr>
                <th style="text-align:left;">Cant&nbsp; Producto</th>
                <th style="text-align:right;">Precio unit</th>
                <th style="text-align:right;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $it)
            <tr>
                <td>{{ number_format((float)$it->cantidad, 2) }}
                    {{ strtoupper($it->product?->nombre ?? '#'.$it->product_id) }}</td>
                <td class="right">{{ number_format((float)$it->precio_unitario, 2) }}</td>
                <td class="right bold">{{ number_format((float)$it->importe, 2) }}</td>
            </tr>
            @if($it->descuento > 0)
            <tr>
                <td colspan="3" class="sm right" style="color:#cc0000;">
                    Desc: -{{ number_format((float)$it->descuento, 2) }}
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    <hr class="dashed">

    {{-- TOTALES --}}
    <table class="totals">
        <tr>
            <td>SubTotal</td>
            <td class="right">{{ number_format((float)$sale->subtotal, 2) }}</td>
        </tr>
        @if((float)$sale->descuento > 0)
        <tr>
            <td>Descuento</td>
            <td class="right">-{{ number_format((float)$sale->descuento, 2) }}</td>
        </tr>
        @endif
        @if((float)$sale->impuestos > 0)
        <tr>
            <td>Impuestos</td>
            <td class="right">{{ number_format((float)$sale->impuestos, 2) }}</td>
        </tr>
        @endif
        <tr class="total-final">
            <td>Total</td>
            <td class="right">{{ number_format((float)$sale->total, 2) }}</td>
        </tr>
        <tr>
            <td class="sm" style="padding-top:3px;">
                {{ $sale->metodo_pago }}{{ $sale->referencia ? ' · '.$sale->referencia : '' }}
            </td>
            <td></td>
        </tr>
        @if((float)($sale->efectivo ?? 0) > 0)
        <tr>
            <td class="sm">Efectivo recibido</td>
            <td class="right sm">{{ number_format((float)$sale->efectivo, 2) }}</td>
        </tr>
        <tr>
            <td class="sm">Cambio</td>
            <td class="right sm">{{ number_format((float)$sale->cambio, 2) }}</td>
        </tr>
        @endif
    </table>

    <hr class="dashed" style="margin-top:6mm;">
    <div class="center bold" style="font-size:12px;margin-bottom:4mm;">¡Gracias por su compra!</div>

</div>
