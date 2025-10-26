{{-- resources/views/admin/ar/payments/create.blade.php --}}
<x-admin-layout
    title="Registrar cobro"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Finanzas'],
        ['name'=>'Cobros','url'=>route('admin.ar-payments.create')],
        ['name'=>'Nuevo'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.ar.index') }}" class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">Volver</a>
        <button form="pay-form" class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">Guardar</button>
    </x-slot>

    <x-wire-card>
        <form id="pay-form" action="{{ route('admin.ar-payments.store') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Cliente (select manual) --}}
            <div class="space-y-2 w-full">
                <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                @php $sel = old('client_id'); @endphp
                <select name="client_id" id="client_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">-- Selecciona --</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ (string)$sel === (string)$c->id ? 'selected' : '' }}>
                            {{ $c->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('client_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-wire-input label="Fecha" name="fecha" type="date" :value="old('fecha', now()->toDateString())" required />

            <x-wire-input label="Monto" name="amount" type="number" step="0.01" :value="old('amount')" required />

            {{-- Tipo de pago (select manual) --}}
            <div class="space-y-2 w-full">
                <label for="payment_type_id" class="block text-sm font-medium text-gray-700">Forma de pago</label>
                @php $tipo = old('payment_type_id'); @endphp
                <select name="payment_type_id" id="payment_type_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">-- Selecciona --</option>
                    @foreach($types as $t)
                        <option value="{{ $t->id }}" {{ (string)$tipo === (string)$t->id ? 'selected' : '' }}>
                            {{ $t->name ?? $t->descripcion ?? ('Tipo '.$t->id) }}
                        </option>
                    @endforeach
                </select>
                @error('payment_type_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-wire-input label="Referencia" name="reference" type="text" :value="old('reference')" placeholder="Folio/nota/transferencia" />
            <x-wire-textarea label="Notas" name="notes">{{ old('notes') }}</x-wire-textarea>
        </form>
    </x-wire-card>
</x-admin-layout>
