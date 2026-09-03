<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket {{ $order->folio }}</title>
</head>
<body>

<div class="no-print" style="text-align:center;padding:8px;background:#f3f4f6;border-bottom:1px solid #e5e7eb;">
    <button onclick="imprimirTicket()"
            style="padding:6px 20px;background:#1d4ed8;color:#fff;border:0;border-radius:4px;font-size:13px;cursor:pointer;">
        Imprimir
    </button>
    <a href="{{ route('admin.sales-orders.edit', $order) }}"
       style="margin-left:12px;font-size:12px;color:#6b7280;text-decoration:none;">← Volver</a>
</div>

@include('admin.sales_orders.partials.ticket-body', ['order' => $order, 'empresa' => $empresa, 'forPdf' => false])

<script>
// El @page del ticket usa "auto" de alto, y en algunos navegadores/drivers
// de impresoras de ticket ese cálculo automático sale mal y parte el
// ticket en 2 "páginas" (la 2da casi vacía imprime como una tira larga
// de papel en blanco). Medimos el alto real del ticket ya renderizado y
// fijamos el @page a ese alto exacto justo antes de imprimir, para que
// no haya adivinanza de por medio.
function ajustarAltoPaginaTicket() {
    var el = document.querySelector('.ticket');
    if (!el) return;
    var alturaPx = el.getBoundingClientRect().height;
    // El margen (2cm arriba, 1cm abajo) se resta del área imprimible, así
    // que hay que sumarlo aparte al alto de página — si no, el margen
    // "come" del alto ya justo para el contenido y el ticket se corta a
    // una 2da hoja.
    var MARGEN_ARRIBA_MM = 20;
    var MARGEN_ABAJO_MM  = 10;
    var alturaMm = Math.ceil(alturaPx * 25.4 / 96) + 5 + MARGEN_ARRIBA_MM + MARGEN_ABAJO_MM; // +5mm de colchón
    var style = document.getElementById('ticket-page-size') || document.createElement('style');
    style.id = 'ticket-page-size';
    style.innerHTML = '@page { size: 68mm ' + alturaMm + 'mm; margin: ' + MARGEN_ARRIBA_MM + 'mm 0 ' + MARGEN_ABAJO_MM + 'mm 0; }';
    document.head.appendChild(style);
}

function imprimirTicket() {
    ajustarAltoPaginaTicket();
    window.print();
}

// Auto-imprimir si se abrió desde el panel de despacho
if (window.opener) {
    window.addEventListener('load', imprimirTicket);
}
</script>
</body>
</html>
