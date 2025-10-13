@php
    $client = $order->client ?? null;
@endphp

<p>Hola {{ $client->nombre ?? 'cliente' }},</p>

<p>Te compartimos la <strong>remisión del pedido #{{ $order->id }}</strong>.</p>

<ul>
    <li><strong>Fecha:</strong> {{ optional($order->fecha)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }}</li>
    <li><strong>Total:</strong> {{ number_format((float)$order->total, 2) }} {{ $order->moneda ?? 'MXN' }}</li>
    <li><strong>Estatus:</strong> {{ $order->status }}</li>
</ul>

<p>Se adjunta el PDF con el detalle completo.</p>

<p>Saludos,<br>
{{ config('app.name') }}</p>
