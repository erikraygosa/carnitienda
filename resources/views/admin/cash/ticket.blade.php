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

        <div class="center">
            @if($company?->logo_path)
                <img src="{{ Storage::url($company->logo_path) }}" alt="Logo" class="logo">
            @endif

            <div class="bold">{{ $company?->nombre_comercial ?? $company?->razon_social ?? 'Mi Tienda' }}</div>
            @if($company?->razon_social && $company?->nombre_comercial)
                <div class="xs">{{ $company->razon_social }}</div>
            @endif
            @if($company?->rfc)
                <div class="xs">RFC: {{ $company->rfc }}</div>
            @endif
            @if($company?->telefono)
                <div class="xs">Tel: {{ $company->telefono }}</div>
            @endif
            @if($company?->email)
                <div class="xs">{{ $company->email }}</div>
            @endif

            <hr>

            <div class="xs bold">Caja #{{ $register->id }} • {{ $register->fecha->format('d/m/Y') }}</div>
            <div class="xs">Usuario: {{ $register->user?->name ?? 'N/D' }}</div>
            <div class="xs">Almacén: {{ $register->warehouse?->nombre ?? 'N/D' }}</div>

            <hr>
        </div>

        <table>
            <tbody>
                <tr>
                    <td>Apertura</td>
                    <td class="right">${{ number_format($register->monto_apertura, 2) }}</td>
                </tr>
                <tr>
                    <td>Ingresos</td>
                    <td class="right">${{ number_format($register->ingresos, 2) }}</td>
                </tr>
                <tr>
                    <td>Egresos</td>
                    <td class="right">- ${{ number_format($register->egresos, 2) }}</td>
                </tr>
                <tr>
                    <td>Ventas efectivo</td>
                    <td class="right">${{ number_format($register->ventas_efectivo, 2) }}</td>
                </tr>
                <tr>
                    <td class="bold">Saldo final</td>
                    <td class="right bold">${{ number_format($register->monto_cierre, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <hr class="mt-2">

        <div class="center small bold">Movimientos</div>
        <table>
            <thead>
                <tr>
                    <th class="left">Hora</th>
                    <th class="left">Tipo</th>
                    <th class="left">Concepto</th>
                    <th class="right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($register->movements()->oldest()->get() as $m)
                <tr>
                    <td class="xs">{{ $m->created_at->format('H:i') }}</td>
                    <td class="xs">{{ $m->tipo }}</td>
                    <td class="xs">{{ \Illuminate\Support\Str::limit($m->concepto, 18) }}</td>
                    <td class="xs right">${{ number_format($m->monto, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="xs center">Sin movimientos</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <hr class="mt-2">
        <div class="center xs">¡Gracias!</div>
        @if($company?->sitio_web)
            <div class="center xs">{{ $company->sitio_web }}</div>
        @endif

    </div>

    <div class="btns">
        <a href="{{ route('admin.cash.ticket.pdf', $register) }}" target="_blank" class="btn btn-primary">
            🖨 Imprimir / PDF
        </a>
        <a href="{{ route('admin.cash.show', $register) }}" class="btn">Volver</a>
    </div>
</body>
</html>