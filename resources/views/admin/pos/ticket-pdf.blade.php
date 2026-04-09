<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket POS #{{ $sale->id }}</title>
    <style>
        @page { margin: 8px 10px; }
        body  { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .center { text-align: center; }
        .right  { text-align: right; }
        .small  { font-size: 10px; }
        .bold   { font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; vertical-align: top; }
        .logo { max-width: 160px; max-height: 60px; }
    </style>
</head>
<body>

    <div class="center">
        {{-- Logo --}}
        @if($company?->logo_path)
            <img src="{{ storage_path('app/public/' . $company->logo_path) }}"
                 alt="Logo" class="logo"><br>
        @endif

        {{-- Datos empresa --}}
        <div class="bold">{{ $company?->nombre_comercial ?? $company?->razon_social ?? 'Mi Tienda' }}</div>
        @if($company?->razon_social && $company?->nombre_comercial)
            <div class="small">{{ $company->razon_social }}</div>
        @endif
        @if($company?->rfc)
            <div class="small">RFC: {{ $company->rfc }}</div>
        @endif
        @if($company?->telefono)
            <div class="small">Tel: {{ $company->telefono }}</div>
        @endif
        @if($company?->email)
            <div class="small">{{ $company->email }}</div>
        @endif
        @if($company?->sitio_web)
            <div class="small">{{ $company->sitio_web }}</div>
        @endif

        <hr>

        <div class="small bold">Ticket #{{ $sale->id }}</div>
        <div class="small">{{ $sale->fecha->format('d/m/Y H:i') }}</div>
        @if($sale->client)
            <div class="small">Cliente: {{ $sale->client->nombre }}</div>
        @endif
        <hr>
    </div>

    {{-- Partidas --}}
    <table>
        <thead>
            <tr>
                <th style="text-align:left;">Producto</th>
                <th class="right">Cant</th>
                <th class="right">P.U.</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $it)
            <tr>
                <td>{{ \Illuminate\Support\Str::limit($it->product?->nombre ?? '#'.$it->product_id, 20) }}</td>
                <td class="right">{{ number_format($it->cantidad, 3) }}</td>
                <td class="right">${{ number_format($it->precio_unitario, 2) }}</td>
                <td class="right">${{ number_format($it->importe, 2) }}</td>
            </tr>
            @if($it->descuento > 0)
            <tr>
                <td colspan="4" class="right small" style="color:#cc0000;">
                    Desc: -${{ number_format($it->descuento, 2) }}
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    <hr>

    {{-- Totales --}}
    <table>
        <tr>
            <td>Subtotal</td>
            <td class="right">${{ number_format($sale->subtotal, 2) }}</td>
        </tr>
        @if($sale->descuento > 0)
        <tr>
            <td>Descuento</td>
            <td class="right" style="color:#cc0000;">-${{ number_format($sale->descuento, 2) }}</td>
        </tr>
        @endif
        @if($sale->impuestos > 0)
        <tr>
            <td>Impuestos</td>
            <td class="right">${{ number_format($sale->impuestos, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td class="bold" style="border-top:1px solid #000;padding-top:4px;">TOTAL</td>
            <td class="right bold" style="border-top:1px solid #000;padding-top:4px;">
                ${{ number_format($sale->total, 2) }}
            </td>
        </tr>
        <tr>
            <td class="small" style="color:#555;">
                {{ $sale->metodo_pago }}{{ $sale->referencia ? ' · '.$sale->referencia : '' }}
            </td>
            <td></td>
        </tr>
    </table>

    <hr>
    <div class="center small">¡Gracias por su compra!</div>

</body>
</html>