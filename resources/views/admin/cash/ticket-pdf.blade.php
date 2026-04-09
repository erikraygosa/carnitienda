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
        hr { border: 0; border-top: 1px dashed #333; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; font-size: 11px; vertical-align: top; }
        .logo { max-width: 55mm; max-height: 18mm; display: block; margin: 0 auto 4px; }
    </style>
</head>
<body>

    <div class="center">
        @if($company?->logo_path)
            <img src="{{ storage_path('app/public/' . $company->logo_path) }}" alt="Logo" class="logo">
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

    <hr>
    <div class="center xs bold">Movimientos</div>
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

    <hr>
    <div class="center xs">¡Gracias!</div>
    @if($company?->sitio_web)
        <div class="center xs">{{ $company->sitio_web }}</div>
    @endif

</body>
</html>