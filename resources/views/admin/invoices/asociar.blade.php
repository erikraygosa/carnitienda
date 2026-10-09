<x-admin-layout
    title="Asociar pedidos a la factura"
    :breadcrumbs="[['name'=>'Dashboard','url'=>route('admin.dashboard')],['name'=>'Facturas','url'=>route('admin.invoices.index')],['name'=>'Asociar pedidos']]"
>
    @php
        $folioFactura = ($invoice->serie ?? '') . ($invoice->folio ?? $invoice->id);
        $inicialPed   = $invoice->salesOrders->map(fn ($o) => ['tipo'=>'pedido','id'=>$o->id,'folio'=>$o->folio,'cliente'=>$o->client?->nombre ?? '—','total'=>(float)$o->total])->values();
        $inicialNota  = $invoice->sales->map(fn ($n) => ['tipo'=>'nota','id'=>$n->id,'folio'=>$n->folio,'cliente'=>$n->client?->nombre ?? 'PUBLICO EN GENERAL','total'=>(float)$n->total])->values();
    @endphp

    <x-wire-card>
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <div class="text-xs text-gray-500">Factura</div>
                <div class="text-lg font-semibold text-gray-800">{{ $folioFactura }}
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $invoice->estatus }}</span>
                </div>
                <div class="text-sm text-gray-600">{{ $invoice->client?->nombre ?? '—' }}</div>
            </div>
            <div class="text-right">
                <div class="text-xs text-gray-500">Total de la factura</div>
                <div class="text-xl font-bold text-gray-800">${{ number_format((float) $invoice->total, 2) }}</div>
            </div>
        </div>

        <p class="text-sm text-gray-500 mb-4">
            Marca los pedidos o notas de venta que cubre esta factura. Una factura puede cubrir varios, y un pedido puede
            tener varias facturas (por ejemplo, un pedido de $5,000 en efectivo facturado en partes). En Pedidos se verá
            a qué factura(s) pertenece cada uno.
        </p>

        <form method="POST" action="{{ route('admin.invoices.asociar.guardar', $invoice) }}" id="form-asociar">
            @csrf
            <div id="hidden-sel"></div>

            {{-- Seleccionados --}}
            <div class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-indigo-800">Asociados a esta factura (<span id="sel-count">0</span>)</span>
                    <span class="text-xs text-gray-600">Suma de documentos: <b id="sel-total">$0.00</b> · Factura: <b>${{ number_format((float) $invoice->total, 2) }}</b></span>
                </div>
                <div id="sel-lista" class="flex flex-wrap gap-2"></div>
            </div>

            {{-- Buscador --}}
            <div class="flex flex-wrap items-center gap-3 mb-3">
                <input type="text" id="as-search" placeholder="Buscar folio o cliente..."
                       class="w-64 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <input type="date" id="as-desde" value="{{ now()->subDays(60)->toDateString() }}" title="Desde"
                       class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" id="as-todos" class="rounded border-gray-300 text-indigo-600">
                    De todos los clientes
                </label>
                <span id="as-count" class="text-xs text-gray-500 ml-auto"></span>
            </div>

            <div class="overflow-auto max-h-[55vh] rounded-lg border border-gray-200">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 w-8"></th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Documento</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Otras facturas</th>
                        </tr>
                    </thead>
                    <tbody id="as-tbody" class="divide-y divide-gray-100 bg-white">
                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <a href="{{ route('admin.invoices.index') }}" class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Guardar asociación</button>
            </div>
        </form>
    </x-wire-card>

    <script>
    (function () {
        const DATA_URL = @json(route('admin.invoices.asociar.data', $invoice));
        const $ = id => document.getElementById(id);
        const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const money = v => '$' + Number(v || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // Seleccionados: clave "pedido:ID" / "nota:ID" -> datos para mostrar
        const sel = new Map();
        @json($inicialPed).concat(@json($inicialNota)).forEach(d => sel.set(d.tipo + ':' + d.id, d));

        function pintarSeleccion() {
            $('sel-count').textContent = sel.size;
            let total = 0;
            $('sel-lista').innerHTML = [...sel.values()].map(d => {
                total += d.total;
                return `<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white border border-indigo-200 text-xs">
                    <b class="font-mono">${esc(d.folio)}</b> <span class="text-gray-500">${money(d.total)}</span>
                    <button type="button" data-quitar="${d.tipo}:${d.id}" class="text-gray-400 hover:text-red-600">✕</button></span>`;
            }).join('') || '<span class="text-xs text-gray-500">Ninguno todavía.</span>';
            $('sel-total').textContent = money(total);
            $('hidden-sel').innerHTML = [...sel.values()].map(d =>
                `<input type="hidden" name="${d.tipo === 'pedido' ? 'orders[]' : 'sales[]'}" value="${d.id}">`).join('');
        }

        let seq = 0;
        async function cargar() {
            const my = ++seq;
            const p = new URLSearchParams({ search: $('as-search').value.trim(), desde: $('as-desde').value, todos: $('as-todos').checked ? 1 : 0 });
            $('as-tbody').innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">Cargando...</td></tr>';
            const r = await fetch(DATA_URL + '?' + p, { headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            if (my !== seq) return;
            $('as-count').textContent = d.rows.length + ' documento(s)';
            $('as-tbody').innerHTML = d.rows.map(o => {
                const k = o.tipo + ':' + o.id;
                return `<tr class="hover:bg-gray-50">
                    <td class="px-3 py-2"><input type="checkbox" data-k="${k}" ${sel.has(k) ? 'checked' : ''} class="rounded border-gray-300 text-indigo-600"></td>
                    <td class="px-3 py-2 font-mono text-indigo-700">${esc(o.folio)} ${o.tipo === 'nota' ? '<span class="ml-1 px-1.5 py-0.5 rounded bg-sky-100 text-sky-700 text-[10px]">Nota de venta</span>' : ''}</td>
                    <td class="px-3 py-2 text-gray-700">${esc(o.cliente)}</td>
                    <td class="px-3 py-2 text-gray-600 text-xs">${esc(o.fecha ?? '—')}</td>
                    <td class="px-3 py-2 text-right font-mono">${money(o.total)}</td>
                    <td class="px-3 py-2 text-xs ${o.otras.length ? 'text-amber-700' : 'text-gray-300'}">${o.otras.length ? esc(o.otras.join(', ')) : '—'}</td>
                </tr>`;
            }).join('') || '<tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">Sin resultados.</td></tr>';
            // guarda los datos de cada fila para poder seleccionarla
            d.rows.forEach(o => { rowsCache[o.tipo + ':' + o.id] = o; });
        }
        const rowsCache = {};

        $('as-tbody').addEventListener('change', e => {
            const k = e.target.dataset.k; if (!k) return;
            if (e.target.checked) { const o = rowsCache[k]; sel.set(k, { tipo: o.tipo, id: o.id, folio: o.folio, cliente: o.cliente, total: o.total }); }
            else sel.delete(k);
            pintarSeleccion();
        });
        $('sel-lista').addEventListener('click', e => {
            const k = e.target.dataset.quitar; if (!k) return;
            sel.delete(k); pintarSeleccion();
            const cb = document.querySelector(`input[data-k="${k}"]`); if (cb) cb.checked = false;
        });

        let t; $('as-search').addEventListener('input', () => { clearTimeout(t); t = setTimeout(cargar, 300); });
        $('as-desde').addEventListener('change', cargar);
        $('as-todos').addEventListener('change', cargar);

        pintarSeleccion();
        cargar();
    })();
    </script>
</x-admin-layout>
