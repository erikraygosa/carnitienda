<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nota de venta #{{ $sale->folio ?? $sale->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .table { width:100%; border-collapse: collapse; }
        .table th, .table td { border:1px solid #ccc; padding:6px; }
        .text-right{ text-align:right; }
        .mb-2{ margin-bottom:10px; }
    </style>
</head>
<body>
    <h2>Nota de venta {{ $sale->folio ?? ('#'.$sale->id) }}</h2>
    <p class="mb-2">
        Fecha: {{ optional($sale->fecha)->format('Y-m-d H:i') }}<br>
        Cliente: {{ $sale->client->nombre ?? 'Público en general' }}<br>
        Almacén: {{ $sale->warehouse->nombre ?? '-' }}<br>
        Moneda: {{ $sale->moneda }}
    </p>

    @if($sale->delivery_type === 'ENVIO')
    <p class="mb-2">
        <b>Entrega:</b> {{ $sale->entrega_nombre }} | {{ $sale->entrega_telefono }}<br>
        {{ $sale->entrega_calle }} {{ $sale->entrega_numero }}, {{ $sale->entrega_colonia }}<br>
        {{ $sale->entrega_ciudad }}, {{ $sale->entrega_estado }} {{ $sale->entrega_cp }}
    </p>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Descripción</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Desc.</th>
                <th class="text-right">Impuesto</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $it)
            <tr>
                <td>{{ $it->product->nombre ?? $it->product_id }}</td>
                <td>{{ $it->descripcion }}</td>
                <td class="text-right">{{ number_format($it->cantidad,3) }}</td>
                <td class="text-right">{{ number_format($it->precio,4) }}</td>
                <td class="text-right">{{ number_format($it->descuento,2) }}</td>
                <td class="text-right">{{ number_format($it->impuesto,2) }}</td>
                <td class="text-right">{{ number_format($it->total,2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="text-right">
        Subtotal: {{ number_format($sale->subtotal,2) }}<br>
        Descuento: {{ number_format($sale->descuento,2) }}<br>
        Impuestos: {{ number_format($sale->impuestos,2) }}<br>
        <b>Total: {{ number_format($sale->total,2) }}</b>
    </p>
</body>
</html>
