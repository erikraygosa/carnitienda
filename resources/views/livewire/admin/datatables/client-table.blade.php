<div>
    {{-- Filtros --}}
    <div class="flex flex-col md:flex-row md:items-center gap-3 mb-4">
        <div class="flex-1">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Buscar nombre / email / teléfono..."
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <select wire:model.live="activo"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>
        </div>
        <div>
            <select wire:model.live="perPage"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @php
                        $th = fn($col, $label) => '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100"
                            wire:click="sort(\''.$col.'\')">'.$label.($this->sortBy===$col ? ($this->sortDir==='asc'?' ↑':' ↓'):'').'</th>';
                    @endphp
                    {!! $th('id','ID') !!}
                    {!! $th('nombre','Nombre') !!}
                    {!! $th('email','Email') !!}
                    {!! $th('telefono','Teléfono') !!}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruta</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lista precio</th>
                    {!! $th('credito_limite','Créd. límite') !!}
                    {!! $th('credito_dias','Créd. días') !!}
                    {!! $th('activo','Activo') !!}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($clients as $client)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $client->id }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $client->nombre }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $client->email ?: '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $client->telefono ?: '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $client->shippingRoute?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $client->priceList?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-gray-700">${{ number_format((float)$client->credito_limite, 2) }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $client->credito_dias ?? 0 }}d</td>
                    <td class="px-4 py-3">
                        @if($client->activo)
                            <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-rose-100 text-rose-700">Inactivo</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @include('admin.clients.actions', ['client' => $client])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-gray-400">
                        No se encontraron clientes.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $clients->links() }}
    </div>
</div>