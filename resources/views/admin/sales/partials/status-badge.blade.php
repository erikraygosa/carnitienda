@php
$classes = [
    'ABIERTA'   => 'bg-sky-100 text-sky-700',
    'CERRADA'   => 'bg-emerald-100 text-emerald-700',
    'CANCELADA' => 'bg-rose-100 text-rose-700',
];
@endphp
<span class="px-2 py-1 text-xs rounded-full {{ $classes[$sale->status] ?? 'bg-gray-100 text-gray-700' }}">
    {{ $sale->status }}
</span>
