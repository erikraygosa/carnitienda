<div class="space-y-3">
    {{-- Buscador --}}
    <input
        type="text"
        wire:model.debounce.500ms="search"
        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        placeholder="Buscar cliente..."
    >

    {{-- Tabla --}}
    <div class="overflow-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2 text-left">Cliente</th>
                    <th class="p-2 text-right">Saldo</th>
                    <th class="p-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr class="border-b">
                        <td class="p-2">{{ $r->nombre }}</td>
                        <td class="p-2 text-right">
                            {{ $r->saldo >= 0 ? '$'.number_format($r->saldo, 2) : '($'.number_format(abs($r->saldo), 2).')' }}
                        </td>
                        <td class="p-2">
                            <x-wire-button href="{{ route('admin.ar.show', $r->id) }}" blue xs>
                                Ver
                            </x-wire-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="p-4 text-center text-gray-500">Sin datos</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div>
        {{ $rows->links() }}
    </div>
</div>
