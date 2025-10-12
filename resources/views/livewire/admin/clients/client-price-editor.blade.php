<div class="mt-8">
    <div class="rounded-xl border bg-white shadow">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold">Lista de precios personalizada</h3>

            <div class="flex items-center gap-2">
                <input
                    type="text"
                    placeholder="Buscar producto (SKU/Nombre)..."
                    wire:model.live.debounce.400ms="search"
                    class="w-64 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                />
                <select wire:model.live="perPage" class="w-28 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="10">10 / pág</option>
                    <option value="25">25 / pág</option>
                    <option value="50">50 / pág</option>
                </select>
                <x-wire-button sm gray outline wire:click="setZeroForEmpty">Vacíos → 0</x-wire-button>
                <x-wire-button sm blue wire:click="save">Guardar</x-wire-button>
            </div>
        </div>

        <div class="p-5 overflow-x-auto">
            <table class="min-w-full divide-y">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold text-gray-700">
                        <th class="px-3 py-2">ID</th>
                        <th class="px-3 py-2">SKU</th>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2 text-right">Base</th>
                        <th class="px-3 py-2 text-right">Lista cliente</th>
                        <th class="px-3 py-2 text-right">Precio personalizado</th>
                        <th class="px-3 py-2 text-right">Efectivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($rows as $p)
                        @php
                            $list = $this->listPrice($p->id);
                            $eff  = $this->effectivePrice((float)$p->precio_base, $list, (int)$p->id);
                        @endphp
                        <tr>
                            <td class="px-3 py-2">{{ $p->id }}</td>
                            <td class="px-3 py-2 font-mono">{{ $p->sku }}</td>
                            <td class="px-3 py-2">{{ $p->nombre }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float)$p->precio_base, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">
                                {{ $list !== null ? number_format($list, 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <input
                                    type="number" step="0.01" min="0"
                                    wire:model.lazy="inputs.{{ $p->id }}"
                                    placeholder="0.00"
                                    class="w-32 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-right font-mono"
                                />
                                <div class="text-[10px] text-gray-500 mt-1 text-right">Vacío ⇒ 0</div>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">
                                {{ number_format($eff, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4">
                {{ $rows->links() }}
            </div>
        </div>

        <div class="px-5 py-4 border-t flex justify-between">
            <div class="text-xs text-gray-500">
                “Guardar” crea/actualiza <code>client_price_overrides</code>. Si un campo está vacío ⇒ 0.
            </div>
            <div class="flex gap-2">
                <x-wire-button gray outline wire:click="$refresh">Refrescar</x-wire-button>
                <x-wire-button blue wire:click="save">Guardar</x-wire-button>
            </div>
        </div>
    </div>
</div>
