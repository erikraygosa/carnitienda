{{--
    Contenido del ticket de corte de caja, compartido entre la vista en
    pantalla (admin/cash/ticket.blade.php) y el PDF (ticket-pdf.blade.php)
    para que no se desincronicen. Espera $register (con movements/posSales/
    sales cargables), $company y $resumen (bool — true = solo el corte, sin
    el desglose de movimientos ni de notas de venta).
--}}
@php
    $resumen = $resumen ?? false;

    // Desglose de cómo se pagó todo lo registrado en esta caja (POS +
    // Notas de venta) — es justo lo que se necesita para el corte.
    $desglosePagos = collect();
    foreach ($register->posSales as $venta) {
        $clave = strtoupper($venta->metodo_pago ?? 'SIN ESPECIFICAR');
        $desglosePagos[$clave] = ($desglosePagos[$clave] ?? 0) + (float) $venta->total;
    }
    foreach ($register->sales as $nota) {
        $clave = strtoupper($nota->paymentType?->descripcion ?? ($nota->tipo_venta === 'CREDITO' ? 'CRÉDITO' : $nota->tipo_venta));
        $desglosePagos[$clave] = ($desglosePagos[$clave] ?? 0) + (float) $nota->total;
    }
    // Mismo logo que ya usan el sidebar y los PDFs de carta (invoice, quote,
    // sales_order): un archivo fijo en public/logo.jpg, no un campo de BD.
    // Se embebe en base64 para que funcione igual en pantalla y en el PDF.
    // Configurable en Superadmin → Configuración ('tickets.mostrar_logo').
    $logoPath   = public_path('logo.jpg');
    $logoExists = file_exists($logoPath) && \App\Models\SystemSetting::get('tickets.mostrar_logo', true);
    if ($logoExists) {
        $logoMime = mime_content_type($logoPath) ?: 'image/jpeg';
        $logoSrc  = 'data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }
@endphp
<div class="center">
    @if($logoExists)
        <img src="{{ $logoSrc }}" alt="Logo" class="logo">
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

@if($desglosePagos->isNotEmpty())
<hr class="mt-2">
<div class="center xs bold">Desglose por forma de pago</div>
<table>
    <tbody>
        @foreach($desglosePagos->sortDesc() as $forma => $monto)
        <tr>
            <td class="xs">{{ $forma }}</td>
            <td class="xs right">${{ number_format($monto, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@unless($resumen)
<hr class="mt-2">
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

@if($register->sales->isNotEmpty())
<hr class="mt-2">
<div class="center xs bold">Notas de venta ({{ $register->sales->count() }})</div>
<table>
    <thead>
        <tr>
            <th class="left xs">Folio</th>
            <th class="left xs">Hora</th>
            <th class="left xs">Pago</th>
            <th class="right xs">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($register->sales as $nota)
        <tr>
            <td class="xs">{{ $nota->folio }}</td>
            <td class="xs">{{ optional($nota->fecha)->format('H:i') }}</td>
            <td class="xs">{{ $nota->paymentType?->descripcion ?? ($nota->tipo_venta === 'CREDITO' ? 'Crédito' : $nota->tipo_venta) }}</td>
            <td class="xs right">${{ number_format($nota->total, 2) }}</td>
        </tr>
        @foreach($nota->items as $item)
        <tr>
            <td class="xs" colspan="2" style="padding-left:8px;">
                {{ \Illuminate\Support\Str::limit($item->product?->nombre ?? $item->descripcion ?? 'Producto', 20) }}
            </td>
            <td class="xs">x{{ $item->cantidad }}</td>
            <td class="xs right">${{ number_format($item->total, 2) }}</td>
        </tr>
        @endforeach
        @endforeach
    </tbody>
</table>
@endif

@if($register->posSales->isNotEmpty())
<hr class="mt-2">
<div class="center xs bold">Notas de venta POS ({{ $register->posSales->count() }})</div>
<table>
    <thead>
        <tr>
            <th class="left xs">#</th>
            <th class="left xs">Hora</th>
            <th class="left xs">Método</th>
            <th class="right xs">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($register->posSales as $venta)
        <tr>
            <td class="xs">{{ $venta->id }}</td>
            <td class="xs">{{ $venta->created_at->format('H:i') }}</td>
            <td class="xs">{{ $venta->metodo_pago }}</td>
            <td class="xs right">${{ number_format($venta->total, 2) }}</td>
        </tr>
        @foreach($venta->items as $item)
        <tr>
            <td class="xs" colspan="2" style="padding-left:8px;">
                {{ \Illuminate\Support\Str::limit($item->product?->nombre ?? 'Producto', 20) }}
            </td>
            <td class="xs">x{{ $item->cantidad }}</td>
            <td class="xs right">${{ number_format($item->subtotal, 2) }}</td>
        </tr>
        @endforeach
        @endforeach
    </tbody>
</table>
@endif
@endunless

<hr class="mt-2">

{{-- Firmas de entrega/recepción de caja --}}
<table class="firmas">
    <tr>
        <td class="center">
            <div class="firma-linea"></div>
            <div class="xs">Entregó</div>
        </td>
        <td class="center">
            <div class="firma-linea"></div>
            <div class="xs">Recibió</div>
        </td>
    </tr>
</table>

@if($company?->sitio_web)
    <div class="center xs mt-2">{{ $company->sitio_web }}</div>
@endif
