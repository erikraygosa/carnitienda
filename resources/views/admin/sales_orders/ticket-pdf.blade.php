<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $order->folio ?? $order->id }}</title>
</head>
<body>
    @include('admin.sales_orders.partials.ticket-body', ['order' => $order, 'empresa' => $empresa, 'forPdf' => true])
</body>
</html>
