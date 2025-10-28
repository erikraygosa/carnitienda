{{-- resources/views/livewire/admin/datatables/cash-registers-table.blade.php --}}
<div class="space-y-3">
  <input type="text"
         wire:model.debounce.500ms="search"
         class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
         placeholder="Buscar por fecha (YYYY-MM-DD)...">

  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="border-b">
          <th class="p-2 text-left">Fecha</th>
          <th class="p-2 text-left">Almacén</th>
          <th class="p-2 text-left">Usuario</th>
          <th class="p-2 text-right">Cierre</th>
          <th class="p-2 text-left">Estatus</th>
          <th class="p-2 text-left">Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr class="border-b">
            <td class="p-2">{{ $r->fecha->format('Y-m-d') }}</td>
            <td class="p-2">{{ $r->warehouse->nombre ?? 'N/D' }}</td>
            <td class="p-2">{{ $r->user->name ?? 'N/D' }}</td>
            <td class="p-2 text-right">${{ number_format($r->monto_cierre,2) }}</td>
            <td class="p-2">{{ $r->estatus }}</td>
            <td class="p-2">
              <x-wire-button href="{{ route('admin.cash.show',$r) }}" blue xs>Ver</x-wire-button>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-4 text-center text-gray-500">Sin datos</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>{{ $rows->links() }}</div>
</div>
