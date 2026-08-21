<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket {{ $sale->folio }}</title>
</head>
<body>

<div class="no-print" style="text-align:center;padding:8px;background:#f3f4f6;border-bottom:1px solid #e5e7eb;">
    <button onclick="window.print()"
            style="padding:6px 20px;background:#1d4ed8;color:#fff;border:0;border-radius:4px;font-size:13px;cursor:pointer;">
        Imprimir
    </button>
    <a href="{{ route('admin.sales.edit', $sale) }}"
       style="margin-left:12px;font-size:12px;color:#6b7280;text-decoration:none;">← Volver</a>
</div>

@include('admin.sales.partials.ticket-body', ['sale' => $sale, 'empresa' => $empresa, 'forPdf' => false])

<script>
if (window.opener) {
    window.addEventListener('load', function () { window.print(); });
}
</script>
</body>
</html>
