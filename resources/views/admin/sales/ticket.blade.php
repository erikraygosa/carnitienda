<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket {{ $sale->folio }}</title>
</head>
<body>

<div class="no-print" style="text-align:center;padding:8px;background:#f3f4f6;border-bottom:1px solid #e5e7eb;">
    <button onclick="imprimirTicket()"
            style="padding:6px 20px;background:#1d4ed8;color:#fff;border:0;border-radius:4px;font-size:13px;cursor:pointer;">
        Imprimir
    </button>
    <a href="{{ route('admin.sales.edit', $sale) }}"
       style="margin-left:12px;font-size:12px;color:#6b7280;text-decoration:none;">← Volver</a>
</div>

@include('admin.sales.partials.ticket-body', ['sale' => $sale, 'empresa' => $empresa, 'forPdf' => false])

<script>
// El @page del ticket usa "auto" de alto, y en algunos navegadores/drivers
// de impresoras de ticket ese cálculo automático sale mal y parte el
// ticket en 2 "páginas" (la 2da casi vacía imprime como una tira larga
// de papel en blanco). Medimos el alto real del ticket ya renderizado y
// fijamos el @page a ese alto exacto justo antes de imprimir.
function ajustarAltoPaginaTicket() {
    var el = document.querySelector('.ticket');
    if (!el) return;
    var alturaPx = el.getBoundingClientRect().height;
    var alturaMm = Math.ceil(alturaPx * 25.4 / 96) + 5; // +5mm de colchón
    var style = document.getElementById('ticket-page-size') || document.createElement('style');
    style.id = 'ticket-page-size';
    style.innerHTML = '@page { size: 72mm ' + alturaMm + 'mm; margin: 0; }';
    document.head.appendChild(style);
}

function imprimirTicket() {
    ajustarAltoPaginaTicket();
    window.print();
}

if (window.opener) {
    window.addEventListener('load', imprimirTicket);
}
</script>
</body>
</html>
