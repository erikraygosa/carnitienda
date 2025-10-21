@php
$links = [
  // HOME
  [
    'name'   => 'Dashboard',
    'icon'   => 'fa-solid fa-gauge',
    'href'   => route('admin.dashboard'),
    'active' => request()->routeIs('admin.dashboard'),
  ],

  // ===== CATÁLOGO =====
  ['header' => 'Catálogo'],
  [
    'name'     => 'Catálogo',
    'icon'     => 'fa-solid fa-database',
    'active'   => request()->routeIs('admin.categories.*')
                 || request()->routeIs('admin.products.*')
                 || request()->routeIs('admin.clients.*')
                 || request()->routeIs('admin.providers.*'),
    'children' => [
      ['name'=>'Categorias','icon'=>'fa-solid fa-list','href'=>route('admin.categories.index'),'active'=>request()->routeIs('admin.categories.*')],
      ['name'=>'Productos','icon'=>'fa-solid fa-box-open','href'=>route('admin.products.index'),'active'=>request()->routeIs('admin.products.*')],
      ['name'=>'Clientes','icon'=>'fa-solid fa-users','href'=>route('admin.clients.index'),'active'=>request()->routeIs('admin.clients.*')],
      ['name'=>'Proveedores','icon'=>'fa-solid fa-truck','href'=>route('admin.providers.index'),'active'=>request()->routeIs('admin.providers.*')],
    ],
  ],

  // ===== COMPRAS =====
  ['header' => 'Compras'],
  [
    'name'     => 'Compras',
    'icon'     => 'fa-solid fa-cart-shopping',
    'active'   => request()->routeIs('admin.purchase-orders.*') || request()->routeIs('admin.purchases.*'),
    'children' => [
      ['name'=>'Órdenes de compra','icon'=>'fa-solid fa-cart-plus','href'=>route('admin.purchase-orders.index'),'active'=>request()->routeIs('admin.purchase-orders.*')],
      ['name'=>'Compras','icon'=>'fa-solid fa-cart-shopping','href'=>route('admin.purchases.index'),'active'=>request()->routeIs('admin.purchases.*')],
    ],
  ],

  // ===== INVENTARIO =====
  ['header' => 'Inventario'],
  [
    'name'     => 'Inventario',
    'icon'     => 'fa-solid fa-warehouse',
    'active'   => request()->routeIs('admin.warehouses.*') || request()->routeIs('admin.stock.*'),
    'children' => [
      ['name'=>'Almacenes','icon'=>'fa-solid fa-warehouse','href'=>route('admin.warehouses.index'),'active'=>request()->routeIs('admin.warehouses.*')],
      ['name'=>'Stock','icon'=>'fa-solid fa-layer-group','href'=>route('admin.stock.index'),'active'=>request()->routeIs('admin.stock.*')],
    ],
  ],

  // ===== VENTAS =====
  ['header' => 'Ventas'],
  [
    'name'     => 'Ventas',
    'icon'     => 'fa-solid fa-file-invoice-dollar',
    'active'   => request()->routeIs('admin.quotes.*')
                 || request()->routeIs('admin.sales-orders.*')
                 || request()->routeIs('admin.sales.*'),
    'children' => [
      ['name'=>'Cotizaciones','icon'=>'fa-solid fa-file-invoice-dollar','href'=>route('admin.quotes.index'),'active'=>request()->routeIs('admin.quotes.*')],
      ['name'=>'Pedidos','icon'=>'fa-solid fa-file-invoice','href'=>route('admin.sales-orders.index'),'active'=>request()->routeIs('admin.sales-orders.*')],
      ['name'=>'Notas de venta','icon'=>'fa-solid fa-receipt','href'=>route('admin.sales.index'),'active'=>request()->routeIs('admin.sales.*')],
      ['name'=>'Facturas','icon'=>'fa-solid fa-file-invoice','href'=>route('admin.invoices.index'),'active'=>request()->routeIs('admin.invoices.*')]
    ],
  ],

  // ===== LOGÍSTICA (futuro) =====
  ['header' => 'Logística'],
  [
    'name'     => 'Despacho',
    'icon'     => 'fa-solid fa-truck-fast',
    'active'   => request()->routeIs('admin.shipping-routes.*') || request()->routeIs('admin.drivers.*'),
    'children' => [
      // futuros: rutas, choferes...
    ],
  ],

  // ===== FINANZAS (futuro) =====
  ['header' => 'Finanzas'],
  [
    'name'     => 'Cuentas y cobros',
    'icon'     => 'fa-solid fa-piggy-bank',
    'active'   => request()->routeIs('admin.accounts-receivable.*') || request()->routeIs('admin.ar-payments.*'),
    'children' => [
      // futuros: CxC, pagos...
    ],
  ],

  // ===== CONFIGURACIÓN (futuro) =====
  ['header' => 'Configuración'],
  [
    'name'     => 'Parámetros',
    'icon'     => 'fa-solid fa-sliders',
    'active'   => request()->routeIs('admin.price-lists.*')
                 || request()->routeIs('admin.pos-registers.*')
                 || request()->routeIs('admin.payment-types.*')
                 || request()->routeIs('admin.users.*'),
    'children' => [
      // futuros: listas de precios, POS, tipos de pago, usuarios...
    ],
  ],
];

// Marcar si un grupo tiene hijo activo
foreach ($links as $i => $item) {
  if (!empty($item['children']) && is_array($item['children'])) {
    $links[$i]['has_active_child'] = collect($item['children'])->contains(fn($c) => !empty($c['active']));
  }
}
@endphp

<aside id="logo-sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 -translate-x-full sm:translate-x-0 bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700" aria-label="Sidebar">
  <div class="h-full px-3 pb-4 overflow-y-auto">
    <ul class="space-y-2 font-medium">
      @foreach ($links as $link)
        @if(isset($link['header']))
          <li class="px-2 pt-4 text-xs font-semibold text-gray-400 uppercase tracking-wide dark:text-gray-500">
            {{ $link['header'] }}
          </li>
          @continue
        @endif

        @if(!empty($link['children']) && is_array($link['children']))
          @php
            $collapseId      = 'submenu-'.\Illuminate\Support\Str::slug($link['name'] ?? \Illuminate\Support\Str::random(6));
            $isOpen          = !empty($link['has_active_child']);
            $isActiveParent  = !empty($link['active']) || $isOpen;
          @endphp

          <li>
            <button type="button"
              class="flex w-full items-center p-2 rounded-lg transition duration-75 group
                    {{ $isActiveParent ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                       : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700' }}"
              aria-controls="{{ $collapseId }}"
              data-sidebar-toggle="{{ $collapseId }}"
              aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
              <span class="inline-flex justify-center items-center w-6 h-6 rounded-lg
                    {{ $isActiveParent ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-white'
                                       : 'text-gray-500 group-hover:bg-gray-200 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:bg-gray-700 dark:group-hover:text-white' }}">
                <i class="{{ $link['icon'] ?? 'fa-regular fa-circle' }}"></i>
              </span>
              <span class="ms-3 flex-1 text-left whitespace-nowrap">{{ $link['name'] ?? 'Menu' }}</span>
              <svg class="w-3 h-3 transition-transform {{ $isOpen ? 'rotate-180' : '' }}" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
              </svg>
            </button>

            <ul id="{{ $collapseId }}" class="{{ $isOpen ? '' : 'hidden' }} py-2 space-y-1">
              @foreach ($link['children'] as $child)
                @php $activeChild = !empty($child['active']); @endphp
                <li>
                  <a href="{{ $child['href'] ?? '#' }}"
                     class="flex items-center w-full p-2 rounded-lg pl-11 transition
                           {{ $activeChild ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                           : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700' }}">
                    {{ $child['name'] ?? 'Item' }}
                  </a>
                </li>
              @endforeach
            </ul>
          </li>
        @else
          @php $isActive = !empty($link['active']); @endphp
          <li>
            <a href="{{ $link['href'] ?? '#' }}"
               class="flex items-center p-2 rounded-lg group transition
                     {{ $isActive ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                  : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700' }}">
              <span class="inline-flex justify-center items-center w-6 h-6 rounded-lg
                    {{ $isActive ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-white'
                                 : 'text-gray-500 group-hover:bg-gray-200 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:bg-gray-700 dark:group-hover:text-white' }}">
                <i class="{{ $link['icon'] ?? 'fa-regular fa-circle' }}"></i>
              </span>
              <span class="ms-3">{{ $link['name'] ?? 'Link' }}</span>
            </a>
          </li>
        @endif
      @endforeach
    </ul>
  </div>
</aside>

<script>
document.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-sidebar-toggle]');
  if (!btn) return;

  const id = btn.getAttribute('data-sidebar-toggle');
  const target = document.getElementById(id);
  if (!target) return;

  // Cierra los demás submenús
  document.querySelectorAll('[id^="submenu-"]').forEach(el => {
    if (el !== target) el.classList.add('hidden');
  });

  // Toggle del submenú actual
  const wasHidden = target.classList.contains('hidden');
  target.classList.toggle('hidden', !wasHidden);

  // Actualiza aria-expanded y la flecha
  const expanded = wasHidden ? 'true' : 'false';
  btn.setAttribute('aria-expanded', expanded);

  const svg = btn.querySelector('svg.w-3.h-3');
  if (svg) svg.classList.toggle('rotate-180', expanded === 'true');
}, false);
</script>
