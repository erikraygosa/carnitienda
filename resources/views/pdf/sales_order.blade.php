@php
    // Helpers seguros para evitar errores si algo viene null
    $client   = $order->client ?? null;
    $wh       = $order->warehouse ?? null;
    $driver   = $order->driver ?? null;
    $route    = $order->route ?? null;

    // Campos opcionales sugeridos en el modelo de pedido (ajusta a tus nombres reales)
    $entregaTipo      = $order->entrega_tipo      ?? '-';
    $entregaDireccion = $order->entrega_direccion ?? '-';
    $entregaContacto  = $order->entrega_contacto  ?? '-';
    $entregaNotas     = $order->entrega_notas     ?? '';

    $metodoPago       = $order->payment_method    ?? '-';
    $terminoPago      = $order->payment_terms     ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Remisión Pedido #{{ $order->id }}</title>
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 12px; color: #111; }
    .flex { display: flex; }
    .between { justify-content: space-between; }
    .mt-2 { margin-top: 8px; }
    .mt-4 { margin-top: 16px; }
    .mb-2 { margin-bottom: 8px; }
    .mb-4 { margin-bottom: 16px; }
    .p-2 { padding: 8px; }
    .border { border: 1px solid #ddd; }
    .rounded { border-radius: 6px; }
    .w-50 { width: 49%; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { border: 1px solid #ddd; padding: 6px; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-sm { font-size: 11px; color:#555; }
    .text-lg { font-size: 14px; font-weight: bold; }
    .badge { display:inline-block; padding:2px 6px; border:1px solid #999; border-radius: 4px; font-size: 10px;}
    .muted { color:#666; }
</style>
</head>
<body>

    {{-- ENCABEZADO --}}
    <div class="flex between">
        <div>
            <div class="text-lg">Remisión de Pedido</div>
            <div class="mt-2">
                <div><b>Pedido #:</b> {{ $order->id }}</div>
                <div><b>Fecha:</b> {{ optional($order->fecha)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }}</div>
                <div><b>Estatus:</b> <span class="badge">{{ $order->status ?? '' }}</span></div>
            </div>
        </div>
        <div class="text-right">
            {{-- Pon tu logo si quieres --}}
            {{-- <img src="{{ public_path('img/logo.png') }}" height="60"> --}}
            <div class="text-sm muted">{{ config('app.name') }}</div>
        </div>
    </div>

    {{-- CLIENTE / ENTREGA --}}
    <div class="flex between mt-4">
        <div class="w-50 border rounded p-2">
            <div class="text-lg mb-2">Cliente</div>
            <div><b>Nombre:</b> {{ $client->nombre ?? '-' }}</div>
            <div><b>Email:</b> {{ $client->email ?? '-' }}</div>
            <div><b>Teléfono:</b> {{ $client->telefono ?? '-' }}</div>
        </div>

        <div class="w-50 border rounded p-2">
            <div class="text-lg mb-2">Entrega</div>
            <div><b>Tipo:</b> {{ $entregaTipo }}</div>
            <div><b>Dirección:</b> {{ $entregaDireccion }}</div>
            <div><b>Contacto:</b> {{ $entregaContacto }}</div>
            @if($entregaNotas)
                <div><b>Notas:</b> {{ $entregaNotas }}</div>
            @endif
        </div>
    </div>

    {{-- ALMACÉN / RUTA --}}
    <div class="flex between mt-2">
        <div class="w-50 border rounded p-2">
            <div class="text-lg mb-2">Almacén</div>
            <div><b>Nombre:</b> {{ $wh->nombre ?? '-' }}</div>
        </div>
        <div class="w-50 border rounded p-2">
            <div class="text-lg mb-2">Logística</div>
            <div><b>Chofer:</b> {{ $driver->nombre ?? '-' }}</div>
            <div><b>Ruta:</b> {{ $route->nombre ?? '-' }}</div>
        </div>
    </div>

    {{-- MÉTODO DE PAGO --}}
    <div class="border rounded p-2 mt-2">
        <div class="text-lg mb-2">Pago</div>
        <div><b>Método:</b> {{ $metodoPago }}</div>
        @if($terminoPago)<div><b>Términos:</b> {{ $terminoPago }}</div>@endif
        <div><b>Moneda:</b> {{ $order->moneda ?? 'MXN' }}</div>
    </div>

    {{-- PARTIDAS --}}
    <div class="mt-4">
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
            @foreach($order->items as $it)
                <tr>
                    <td>{{ $it->product->nombre ?? '-' }}</td>
                    <td>{{ $it->descripcion }}</td>
                    <td class="text-right">{{ number_format((float)$it->cantidad, 3) }}</td>
                    <td class="text-right">{{ number_format((float)$it->precio, 4) }}</td>
                    <td class="text-right">{{ number_format((float)$it->descuento, 2) }}</td>
                    <td class="text-right">{{ number_format((float)$it->impuesto, 2) }}</td>
                    <td class="text-right">{{ number_format((float)$it->total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- TOTALES --}}
    <div class="flex between mt-4">
        <div></div>
        <table class="table" style="width: 45%;">
            <tr>
                <td><b>Subtotal</b></td>
                <td class="text-right">{{ number_format((float)$order->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td><b>Descuento</b></td>
                <td class="text-right">{{ number_format((float)$order->descuento, 2) }}</td>
            </tr>
            <tr>
                <td><b>Impuestos</b></td>
                <td class="text-right">{{ number_format((float)$order->impuestos, 2) }}</td>
            </tr>
            <tr>
                <td><b>Total</b></td>
                <td class="text-right"><b>{{ number_format((float)$order->total, 2) }}</b></td>
            </tr>
        </table>
    </div>

    <div class="text-sm mt-4">
        Documento generado por {{ config('app.name') }} — {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
