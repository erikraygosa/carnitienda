<x-admin-layout
    title="Ajuste de inventario"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Stock','url'=>route('admin.stock.index')],
        ['name'=>'Ajuste'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.stock.index') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
        <button form="adj-form" type="submit" class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    @php
        $selWarehouse = (string) old('warehouse_id', request('warehouse_id'));
        $selProduct   = (string) old('product_id', request('product_id'));
        $selTipo      = (string) old('tipo', 'AJUSTE');
    @endphp

    <x-wire-card>
        <form id="adj-form" method="POST" action="{{ route('admin.stock.adjustments.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="space-y-2">
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Almacén</label>
                    <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $selWarehouse===(string)$w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="product_id" class="block text-sm font-medium text-gray-700">Producto</label>
                    <select name="product_id" id="product_id" class="w-full rounded-md border-gray-300" required>
                        <option value="">-- seleccionar --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ $selProduct===(string)$p->id ? 'selected' : '' }}>
                                {{ $p->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select name="tipo" id="tipo" class="w-full rounded-md border-gray-300" required>
                        <option value="IN" {{ $selTipo==='IN' ? 'selected' : '' }}>Entrada</option>
                        <option value="OUT" {{ $selTipo==='OUT' ? 'selected' : '' }}>Salida</option>
                        <option value="AJUSTE" {{ $selTipo==='AJUSTE' ? 'selected' : '' }}>Ajuste</option>
                    </select>
                </div>

                <div>
                    <x-wire-input label="Cantidad" name="cantidad" type="number" min="0.001" step="0.001" value="{{ old('cantidad',1) }}" required />
                </div>

                <div class="md:col-span-4">
                    <x-wire-input label="Motivo (opcional)" name="motivo" type="text" value="{{ old('motivo') }}" />
                </div>
            </div>
        </form>
    </x-wire-card>
</x-admin-layout>
