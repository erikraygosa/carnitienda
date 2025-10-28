{{-- resources/views/admin/cash/create.blade.php --}}
<x-admin-layout
  title="Abrir caja"
  :breadcrumbs="[
    ['name'=>'Dashboard','url'=>route('admin.dashboard')],
    ['name'=>'POS'],
    ['name'=>'Cajas','url'=>route('admin.cash.index')],
    ['name'=>'Abrir'],
  ]"
>
  <x-slot name="action">
    <a href="{{ route('admin.cash.index') }}"
       class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
       Cancelar
    </a>
    <button form="open-form"
       class="ml-2 inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
       Guardar
    </button>
  </x-slot>

  <x-wire-card>
    <form id="open-form" action="{{ route('admin.cash.store') }}" method="POST" class="space-y-6">
      @csrf

      {{-- Almacén --}}
      <div class="space-y-2 w-full">
        <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Almacén</label>
        @php $sel = old('warehouse_id', auth()->user()->warehouse_id); @endphp
        <select id="warehouse_id" name="warehouse_id"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
          <option value="">-- Selecciona --</option>
          @foreach($warehouses as $w)
            <option value="{{ $w->id }}" {{ (string)$sel===(string)$w->id ? 'selected' : '' }}>
              {{ $w->nombre }}
            </option>
          @endforeach
        </select>
        @error('warehouse_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
      </div>

      <x-wire-input label="Fecha" name="fecha" type="date" :value="old('fecha', now()->toDateString())" required />
      <x-wire-input label="Monto de apertura" name="monto" type="number" step="0.01" :value="old('monto', 0)" />
      <x-wire-textarea label="Notas" name="notas">{{ old('notas') }}</x-wire-textarea>
    </form>
  </x-wire-card>
</x-admin-layout>
