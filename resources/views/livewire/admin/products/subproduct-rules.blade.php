<div class="mt-8 border-t pt-6">
    <h3 class="text-lg font-semibold mb-4">Subproductos (rendimientos)</h3>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        {{-- Subproducto --}}
        <x-wire-select
            name="sub_product_id"
            label="Subproducto"
            wire:model.defer="sub_product_id"
            :options="$subproductOptions"
            :option-label="'name'"
            :option-value="'id'"
            placeholder="Seleccione subproducto"
            searchable
        />

        {{-- % rendimiento --}}
        <x-wire-input
            type="number"
            step="0.001"
            min="0.001"
            max="100"
            name="rendimiento_pct"
            label="Rendimiento (%)"
            wire:model.defer="rendimiento_pct"
            placeholder="0.000"
        />

        {{-- % merma (opcional) --}}
        <x-wire-input
            type="number"
            step="0.001"
            min="0"
            max="100"
            name="merma_porcent"
            label="Merma (%)"
            wire:model.defer="merma_porcent"
            placeholder="0.000"
        />

        <div class="flex items-end gap-2">
            @if($editingId)
                <x-wire-button wire:click="update" violet>Actualizar</x-wire-button>
                <x-wire-button wire:click="$refresh" gray outline>Cancelar</x-wire-button>
            @else
                <x-wire-button wire:click="create" blue>Agregar</x-wire-button>
            @endif
        </div>
    </div>

    {{-- Listado --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-600 border-b">
                    <th class="py-2 pr-4">Subproducto</th>
                    <th class="py-2 pr-4">Rendimiento (%)</th>
                    <th class="py-2 pr-4">Merma (%)</th>
                    <th class="py-2 pr-4 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules as $r)
                    <tr class="border-b">
                        <td class="py-2 pr-4">{{ $r->subproduct?->nombre ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ number_format((float)$r->rendimiento_pct, 3) }}</td>
                        <td class="py-2 pr-4">{{ number_format((float)($r->merma_porcent ?? 0), 3) }}</td>
                        <td class="py-2 pr-0 text-right">
                            <div class="inline-flex gap-2">
                                <x-wire-button xs gray outline wire:click="edit({{ $r->id }})">Editar</x-wire-button>
                                <x-wire-button xs red outline wire:click="delete({{ $r->id }})">Eliminar</x-wire-button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="py-3 text-gray-500" colspan="4">Sin subproductos todavía.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 mt-3">
        Al despezar <strong>{{ $product->nombre }}</strong>, se dará entrada a los subproductos según estos porcentajes en el <strong>almacén principal</strong>.
    </p>
</div>
