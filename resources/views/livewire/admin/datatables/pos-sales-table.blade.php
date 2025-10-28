{{-- resources/views/livewire/admin/datatables/pos-sales-table.blade.php --}}
<div class="space-y-3">
  <input type="text" wire:model.debounce.500ms="search"
         class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
         placeholder="Buscar por folio...">

  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="border-b">
          <th class="p-2 text-left">Folio</th>
          <th class="p-2 text-left">Fecha</th>
          <th class="p-2 text-left">Cliente</th>
          <th class="p-2 text-right">Total</th>
          <th class="p-2 text-left">Método</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr class="border-b">
            <td class="p-2">#{{ $r->id }}</td>
            <td class="p-2">{{ $r->fecha->format('Y-m-d H:i') }}</td>
            <td class="p-2">{{ $r->client->nombre ?? 'Público' }}</td>
            <td class="p-2 text-right">${{ number_format($r->total,2) }}</td>
            <td class="p-2">{{ $r->metodo_pago }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="p-4 text-center text-gray-500">Sin ventas</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>{{ $rows->links() }}</div>
</div>
