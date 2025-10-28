{{-- resources/views/admin/pos/create.blade.php --}}
<x-admin-layout
  title="Punto de venta"
  :breadcrumbs="[
    ['name'=>'Dashboard','url'=>route('admin.dashboard')],
    ['name'=>'POS'],
    ['name'=>'Venta'],
  ]"
>
  <x-slot name="action">
    <x-wire-button href="{{ route('admin.cash.show',$reg) }}" gray>Ver caja</x-wire-button>
  </x-slot>

  <x-wire-card>
    <form id="pos-form" action="{{ route('admin.pos.store') }}" method="POST" class="space-y-6">
      @csrf
      <input type="hidden" name="cash_register_id" value="{{ $reg->id }}">

      {{-- Encabezado: Cliente + Fecha --}}
      <div class="grid md:grid-cols-3 gap-4">
        <div class="space-y-2">
          <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
          @php $sel = old('client_id'); @endphp
          <select id="client_id" name="client_id"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Público en general</option>
            @foreach($clients as $c)
              <option value="{{ $c->id }}" {{ (string)$sel===(string)$c->id ? 'selected' : '' }}>
                {{ $c->nombre }}
              </option>
            @endforeach
          </select>
          @error('client_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
          <x-wire-input label="Fecha" name="fecha" type="datetime-local"
                        :value="old('fecha', now()->format('Y-m-d\TH:i'))" required />
          @error('fecha') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
      </div>

      {{-- PARTIDAS --}}
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">Partidas</h3>
          <button type="button" id="btn-add-row"
                  class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            + Agregar producto
          </button>
        </div>

        <div class="overflow-auto">
          <table class="min-w-full text-sm" id="items-table">
            <thead>
              <tr class="border-b">
                <th class="p-2 text-left w-[36%]">Producto</th>
                <th class="p-2 text-right w-[10%]">Cant.</th>
                <th class="p-2 text-right w-[14%]">Precio</th>
                <th class="p-2 text-right w-[12%]">Desc.</th>
                <th class="p-2 text-right w-[12%]">Imp.</th>
                <th class="p-2 text-right w-[14%]">Importe</th>
                <th class="p-2 text-center w-[6%]">–</th>
              </tr>
            </thead>
            <tbody id="items-body">
              {{-- Fila inicial --}}
              <tr class="border-b item-row">
                <td class="p-2">
                  <select name="items[0][product_id]" class="w-full rounded-md border-gray-300">
                    @foreach($products as $p)
                      <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                  </select>
                </td>
                <td class="p-2">
                  <input name="items[0][cantidad]" type="number" step="0.001" value="1" class="w-full text-right rounded-md border-gray-300 item-cant">
                </td>
                <td class="p-2">
                  <input name="items[0][precio_unitario]" type="number" step="0.01" value="0" class="w-full text-right rounded-md border-gray-300 item-precio">
                </td>
                <td class="p-2">
                  <input name="items[0][descuento]" type="number" step="0.01" value="0" class="w-full text-right rounded-md border-gray-300 item-desc">
                </td>
                <td class="p-2">
                  <input name="items[0][impuestos]" type="number" step="0.01" value="0" class="w-full text-right rounded-md border-gray-300 item-imp">
                </td>
                <td class="p-2 text-right">
                  <span class="item-importe">0.00</span>
                </td>
                <td class="p-2 text-center">
                  <button type="button" class="btn-del-row text-red-600 hover:underline">Eliminar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        @error('items') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
      </div>

      {{-- RESUMEN & COBRO --}}
      <div class="grid md:grid-cols-2 gap-6">
        {{-- Resumen --}}
        <x-wire-card>
          <h3 class="font-semibold mb-3">Resumen</h3>
          <div class="space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-gray-600">Subtotal</span>
              <span id="sum-subtotal">$0.00</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-600">Descuento</span>
              <span id="sum-descuento">$0.00</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-600">Impuestos</span>
              <span id="sum-impuestos">$0.00</span>
            </div>
            <div class="flex items-center justify-between text-lg font-semibold pt-1 border-t">
              <span>Total</span>
              <span id="sum-total">$0.00</span>
            </div>
          </div>
        </x-wire-card>

        {{-- Cobro --}}
        <x-wire-card>
          <h3 class="font-semibold mb-3">Cobro</h3>

          <div class="space-y-2">
            <label for="metodo_pago" class="block text-sm font-medium text-gray-700">Método de pago</label>
            @php $mp = old('metodo_pago','EFECTIVO'); @endphp
            <select id="metodo_pago" name="metodo_pago"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
              @foreach(['EFECTIVO','TARJETA','TRANSFERENCIA','MIXTO','OTRO'] as $opt)
                <option value="{{ $opt }}" {{ $mp===$opt ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
            @error('metodo_pago') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
          </div>

          <div class="grid grid-cols-2 gap-3 mt-2">
            <div>
              <x-wire-input label="Efectivo recibido" name="efectivo" type="number" step="0.01" :value="old('efectivo', 0)" />
              @error('efectivo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
              <x-wire-input label="Cambio" name="cambio" type="number" step="0.01" :value="old('cambio', 0)" />
              @error('cambio') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
          </div>

          <x-wire-input class="mt-3" label="Referencia" name="referencia" type="text" :value="old('referencia')" />
        </x-wire-card>
      </div>

      {{-- Acciones --}}
      <div class="pt-2 flex items-center gap-2">
        <button type="submit"
                class="inline-flex items-center px-4 py-2 text-sm rounded-md bg-indigo-600 text-white">
          Cobrar
        </button>
      </div>
    </form>
  </x-wire-card>

  @push('js')
  <script>
    (function(){
      const body = document.getElementById('items-body');
      const addBtn = document.getElementById('btn-add-row');

      const money = n => '$' + (Number(n||0).toFixed(2));
      const toNum = v => parseFloat(v || 0);

      function renumber() {
        // renombra los name="items[i][campo]" al índice correcto
        [...body.querySelectorAll('tr.item-row')].forEach((tr, idx) => {
          tr.querySelectorAll('select, input').forEach(inp => {
            const name = inp.getAttribute('name');
            if (!name) return;
            const newName = name.replace(/items\[\d+\]/, 'items['+idx+']');
            inp.setAttribute('name', newName);
          });
        });
      }

      function lineImporte(tr){
        const cant   = toNum(tr.querySelector('.item-cant').value);
        const precio = toNum(tr.querySelector('.item-precio').value);
        const desc   = toNum(tr.querySelector('.item-desc').value);
        const imp    = toNum(tr.querySelector('.item-imp').value);
        const importe = (cant * precio) - desc + imp;
        tr.querySelector('.item-importe').textContent = (importe).toFixed(2);
        return {subtotal: cant*precio, descuento: desc, impuestos: imp, importe};
      }

      function recalc(){
        let sub=0, des=0, imp=0, tot=0;
        body.querySelectorAll('tr.item-row').forEach(tr=>{
          const r = lineImporte(tr);
          sub += r.subtotal; des += r.descuento; imp += r.impuestos; tot += r.importe;
        });
        document.getElementById('sum-subtotal').textContent = money(sub);
        document.getElementById('sum-descuento').textContent = money(des);
        document.getElementById('sum-impuestos').textContent = money(imp);
        document.getElementById('sum-total').textContent = money(tot);

        // Calcula cambio en tiempo real si hay efectivo
        const efectivoInp = document.querySelector('input[name="efectivo"]');
        const cambioInp   = document.querySelector('input[name="cambio"]');
        const efectivo = toNum(efectivoInp.value);
        const cambio = Math.max(0, efectivo - tot);
        cambioInp.value = cambio.toFixed(2);
      }

      // listeners para inputs existentes
      function bindRow(tr){
        tr.querySelectorAll('.item-cant,.item-precio,.item-desc,.item-imp').forEach(inp=>{
          inp.addEventListener('input', recalc);
        });
        tr.querySelector('.btn-del-row')?.addEventListener('click', ()=>{
          if (body.querySelectorAll('tr.item-row').length === 1) {
            // limpia en vez de borrar la última fila
            tr.querySelectorAll('input').forEach(i=> i.value = (i.classList.contains('item-cant')?1:0));
            recalc();
          } else {
            tr.remove(); renumber(); recalc();
          }
        });
      }

      bindRow(body.querySelector('tr.item-row'));
      recalc();

      addBtn.addEventListener('click', ()=>{
        const idx = body.querySelectorAll('tr.item-row').length;
        const template = `
          <tr class="border-b item-row">
            <td class="p-2">
              <select name="items[${idx}][product_id]" class="w-full rounded-md border-gray-300">
                @foreach($products as $p)
                  <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                @endforeach
              </select>
            </td>
            <td class="p-2">
              <input name="items[${idx}][cantidad]" type="number" step="0.001" value="1" class="w-full text-right rounded-md border-gray-300 item-cant">
            </td>
            <td class="p-2">
              <input name="items[${idx}][precio_unitario]" type="number" step="0.01" value="0" class="w-full text-right rounded-md border-gray-300 item-precio">
            </td>
            <td class="p-2">
              <input name="items[${idx}][descuento]" type="number" step="0.01" value="0" class="w-full text-right rounded-md border-gray-300 item-desc">
            </td>
            <td class="p-2">
              <input name="items[${idx}][impuestos]" type="number" step="0.01" value="0" class="w-full text-right rounded-md border-gray-300 item-imp">
            </td>
            <td class="p-2 text-right"><span class="item-importe">0.00</span></td>
            <td class="p-2 text-center"><button type="button" class="btn-del-row text-red-600 hover:underline">Eliminar</button></td>
          </tr>`;
        body.insertAdjacentHTML('beforeend', template);
        bindRow(body.lastElementChild);
        recalc();
      });

      // recalcular cambio cuando cambie efectivo manualmente
      document.querySelector('input[name="efectivo"]').addEventListener('input', recalc);
    })();
  </script>
  @endpush
</x-admin-layout>
