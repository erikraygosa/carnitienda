<div>
    {{-- Filtros --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
        <div class="md:col-span-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Buscar folio, serie, cliente..."
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <select wire:model.live="tipoComprobante"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los tipos</option>
                <option value="I">Factura (Ingreso)</option>
                <option value="E">Nota de crédito (Egreso)</option>
                <option value="P">Complemento de pago</option>
                <option value="N">Nómina</option>
            </select>
        </div>
        <div>
            <select wire:model.live="estatus"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los estatus</option>
                <option value="BORRADOR">Borrador</option>
                <option value="TIMBRADA">Timbrada</option>
                <option value="CANCELACION_PENDIENTE">Cancelación pendiente</option>
                <option value="CANCELADA">Cancelada</option>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <input type="date" wire:model.live="fechaDesde"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <input type="date" wire:model.live="fechaHasta"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    {{-- Filtros de pago y fecha --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
        <div>
            <select wire:model.live="formaPago"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todas las formas de pago</option>
                @foreach(\App\Support\SatCatalogs::FORMA_PAGO as $clave => $nombre)
                    <option value="{{ $clave }}">{{ $clave }} — {{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select wire:model.live="metodoPago"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los métodos (PUE / PPD)</option>
                <option value="PUE">PUE — Una sola exhibición</option>
                <option value="PPD">PPD — Parcialidades o diferido</option>
            </select>
        </div>
        <div>
            <select wire:model.live="campoFecha"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    title="Qué fecha usan los filtros 'desde' y 'hasta'">
                <option value="fecha">Filtrar por fecha de elaboración</option>
                <option value="fecha_timbrado">Filtrar por certificación SAT</option>
            </select>
        </div>
        <div class="flex justify-end">
            <a href="{{ route('admin.invoices.export', array_filter($this->filtros(), fn ($v) => $v !== '' && $v !== null)) }}"
               class="inline-flex items-center gap-1.5 h-[38px] px-4 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v2a2 2 0 002 2h14a2 2 0 002-2v-2M7 10V5a2 2 0 012-2h6a2 2 0 012 2v5"/></svg>
                Descargar Excel
            </a>
        </div>
    </div>

    {{-- Totales de lo filtrado --}}
    <div class="mb-3 flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm">
        <span class="text-gray-500"><strong class="text-gray-800">{{ number_format($totales['registros']) }}</strong> registro(s) con estos filtros</span>
        <span class="text-gray-500"><strong class="text-gray-800">{{ number_format($totales['facturas']) }}</strong> factura(s) vigente(s)</span>
        <span class="text-gray-500">Total vigente: <strong class="font-mono text-emerald-700">${{ number_format($totales['neto'], 2) }}</strong>
            <span class="text-xs text-gray-400">(sin canceladas ni borradores; las notas de crédito restan)</span></span>
    </div>

    {{-- Fila inferior filtros --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <select wire:model.live="perPage"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <span class="text-xs text-gray-400">por página</span>
            <span class="text-xs text-gray-400 ml-2">
                (por default: {{ \Carbon\Carbon::parse(now()->startOfMonth())->translatedFormat('F Y') }})
            </span>
        </div>
        <button type="button" wire:click="limpiarFiltros" class="text-xs text-indigo-600 hover:underline">
            Limpiar filtros
        </button>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @php
                        $th = fn($col,$label) => '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100"
                            wire:click="sort(\''.$col.'\')">'.$label.($this->sortBy===$col?($this->sortDir==='asc'?' ↑':' ↓'):'').'</th>';
                    @endphp
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    {!! $th('folio','Folio') !!}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    {!! $th('fecha','Fecha elaboración') !!}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Certificación SAT</th>
                    {!! $th('estatus','Estatus') !!}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Forma de pago</th>
                    {!! $th('total','Total') !!}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @php
                    $tipoLabels = ['I' => 'Factura', 'E' => 'Nota crédito', 'P' => 'Complemento', 'N' => 'Nómina'];
                    $tipoClasses = [
                        'I' => 'bg-indigo-100 text-indigo-700',
                        'E' => 'bg-orange-100 text-orange-700',
                        'P' => 'bg-emerald-100 text-emerald-700',
                        'N' => 'bg-violet-100 text-violet-700',
                    ];
                    $statusClasses = [
                        'BORRADOR'              => 'bg-gray-100 text-gray-700',
                        'TIMBRADA'              => 'bg-emerald-100 text-emerald-700',
                        'CANCELACION_PENDIENTE' => 'bg-yellow-100 text-yellow-700',
                        'CANCELADA'             => 'bg-rose-100 text-rose-700',
                    ];
                @endphp
                @forelse($invoices as $invoice)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $invoice->id }}</td>
                    <td class="px-4 py-3 font-mono text-indigo-700 font-medium">{{ $invoice->serie }}{{ $invoice->folio ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full {{ $tipoClasses[$invoice->tipo_comprobante] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $tipoLabels[$invoice->tipo_comprobante] ?? $invoice->tipo_comprobante }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $invoice->client?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ optional($invoice->fecha)->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">{{ optional($invoice->fecha_timbrado)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full {{ $statusClasses[$invoice->estatus] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $invoice->estatus }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-700">
                        @if($invoice->forma_pago)
                            <div class="font-medium whitespace-nowrap">{{ $invoice->forma_pago }} · {{ \App\Support\SatCatalogs::FORMA_PAGO[$invoice->forma_pago] ?? '' }}</div>
                            <div class="text-gray-400">{{ $invoice->metodo_pago }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 font-mono text-gray-700 whitespace-nowrap" style="white-space:nowrap">
                        ${{ number_format((float) $invoice->total, 2) }} <span class="text-xs text-gray-400">{{ $invoice->moneda ?? 'MXN' }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @include('admin.invoices.partials.actions', ['invoice' => $invoice])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-gray-400">
                        No se encontraron facturas con estos filtros.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $invoices->links() }}
    </div>
</div>
