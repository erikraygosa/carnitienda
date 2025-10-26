<div class="space-y-3">
    <div>
        <input type="text" wire:model.debounce.500ms="search"
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
               placeholder="Buscar por chofer o fecha...">
    </div>

    <div class="overflow-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2 text-left">Fecha</th>
                    <th class="p-2 text-left">Chofer</th>
                    <th class="p-2 text-left">Estatus</th>
                    <th class="p-2 text-right">Saldo actual</th>
                    <th class="p-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                <tr class="border-b">
                    <td class="p-2">{{ $r->fecha->format('Y-m-d') }}</td>
                    <td class="p-2">{{ $r->driver->name ?? 'N/D' }}</td>
                    <td class="p-2">{{ $r->estatus }}</td>
                    <td class="p-2 text-right">
                        ${{ number_format(($r->saldo_inicial + $r->saldo_cargos) - $r->saldo_abonos, 2) }}
                    </td>
                    <td class="p-2">
                        @include('admin.driver-cash.partials.actions', ['register'=>$r])
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-4 text-center text-gray-500">Sin registros</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
