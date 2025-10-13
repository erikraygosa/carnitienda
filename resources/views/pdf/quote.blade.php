<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cotización #{{ $quote->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th,td { border:1px solid #ddd; padding:6px; }
        th { background:#f2f2f2; }
        .right { text-align:right; }
    </style>
</head>
<body>
    <h2>Cotización #{{ $quote->id }}</h2>
    <p><b>Cliente:</b> {{ $quote->client->nombre ?? '—' }}</p>
    <p><b>Fecha:</b> {{ optional($quote->fecha)->format('Y-m-d H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Descripción</th>
                <th class="right">Cant.</th>
                <th class="right">Precio</th>
                <th class="right">Desc.</th>
                <th class="right">Impuesto</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $it)
                <tr>
                    <td>{{ $it->product?->nombre ?? '—' }}</td>
                    <td>{{ $it->descripcion }}</td>
                    <td class="right">{{ number_format($it->cantidad, 3) }}</td>
                    <td class="right">{{ number_format($it->precio, 4) }}</td>
                    <td class="right">{{ number_format($it->descuento, 2) }}</td>
                    <td class="right">{{ number_format($it->impuesto, 2) }}</td>
                    <td class="right">{{ number_format($it->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="right"><b>Subtotal:</b> {{ number_format($quote->subtotal, 2) }}</p>
    <p class="right"><b>Descuento:</b> {{ number_format($quote->descuento, 2) }}</p>
    <p class="right"><b>Impuestos:</b> {{ number_format($quote->impuestos, 2) }}</p>
    <p class="right"><b>Total:</b> {{ number_format($quote->total, 2) }}</p>
</body>
</html>
