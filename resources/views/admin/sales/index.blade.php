<x-admin-layout
    title="Notas de venta"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Ventas'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.sales.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Nueva nota
        </a>
    </x-slot>

    {{-- ====== Filtros ====== --}}
    <x-wire-card class="mb-4">
        @php
            $q           = request('q');
            $status      = request('status');              // ABIERTA | CERRADA | CANCELADA
            $tipoVenta   = request('tipo_venta');          // CONTADO | CREDITO | CONTRAENTREGA
            $delivery    = request('delivery_type');       // ENVIO | RECOGER
            $dateFrom    = request('from');                // YYYY-MM-DD
            $dateTo      = request('to');                  // YYYY-MM-DD
        @endphp

        <form method="GET" action="{{ route('admin.sales.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            {{-- Búsqueda libre --}}
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                <input type="text" name="q" value="{{ $q }}"
                       class="w-full rounded-md border-gray-300"
                       placeholder="Folio, cliente, producto…">
            </div>

            {{-- Estatus --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Estatus</label>
                <select name="status" class="w-full rounded-md border-gray-300">
                    <option value="">— Todos —</option>
                    @foreach (['ABIERTA'=>'Abierta','CERRADA'=>'Cerrada','CANCELADA'=>'Cancelada'] as $k=>$v)
                        <option value="{{ $k }}" {{ $status===$k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tipo de venta --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de venta</label>
                <select name="tipo_venta" class="w-full rounded-md border-gray-300">
                    <option value="">— Todas —</option>
                    @foreach (['CONTADO'=>'Contado','CREDITO'=>'Crédito','CONTRAENTREGA'=>'Contraentrega'] as $k=>$v)
                        <option value="{{ $k }}" {{ $tipoVenta===$k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tipo de entrega --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Entrega</label>
                <select name="delivery_type" class="w-full rounded-md border-gray-300">
                    <option value="">— Todas —</option>
                    @foreach (['ENVIO'=>'Envío a domicilio','RECOGER'=>'Recoger'] as $k=>$v)
                        <option value="{{ $k }}" {{ $delivery===$k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Rango de fechas --}}
            <div class="md:col-span-2 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
                    <input type="date" name="from" value="{{ $dateFrom }}" class="w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                    <input type="date" name="to" value="{{ $dateTo }}" class="w-full rounded-md border-gray-300">
                </div>
            </div>

            {{-- Botones --}}
            <div class="md:col-span-6 flex items-center gap-2 justify-end">
                <a href="{{ route('admin.sales.index') }}"
                   class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                    Limpiar
                </a>
                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Aplicar filtros
                </button>
            </div>
        </form>

        {{-- Pestañas rápidas por estatus (opcional) --}}
        <div class="mt-3 flex flex-wrap gap-2">
            @php
                $tabs = [
                    ''           => 'Todas',
                    'ABIERTA'    => 'Abiertas',
                    'CERRADA'    => 'Cerradas',
                    'CANCELADA'  => 'Canceladas',
                ];
            @endphp
            @foreach($tabs as $key=>$label)
                @php
                    $isActive = ($status === $key) || ($key==='' && empty($status));
                    $query = array_filter(array_merge(request()->query(), ['status'=>$key ?: null]));
                @endphp
                <a href="{{ route('admin.sales.index', $query) }}"
                   class="px-2.5 py-1 text-xs rounded-md border
                          {{ $isActive ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </x-wire-card>

    {{-- ====== Tabla ====== --}}
    <x-wire-card>
        {{-- Pasa los filtros al componente vía atributos o deja que lea request() --}}
        @livewire('admin.datatables.sales-table', [
            'q'             => $q,
            'status'        => $status,
            'tipo_venta'    => $tipoVenta,
            'delivery_type' => $delivery,
            'from'          => $dateFrom,
            'to'            => $dateTo,
        ])
    </x-wire-card>
</x-admin-layout>

