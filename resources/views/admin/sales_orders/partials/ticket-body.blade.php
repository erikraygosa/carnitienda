{{--
    Contenido del ticket térmico del pedido, compartido entre la vista en
    pantalla (admin/sales_orders/ticket.blade.php) y el PDF
    (admin/sales_orders/ticket-pdf.blade.php) para que no se desincronicen.
    Espera $order (con client/items.product cargados) y $empresa (con
    fiscalData cargada).
--}}
@php
    $client  = $order->client ?? null;
    $emp     = $empresa ?? null;
    $ef      = $emp?->fiscalData ?? null;
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
     ancho de 68mm y abren el diálogo en Horizontal por default — ahí el
     ticket completo ya no cabe en una sola "página" de 68mm de alto y se
     reparte en 2-3 hojas. Fijar una altura holgada en vez de "auto" fuerza
     que el ancho (68mm) quede como lado corto sí o sí, sea cual sea la
     orientación que la impresora/driver haya dejado seleccionada.

     68mm (no 72mm) porque hay impresoras de 76mm de papel (ej. Epson
     TM-U220 y sus clones/compatibles) cuyo ancho imprimible real es más
     angosto que el de una térmica de 80mm — a 72mm el ticket se cortaba
     por el lado derecho en esas impresoras. Con 68mm cabe completo ahí y
     sigue viéndose bien en las de 80mm (solo queda un poco más de margen
     a la derecha). --}}
@media print {
    @page { size: 68mm auto; margin: 2cm 0 1cm 0; }
}
@page { size: 68mm auto; margin: 2cm 0 1cm 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
.ticket {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    font-weight: normal;
    color: #000;
    background: #fff;
    width: 68mm;
    max-width: 68mm;
    margin: 0 auto;
    padding: 4mm 3mm 4mm 6mm;
}
.ticket .center  { text-align: center; }
.ticket .right   { text-align: right; }
.ticket .bold    { font-weight: normal; }
.ticket .sm      { font-size: 12px; }
.ticket .dashed  { border: 0; border-top: 1px dashed #000; margin: 3mm 0; }

.ticket table { width: 100%; border-collapse: collapse; }
.ticket td, .ticket th { vertical-align: top; }
.ticket .items thead tr th { font-size: 12px; border-bottom: 1px solid #000; padding-bottom: 2px; }
.ticket .items td { font-size: 12px; padding: 1px 0; }
.ticket .items .item-precio-row td { padding-bottom: 4px; font-size: 15px; }
.ticket .totals td { font-size: 12px; padding: 1px 0; }
.ticket .total-final td { font-size: 12px; font-weight: normal; border-top: 2px solid #000; padding-top: 3px; }

.ticket .observaciones {
    font-size: 12px;
    line-height: 1.1;
    margin: 3mm 0;
}
.ticket .pagare {
    font-size: 12px;
    line-height: 1.1;
    text-align: justify;
    margin: 2mm 0;
}
.ticket .firma-line {
    border-top: 1px solid #000;
    margin-top: 10mm;
    padding-top: 2px;
    font-size: 12px;
    text-align: center;
    width: 55mm;
    margin-left: auto;
    margin-right: auto;
}
.ticket .logo { max-width: 55mm; max-height: 18mm; object-fit: contain; display: block; margin: 0 auto 4px; }

@media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    .ticket { margin: 0; padding: 2mm 2mm 2mm 5mm; }
}
</style>

<div class="ticket">

    {{-- EMPRESA --}}
    <div class="center">
        @if($logoExists)
            <img src="{{ $logoSrc }}" alt="Logo" class="logo">
        @endif
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
            {{-- La nota debe mostrar la fecha en que se va a entregar
                 (programado_para), no la fecha/hora en que se capturó el
                 pedido — antes salía la de creación y no coincidía con
                 cuándo de verdad iba a salir el pedido. --}}
            <td>Fecha:</td>
            <td class="right">{{ optional($order->programado_para ?? $order->fecha)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>Hora:</td>
            <td class="right">{{ optional($order->fecha)->format('h:i:s a') }}</td>
        </tr>
    </table>

    <hr class="dashed">

    {{-- CLIENTE --}}
    @if($client)
    @php
        // Dirección: primero la capturada en el pedido (entrega_*), si no
        // hay usamos la de entrega efectiva del cliente, y si tampoco hay
        // caemos a la dirección libre del cliente.
        $efectiva = $client->getEntregaEfectiva();
        $calle    = $order->entrega_calle   ?? $efectiva['calle']   ?? '';
        $numero   = $order->entrega_numero  ?? $efectiva['numero']  ?? '';
        $colonia  = $order->entrega_colonia ?? $efectiva['colonia'] ?? '';
        $ciudad   = $order->entrega_ciudad  ?? $efectiva['ciudad']  ?? '';
        $estado   = $order->entrega_estado  ?? $efectiva['estado']  ?? '';
        $cp       = $order->entrega_cp      ?? $efectiva['cp']      ?? '';

        $dirParts = array_filter([
            trim($calle . ' ' . $numero),
            $colonia,
            trim($ciudad . ($estado ? ', ' . $estado : '') . ($cp ? '  CP ' . $cp : '')),
        ]);

        // Si no hay nada estructurado, usamos el campo libre de dirección.
        if (!count($dirParts) && $client->direccion) {
            $dirParts = [$client->direccion];
        }

        $telEntrega = $order->entrega_telefono ?? $client->telefono ?? '';
    @endphp
    <div>
        <span class="sm">Cliente:</span>
        <span class="sm">{{ $client->telefono ?? '' }}</span>
        <div class="bold">{{ strtoupper($order->entrega_nombre ?: $client->nombre) }}</div>
        @if(count($dirParts))
            <div class="sm">{{ implode(', ', $dirParts) }}</div>
        @endif
        @if($telEntrega)
            <div class="sm">Teléfono: {{ $telEntrega }}</div>
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
            @continue((float)$it->cantidad <= 0)
            <tr>
                <td colspan="3">{{ number_format((float)$it->cantidad, 2) }}
                    {{ strtoupper($it->descripcion ?: ($it->product->nombre ?? '#'.$it->product_id)) }}</td>
            </tr>
            <tr class="item-precio-row">
                <td colspan="3">
                    <div style="display:flex; justify-content:space-between;">
                        <span>${{ number_format((float)$it->precio, 2) }}</span>
                        <span>${{ number_format((float)$it->total, 2) }}</span>
                    </div>
                </td>
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
    <div class="sm" style="margin-top:2px;">
        {{ \App\Services\NumeroALetrasService::convertir((float) $order->total) }}
    </div>

    {{-- COMENTARIOS DEL PEDIDO --}}
    @if($order->comentarios)
    <div class="observaciones" style="margin-top:3mm;">
        <div class="bold">COMENTARIOS:</div>
        <div style="margin-top:2px;">{{ $order->comentarios }}</div>
    </div>
    @endif

    {{-- ESPACIO + OBSERVACIONES --}}
    <div style="margin-top:5mm;"></div>
    <div class="observaciones">
        <div class="bold">OBSERVACIONES:</div>
        <div style="margin-top:2px;">
            Es responsabilidad del Cliente revisar al momento de la entrega que el producto vaya conforme fue solicitado, después de que el chofer se retire no serán aceptadas devoluciones y/o reclamaciones.
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
