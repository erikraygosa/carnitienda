{{-- resources/views/admin/cash/index.blade.php --}}
<x-admin-layout
  title="Cajas"
  :breadcrumbs="[
    ['name'=>'Dashboard','url'=>route('admin.dashboard')],
    ['name'=>'POS'],
    ['name'=>'Cajas'],
  ]"
>
  <x-slot name="action">
    <x-wire-button href="{{ route('admin.cash.create') }}" blue>
      Abrir caja
    </x-wire-button>
  </x-slot>

  <x-wire-card>
    <livewire:admin.datatables.cash-registers-table />
  </x-wire-card>
</x-admin-layout>

