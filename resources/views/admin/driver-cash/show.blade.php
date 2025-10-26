{{-- resources/views/admin/driver-cash/show.blade.php --}}
<x-admin-layout
    :title="'Corte de chofer #'.$register->id"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Logística'],
        ['name'=>'Cortes de choferes','url'=>route('admin.driver-cash.index')],
        ['name'=>'Detalle'],
    ]"
>
    <x-slot name="action">
        @if($register->estatus === 'ABIERTO')
            <form action="{{ route('admin.driver-cash.close',$register) }}" method="POST" class="inline close-form">
                @csrf
                <input type="hidden" name="require_zero" value="0">
                <x-wire-button type="submit" red>Cerrar corte</x-wire-button>
            </form>
        @endif
    </x-slot>

    <div class="grid md:grid-cols-4 gap-4">
        <x-wire-card>
            <div class="text-sm text-gray-500">Chofer</div>
            <div class="text-lg font-semibold">{{ $register->driver->name ?? 'N/D' }}</div>
        </x-wire-card>
        <x-wire-card>
            <div class="text-sm text-gray-500">Saldo inicial</div>
            <div class="text-lg font-semibold">${{ number_format($register->saldo_inicial,2) }}</div>
        </x-wire-card>
        <x-wire-card>
            <div class="text-sm text-gray-500">Cargos / Abonos</div>
            <div class="text-lg font-semibold">${{ number_format($register->saldo_cargos,2) }} / ${{ number_format($register->saldo_abonos,2) }}</div>
        </x-wire-card>
        <x-wire-card>
            <div class="text-sm text-gray-500">Saldo actual</div>
            <div class="text-lg font-semibold">{{ $register->saldo_actual >=0 ? '$'.number_format($register->saldo_actual,2) : '($'.number_format(abs($register->saldo_actual),2).')' }}</div>
        </x-wire-card>
    </div>

    <div class="grid md:grid-cols-3 gap-4 mt-6">
        <x-wire-card class="md:col-span-2">
            <h3 class="font-semibold mb-3">Movimientos</h3>
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="p-2 text-left">Fecha</th>
                            <th class="p-2 text-left">Tipo</th>
                            <th class="p-2 text-left">Descripción</th>
                            <th class="p-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($register->movements()->latest()->get() as $m)
                            <tr class="border-b">
                                <td class="p-2">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                                <td class="p-2">{{ $m->tipo }}</td>
                                <td class="p-2">{{ $m->descripcion }}</td>
                                <td class="p-2 text-right">{{ number_format($m->monto,2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="p-3 text-center text-gray-500" colspan="4">Sin movimientos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-wire-card>

        <x-wire-card>
            <h3 class="font-semibold mb-3">Registrar abono</h3>
            @if($register->estatus === 'ABIERTO')
            <form action="{{ route('admin.driver-cash.abono',$register) }}" method="POST" class="space-y-4">
                @csrf
                <x-wire-input label="Monto" name="monto" type="number" step="0.01" required />
                <x-wire-input label="Descripción" name="descripcion" type="text" placeholder="Entrega de efectivo" />
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
                </div>
            </form>
            @else
                <div class="text-sm text-gray-500">El corte está cerrado.</div>
            @endif
        </x-wire-card>
    </div>

    @push('js')
    <script>
        // confirmadores si necesitas más acciones
    </script>
    @endpush
</x-admin-layout>
