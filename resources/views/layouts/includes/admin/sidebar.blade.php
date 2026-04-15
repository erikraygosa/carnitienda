@php
$user = auth()->user();

$links = [
  ['name'=>'Dashboard','icon'=>'fa-solid fa-gauge','href'=>route('admin.dashboard'),'active'=>request()->routeIs('admin.dashboard'),'can'=>true],
  ['header'=>'Catálogo','can'=>$user->hasAnyPermission(['ver productos','ver clientes','ver proveedores'])],
  ['name'=>'Catálogo','icon'=>'fa-solid fa-database','can'=>$user->hasAnyPermission(['ver productos','ver clientes','ver proveedores']),'active'=>request()->routeIs('admin.categories.*')||request()->routeIs('admin.products.*')||request()->routeIs('admin.clients.*')||request()->routeIs('admin.providers.*'),'children'=>[['name'=>'Categorias','icon'=>'fa-solid fa-list','href'=>route('admin.categories.index'),'active'=>request()->routeIs('admin.categories.*'),'can'=>$user->hasPermissionTo('ver productos')],['name'=>'Productos','icon'=>'fa-solid fa-box-open','href'=>route('admin.products.index'),'active'=>request()->routeIs('admin.products.*'),'can'=>$user->hasPermissionTo('ver productos')],['name'=>'Clientes','icon'=>'fa-solid fa-users','href'=>route('admin.clients.index'),'active'=>request()->routeIs('admin.clients.*'),'can'=>$user->hasPermissionTo('ver clientes')],['name'=>'Proveedores','icon'=>'fa-solid fa-truck','href'=>route('admin.providers.index'),'active'=>request()->routeIs('admin.providers.*'),'can'=>$user->hasPermissionTo('ver proveedores')]]],
  ['header'=>'Compras','can'=>$user->hasRole('admin')],
  ['name'=>'Compras','icon'=>'fa-solid fa-cart-shopping','can'=>$user->hasRole('admin'),'active'=>request()->routeIs('admin.purchase-orders.*')||request()->routeIs('admin.purchases.*'),'children'=>[['name'=>'Órdenes de compra','icon'=>'fa-solid fa-cart-plus','href'=>route('admin.purchase-orders.index'),'active'=>request()->routeIs('admin.purchase-orders.*'),'can'=>$user->hasRole('admin')],['name'=>'Compras','icon'=>'fa-solid fa-cart-shopping','href'=>route('admin.purchases.index'),'active'=>request()->routeIs('admin.purchases.*'),'can'=>$user->hasRole('admin')]]],
  ['header'=>'Inventario','can'=>$user->hasPermissionTo('ver stock')],
  ['name'=>'Inventario','icon'=>'fa-solid fa-warehouse','can'=>$user->hasPermissionTo('ver stock'),'active'=>request()->routeIs('admin.warehouses.*')||request()->routeIs('admin.stock.*'),'children'=>[['name'=>'Almacenes','icon'=>'fa-solid fa-warehouse','href'=>route('admin.warehouses.index'),'active'=>request()->routeIs('admin.warehouses.*'),'can'=>$user->hasRole('admin')],['name'=>'Stock','icon'=>'fa-solid fa-layer-group','href'=>route('admin.stock.index'),'active'=>request()->routeIs('admin.stock.index'),'can'=>$user->hasPermissionTo('ver stock')],['name'=>'Traspasos','icon'=>'fa-solid fa-right-left','href'=>route('admin.stock.transfers.index'),'active'=>request()->routeIs('admin.stock.transfers.*'),'can'=>$user->hasPermissionTo('gestionar traspasos')]]],
  ['header'=>'Ventas','can'=>$user->hasPermissionTo('ver pedidos')],
  ['name'=>'Ventas','icon'=>'fa-solid fa-file-invoice-dollar','can'=>$user->hasPermissionTo('ver pedidos'),'active'=>request()->routeIs('admin.quotes.*')||request()->routeIs('admin.sales-orders.*')||request()->routeIs('admin.sales.*')||request()->routeIs('admin.invoices.*'),'children'=>[['name'=>'Cotizaciones','icon'=>'fa-solid fa-file-invoice-dollar','href'=>route('admin.quotes.index'),'active'=>request()->routeIs('admin.quotes.*'),'can'=>$user->hasPermissionTo('ver pedidos')],['name'=>'Pedidos','icon'=>'fa-solid fa-file-invoice','href'=>route('admin.sales-orders.index'),'active'=>request()->routeIs('admin.sales-orders.*'),'can'=>$user->hasPermissionTo('ver pedidos')],['name'=>'Notas de venta','icon'=>'fa-solid fa-receipt','href'=>route('admin.sales.index'),'active'=>request()->routeIs('admin.sales.*'),'can'=>$user->hasPermissionTo('ver pedidos')],['name'=>'Facturas','icon'=>'fa-solid fa-file-invoice','href'=>route('admin.invoices.index'),'active'=>request()->routeIs('admin.invoices.*'),'can'=>$user->hasRole('admin')]]],
  ['header'=>'Almacén','can'=>$user->hasPermissionTo('salida de producto')],
  ['name'=>'Salida de Producto','icon'=>'fa-solid fa-box-open','can'=>$user->hasPermissionTo('salida de producto'),'active'=>request()->routeIs('admin.despacho.*'),'href'=>route('admin.despacho.panel')],
  ['header'=>'Logística','can'=>$user->hasPermissionTo('ver despachos')],
  ['name'=>'Despacho','icon'=>'fa-solid fa-truck-fast','can'=>$user->hasPermissionTo('ver despachos'),'active'=>request()->routeIs('admin.dispatches.*')||request()->routeIs('admin.driver-cash.*'),'children'=>[['name'=>'Despachos','icon'=>'fa-solid fa-route','href'=>route('admin.dispatches.index'),'active'=>request()->routeIs('admin.dispatches.*'),'can'=>$user->hasPermissionTo('ver despachos')],['name'=>'Cortes de choferes','icon'=>'fa-solid fa-cash-register','href'=>route('admin.driver-cash.index'),'active'=>request()->routeIs('admin.driver-cash.*'),'can'=>$user->hasPermissionTo('ver despachos')]]],
  ['name'=>'Punto de venta','icon'=>'fa-solid fa-shop','can'=>$user->hasPermissionTo('usar pos'),'active'=>request()->routeIs('admin.cash.*')||request()->routeIs('admin.pos.*'),'children'=>[['name'=>'Caja','icon'=>'fa-solid fa-cash-register','href'=>route('admin.cash.index'),'active'=>request()->routeIs('admin.cash.*'),'can'=>$user->hasPermissionTo('usar pos')],['name'=>'Venta POS','icon'=>'fa-solid fa-barcode','href'=>route('admin.pos.create'),'active'=>request()->routeIs('admin.pos.*'),'can'=>$user->hasPermissionTo('usar pos')]]],
  ['header'=>'Finanzas','can'=>$user->hasPermissionTo('ver cxc')],
  ['name'=>'Cuentas y cobros','icon'=>'fa-solid fa-piggy-bank','can'=>$user->hasPermissionTo('ver cxc'),'active'=>request()->routeIs('admin.ar.*')||request()->routeIs('admin.ar-payments.*'),'children'=>[['name'=>'Cuentas','icon'=>'fa-solid fa-file-invoice-dollar','href'=>route('admin.ar.index'),'active'=>request()->routeIs('admin.ar.*'),'can'=>$user->hasPermissionTo('ver cxc')],['name'=>'Cobros','icon'=>'fa-solid fa-cash-register','href'=>route('admin.ar-payments.create'),'active'=>request()->routeIs('admin.ar-payments.*'),'can'=>$user->hasPermissionTo('registrar cobros')],['name'=>'Notas de crédito','icon'=>'fa-solid fa-file-invoice-dollar','href'=>route('admin.ar-payments.notas.index'),'active'=>request()->routeIs('admin.ar-payments.notas.index'),'can'=>$user->hasPermissionTo('ver cxc')]]],
  ['header'=>'Configuración','can'=>$user->hasAnyPermission(['ver configuracion','ver usuarios'])],
  ['name'=>'Parámetros','icon'=>'fa-solid fa-sliders','can'=>$user->hasPermissionTo('ver configuracion'),'active'=>request()->routeIs('admin.parametros.*'),'children'=>[['name'=>'Empresas','href'=>route('admin.parametros.companies.index'),'icon'=>'fa-solid fa-building','active'=>request()->routeIs('admin.parametros.companies.*'),'can'=>$user->hasPermissionTo('editar configuracion')]]],
  ['name'=>'Sistema','icon'=>'fa-solid fa-shield-halved','can'=>$user->hasAnyPermission(['ver usuarios','ver configuracion']),'active'=>request()->routeIs('admin.users.*')||request()->routeIs('admin.roles.*'),'children'=>[['name'=>'Usuarios','icon'=>'fa-solid fa-users-gear','href'=>route('admin.users.index'),'active'=>request()->routeIs('admin.users.*'),'can'=>$user->hasPermissionTo('ver usuarios')],['name'=>'Roles y permisos','icon'=>'fa-solid fa-key','href'=>route('admin.roles.index'),'active'=>request()->routeIs('admin.roles.*'),'can'=>$user->hasPermissionTo('ver configuracion')]]],
  ...(auth()->user()?->is_superadmin?[['header'=>'Superadmin','can'=>true],['name'=>'Superadmin','icon'=>'fa-solid fa-shield-halved','href'=>route('superadmin.dashboard'),'active'=>request()->routeIs('superadmin.*'),'can'=>true]]:[]),
];

foreach ($links as $i => $item) {
    if (!empty($item['children']) && is_array($item['children'])) {
        $links[$i]['children'] = array_filter($item['children'], fn($c) => $c['can'] ?? true);
        $links[$i]['has_active_child'] = collect($links[$i]['children'])->contains(fn($c) => !empty($c['active']));
    }
}
@endphp

{{--
  ESTRATEGIA: Todo el comportamiento (hover + pin) lo maneja JS.
  CSS solo define anchos base. Sin hover CSS para evitar conflictos con el main.

  Estados:
    - collapsed  : w-16, solo iconos, main con ml-16
    - hover-open : w-64, texto visible, main NO se mueve (sidebar flota encima)
    - pinned     : w-64, texto visible, main con ml-64 (se mueve)
--}}

<style>
  /* ── Sidebar ── */
  #logo-sidebar {
    width: 4rem; /* colapsado por defecto */
    transition: width 0.25s ease-in-out;
    will-change: width;
  }
  #logo-sidebar.sidebar-expanded {
    width: 16rem;
  }

  /* Textos y flechas: ocultos por defecto */
  #logo-sidebar .sidebar-text,
  #logo-sidebar .sidebar-arrow { display: none; }

  #logo-sidebar.sidebar-expanded .sidebar-text { display: inline !important; }
  #logo-sidebar.sidebar-expanded .sidebar-arrow { display: inline-flex !important; }

  /* Headers */
  #logo-sidebar .sidebar-divider { display: block; }
  #logo-sidebar .sidebar-label   { display: none;  }
  #logo-sidebar.sidebar-expanded .sidebar-divider { display: none;  }
  #logo-sidebar.sidebar-expanded .sidebar-label   { display: block; }

  /* Botón pin: solo visible cuando está expandido */
  #sidebar-pin-btn { opacity: 0; transition: opacity 0.2s; pointer-events: none; }
  #logo-sidebar.sidebar-expanded #sidebar-pin-btn { opacity: 1; pointer-events: auto; }

  /* Main: el margin lo maneja JS con inline style para ganar especificidad */
</style>

<aside id="logo-sidebar"
  class="fixed top-0 left-0 z-40 h-screen pt-20
         -translate-x-full sm:translate-x-0
         bg-white border-r border-gray-200
         dark:bg-gray-800 dark:border-gray-700
         overflow-hidden"
  aria-label="Sidebar">

  {{-- Botón PIN --}}
  <button id="sidebar-pin-btn"
    title="Fijar sidebar"
    class="absolute top-[4.6rem] right-1.5 z-50
           flex items-center justify-center w-6 h-6 rounded-full
           bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600
           text-gray-500 dark:text-gray-300 focus:outline-none">
    <svg id="pin-icon-off" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="12" y1="17" x2="12" y2="22"/>
      <path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/>
    </svg>
    <svg id="pin-icon-on" class="w-3.5 h-3.5 hidden text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="12" y1="17" x2="12" y2="22"/>
      <path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/>
    </svg>
  </button>

  <div class="h-full px-2 pb-4 overflow-y-auto overflow-x-hidden">
    <ul class="space-y-1 font-medium">
      @foreach ($links as $link)

        @if(isset($link['can']) && !$link['can']) @continue @endif

        @if(isset($link['header']))
          <li class="px-2 pt-4 pb-1 h-8 flex items-center">
            <span class="sidebar-divider block w-full border-t border-gray-200 dark:border-gray-600"></span>
            <span class="sidebar-label text-xs font-semibold text-gray-400 uppercase tracking-wide dark:text-gray-500 whitespace-nowrap">
              {{ $link['header'] }}
            </span>
          </li>
          @continue
        @endif

        @if(!empty($link['children']) && is_array($link['children']) && count($link['children']) > 0)
          @php
            $collapseId     = 'submenu-'.\Illuminate\Support\Str::slug($link['name'] ?? \Illuminate\Support\Str::random(6));
            $isOpen         = !empty($link['has_active_child']);
            $isActiveParent = !empty($link['active']) || $isOpen;
          @endphp
          <li>
            <button type="button"
              class="flex w-full items-center p-2 rounded-lg transition duration-75 group/btn
                    {{ $isActiveParent ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                       : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700' }}"
              aria-controls="{{ $collapseId }}"
              data-sidebar-toggle="{{ $collapseId }}"
              aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
              <span class="inline-flex flex-shrink-0 justify-center items-center w-6 h-6 rounded-lg
                    {{ $isActiveParent ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-white'
                                       : 'text-gray-500 group-hover/btn:bg-gray-200 group-hover/btn:text-gray-900 dark:text-gray-400 dark:group-hover/btn:bg-gray-700 dark:group-hover/btn:text-white' }}">
                <i class="{{ $link['icon'] ?? 'fa-regular fa-circle' }}"></i>
              </span>
              <span class="sidebar-text ms-3 flex-1 text-left whitespace-nowrap">{{ $link['name'] ?? 'Menu' }}</span>
              <svg class="sidebar-arrow w-3 h-3 flex-shrink-0 transition-transform {{ $isOpen ? 'rotate-180' : '' }}"
                   viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
              </svg>
            </button>
            <ul id="{{ $collapseId }}" class="{{ $isOpen ? '' : 'hidden' }} py-1 space-y-1">
              @foreach ($link['children'] as $child)
                @php $activeChild = !empty($child['active']); @endphp
                <li>
                  <a href="{{ $child['href'] ?? '#' }}"
                     class="flex items-center w-full p-2 rounded-lg pl-11 transition whitespace-nowrap
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
               class="flex items-center p-2 rounded-lg group/link transition
                     {{ $isActive ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                  : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700' }}">
              <span class="inline-flex flex-shrink-0 justify-center items-center w-6 h-6 rounded-lg
                    {{ $isActive ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-white'
                                 : 'text-gray-500 group-hover/link:bg-gray-200 group-hover/link:text-gray-900 dark:text-gray-400 dark:group-hover/link:bg-gray-700 dark:group-hover/link:text-white' }}">
                <i class="{{ $link['icon'] ?? 'fa-regular fa-circle' }}"></i>
              </span>
              <span class="sidebar-text ms-3 whitespace-nowrap">{{ $link['name'] ?? 'Link' }}</span>
            </a>
          </li>
        @endif

      @endforeach
    </ul>
  </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
(function () {
  const sidebar  = document.getElementById('logo-sidebar');
  const main     = document.getElementById('main-content');
  const pinBtn   = document.getElementById('sidebar-pin-btn');
  const iconOff  = document.getElementById('pin-icon-off');
  const iconOn   = document.getElementById('pin-icon-on');

  let pinned     = localStorage.getItem('sidebarPinned') === 'true';
  let hovered    = false;
  let leaveTimer = null;

  const W_MINI   = '4rem';   // 64px  — colapsado
  const W_FULL   = '16rem';  // 256px — expandido

  // Aplicar margin-left directo en style (gana a cualquier clase Tailwind)
  function setMainMargin(expanded) {
    if (!main) return;
    const isMobile = window.innerWidth < 640;
   main.style.marginLeft = isMobile ? '0px' : (expanded ? W_FULL : W_MINI);
  }

  // Reajustar al cambiar tamaño de ventana
  window.addEventListener('resize', () => setMainMargin(pinned));

  // ── Estado inicial sin animación ──
  if (main) main.style.transition = 'none';
  if (pinned) sidebar.classList.add('sidebar-expanded');
  setMainMargin(pinned);
  updatePinIcon(pinned);

  // Activar transición después del primer frame
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      if (main) main.style.transition = 'margin-left 0.25s ease-in-out';
    });
  });

  // ── Hover ──
  sidebar.addEventListener('mouseenter', function () {
    hovered = true;
    clearTimeout(leaveTimer);
    if (!pinned) sidebar.classList.add('sidebar-expanded');
  });

  sidebar.addEventListener('mouseleave', function () {
    hovered = false;
    if (!pinned) {
      leaveTimer = setTimeout(() => {
        sidebar.classList.remove('sidebar-expanded');
        document.querySelectorAll('[id^="submenu-"]').forEach(el => el.classList.add('hidden'));
      }, 150);
    }
  });

  // ── Pin ──
  pinBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    pinned = !pinned;
    localStorage.setItem('sidebarPinned', pinned ? 'true' : 'false');
    if (pinned) {
      sidebar.classList.add('sidebar-expanded');
    } else {
      sidebar.classList.remove('sidebar-expanded');
    }
    setMainMargin(pinned);
    updatePinIcon(pinned);
  });

  function updatePinIcon(isPinned) {
    if (isPinned) {
      iconOff.classList.add('hidden');
      iconOn.classList.remove('hidden');
      pinBtn.title = 'Desanclar sidebar';
    } else {
      iconOff.classList.remove('hidden');
      iconOn.classList.add('hidden');
      pinBtn.title = 'Fijar sidebar';
    }
  }

  // ── Submenús ──
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-sidebar-toggle]');
    if (!btn) return;
    if (!sidebar.classList.contains('sidebar-expanded')) return;

    const id     = btn.getAttribute('data-sidebar-toggle');
    const target = document.getElementById(id);
    if (!target) return;

    document.querySelectorAll('[id^="submenu-"]').forEach(el => {
      if (el !== target) el.classList.add('hidden');
    });

    const wasHidden = target.classList.contains('hidden');
    target.classList.toggle('hidden', !wasHidden);
    btn.setAttribute('aria-expanded', wasHidden ? 'true' : 'false');

    const svg = btn.querySelector('.sidebar-arrow');
    if (svg) svg.classList.toggle('rotate-180', wasHidden);
  }, false);
})();
}); // DOMContentLoaded
</script>