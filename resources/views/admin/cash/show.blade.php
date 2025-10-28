{{-- resources/views/admin/cash/show.blade.php --}}
<x-admin-layout
  :title="'Caja #'.$register->id"
  :breadcrumbs="[
    ['name'=>'Dashboard','url'=>route('admin.dashboard')],
    ['name'=>'POS'],
    ['name'=>'Cajas','url'=>route('admin.cash.index')],
    ['name'=>'Detalle'],
  ]"
>
  <x-slot name="action">
    {{-- Botón imprimir ticket 80mm (nueva pestaña) --}}
    <x-wire-button href="{{ route('admin.cash.ticket', $register) }}" target="_blank" rel="noopener noreferrer" outline class="no-print">
      Imprimir ticket
    </x-wire-button>

    @if($register->estatus === 'ABIERTO')
      <form action="{{ route('admin.cash.close',$register) }}" method="POST" class="inline close-form">
        @csrf
        <x-wire-button type="submit" red>Cerrar caja</x-wire-button>
      </form>
    @endif
  </x-slot>

  <div class="grid md:grid-cols-5 gap-4">
    <x-wire-card>
      <div class="text-sm text-gray-500">Almacén</div>
      <div class="text-lg font-semibold">{{ $register->warehouse->nombre ?? 'N/D' }}</div>
    </x-wire-card>

    <x-wire-card>
      <div class="text-sm text-gray-500">Apertura</div>
      <div class="text-lg font-semibold">${{ number_format($register->monto_apertura,2) }}</div>
    </x-wire-card>

    <x-wire-card>
      <div class="text-sm text-gray-500">Ingresos / Egresos</div>
      <div class="text-lg font-semibold">
        ${{ number_format($register->ingresos,2) }} / ${{ number_format($register->egresos,2) }}
      </div>
    </x-wire-card>

    <x-wire-card>
      <div class="text-sm text-gray-500">Ventas efectivo</div>
      <div class="text-lg font-semibold">${{ number_format($register->ventas_efectivo,2) }}</div>
    </x-wire-card>

    {{-- NUEVO: Saldo final --}}
    <x-wire-card>
      <div class="text-sm text-gray-500">Saldo final</div>
      <div class="text-lg font-semibold">
        ${{ number_format($register->monto_cierre,2) }}
      </div>
      <div class="text-xs text-gray-500 mt-1">
        Fórmula: apertura + ingresos - egresos + ventas efectivo
      </div>
    </x-wire-card>
  </div>

  <div class="grid md:grid-cols-3 gap-4 mt-6">
    <x-wire-card class="md:col-span-2">
      <h3 class="font-semibold mb-3">Movimientos</h3>
      <div class="overflow-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="p-2 text-left">Fecha</th>
              <th class="p-2 text-left">Tipo</th>
              <th class="p-2 text-left">Concepto</th>
              <th class="p-2 text-right">Monto</th>
            </tr>
          </thead>
          <tbody>
            @forelse($register->movements()->latest()->get() as $m)
              <tr class="border-b">
                <td class="p-2">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                <td class="p-2">{{ $m->tipo }}</td>
                <td class="p-2">{{ $m->concepto }}</td>
                <td class="p-2 text-right">{{ number_format($m->monto,2) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="p-4 text-center text-gray-500">Sin movimientos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </x-wire-card>

    <x-wire-card>
      <h3 class="font-semibold mb-3">Ingreso/Egreso</h3>
      @if($register->estatus === 'ABIERTO')
        <form action="{{ route('admin.cash.movement.store',$register) }}" method="POST" class="space-y-4">
          @csrf

          <div class="space-y-2 w-full">
            <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
            @php $sel = old('tipo','INGRESO'); @endphp
            <select id="tipo" name="tipo"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
              <option value="INGRESO" {{ $sel==='INGRESO'?'selected':'' }}>Ingreso</option>
              <option value="EGRESO"  {{ $sel==='EGRESO'?'selected':'' }}>Egreso</option>
            </select>
          </div>

          <x-wire-input label="Monto" name="monto" type="number" step="0.01" required />
          <x-wire-input label="Concepto" name="concepto" type="text" />

          <button class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Guardar
          </button>
        </form>
      @else
        <div class="text-sm text-gray-500">La caja está cerrada.</div>
      @endif
    </x-wire-card>
  </div>

  @push('js')
  <script>
    // Confirmar cierre con SweetAlert
    document.querySelectorAll('.close-form')?.forEach(form=>{
      form.addEventListener('submit', e=>{
        e.preventDefault();
        Swal.fire({
          title:'¿Cerrar caja?',
          text:'Esta acción no se puede deshacer.',
          icon:'question',
          showCancelButton:true,
          confirmButtonText:'Sí, cerrar',
          cancelButtonText:'Cancelar'
        }).then(r=>{ if(r.isConfirmed) form.submit(); });
      });
    });
  </script>
  @endpush
</x-admin-layout>
