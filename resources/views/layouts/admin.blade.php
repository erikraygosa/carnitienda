@props([
    'title' => config('app.name', 'Laravel'),
    'breadcrumbs' => []
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- PWA: pantalla completa al agregar a inicio --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ $title }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    {{-- Fontawesome --}}
    <script src="https://kit.fontawesome.com/e2d71e4ca2.js" crossorigin="anonymous"></script>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Livewire styles --}}
    @livewireStyles

    <!-- Vite: CSS + JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('css')
</head>

<body class="font-sans antialiased bg-gray-50">

    @include('layouts.includes.admin.navigation')

    @include('layouts.includes.admin.sidebar')

   <div id="main-content" class="p-4 transition-[margin] duration-300 ease-in-out">

        <div class="mt-14 flex items-center">

            @include('layouts.includes.admin.breadcrumb')

            @isset($action)
                <div class="ml-auto">
                    {{ $action }}
                </div>
            @endisset
        </div>

        {{ $slot }}

    </div>

    @stack('modals')

    {{-- Livewire primero --}}
    @livewireScripts

    {{-- WireUI después de Livewire (usa el Alpine de Livewire) --}}
    <wireui:scripts />

    {{-- Flowbite --}}
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>

    @if(session('swal'))
    <script>
        Swal.fire(@json(session('swal')));
    </script>
    @endif

    @stack('js')

</body>

</html>