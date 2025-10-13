@php
use Illuminate\Support\Str;

$links = [
  [
    'name'   => 'Dashboard',
    'icon'   => 'fa-solid fa-gauge',
    'href'   => route('admin.dashboard'),
    'active' => request()->routeIs('admin.dashboard'),
  ],
  ['header' => 'Administrar'],
  [
    'name'   => 'Categorias',
    'icon'   => 'fa-solid fa-list',
    'href'   => route('admin.categories.index'),
    'active' => request()->routeIs('admin.categories.'),
  ],
  [
    'name'   => 'Productos',
    'icon'   => 'fa-solid fa-box-open',
    'href'   => route('admin.products.index'),
    'active' => request()->routeIs('admin.products.*'),
  ],
  [ 
    'name'   => 'Clientes',
    'icon'   => 'fa-solid fa-users',
    'href'   => route('admin.clients.index'),
    'active' => request()->routeIs('admin.clients.*'),
  ],
   [ 
    'name'   => 'Proveedores',
    'icon'   => 'fa-solid fa-truck',
    'href'   => route('admin.providers.index'),
    'active' => request()->routeIs('admin.providers.*'),
  ],
   [ 
    'name'   => 'Almacenes',
    'icon'   => 'fa-solid fa-warehouse',
    'href'   => route('admin.warehouses.index'),
    'active' => request()->routeIs('admin.warehouses.*'),
  ],
   [ 
    'name'   => 'Ordenes de compra',
    'icon'   => 'fa-solid fa-cart-plus',
    'href'   => route('admin.purchase-orders.index'),
    'active' => request()->routeIs('admin.purchase-orders.*'),
  ],
    [ 
    'name'   => 'Compras',
    'icon'   => 'fa-solid fa-cart-shopping',
    'href'   => route('admin.purchases.index'),
    'active' => request()->routeIs('admin.purchases.*'),
  ],
  [ 
    'name'   => 'Stock',
    'icon'   => 'fa-solid fa-layer-group',    
    'href'   => route('admin.stock.index'),
    'active' => request()->routeIs('admin.stock.*'),
  ],
   [ 
    'name'   => 'Cotizaciones',
    'icon'   => 'fa-solid fa-file-invoice-dollar',
    'href'   => route('admin.quotes.index'),
    'active' => request()->routeIs('admin.quotes.*'),
  ],


];

// Precalcula "hay hijo activo" para cada item con submenu
foreach ($links as $i => $item) {
  if (!empty($item['submenu']) && is_array($item['submenu'])) {
    $links[$i]['has_active_child'] = collect($item['submenu'])->contains(fn($c) => !empty($c['active']));
  }
}
@endphp

<aside id="logo-sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full bg-white border-r border-gray-200 sm:translate-x-0 dark:bg-gray-800 dark:border-gray-700" aria-label="Sidebar">
  <div class="h-full px-3 pb-4 overflow-y-auto bg-white dark:bg-gray-800">
    <ul class="space-y-2 font-medium">

      @foreach ($links as $link)
        {{-- HEADER --}}
        @if(isset($link['header']))
          <li class="px-2 pt-4 text-xs font-semibold text-gray-400 uppercase tracking-wide dark:text-gray-500">
            {{ $link['header'] }}
          </li>
          @continue
        @endif

        {{-- ITEM CON SUBMENÚ --}}
        @if(!empty($link['submenu']) && is_array($link['submenu']))
          @php
            $collapseId = 'submenu-'.Str::slug($link['name'] ?? Str::random(6));
            $isOpen = !empty($link['has_active_child']); // abrir si hay hijo activo
            $isActiveParent = !empty($link['active']) || $isOpen;
          @endphp

          <li>
            <button
              type="button"
              class="flex w-full items-center p-2 rounded-lg transition duration-75 group
                     {{ $isActiveParent ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700' }}"
              aria-controls="{{ $collapseId }}"
              data-collapse-toggle="{{ $collapseId }}"
              aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
            >
              <span class="inline-flex justify-center items-center w-6 h-6 rounded-lg
                           {{ $isActiveParent ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-white'
                                              : 'text-gray-500 group-hover:bg-gray-200 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:bg-gray-700 dark:group-hover:text-white' }}">
                <i class="{{ $link['icon'] ?? 'fa-regular fa-circle' }}"></i>
              </span>
              <span class="ms-3 flex-1 text-left rtl:text-right whitespace-nowrap">
                {{ $link['name'] ?? 'Menu' }}
              </span>
              <svg class="w-3 h-3 transition-transform {{ $isOpen ? 'rotate-180' : '' }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
              </svg>
            </button>

            <ul id="{{ $collapseId }}" class="{{ $isOpen ? '' : 'hidden' }} py-2 space-y-1">
              @foreach ($link['submenu'] as $child)
                @php $activeChild = !empty($child['active']); @endphp
                <li>
                  <a href="{{ $child['href'] ?? '#' }}"
                     class="flex items-center w-full p-2 rounded-lg pl-11 transition
                            {{ $activeChild
                                ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700' }}">
                    {{ $child['name'] ?? 'Item' }}
                  </a>
                </li>
              @endforeach
            </ul>
          </li>

        {{-- ITEM SIMPLE --}}
        @else
          @php $isActive = !empty($link['active']); @endphp
          <li>
            <a href="{{ $link['href'] ?? '#' }}"
               class="flex items-center p-2 rounded-lg group transition
                      {{ $isActive
                        ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                        : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700' }}">
              <span class="inline-flex justify-center items-center w-6 h-6 rounded-lg
                           {{ $isActive
                              ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-white'
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

{{-- Fallback mini para el toggle si no usas Flowbite JS --}}
<script>
document.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-collapse-toggle]');
  if (!btn) return;
  const id = btn.getAttribute('data-collapse-toggle');
  const target = document.getElementById(id);
  if (!target) return;

  const isHidden = target.classList.contains('hidden');
  document.querySelectorAll('[id^="submenu-"]').forEach(el => {
    if (el !== target) el.classList.add('hidden');
  });

  target.classList.toggle('hidden');
  const expanded = target.classList.contains('hidden') ? 'false' : 'true';
  btn.setAttribute('aria-expanded', expanded);

  // Rotar flecha
  const svg = btn.querySelector('svg.w-3.h-3');
  if (svg) svg.classList.toggle('rotate-180', expanded === 'true');
}, false);
</script>
