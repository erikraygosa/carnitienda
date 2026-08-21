<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $sale->folio ?? $sale->id }}</title>
</head>
<body>
    @include('admin.sales.partials.ticket-body', ['sale' => $sale, 'empresa' => $empresa, 'forPdf' => true])
</body>
</html>
