<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket Caja #{{ $register->id }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --w: 80mm; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            background: #f8fafc;
            margin: 0;
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .wrap {
            width: var(--w);
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.12);
            padding: 12px;
        }
        .center { text-align: center; }
        .right  { text-align: right; }
        .left   { text-align: left; }
        .bold   { font-weight: 700; }
        .small  { font-size: 11px; }
        .xs     { font-size: 10px; }
        .mt-2   { margin-top: 8px; }
        hr { border: 0; border-top: 1px dashed #333; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; font-size: 11px; vertical-align: top; }
        .logo { max-width: 55mm; max-height: 18mm; object-fit: contain; margin-bottom: 4px; display: block; margin-left: auto; margin-right: auto; }
        .firmas td { padding-top: 28px; width: 50%; }
        .firma-linea { border-top: 1px solid #333; margin: 0 6px; }
        .btns {
            width: var(--w);
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 8px;
        }
        .btn {
            font-size: 12px;
            border: 1px solid #ddd;
            background: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            color: #111;
        }
        .btn-primary { background: #4f46e5; color: #fff; border-color: #4f46e5; }
        @media print {
            .btns { display: none !important; }
            body  { background: #fff; padding: 0; }
            .wrap { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="wrap" id="ticket">
        @include('admin.cash.partials.ticket-body', ['register' => $register, 'company' => $company, 'forPdf' => false])
    </div>

    <div class="btns">
        <a href="{{ route('admin.cash.ticket.pdf', $register) }}" target="_blank" class="btn btn-primary">
            🖨 Imprimir / PDF
        </a>
        <a href="{{ route('admin.cash.show', $register) }}" class="btn">Volver</a>
    </div>
</body>
</html>
