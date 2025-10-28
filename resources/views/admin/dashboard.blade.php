{{-- resources/views/admin/dashboard.blade.php --}}
<x-admin-layout
    title="Dashboard"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
    ]"
>
    {{-- Acciones arriba a la derecha (opcional) --}}
    <x-slot name="action">
        {{-- Ejemplo: botón a POS --}}
        <x-wire-button href="{{ route('admin.pos.create') }}" blue>Nuevo POS</x-wire-button>
    </x-slot>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-wire-card>
            <div class="text-sm text-gray-500">Ventas del día (conteo)</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format($ventasCount) }}</div>
        </x-wire-card>

        <x-wire-card>
            <div class="text-sm text-gray-500">Ventas del día ($)</div>
            <div class="mt-1 text-2xl font-semibold">${{ number_format($ventasTotal,2) }}</div>
        </x-wire-card>

        <x-wire-card>
            <div class="text-sm text-gray-500">Ventas en efectivo (hoy)</div>
            <div class="mt-1 text-2xl font-semibold">${{ number_format($ventasEfectivo,2) }}</div>
        </x-wire-card>

        <x-wire-card>
            <div class="text-sm text-gray-500">Pedidos en tránsito</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format($pedidosTransito) }}</div>
        </x-wire-card>
    </div>

    {{-- Listas --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Últimas ventas --}}
        <x-wire-card>
            <h3 class="font-semibold mb-3">Últimas ventas</h3>
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="p-2 text-left">Folio</th>
                            <th class="p-2 text-left">Fecha</th>
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2 text-left">Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ultimasVentas as $v)
                            <tr class="border-b">
                                <td class="p-2">#{{ $v->id }}</td>
                                <td class="p-2">{{ \Carbon\Carbon::parse($v->fecha)->format('Y-m-d H:i') }}</td>
                                <td class="p-2 text-right">${{ number_format($v->total,2) }}</td>
                                <td class="p-2">{{ $v->metodo_pago }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4 text-center text-gray-500">Sin registros</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-wire-card>

        {{-- Pedidos en tránsito --}}
        <x-wire-card>
            <h3 class="font-semibold mb-3">Pedidos en tránsito</h3>
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="p-2 text-left">Pedido</th>
                            <th class="p-2 text-left">Estatus</th>
                            <th class="p-2 text-left">Actualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ultimosPedidosTransito as $p)
                            <tr class="border-b">
                                <td class="p-2">#{{ $p->id }}</td>
                                <td class="p-2">{{ $p->status }}</td>
                                <td class="p-2">{{ \Carbon\Carbon::parse($p->updated_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-4 text-center text-gray-500">Sin registros</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-wire-card>
    </div>
</x-admin-layout>
