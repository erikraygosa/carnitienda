<x-admin-layout
    title="Editar producto"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Productos', 'url' => route('admin.products.index')],
        ['name' => 'Editar'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">

        {{-- FORM PRINCIPAL (UPDATE) --}}
        <form id="product-update-form" method="POST" action="{{ route('admin.products.update', $product) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('admin.products.partials._form', ['product' => $product])

            <div class="flex items-center justify-end gap-3">
                <x-wire-button href="{{ route('admin.products.index') }}" gray outline>
                    Volver
                </x-wire-button>

                <x-wire-button form="product-update-form" type="submit" blue>
                    Actualizar
                </x-wire-button>
            </div>
        </form>
        @livewire('admin.products.subproduct-rules', ['product' => $product])

        {{-- FORM DESACTIVAR (ANTES: eliminar) - Fuera del form principal --}}
        <form id="product-deactivate-form" method="POST" action="{{ route('admin.products.destroy', $product) }}" class="mt-4">
            @csrf
            @method('DELETE')
            <x-wire-button type="submit" red outline>
                Desactivar
            </x-wire-button>
        </form>

        {{-- BLOQUE DESPIECE (solo si este producto es PADRE de subproductos) --}}
        @if($product->subproductRulesAsParent()->exists())
            <div class="mt-8 border-t pt-6">
                <h3 class="text-lg font-semibold mb-4">Despiece</h3>

                <form method="POST" action="{{ route('admin.products.despiece', $product) }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf

                    <x-wire-input
                        type="number"
                        step="0.001"
                        min="0.001"
                        name="cantidad"
                        label="Cantidad a despezar ({{ $product->unidad }})"
                        placeholder="0.000"
                        required
                    />

                    {{-- Si quieres permitir una nota opcional --}}
                    <x-wire-input
                        name="nota"
                        label="Nota (opcional)"
                        placeholder="Ej. Lote 123"
                    />

                    <div class="flex items-end">
                        <x-wire-button type="submit" violet>
                            Procesar despiece
                        </x-wire-button>
                    </div>
                </form>
                <p class="text-xs text-gray-500 mt-2">
                    Se usará el <strong>almacén principal</strong> por defecto.
                </p>
            </div>
        @endif

        {{-- (Opcional) Si el producto es SUBPRODUCTO, mostrar de qué padres puede provenir --}}
        @if($product->parentRulesAsChild()->exists())
            <div class="mt-8 border-t pt-6">
                <h3 class="text-lg font-semibold mb-2">Es subproducto de</h3>
                <ul class="text-sm list-disc ml-5">
                    @foreach($product->parentRulesAsChild()->with('parent')->get() as $rule)
                        <li>
                            {{ $rule->parent?->nombre ?? 'Producto padre' }}
                            — rendimiento: {{ number_format($rule->rendimiento_pct ?? 0, 2) }}%
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    </div>
</x-admin-layout>
