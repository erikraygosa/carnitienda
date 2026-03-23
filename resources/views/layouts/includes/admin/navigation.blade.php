<nav class="fixed top-0 z-50 w-full bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
  <div class="px-3 py-3 lg:px-5 lg:pl-3">
    <div class="flex items-center justify-between">

      {{-- Logo --}}
      <div class="flex items-center justify-start rtl:justify-end">
        <button data-drawer-target="logo-sidebar" data-drawer-toggle="logo-sidebar"
                aria-controls="logo-sidebar" type="button"
                class="inline-flex items-center p-2 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
            <span class="sr-only">Open sidebar</span>
            <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
               <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
            </svg>
        </button>
        <a href="/" class="flex ms-2 md:me-24">
            <img src="{{ asset('logo.jpg') }}" class="h-10 me-3 rounded-lg shadow-md" alt="Logo">
            <span class="self-center text-xl font-semibold sm:text-2xl whitespace-nowrap dark:text-white">CarniFlow</span>
        </a>
      </div>

      {{-- User menu --}}
      <div class="flex items-center">
        <div class="ms-3 relative">

            <button type="button"
        id="user-menu-btn"
        onclick="document.getElementById('user-menu').classList.toggle('hidden')"
        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">

    <div class="w-7 h-7 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-bold me-2">
        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
    </div>

    {{ Auth::user()->name }}

    <svg class="ms-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none"
         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
    </svg>
</button>
            <div id="user-menu"
                 class="hidden absolute right-0 mt-2 w-52 bg-white rounded-md shadow-lg border border-gray-200 z-50">

                {{-- Info cuenta --}}
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs text-gray-400">{{ __('Manage Account') }}</p>
                    <p class="text-sm font-medium text-gray-700 truncate mt-0.5">{{ Auth::user()->email }}</p>
                </div>

                {{-- Perfil --}}
                <a href="{{ route('profile.show') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    {{ __('Profile') }}
                </a>

                @if(Laravel\Jetstream\Jetstream::hasApiFeatures())
                <a href="{{ route('api-tokens.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                    </svg>
                    {{ __('API Tokens') }}
                </a>
                @endif

                <div class="border-t border-gray-100"></div>

                {{-- Superadmin link si aplica --}}
                @if(auth()->user()?->is_superadmin)
                <a href="{{ route('superadmin.dashboard') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm text-indigo-600 hover:bg-indigo-50 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    Superadmin
                </a>
                <div class="border-t border-gray-100"></div>
                @endif

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}" id="logout-form">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        {{ __('Log Out') }}
                    </button>
                </form>

            </div>
        </div>
      </div>

    </div>
  </div>
</nav>

<script>
document.addEventListener('click', function(e) {
    const menu = document.getElementById('user-menu');
    const btn  = document.getElementById('user-menu-btn');
    if (!menu || !btn) return;
    if (!btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.add('hidden');
    }
});
</script>