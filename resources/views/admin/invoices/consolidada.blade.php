<x-admin-layout
    title="Facturar varios pedidos"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas','url'=>route('admin.invoices.index')],
        ['name'=>'Facturar varios pedidos'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border">Regresar</a>
    </x-slot>

    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
        Selecciona varios pedidos <strong>sin facturar</strong> para juntarlos en una sola factura. Las partidas se
        combinan por producto (sumando cantidades). Si todos los pedidos son del mismo cliente puedes facturar con
        sus datos reales; si no, se factura a "Público en general".
    </div>

    <x-wire-card>
        {{-- Filtros --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Cliente</label>
                <select id="fc-cliente" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los clientes</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Buscar folio</label>
                <input type="text" id="fc-buscar" placeholder="Folio..."
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Fecha desde</label>
                <input type="date" id="fc-desde" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Fecha hasta</label>
                <input type="date" id="fc-hasta" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        {{-- Lista --}}
        <div class="overflow-auto border rounded max-h-[28rem]">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b sticky top-0">
                    <tr>
                        <th class="p-2 w-8"><input type="checkbox" id="fc-check-all" class="rounded border-gray-300"></th>
                        <th class="p-2 text-left">Folio</th>
                        <th class="p-2 text-left">Cliente</th>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody id="fc-tbody">
                    <tr><td colspan="5" class="p-4 text-center text-gray-400">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        {{-- Barra de selección + generar --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 px-4 py-3 rounded-lg border border-gray-200 bg-gray-50">
            <div class="text-sm text-gray-600">
                <span id="fc-count" class="font-semibold text-gray-800">0</span> pedido(s) seleccionado(s) —
                Total: <span id="fc-total" class="font-mono font-bold text-indigo-700">$0.00</span>
            </div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                    <input type="radio" name="fc-modo" value="cliente" class="text-indigo-600">
                    Datos del cliente <span id="fc-cliente-detectado" class="text-xs text-gray-400"></span>
                </label>
                <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                    <input type="radio" name="fc-modo" value="publico_general" class="text-indigo-600" checked>
                    Público en general
                </label>
                <button type="button" id="fc-generar"
                        class="inline-flex items-center px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed"
                        disabled>
                    Generar factura consolidada
                </button>
            </div>
        </div>
    </x-wire-card>

    <form id="fc-form" method="POST" action="{{ route('admin.invoices.consolidada.preparar') }}" class="hidden">
        @csrf
        <input type="hidden" name="modo_receptor" id="fc-form-modo">
        <div id="fc-form-orders"></div>
    </form>

    <script>
    (function () {
        const DATA_URL = '{{ route('admin.invoices.consolidada.data') }}';
        const $ = id => document.getElementById(id);

        let rows = [];
        let seleccionadas = new Map(); // id -> {total, client_id}

        const fmtMoney = v => '$' + parseFloat(v || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        async function load() {
            $('fc-tbody').innerHTML = `<tr><td colspan="5" class="p-4 text-center text-gray-400">Cargando...</td></tr>`;
            const params = new URLSearchParams({
                client_id:   $('fc-cliente').value,
                search:      $('fc-buscar').value,
                fecha_desde: $('fc-desde').value,
                fecha_hasta: $('fc-hasta').value,
            });
            try {
                const res  = await fetch(`${DATA_URL}?${params}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                rows = data.rows || [];
                render();
            } catch (e) {
                $('fc-tbody').innerHTML = `<tr><td colspan="5" class="p-4 text-center text-red-400">Error cargando pedidos.</td></tr>`;
            }
        }

        function render() {
            if (!rows.length) {
                $('fc-tbody').innerHTML = `<tr><td colspan="5" class="p-4 text-center text-gray-400">Sin pedidos sin facturar para estos filtros.</td></tr>`;
                return;
            }
            $('fc-tbody').innerHTML = rows.map(r => {
                const marcada = seleccionadas.has(r.id);
                return `
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2">
                        <input type="checkbox" class="fc-check rounded border-gray-300"
                               ${marcada ? 'checked' : ''}
                               data-id="${r.id}" data-total="${r.total}" data-client-id="${r.client_id ?? ''}"
                               onchange="window.__fcToggle(this)">
                    </td>
                    <td class="p-2 font-mono text-xs text-indigo-700">${r.folio}</td>
                    <td class="p-2 text-gray-700">${r.cliente}</td>
                    <td class="p-2 text-xs text-gray-400">${r.fecha ?? '—'}</td>
                    <td class="p-2 text-right font-mono">${fmtMoney(r.total)}</td>
                </tr>`;
            }).join('');
        }

        window.__fcToggle = function (chk) {
            const id = parseInt(chk.dataset.id, 10);
            if (chk.checked) {
                seleccionadas.set(id, { total: parseFloat(chk.dataset.total || 0), client_id: chk.dataset.clientId || null });
            } else {
                seleccionadas.delete(id);
            }
            actualizarBarra();
        };

        function actualizarBarra() {
            let total = 0;
            const clientIds = new Set();
            seleccionadas.forEach(v => { total += v.total; clientIds.add(v.client_id || ''); });

            $('fc-count').textContent = seleccionadas.size;
            $('fc-total').textContent = fmtMoney(total);
            $('fc-generar').disabled  = seleccionadas.size === 0;

            const radioCliente = document.querySelector('input[name="fc-modo"][value="cliente"]');
            const soloUnCliente = clientIds.size === 1 && [...clientIds][0] !== '';
            radioCliente.disabled = !soloUnCliente;
            if (!soloUnCliente && radioCliente.checked) {
                document.querySelector('input[name="fc-modo"][value="publico_general"]').checked = true;
            }
            $('fc-cliente-detectado').textContent = soloUnCliente
                ? '(' + (rows.find(r => String(r.client_id) === [...clientIds][0])?.cliente ?? '') + ')'
                : (clientIds.size > 1 ? '— hay más de un cliente' : '');
        }

        $('fc-check-all').addEventListener('change', function () {
            document.querySelectorAll('.fc-check').forEach(chk => {
                chk.checked = this.checked;
                window.__fcToggle(chk);
            });
        });

        window.__fcLoad = load; // select2 lo dispara directo (ver el bloque de scripts al final del archivo)
        $('fc-cliente').addEventListener('change', load);
        $('fc-buscar').addEventListener('input', function () { clearTimeout(this._t); this._t = setTimeout(load, 350); });
        $('fc-desde').addEventListener('change', load);
        $('fc-hasta').addEventListener('change', load);

        $('fc-generar').addEventListener('click', function () {
            if (seleccionadas.size === 0) return;
            const modo = document.querySelector('input[name="fc-modo"]:checked').value;

            const ordersWrap = $('fc-form-orders');
            ordersWrap.innerHTML = '';
            seleccionadas.forEach((v, id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order_ids[]';
                input.value = id;
                ordersWrap.appendChild(input);
            });
            $('fc-form-modo').value = modo;
            $('fc-form').submit();
        });

        load();
    })();
    </script>

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
.select2-container .select2-selection--single { height: 38px !important; border-color: #d1d5db !important; border-radius: 6px !important; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 0.875rem; color: #374151; padding-left: 10px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
.select2-dropdown { border-color: #d1d5db; border-radius: 6px; font-size: 0.875rem; }
.select2-container--default .select2-search--dropdown .select2-search__field { border-color: #d1d5db; border-radius: 4px; padding: 4px 8px; }
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function () {
    $('#fc-cliente').select2({
        placeholder: 'Todos los clientes',
        allowClear: true,
        width: '100%',
        language: { searching: function() { return 'Buscando...'; }, noResults: function() { return 'Sin resultados'; } },
    }).on('change', function () {
        // El 'change' nativo (addEventListener) no siempre se entera cuando
        // select2 lo dispara vía jQuery — se llama directo para no depender
        // de eso.
        if (window.__fcLoad) window.__fcLoad();
    });

    // Bug conocido de select2: al dar clic en la "x" de limpiar, el mismo clic
    // se propaga y vuelve a abrir el dropdown (se ve como "abre y cierra sin borrar").
    $(document).on('select2:unselecting', function(e) {
        $(e.target).data('unselecting', true);
    }).on('select2:opening', function(e) {
        if ($(e.target).data('unselecting')) {
            $(e.target).removeData('unselecting');
            e.preventDefault();
        }
    });
});
</script>
@endpush
</x-admin-layout>
