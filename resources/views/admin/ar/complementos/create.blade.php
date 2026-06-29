<x-admin-layout
    title="Generar Complemento de Pago"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cuentas y cobros','url'=>route('admin.ar.index')],
        ['name'=>'Complementos de Pago','url'=>route('admin.ar.complementos.index')],
        ['name'=>'Nuevo complemento'],
    ]"
>
    <form method="POST" action="{{ route('admin.ar.complementos.store') }}">
        @csrf
        <input type="hidden" name="payment_id" value="{{ $payment->id }}">

        {{-- Datos del cobro --}}
        <x-wire-card class="mb-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">
                Datos del cobro
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 text-xs uppercase">Fecha</span>
                    <p class="font-medium text-gray-800 mt-0.5">
                        {{ \Carbon\Carbon::parse($payment->fecha)->format('d/m/Y') }}
                    </p>
                </div>
                <div>
                    <span class="text-gray-500 text-xs uppercase">Monto</span>
                    <p class="font-mono font-semibold text-gray-800 mt-0.5">
                        ${{ number_format($payment->monto, 2) }}
                    </p>
                </div>
                <div>
                    <span class="text-gray-500 text-xs uppercase">Forma de pago</span>
                    <p class="font-medium text-gray-800 mt-0.5">
                        {{ $payment->paymentType?->descripcion ?? '—' }}
                        @if($payment->paymentType?->clave)
                            <span class="text-gray-400 text-xs">({{ $payment->paymentType->clave }})</span>
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-gray-500 text-xs uppercase">Cliente</span>
                    <p class="font-medium text-gray-800 mt-0.5">{{ $cliente?->nombre ?? '—' }}</p>
                </div>
                @if($payment->referencia)
                <div>
                    <span class="text-gray-500 text-xs uppercase">Referencia</span>
                    <p class="font-medium text-gray-800 mt-0.5 italic">{{ $payment->referencia }}</p>
                </div>
                @endif
                @if($payment->nota)
                <div class="col-span-2">
                    <span class="text-gray-500 text-xs uppercase">Nota</span>
                    <p class="text-gray-700 mt-0.5">{{ $payment->nota }}</p>
                </div>
                @endif
            </div>
        </x-wire-card>

        {{-- Documentos relacionados (DoctoRelacionado SAT) --}}
        <x-wire-card class="mb-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">
                Documentos relacionados
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">UUID Factura</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Folio</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Parcialidad #</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Saldo anterior</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Importe pagado</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Saldo insoluto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($docs as $i => $doc)
                        <tr>
                            <input type="hidden" name="docs[{{ $i }}][related_invoice_id]" value="{{ $doc['related_invoice_id'] }}">
                            <input type="hidden" name="docs[{{ $i }}][num_parcialidad]"    value="{{ $doc['num_parcialidad'] }}">
                            <input type="hidden" name="docs[{{ $i }}][imp_saldo_anterior]" value="{{ $doc['imp_saldo_anterior'] }}">
                            <input type="hidden" name="docs[{{ $i }}][imp_saldo_insoluto]" value="{{ $doc['imp_saldo_insoluto'] }}">
                            <input type="hidden" name="docs[{{ $i }}][imp_pagado]"         value="{{ $doc['imp_pagado'] }}">

                            <td class="px-3 py-2 font-mono text-xs text-gray-500 max-w-[200px] truncate"
                                title="{{ $doc['invoice']->uuid }}">
                                {{ $doc['invoice']->uuid }}
                            </td>
                            <td class="px-3 py-2 font-mono text-indigo-700 font-semibold text-xs">
                                {{ $doc['invoice']->serie }}{{ $doc['invoice']->folio }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-700">
                                {{ $doc['num_parcialidad'] }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-gray-700">
                                ${{ number_format($doc['imp_saldo_anterior'], 2) }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono font-semibold text-emerald-700">
                                ${{ number_format($doc['imp_pagado'], 2) }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono {{ $doc['imp_saldo_insoluto'] > 0 ? 'text-rose-600' : 'text-gray-500' }}">
                                ${{ number_format($doc['imp_saldo_insoluto'], 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-wire-card>

        {{-- Configuración del complemento --}}
        <x-wire-card>
            <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">
                Configuración del CFDI
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                {{-- Serie --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Serie <span class="text-red-500">*</span></label>
                    @if($series->isEmpty())
                        <p class="text-xs text-rose-600">
                            No hay series tipo P activas. Crea una en
                            <a href="{{ route('admin.invoices.index') }}" class="underline">Facturas → Configuración</a>.
                        </p>
                        <input type="hidden" name="serie" value="">
                    @else
                        <select name="serie" required
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($series as $s)
                                <option value="{{ $s->serie }}">
                                    {{ $s->serie }} — siguiente folio: {{ $s->folio_actual + 1 }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>

                {{-- Fecha --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fecha del CFDI <span class="text-red-500">*</span></label>
                    <input type="date" name="fecha" required
                           value="{{ old('fecha', now()->format('Y-m-d')) }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Lugar de expedición --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CP expedición <span class="text-red-500">*</span></label>
                    <input type="text" name="lugar_expedicion" required maxlength="10"
                           value="{{ old('lugar_expedicion', $emisorDefaults['lugar_expedicion']) }}"
                           placeholder="Ej. 64000"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Régimen fiscal emisor --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Régimen fiscal emisor <span class="text-red-500">*</span></label>
                    <input type="text" name="regimen_fiscal_emisor" required maxlength="3"
                           value="{{ old('regimen_fiscal_emisor', $emisorDefaults['regimen_fiscal_emisor']) }}"
                           placeholder="Ej. 601"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Régimen fiscal receptor --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Régimen fiscal receptor <span class="text-red-500">*</span></label>
                    <input type="text" name="regimen_fiscal_receptor" required maxlength="3"
                           value="{{ old('regimen_fiscal_receptor', $cliente?->regimen_fiscal ?? '616') }}"
                           placeholder="Ej. 616"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            @if($errors->any())
            <div class="mt-4 p-3 bg-rose-50 rounded-md text-sm text-rose-700">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="mt-6 flex items-center gap-3">
                <button type="submit"
                        class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700 font-medium">
                    <i class="fa-solid fa-file-circle-plus mr-1.5"></i>
                    Crear borrador
                </button>
                <a href="{{ route('admin.ar.complementos.index') }}"
                   class="px-4 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 text-gray-600">
                    Cancelar
                </a>
            </div>
        </x-wire-card>
    </form>
</x-admin-layout>
