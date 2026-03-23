<x-admin-layout
    title="Editar producto"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Productos', 'url' => route('admin.products.index')],
        ['name' => 'Editar'],
    ]"
>
    <div class="bg-white rounded-xl p-6 shadow">

        {{-- FORM PRINCIPAL (UPDATE) --}}
        <form id="product-update-form" method="POST"
              action="{{ route('admin.products.update', $product) }}"
              class="space-y-6">
            @csrf
            @method('PUT')

            @include('admin.products.partials._form', ['product' => $product])

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.products.index') }}"
                   class="inline-flex px-4 py-2 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                    Volver
                </a>
                <button form="product-update-form" type="submit"
                        class="inline-flex px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Actualizar
                </button>
            </div>
        </form>

        {{-- ====== SUBPRODUCTOS JS VANILLA ====== --}}
        @if($product->es_compuesto)
        <div class="mt-8 border-t pt-6" id="subproducts-section">
            <h3 class="text-lg font-semibold mb-4">Subproductos (rendimientos)</h3>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subproducto</label>
                    <select id="sp-select"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- seleccionar --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rendimiento (%)</label>
                    <input type="number" id="sp-rendimiento" step="0.001" min="0.001"
                           placeholder="Ej. 85"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Merma (%)</label>
                    <input type="number" id="sp-merma" step="0.001" min="0" max="100"
                           placeholder="0.000"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex gap-2">
                    <button type="button" id="sp-btn-add"
                            class="inline-flex px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Agregar
                    </button>
                    <button type="button" id="sp-btn-cancel"
                            class="hidden inline-flex px-4 py-2 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                        Cancelar
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-600 border-b text-xs uppercase">
                            <th class="py-2 pr-4">Subproducto</th>
                            <th class="py-2 pr-4">Rendimiento (%)</th>
                            <th class="py-2 pr-4">Merma (%)</th>
                            <th class="py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="sp-tbody">
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-gray-500 mt-3">
                Al despezar <strong>{{ $product->nombre }}</strong>, se dará entrada a los
                subproductos según estos porcentajes en el almacén principal.
            </p>
        </div>
        @endif

        {{-- DESACTIVAR --}}
        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="mt-4">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex px-4 py-2 text-sm rounded-md border border-red-300 text-red-600 bg-white hover:bg-red-50">
                Desactivar
            </button>
        </form>

        {{-- DESPIECE --}}
        @if($product->subproductRulesAsParent()->exists())
        <div class="mt-8 border-t pt-6">
            <h3 class="text-lg font-semibold mb-4">Despiece</h3>
            <form method="POST" action="{{ route('admin.products.despiece', $product) }}"
                  class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Cantidad a despezar ({{ $product->unidad }}) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="cantidad" step="0.001" min="0.001"
                           placeholder="0.000" required
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nota (opcional)</label>
                    <input type="text" name="nota" placeholder="Ej. Lote 123"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                            class="inline-flex px-4 py-2 text-sm rounded-md bg-violet-600 text-white hover:bg-violet-700">
                        Procesar despiece
                    </button>
                </div>
            </form>
            <p class="text-xs text-gray-500 mt-2">
                Se usará el <strong>almacén principal</strong> por defecto.
            </p>
        </div>
        @endif

        {{-- ES SUBPRODUCTO DE --}}
        @if($product->parentRulesAsChild()->exists())
        <div class="mt-8 border-t pt-6">
            <h3 class="text-lg font-semibold mb-2">Es subproducto de</h3>
            <ul class="text-sm list-disc ml-5">
                @foreach($product->parentRulesAsChild()->with('parent')->get() as $rule)
                <li>
                    {{ $rule->parent?->nombre ?? 'Producto padre' }}
                    — rendimiento: {{ number_format(($rule->ratio ?? 0) * 100, 2) }}%
                    @if(!is_null($rule->merma_porcent))
                        — merma: {{ number_format($rule->merma_porcent, 2) }}%
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
        @endif

    </div>

    {{-- Toast subproductos --}}
    <div id="sp-toast" style="display:none;position:fixed;bottom:24px;left:24px;padding:8px 16px;border-radius:8px;color:#fff;font-size:14px;z-index:9999"></div>

    @if($product->es_compuesto)
    <script>
    (function(){
        const DATA_URL   = '{{ route('admin.products.subproducts.data',   $product) }}';
        const STORE_URL  = '{{ route('admin.products.subproducts.store',  $product) }}';
        const UPDATE_BASE= '{{ url('admin/products/'.$product->id.'/subproducts') }}';
        const CSRF       = '{{ csrf_token() }}';

        let editingId = null;
        let options   = [];

        const $ = id => document.getElementById(id);

        function showToast(msg, ok = true) {
            const t = $('sp-toast');
            t.textContent   = msg;
            t.style.background = ok ? '#059669' : '#dc2626';
            t.style.display = 'block';
            clearTimeout(t._t);
            t._t = setTimeout(() => t.style.display = 'none', 3000);
        }

        async function load() {
            $('sp-tbody').innerHTML = `<tr><td colspan="4" class="py-4 text-center text-gray-400">Cargando...</td></tr>`;
            const res  = await fetch(DATA_URL, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            options = data.options;
            renderOptions();
            renderTable(data.rules);
        }

        function renderOptions(selected = null) {
            const sel = $('sp-select');
            sel.innerHTML = '<option value="">-- seleccionar --</option>';
            options.forEach(o => {
                const opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = o.nombre;
                if (selected && String(selected) === String(o.id)) opt.selected = true;
                sel.appendChild(opt);
            });
        }

        function renderTable(rules) {
            const tbody = $('sp-tbody');
            if (!rules.length) {
                tbody.innerHTML = `<tr><td colspan="4" class="py-4 text-center text-gray-400">Sin subproductos todavía.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            rules.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'border-b hover:bg-gray-50';
                tr.innerHTML = `
                    <td class="py-2 pr-4 font-medium">${r.nombre}</td>
                    <td class="py-2 pr-4">${r.rendimiento.toFixed(3)}%</td>
                    <td class="py-2 pr-4">${r.merma.toFixed(3)}%</td>
                    <td class="py-2 text-right">
                        <button type="button" data-id="${r.id}" data-pid="${r.sub_product_id}"
                                data-rend="${r.rendimiento}" data-merma="${r.merma}"
                                class="btn-edit inline-flex px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50 mr-1">
                            Editar
                        </button>
                        <button type="button" data-id="${r.id}"
                                class="btn-delete inline-flex px-2 py-1 text-xs rounded border border-red-200 text-red-600 hover:bg-red-50">
                            Eliminar
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', function() {
                    editingId = this.dataset.id;
                    renderOptions(this.dataset.pid);
                    $('sp-rendimiento').value       = this.dataset.rend;
                    $('sp-merma').value             = this.dataset.merma;
                    $('sp-btn-add').textContent     = 'Actualizar';
                    $('sp-btn-cancel').classList.remove('hidden');
                });
            });

            tbody.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', async function() {
                    if (!confirm('¿Eliminar este subproducto?')) return;
                    await fetch(`${UPDATE_BASE}/${this.dataset.id}`, {
                        method:  'DELETE',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    });
                    showToast('Subproducto eliminado');
                    load();
                });
            });
        }

        function resetForm() {
            editingId = null;
            $('sp-select').value        = '';
            $('sp-rendimiento').value   = '';
            $('sp-merma').value         = '';
            $('sp-btn-add').textContent = 'Agregar';
            $('sp-btn-cancel').classList.add('hidden');
        }

        $('sp-btn-add').addEventListener('click', async function() {
            const payload = {
                sub_product_id:  $('sp-select').value,
                rendimiento_pct: $('sp-rendimiento').value,
                merma_porcent:   $('sp-merma').value || 0,
            };

            if (!payload.sub_product_id || !payload.rendimiento_pct) {
                showToast('Selecciona subproducto y rendimiento', false);
                return;
            }

            const url    = editingId ? `${UPDATE_BASE}/${editingId}` : STORE_URL;
            const method = editingId ? 'PUT' : 'POST';

            const res  = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept':       'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.ok) {
                showToast(editingId ? '✓ Actualizado' : '✓ Agregado');
                resetForm();
                load();
            } else {
                showToast('Error al guardar', false);
            }
        });

        $('sp-btn-cancel').addEventListener('click', resetForm);

        load();
    })();
    </script>
    @endif

</x-admin-layout>