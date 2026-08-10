<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket POS #{{ $sale->id }}</title>
</head>
<body>
    @include('admin.pos.partials.ticket-body', ['sale' => $sale, 'company' => $company])
</body>
</html>
