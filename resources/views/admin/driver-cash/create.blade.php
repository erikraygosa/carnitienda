{{-- resources/views/admin/driver-cash/create.blade.php --}}
<x-admin-layout
    title="Abrir corte de chofer"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Logística'],
        ['name'=>'Cortes de choferes','url'=>route('admin.driver-cash.index')],
        ['name'=>'Abrir'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.driver-cash.index') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Cancelar
        </a>
        <button form="open-form" class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
        </button>
    </x-slot>

    <x-wire-card>
        <form id="open-form" action="{{ route('admin.driver-cash.store') }}" method="POST" class="space-y-6">
            @csrf

           {{-- Select de Chofer (create.blade.php) --}}
                <div class="space-y-2 w-full">
                    <label for="driver_id" class="block text-sm font-medium text-gray-700">Chofer</label>
                    @php $sel = old('driver_id'); @endphp
                    <select
                        name="driver_id"
                        id="driver_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="">-- Selecciona --</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ (string)$sel === (string)$d->id ? 'selected' : '' }}>
                                {{ $d->nombre }}   {{-- <— AQUÍ estaba el detalle --}}
                            </option>
                        @endforeach
                    </select>
                    @error('driver_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>


            <x-wire-input label="Fecha" name="fecha" type="date" :value="old('fecha', now()->toDateString())" required />
            <x-wire-input label="Saldo inicial" name="saldo_inicial" type="number" step="0.01" :value="old('saldo_inicial', 0)" />
            <x-wire-textarea label="Notas" name="notas">{{ old('notas') }}</x-wire-textarea>
        </form>
    </x-wire-card>
</x-admin-layout>
