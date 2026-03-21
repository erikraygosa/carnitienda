<x-admin-layout
    title="Registrar cobro"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Cuentas por cobrar','url'=>route('admin.ar.index')],
        ['name'=>'Registrar cobro'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.ar.index') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Volver
        </a>
        <button form="pay-form"
                class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Guardar cobro
        </button>
    </x-slot>

    <x-wire-card>
        <form id="pay-form"
              action="{{ route('admin.ar-payments.store') }}"
              method="POST"
              class="space-y-5">
            @csrf

            {{-- Cliente --}}
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                @php
                    // Prioridad: 1) error de validación, 2) cliente preseleccionado desde URL
                    $selCliente = old('client_id', $preClientId ?? '');
                @endphp
                <select name="client_id" id="client_id"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                    <option value="">-- Selecciona --</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}"
                            {{ $selCliente === (string)$c->id ? 'selected' : '' }}>
                            {{ $c->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('client_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Fecha y Monto --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="date" name="fecha"
                           value="{{ old('fecha', now()->toDateString()) }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           required>
                    @error('fecha')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm pointer-events-none">$</span>
                        <input type="number" name="amount"
                            min="0.01" step="0.01"
                            value="{{ old('amount') }}"
                            class="w-full pl-8 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                    </div>
                    @error('amount')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Forma de pago --}}
            <div>
                <label for="payment_type_id" class="block text-sm font-medium text-gray-700 mb-1">Forma de pago</label>
                @php $selTipo = old('payment_type_id', ''); @endphp
                <select name="payment_type_id" id="payment_type_id"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                    <option value="">-- Selecciona --</option>
                    @foreach($types as $t)
                        <option value="{{ $t->id }}"
                            {{ $selTipo === (string)$t->id ? 'selected' : '' }}>
                            {{ $t->descripcion ?? $t->name ?? 'Tipo '.$t->id }}
                        </option>
                    @endforeach
                </select>
                @error('payment_type_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Referencia y Notas --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Referencia
                        <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <input type="text" name="reference"
                           value="{{ old('reference') }}"
                           placeholder="Folio, transferencia, cheque..."
                           maxlength="255"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Notas
                        <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea name="notes" rows="1"
                              class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                </div>
            </div>

        </form>
    </x-wire-card>
</x-admin-layout>