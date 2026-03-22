<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Superadmin') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
<div class="min-h-screen flex">

    {{-- Sidebar --}}
    <aside class="w-56 bg-gray-900 flex flex-col fixed inset-y-0 left-0 z-50">
        <div class="flex items-center gap-2 px-5 py-5 border-b border-gray-800">
            <div class="w-7 h-7 rounded bg-indigo-600 flex items-center justify-center">
                <i class="fa-solid fa-shield-halved text-white text-xs"></i>
            </div>
            <div>
                <div class="text-white text-sm font-semibold leading-none">Superadmin</div>
                <div class="text-gray-500 text-xs mt-0.5">{{ config('app.name') }}</div>
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @php
                $navItems = [
                    ['route' => 'superadmin.dashboard',       'icon' => 'fa-solid fa-gauge',    'label' => 'Dashboard'],
                    ['route' => 'superadmin.pac.index',       'icon' => 'fa-solid fa-plug',     'label' => 'PAC / Timbrado'],
                    ['route' => 'superadmin.companies.index', 'icon' => 'fa-solid fa-building', 'label' => 'Empresas'],
                    ['route' => 'superadmin.series.index',    'icon' => 'fa-solid fa-list-ol',  'label' => 'Series y folios'],
                    ['route' => 'superadmin.settings.index',  'icon' => 'fa-solid fa-sliders',  'label' => 'Configuración'],
                ];
            @endphp
            @foreach($navItems as $item)
                @php $isActive = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition
                       {{ $isActive ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <i class="{{ $item['icon'] }} w-4 text-center"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="px-4 py-4 border-t border-gray-800 space-y-2">
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-2 text-xs text-gray-500 hover:text-gray-300 transition">
                <i class="fa-solid fa-arrow-left"></i>
                Volver al sistema
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-2 text-xs text-gray-500 hover:text-red-400 transition w-full">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 ml-56 flex flex-col min-h-screen bg-gray-950">

        <header class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex items-center justify-between sticky top-0 z-40">
            <div>
                <h1 class="text-white text-base font-semibold">@yield('title', 'Superadmin')</h1>
                @hasSection('breadcrumbs')
                <div class="flex items-center gap-1 text-xs text-gray-500 mt-0.5">
                    @yield('breadcrumbs')
                </div>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @php $pacActivo = \App\Models\PacConfiguration::activo()->first(); @endphp
                @if($pacActivo)
                <span class="flex items-center gap-1.5 text-xs px-2 py-1 rounded-full
                    {{ $pacActivo->esSandbox() ? 'bg-amber-900 text-amber-300' : 'bg-emerald-900 text-emerald-300' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $pacActivo->esSandbox() ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                    {{ $pacActivo->nombre }} · {{ $pacActivo->ambiente_label }}
                </span>
                @endif
                <span class="text-sm text-gray-400">{{ auth()->user()->name }}</span>
            </div>
        </header>

        @if(session('success'))
        <div class="mx-6 mt-4 rounded-lg border border-emerald-700 bg-emerald-900/30 px-4 py-3 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mx-6 mt-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">
            {{ session('error') }}
        </div>
        @endif

        <main class="flex-1 p-6">
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>