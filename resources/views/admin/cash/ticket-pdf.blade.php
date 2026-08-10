<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket Caja #{{ $register->id }}</title>
    <style>
        @page { margin: 8px 10px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 0; padding: 0; }
        .center { text-align: center; }
        .right  { text-align: right; }
        .left   { text-align: left; }
        .bold   { font-weight: 700; }
        .xs     { font-size: 10px; }
        .mt-2   { margin-top: 8px; }
        hr { border: 0; border-top: 1px dashed #333; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; font-size: 11px; vertical-align: top; }
        .logo { max-width: 55mm; max-height: 18mm; display: block; margin: 0 auto 4px; }
        .firmas td { padding-top: 28px; width: 50%; }
        .firma-linea { border-top: 1px solid #333; margin: 0 6px; }
    </style>
</head>
<body>
    @include('admin.cash.partials.ticket-body', ['register' => $register, 'company' => $company, 'forPdf' => true])
</body>
</html>
